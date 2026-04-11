<?php

namespace App\Http\Controllers;

use App\Models\Deck;
use App\Models\UserGoal;
use App\Models\Achievement;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\StudyMaterial;
use Illuminate\Support\Carbon;
use App\Models\FlashcardReview;
use App\Models\UserAchievement;
use App\Models\StudyActivityTiming;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function overview(Request $request)
    {
        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }
        $userId = $supabaseUser['id'];
        $today = Carbon::today();
        // Resolve local app user (numeric) for reviews table
        $appUser = $this->resolveLocalUser($supabaseUser);
        $localUserId = $appUser->id;
        $searchReviewTableExists = $this->searchReviewTableExists();

        // Cards studied today - combine regular flashcards and search flashcards
        $regularCardsToday = FlashcardReview::where('user_id', $localUserId)
            ->whereDate('reviewed_at', $today)
            ->count();

        $searchCardsToday = $searchReviewTableExists
            ? \App\Models\SearchFlashcardReview::where('user_id', $userId) // Search reviews use supabase user id
            ->whereDate('reviewed_at', $today)
            ->count()
            : 0;

        $cardsStudiedToday = $regularCardsToday + $searchCardsToday;

        // Current streak (days in a row with at least one review from either regular or search flashcards)
        $regularDates = FlashcardReview::where('user_id', $localUserId)
            ->selectRaw('DATE(reviewed_at) as date')
            ->distinct()
            ->pluck('date');

        $searchDates = $searchReviewTableExists
            ? \App\Models\SearchFlashcardReview::where('user_id', $userId)
            ->selectRaw('DATE(reviewed_at) as date')
            ->distinct()
            ->pluck('date')
            : collect();

        // Combine and sort dates from both types of reviews
        $allDates = $regularDates->merge($searchDates)->unique()->sort()->reverse()->values();

        $streak = 0;
        $current = $today->copy();
        foreach ($allDates as $date) {
            if ($date == $current->toDateString()) {
                $streak++;
                $current->subDay();
            } else {
                break;
            }
        }

        // Overall progress (percentage of reviewed study materials)
        $total = StudyMaterial::whereHas('document', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->count();
        $reviewed = FlashcardReview::where('user_id', $localUserId)
            ->distinct('study_material_id')
            ->count('study_material_id');
        $overallProgress = $total > 0 ? round(($reviewed / $total) * 100) : 0;

        // Study time (LIFETIME TOTAL - sum from activity timings + fallback to old review-based timing)
        // Use user_id field in timing tables for accurate user-specific timing
        $activityTimingStudyTime = StudyActivityTiming::where('user_id', $userId)
            ->sum('duration_seconds');

        // Fallback: Old method for backward compatibility (if no timing data exists) - ALL TIME
        $regularStudyTime = FlashcardReview::where('user_id', $localUserId)
            ->sum('study_time');

        $searchStudyTime = $searchReviewTableExists
            ? \App\Models\SearchFlashcardReview::where('user_id', $userId)
            ->sum('study_time')
            : 0;

        // Use timing tables if available, otherwise fallback to review-based timing
        $studyTimeSeconds = $activityTimingStudyTime > 0 ? $activityTimingStudyTime : ($regularStudyTime + $searchStudyTime);
        $studyTimeMinutes = intval($studyTimeSeconds / 60);
        $hours = intdiv($studyTimeMinutes, 60);
        $minutes = $studyTimeMinutes % 60;
        $studyTime = ($hours ? $hours . 'h ' : '') . $minutes . 'm';

        return response()->json([
            'cards_studied_today' => $cardsStudiedToday,
            'current_streak' => $streak,
            'overall_progress' => $overallProgress,
            'study_time' => $studyTime,
        ]);
    }

    public function recentDecks(Request $request)
    {
        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }
        $userId = $supabaseUser['id'];
        // Resolve local app user id for reviews
        $appUser = $this->resolveLocalUser($supabaseUser);
        $localUserId = $appUser->id;
        $decks = Deck::where('user_id', $userId)
            ->with(['studyMaterials.reviews' => function ($q) use ($localUserId) {
                $q->where('user_id', $localUserId);
            }])
            ->orderByDesc('updated_at')
            ->take(3)
            ->get();

        $result = $decks->map(function ($deck) use ($localUserId) {
            // Count only flashcard type study materials
            $total = $deck->studyMaterials->where('type', 'flashcard')->count();
            $reviewed = $deck->studyMaterials->where('type', 'flashcard')->filter(function ($sm) use ($localUserId) {
                return $sm->reviews->where('user_id', $localUserId)->count() > 0;
            })->count();
            $progress = $total > 0 ? round(($reviewed / $total) * 100) : 0;
            $lastStudied = $deck->studyMaterials->flatMap->reviews
                ->where('user_id', $localUserId)
                ->sortByDesc('reviewed_at')
                ->first()?->reviewed_at;

            // Format last studied time
            $lastStudiedText = 'Never studied';
            if ($lastStudied) {
                $diff = Carbon::parse($lastStudied)->diffForHumans();
                $lastStudiedText = 'Last studied ' . $diff;
            }

            return [
                'id' => $deck->id,
                'name' => $deck->name,
                'card_count' => $total,
                'last_studied' => $lastStudiedText,
                'progress' => $progress,
            ];
        });

        return response()->json($result);
    }

    public function todaysGoal(Request $request)
    {
        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }
        $userId = $supabaseUser['id'];
        $today = Carbon::today();
        $goal = UserGoal::where('user_id', $userId)->latest()->first();

        // Resolve local user ID for regular flashcard reviews
        $appUser = $this->resolveLocalUser($supabaseUser);
        $localUserId = $appUser->id;
        $searchReviewTableExists = $this->searchReviewTableExists();

        // Get goal type and set defaults
        $goalType = $goal ? $goal->goal_type : 'cards_studied';
        $goalDescription = $goal ? $goal->description : ($goalType === 'study_time' ? 'Study time daily' : 'Study cards daily');
        $dailyGoal = $goal ? $goal->daily_goal : ($goalType === 'study_time' ? 60 : 50); // 60 minutes or 50 cards default

        if ($goalType === 'study_time') {
            // Calculate study time today using activity timings with user_id + fallback
            $activityTimingStudyTime = StudyActivityTiming::whereDate('start_time', $today)
                ->where('user_id', $userId)
                ->sum('duration_seconds');

            // Fallback to review-based timing if no timing data
            $regularStudyTime = FlashcardReview::where('user_id', $localUserId)
                ->whereDate('reviewed_at', $today)
                ->sum('study_time');

            $searchStudyTime = $searchReviewTableExists
                ? \App\Models\SearchFlashcardReview::where('user_id', $userId)
                ->whereDate('reviewed_at', $today)
                ->sum('study_time')
                : 0;

            // Use timing tables if available, otherwise fallback to review-based timing
            $studyTimeSeconds = $activityTimingStudyTime > 0 ? $activityTimingStudyTime : ($regularStudyTime + $searchStudyTime);
            $studyTimeMinutes = intval($studyTimeSeconds / 60);

            $currentValue = $studyTimeMinutes;
            $remaining = max(0, $dailyGoal - $currentValue);
            $progressPercentage = $dailyGoal > 0 ? round(($currentValue / $dailyGoal) * 100) : 0;
            $isCompleted = $currentValue >= $dailyGoal;
            $message = $remaining > 0 ? "{$remaining} more minutes to reach your daily goal!" : "Goal completed! Great job!";

            return response()->json([
                'current_value' => $currentValue,
                'goal' => $dailyGoal,
                'remaining' => $remaining,
                'progress_percentage' => $progressPercentage,
                'goal_type' => $goalType,
                'goal_description' => $goalDescription,
                'is_completed' => $isCompleted,
                'message' => $message,
                'unit' => 'minutes'
            ]);
        } else {
            // Cards studied goal (default behavior)
            $regularCardsToday = FlashcardReview::where('user_id', $localUserId)
                ->whereDate('reviewed_at', $today)
                ->count();

            $searchCardsToday = $searchReviewTableExists
                ? \App\Models\SearchFlashcardReview::where('user_id', $userId)
                ->whereDate('reviewed_at', $today)
                ->count()
                : 0;

            $cardsStudiedToday = $regularCardsToday + $searchCardsToday;
            $remaining = max(0, $dailyGoal - $cardsStudiedToday);
            $progressPercentage = $dailyGoal > 0 ? round(($cardsStudiedToday / $dailyGoal) * 100) : 0;
            $isCompleted = $cardsStudiedToday >= $dailyGoal;
            $message = $remaining > 0 ? "{$remaining} more cards to reach your daily goal!" : "Goal completed! Great job!";

            return response()->json([
                'current_value' => $cardsStudiedToday,
                'goal' => $dailyGoal,
                'remaining' => $remaining,
                'progress_percentage' => $progressPercentage,
                'goal_type' => $goalType,
                'goal_description' => $goalDescription,
                'is_completed' => $isCompleted,
                'message' => $message,
                'unit' => 'cards',
                // Keep backward compatibility
                'studied' => $cardsStudiedToday
            ]);
        }
    }

    public function achievements(Request $request)
    {
        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }
        $supabaseUserId = (string) $supabaseUser['id'];
        // Map to local user id without querying bigint users.id by Supabase UUID
        $user = $this->resolveLocalUser($supabaseUser);
        if (!$user) {
            return response()->json(['achievements' => []]);
        }

        // First ensure user has all achievements they qualify for
        $this->ensurePointBasedAchievements($user, $supabaseUserId);

        // Now get all achievements with no duplicates
        $userAchievements = Achievement::join('user_achievements', 'achievements.id', '=', 'user_achievements.achievement_id')
            ->where('user_achievements.user_id', $supabaseUserId)
            ->orderByDesc('user_achievements.achieved_at')
            ->get(['achievements.*', 'user_achievements.achieved_at', 'user_achievements.id as ua_id']);

        // Use collection's unique method to avoid duplicates by achievement name
        $uniqueAchievements = $userAchievements->unique('name');

        $result = $uniqueAchievements->map(function ($a) {
            return [
                'title' => $a->name,
                'description' => $a->description,
                'icon' => $a->icon,
                'earned_at' => $a->achieved_at,
                'points' => $a->points ?? 0, // Ensure points is never null
            ];
        })->take(10)->values(); // Add values() to reindex the collection

        return response()->json($result);
    }

    public function userInfo(Request $request)
    {
        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }
        $user = $this->resolveLocalUser($supabaseUser);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'user_type' => $user->user_type,
            'display_name' => $user->name,
            'user_tag' => ucfirst($user->user_type)
        ]);
    }

    public function dashboard(Request $request)
    {
        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }
        $userId = $supabaseUser['id'];

        // Get user info
        $user = $this->resolveLocalUser($supabaseUser);

        $today = Carbon::today();

        // Use local app user id for review stats
        $localUserId = $user->id;
        $searchReviewTableExists = $this->searchReviewTableExists();

        // Cards studied today - combine regular flashcards and search flashcards
        $regularCardsToday = FlashcardReview::where('user_id', $localUserId)
            ->whereDate('reviewed_at', $today)
            ->count();

        $searchCardsToday = $searchReviewTableExists
            ? \App\Models\SearchFlashcardReview::where('user_id', $userId) // Search reviews use supabase user id
            ->whereDate('reviewed_at', $today)
            ->count()
            : 0;

        $cardsStudiedToday = $regularCardsToday + $searchCardsToday;

        // Current streak (days in a row with at least one review from either regular or search flashcards)
        $regularDates = FlashcardReview::where('user_id', $localUserId)
            ->selectRaw('DATE(reviewed_at) as date')
            ->distinct()
            ->pluck('date');

        $searchDates = $searchReviewTableExists
            ? \App\Models\SearchFlashcardReview::where('user_id', $userId)
            ->selectRaw('DATE(reviewed_at) as date')
            ->distinct()
            ->pluck('date')
            : collect();

        // Combine and sort dates from both types of reviews
        $allDates = $regularDates->merge($searchDates)->unique()->sort()->reverse()->values();

        $streak = 0;
        $current = $today->copy();
        foreach ($allDates as $date) {
            if ($date == $current->toDateString()) {
                $streak++;
                $current->subDay();
            } else {
                break;
            }
        }

        // Overall progress
        $total = StudyMaterial::whereHas('document', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->count();
        $reviewed = FlashcardReview::where('user_id', $localUserId)
            ->distinct('study_material_id')
            ->count('study_material_id');
        $overallProgress = $total > 0 ? round(($reviewed / $total) * 100) : 0;

        // Study time (LIFETIME TOTAL - use activity timings + fallback to review-based timing)
        // Get study time from activity timings using user_id - ALL TIME
        $activityTimingStudyTime = StudyActivityTiming::where('user_id', $userId)
            ->sum('duration_seconds');

        // Fallback to review-based timing if no timing data exists - ALL TIME
        if ($activityTimingStudyTime == 0) {
            $regularStudyTime = FlashcardReview::where('user_id', $localUserId)
                ->sum('study_time');

            $searchStudyTime = $searchReviewTableExists
                ? \App\Models\SearchFlashcardReview::where('user_id', $userId)
                ->sum('study_time')
                : 0;

            $studyTimeSeconds = $regularStudyTime + $searchStudyTime;
            Log::info('Dashboard Main - Using fallback: Regular(' . $regularStudyTime . ') + Search(' . $searchStudyTime . ') = ' . $studyTimeSeconds);
        } else {
            $studyTimeSeconds = $activityTimingStudyTime;
            Log::info('Dashboard Main - Using timing data: ' . $studyTimeSeconds);
        }

        $studyTimeMinutes = intval($studyTimeSeconds / 60);
        $hours = intdiv($studyTimeMinutes, 60);
        $minutes = $studyTimeMinutes % 60;
        $studyTime = ($hours ? $hours . 'h ' : '') . $minutes . 'm';

        // Recent study decks
        $decks = Deck::where('user_id', $userId)
            ->with(['studyMaterials.reviews' => function ($q) use ($localUserId) {
                $q->where('user_id', $localUserId);
            }])
            ->orderByDesc('updated_at')
            ->take(3)
            ->get();

        $recentDecks = $decks->map(function ($deck) use ($localUserId) {
            // Count only flashcard type study materials
            $total = $deck->studyMaterials->where('type', 'flashcard')->count();
            $reviewed = $deck->studyMaterials->where('type', 'flashcard')->filter(function ($sm) use ($localUserId) {
                return $sm->reviews->where('user_id', $localUserId)->count() > 0;
            })->count();
            $progress = $total > 0 ? round(($reviewed / $total) * 100) : 0;
            $lastStudied = $deck->studyMaterials->flatMap->reviews
                ->where('user_id', $localUserId)
                ->sortByDesc('reviewed_at')
                ->first()?->reviewed_at;

            $lastStudiedText = 'Never studied';
            if ($lastStudied) {
                $diff = Carbon::parse($lastStudied)->diffForHumans();
                $lastStudiedText = 'Last studied ' . $diff;
            }

            return [
                'id' => $deck->id,
                'name' => $deck->name,
                'card_count' => $total,
                'last_studied' => $lastStudiedText,
                'progress' => $progress,
            ];
        });

        // Today's goal - use new goal system
        // First check if user has a Daily Flashcards goal in the new system
        $dailyFlashcardsGoal = UserGoal::where('user_id', $userId)
            ->whereHas('goalType', function ($query) {
                $query->where('name', 'Daily Flashcards');
            })
            ->where('is_active', true)
            ->with('goalType')
            ->first();

        if ($dailyFlashcardsGoal) {
            $dailyGoal = $dailyFlashcardsGoal->target_value;
            $goalType = 'cards_studied';
            $goalDescription = $dailyFlashcardsGoal->goalType->description;
        } else {
            // Fallback to default value from goal type
            $defaultGoalType = \App\Models\GoalType::where('name', 'Daily Flashcards')->first();
            $dailyGoal = $defaultGoalType ? $defaultGoalType->default_value : 50;
            $goalType = 'cards_studied';
            $goalDescription = 'Study cards daily';
        }

        $remaining = max(0, $dailyGoal - $cardsStudiedToday);
        $progressPercentage = $dailyGoal > 0 ? round(($cardsStudiedToday / $dailyGoal) * 100) : 0;

        // Calculate TODAY'S study time for daily goals (separate from lifetime total)
        $todayActivityTimingStudyTime = StudyActivityTiming::whereDate('start_time', $today)
            ->where('user_id', $userId)
            ->sum('duration_seconds');

        // Fallback to review-based timing for today if no timing data exists
        if ($todayActivityTimingStudyTime == 0) {
            $todayRegularStudyTime = FlashcardReview::where('user_id', $localUserId)
                ->whereDate('reviewed_at', $today)
                ->sum('study_time');

            $todaySearchStudyTime = $searchReviewTableExists
                ? \App\Models\SearchFlashcardReview::where('user_id', $userId)
                ->whereDate('reviewed_at', $today)
                ->sum('study_time')
                : 0;

            $todayStudyTimeSeconds = $todayRegularStudyTime + $todaySearchStudyTime;
        } else {
            $todayStudyTimeSeconds = $todayActivityTimingStudyTime;
        }

        $todayStudyTimeMinutes = intval($todayStudyTimeSeconds / 60);

        // Get user's additional goals (new goal system)
        $userGoals = UserGoal::where('user_id', $userId)
            ->where('is_active', true)
            ->with('goalType')
            ->get()
            ->map(function ($userGoal) use ($cardsStudiedToday, $todayStudyTimeMinutes, $localUserId, $today, $searchReviewTableExists) {
            $goalCategory = $userGoal->goalType?->category;
            $goalUnit = $userGoal->goalType?->unit;

            // Backward compatibility for legacy goals that do not have goal_type_id.
            if (!$goalCategory || !$goalUnit) {
                if ($userGoal->goal_type === 'study_time') {
                    $goalCategory = 'time';
                    $goalUnit = 'minutes';
                } else {
                    $goalCategory = 'study';
                    $goalUnit = 'cards';
                }
            }

            $current_value = $userGoal->current_value;

                // For flashcard goals, use today's studied count
                if (
                $goalCategory === 'study' &&
                $goalUnit === 'cards'
                ) {
                    $current_value = $cardsStudiedToday;
                }

                // For time-based goals, use today's study time in minutes
                if (
                $goalCategory === 'time' &&
                $goalUnit === 'minutes'
                ) {
                    $current_value = $todayStudyTimeMinutes;
                }

                // For engagement goals, calculate based on review activity and ratings
                if (
                $goalCategory === 'engagement' &&
                $goalUnit === 'points'
                ) {
                    // Calculate engagement score based on today's reviews
                    $todaysReviews = FlashcardReview::where('user_id', $localUserId)
                        ->whereDate('reviewed_at', $today)
                        ->get();

                    $engagementScore = 0;
                    foreach ($todaysReviews as $review) {
                        // Points based on rating
                        $ratingPoints = [
                            'again' => 1,
                            'hard' => 2,
                            'good' => 3,
                            'easy' => 4
                        ];
                        $engagementScore += $ratingPoints[$review->rating] ?? 1;

                        // Time bonus: +1 point per 30 seconds of study time
                        $engagementScore += intdiv($review->study_time, 30);
                    }

                // Add points from search flashcard reviews
                $searchReviews = $searchReviewTableExists
                    ? \App\Models\SearchFlashcardReview::where('user_id', $userGoal->user_id)
                    ->whereDate('reviewed_at', $today)
                    ->get()
                    : collect();

                    foreach ($searchReviews as $review) {
                        $ratingPoints = [
                            'again' => 1,
                            'hard' => 2,
                            'good' => 3,
                            'easy' => 4
                        ];
                        $engagementScore += $ratingPoints[$review->rating] ?? 1;
                        $engagementScore += intdiv($review->study_time, 30);
                    }

                    $current_value = $engagementScore;
                }

            $targetValue = $userGoal->target_value > 0
                ? $userGoal->target_value
                : ($userGoal->daily_goal ?? 0);

            $progress_percentage = $targetValue > 0
                ? round(($current_value / $targetValue) * 100)
                : 0;

                return [
                    'id' => $userGoal->id,
                    'goal_type' => [
                    'id' => $userGoal->goalType?->id,
                    'name' => $userGoal->goalType?->name ?? $userGoal->goal_type ?? 'Legacy Goal',
                    'description' => $userGoal->goalType?->description ?? $userGoal->description,
                    'unit' => $goalUnit,
                    'category' => $goalCategory,
                    ],
                'target_value' => $targetValue,
                    'current_value' => $current_value,
                    'progress_percentage' => min(100, $progress_percentage),
                'is_completed' => $targetValue > 0 && $current_value >= $targetValue,
                    'is_active' => $userGoal->is_active,
                ];
            });

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'user_type' => $user->user_type,
                'display_name' => $user->name,
                'user_tag' => ucfirst($user->user_type)
            ],
            'metrics' => [
                'cards_studied_today' => $cardsStudiedToday,
                'current_streak' => $streak,
                'overall_progress' => $overallProgress,
                'study_time' => $studyTime,
            ],
            'recent_decks' => $recentDecks,
            'todays_goal' => [
                'studied' => $cardsStudiedToday,
                'goal' => $dailyGoal,
                'remaining' => $remaining,
                'progress_percentage' => $progressPercentage,
                'goal_type' => $goalType,
                'goal_description' => $goalDescription,
                'is_completed' => $cardsStudiedToday >= $dailyGoal,
                'message' => $remaining > 0 ? "{$remaining} more cards to reach your daily goal!" : "Goal completed! Great job!"
            ],
            'user_goals' => $userGoals
        ]);
    }

    private function searchReviewTableExists(): bool
    {
        return Schema::hasTable('search_flashcard_reviews');
    }

    private function resolveLocalUser(array $supabaseUser): User
    {
        $supabaseUserId = $supabaseUser['id'] ?? null;
        $email = $supabaseUser['email'] ?? null;
        $userRole = $supabaseUser['role'] ?? 'student';
        $name = $supabaseUser['user_metadata']['full_name'] ?? ($email ?? 'User');

        $user = null;

        if (!empty($supabaseUserId)) {
            $user = User::where('supabase_user_id', $supabaseUserId)->first();
        }

        if (!$user && !empty($email)) {
            $user = User::where('email', $email)->first();
        }

        if (!$user) {
            return User::create([
                'supabase_user_id' => $supabaseUserId,
                'name' => $name,
                'email' => $email,
                'user_type' => $userRole,
                'password' => null,
            ]);
        }

        $updateData = [];

        if (!empty($supabaseUserId) && $user->supabase_user_id !== $supabaseUserId) {
            $updateData['supabase_user_id'] = $supabaseUserId;
        }

        if (!empty($name) && $user->name !== $name) {
            $updateData['name'] = $name;
        }

        if (!empty($userRole) && $user->user_type !== $userRole) {
            $updateData['user_type'] = $userRole;
        }

        if (!empty($email) && $user->email !== $email) {
            $updateData['email'] = $email;
        }

        if (!empty($updateData)) {
            $user->update($updateData);
        }

        return $user;
    }

    /**
     * Ensure the user has all achievements they qualify for based on points
     * 
     * @param \App\Models\User $user
     * @return void
     */
    private function ensurePointBasedAchievements(User $user, string $achievementUserId): void
    {
        if ($achievementUserId === '') {
            return;
        }

        $points = $user->points ?? 0;

        // Define point thresholds for achievements
        $pointAchievements = [
            10 => [
                'name' => 'Getting Started',
                'description' => 'Earned 10 points in the system',
                'icon' => '🎯',
                'points' => 10
            ],
            50 => [
                'name' => 'Fast Learner',
                'description' => 'Earned 50 points in the system',
                'icon' => '🚀',
                'points' => 50
            ],
            100 => [
                'name' => 'Knowledge Seeker',
                'description' => 'Earned 100 points in the system',
                'icon' => '🧠',
                'points' => 100
            ],
            250 => [
                'name' => 'Memory Master',
                'description' => 'Earned 250 points in the system',
                'icon' => '🏆',
                'points' => 250
            ],
            500 => [
                'name' => 'Study Champion',
                'description' => 'Earned 500 points in the system',
                'icon' => '🥇',
                'points' => 500
            ],
        ];

        // Check which achievements the user qualifies for based on points
        foreach ($pointAchievements as $threshold => $achievementData) {
            if ($points >= $threshold) {
                // Check if this achievement already exists in the database
                $existingAchievement = Achievement::where('name', $achievementData['name'])->first();

                if ($existingAchievement) {
                    // Use existing achievement but update it if needed
                    $achievement = $existingAchievement;

                    // Update the achievement if icon or points don't match expected values
                    $needsUpdate = false;
                    if ($achievement->icon !== $achievementData['icon']) {
                        $achievement->icon = $achievementData['icon'];
                        $needsUpdate = true;
                    }
                    if ($achievement->points !== $achievementData['points']) {
                        $achievement->points = $achievementData['points'];
                        $needsUpdate = true;
                    }
                    if ($needsUpdate) {
                        $achievement->save();
                    }
                } else {
                    // Create a new achievement in the database
                    $achievement = Achievement::create([
                        'name' => $achievementData['name'],
                        'description' => $achievementData['description'],
                        'icon' => $achievementData['icon'],
                        'points' => $achievementData['points']
                    ]);
                }

                // Check if user has already been assigned this achievement
                $userHasAchievement = UserAchievement::where('user_id', $achievementUserId)
                    ->where('achievement_id', $achievement->id)
                    ->exists();

                if (!$userHasAchievement) {
                    // Create record in user_achievements table
                    UserAchievement::create([
                        'user_id' => $achievementUserId,
                        'achievement_id' => $achievement->id,
                        'achieved_at' => now()
                    ]);
                }
            }
        }
    }
}
