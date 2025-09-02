<?php

namespace App\Http\Controllers;

use App\Models\Deck;
use App\Models\UserGoal;
use App\Models\Achievement;
use Illuminate\Http\Request;
use App\Models\StudyMaterial;
use Illuminate\Support\Carbon;
use App\Models\FlashcardReview;
use App\Models\UserAchievement;
use App\Models\StudySessionTiming;
use App\Models\StudyActivityTiming;
use Illuminate\Support\Facades\Log;

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
        $userRole = $supabaseUser['role'] ?? 'student';
        $appUser = \App\Models\User::firstOrCreate(
            ['email' => $supabaseUser['email']],
            [
                'id' => $userId,
                'name' => $supabaseUser['user_metadata']['full_name'] ?? ($supabaseUser['email'] ?? 'User'),
                'user_type' => $userRole,
                'password' => null,
            ]
        );

        // Update user_type if it has changed
        if ($appUser->user_type !== $userRole) {
            $appUser->update(['user_type' => $userRole]);
        }
        $localUserId = $appUser->id;

        // Cards studied today - combine regular flashcards and search flashcards
        $regularCardsToday = FlashcardReview::where('user_id', $localUserId)
            ->whereDate('reviewed_at', $today)
            ->count();

        $searchCardsToday = \App\Models\SearchFlashcardReview::where('user_id', $userId) // Search reviews use supabase user id
            ->whereDate('reviewed_at', $today)
            ->count();

        $cardsStudiedToday = $regularCardsToday + $searchCardsToday;

        // Current streak (days in a row with at least one review from either regular or search flashcards)
        $regularDates = FlashcardReview::where('user_id', $localUserId)
            ->selectRaw('DATE(reviewed_at) as date')
            ->distinct()
            ->pluck('date');

        $searchDates = \App\Models\SearchFlashcardReview::where('user_id', $userId)
            ->selectRaw('DATE(reviewed_at) as date')
            ->distinct()
            ->pluck('date');

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

        // Study time today (sum from new timing tables + fallback to old review-based timing)
        // Use user_id field in timing tables for accurate user-specific timing
        $timingStudyTime = StudySessionTiming::whereDate('session_start', $today)
            ->where('user_id', $userId)
            ->sum('total_study_time');

        // Fallback: Old method for backward compatibility (if no timing data exists)
        $regularStudyTime = FlashcardReview::where('user_id', $localUserId)
            ->whereDate('reviewed_at', $today)
            ->sum('study_time');

        $searchStudyTime = \App\Models\SearchFlashcardReview::where('user_id', $userId)
            ->whereDate('reviewed_at', $today)
            ->sum('study_time');

        // Use timing tables if available, otherwise fallback to review-based timing
        $studyTimeSeconds = $timingStudyTime > 0 ? $timingStudyTime : ($regularStudyTime + $searchStudyTime);
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
        $userRole = $supabaseUser['role'] ?? 'student';
        $appUser = \App\Models\User::firstOrCreate(
            ['email' => $supabaseUser['email']],
            [
                'id' => $userId,
                'name' => $supabaseUser['user_metadata']['full_name'] ?? ($supabaseUser['email'] ?? 'User'),
                'user_type' => $userRole,
                'password' => null,
            ]
        );

        // Update user_type if it has changed
        if ($appUser->user_type !== $userRole) {
            $appUser->update(['user_type' => $userRole]);
        }
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

            Log::info($lastStudied);

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
        $appUser = \App\Models\User::firstOrCreate(
            ['email' => $supabaseUser['email']],
            [
                'id' => $userId,
                'name' => $supabaseUser['user_metadata']['full_name'] ?? ($supabaseUser['email'] ?? 'User'),
                'user_type' => $supabaseUser['role'] ?? 'student',
                'password' => null,
            ]
        );
        $localUserId = $appUser->id;

        // Get goal type and set defaults
        $goalType = $goal ? $goal->goal_type : 'cards_studied';
        $goalDescription = $goal ? $goal->description : ($goalType === 'study_time' ? 'Study time daily' : 'Study cards daily');
        $dailyGoal = $goal ? $goal->daily_goal : ($goalType === 'study_time' ? 60 : 50); // 60 minutes or 50 cards default

        if ($goalType === 'study_time') {
            // Calculate study time today using new timing tables with user_id + fallback
            $timingStudyTime = StudySessionTiming::whereDate('session_start', $today)
                ->where('user_id', $userId)
                ->sum('total_study_time');

            // Fallback to review-based timing if no timing data
            $regularStudyTime = FlashcardReview::where('user_id', $localUserId)
                ->whereDate('reviewed_at', $today)
                ->sum('study_time');

            $searchStudyTime = \App\Models\SearchFlashcardReview::where('user_id', $userId)
                ->whereDate('reviewed_at', $today)
                ->sum('study_time');

            // Use timing tables if available, otherwise fallback to review-based timing
            $studyTimeSeconds = $timingStudyTime > 0 ? $timingStudyTime : ($regularStudyTime + $searchStudyTime);
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

            $searchCardsToday = \App\Models\SearchFlashcardReview::where('user_id', $userId)
                ->whereDate('reviewed_at', $today)
                ->count();

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
        // Map to local user id
        $user = \App\Models\User::where('id', $supabaseUser['id'])
            ->orWhere('email', $supabaseUser['email'])
            ->first();
        if (!$user) {
            return response()->json(['achievements' => []]);
        }

        // First ensure user has all achievements they qualify for
        $this->ensurePointBasedAchievements($user);

        // Now get all achievements with no duplicates
        $userAchievements = Achievement::join('user_achievements', 'achievements.id', '=', 'user_achievements.achievement_id')
            ->where('user_achievements.user_id', $user->id)
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
        $userId = $supabaseUser['id'];

        // Get user from database or create if doesn't exist
        // First try to find by Supabase ID, then by email
        $user = \App\Models\User::where('id', $userId)
            ->orWhere('email', $supabaseUser['email'])
            ->first();

        if (!$user) {
            // Create new user if none exists
            $userRole = $supabaseUser['role'] ?? 'student';
            $user = \App\Models\User::create([
                'id' => $userId,
                'name' => $supabaseUser['user_metadata']['full_name'] ?? $supabaseUser['email'] ?? 'User',
                'email' => $supabaseUser['email'],
                'user_type' => $userRole,
                'password' => null
            ]);
        } else {
            // Update existing user with latest info
            $userRole = $supabaseUser['role'] ?? 'student';
            $user->update([
                'name' => $supabaseUser['user_metadata']['full_name'] ?? $supabaseUser['email'] ?? 'User',
                'user_type' => $userRole
            ]);
        }

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
        // First try to find by Supabase ID, then by email
        $user = \App\Models\User::where('id', $userId)
            ->orWhere('email', $supabaseUser['email'])
            ->first();

        if (!$user) {
            // Create new user if none exists
            $userRole = $supabaseUser['role'] ?? 'student';
            $user = \App\Models\User::create([
                'id' => $userId,
                'name' => $supabaseUser['user_metadata']['full_name'] ?? $supabaseUser['email'] ?? 'User',
                'email' => $supabaseUser['email'],
                'user_type' => $userRole,
                'password' => null
            ]);
        } else {
            // Update existing user with latest info
            $userRole = $supabaseUser['role'] ?? 'student';
            $user->update([
                'name' => $supabaseUser['user_metadata']['full_name'] ?? $supabaseUser['email'] ?? 'User',
                'user_type' => $userRole
            ]);
        }

        $today = Carbon::today();

        // Use local app user id for review stats
        $localUserId = $user->id;

        // Cards studied today - combine regular flashcards and search flashcards
        $regularCardsToday = FlashcardReview::where('user_id', $localUserId)
            ->whereDate('reviewed_at', $today)
            ->count();

        $searchCardsToday = \App\Models\SearchFlashcardReview::where('user_id', $userId) // Search reviews use supabase user id
            ->whereDate('reviewed_at', $today)
            ->count();

        $cardsStudiedToday = $regularCardsToday + $searchCardsToday;

        // Current streak (days in a row with at least one review from either regular or search flashcards)
        $regularDates = FlashcardReview::where('user_id', $localUserId)
            ->selectRaw('DATE(reviewed_at) as date')
            ->distinct()
            ->pluck('date');

        $searchDates = \App\Models\SearchFlashcardReview::where('user_id', $userId)
            ->selectRaw('DATE(reviewed_at) as date')
            ->distinct()
            ->pluck('date');

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

        // Study time today (use new timing tables + fallback to review-based timing)
        // Get study time from new timing tables using user_id
        $timingStudyTime = StudySessionTiming::whereDate('session_start', $today)
            ->where('user_id', $userId)
            ->sum('total_study_time');

        // Fallback to review-based timing if no timing data exists
        if ($timingStudyTime == 0) {
            $regularStudyTime = FlashcardReview::where('user_id', $localUserId)
                ->whereDate('reviewed_at', $today)
                ->sum('study_time');

            $searchStudyTime = \App\Models\SearchFlashcardReview::where('user_id', $userId)
                ->whereDate('reviewed_at', $today)
                ->sum('study_time');

            $studyTimeSeconds = $regularStudyTime + $searchStudyTime;
        } else {
            $studyTimeSeconds = $timingStudyTime;
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
                'name' => $deck->name,
                'card_count' => $total,
                'last_studied' => $lastStudiedText,
                'progress' => $progress,
            ];
        });

        // Today's goal - use new goal system
        // First check if user has a Daily Flashcards goal in the new system
        $dailyFlashcardsGoal = \App\Models\UserGoal::where('user_id', $userId)
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

        // Get user's additional goals (new goal system)
        $userGoals = \App\Models\UserGoal::where('user_id', $userId)
            ->where('is_active', true)
            ->with('goalType')
            ->get()
            ->map(function ($userGoal) use ($cardsStudiedToday, $studyTimeMinutes, $localUserId, $today) {
                $current_value = $userGoal->current_value;

                // For flashcard goals, use today's studied count
                if (
                    $userGoal->goalType && $userGoal->goalType->category === 'study' &&
                    $userGoal->goalType->unit === 'cards'
                ) {
                    $current_value = $cardsStudiedToday;
                }

                // For time-based goals, use today's study time in minutes
                if (
                    $userGoal->goalType && $userGoal->goalType->category === 'time' &&
                    $userGoal->goalType->unit === 'minutes'
                ) {
                    $current_value = $studyTimeMinutes;
                }

                // For engagement goals, calculate based on review activity and ratings
                if (
                    $userGoal->goalType && $userGoal->goalType->category === 'engagement' &&
                    $userGoal->goalType->unit === 'points'
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
                    $searchReviews = \App\Models\SearchFlashcardReview::where('user_id', $userGoal->user_id)
                        ->whereDate('reviewed_at', $today)
                        ->get();

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

                $progress_percentage = $userGoal->target_value > 0 ?
                    round(($current_value / $userGoal->target_value) * 100) : 0;

                return [
                    'id' => $userGoal->id,
                    'goal_type' => [
                        'id' => $userGoal->goalType->id,
                        'name' => $userGoal->goalType->name,
                        'description' => $userGoal->goalType->description,
                        'unit' => $userGoal->goalType->unit,
                        'category' => $userGoal->goalType->category,
                    ],
                    'target_value' => $userGoal->target_value,
                    'current_value' => $current_value,
                    'progress_percentage' => min(100, $progress_percentage),
                    'is_completed' => $current_value >= $userGoal->target_value,
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

    /**
     * Ensure the user has all achievements they qualify for based on points
     * 
     * @param \App\Models\User $user
     * @return void
     */
    private function ensurePointBasedAchievements($user)
    {
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
                $userHasAchievement = UserAchievement::where('user_id', $user->id)
                    ->where('achievement_id', $achievement->id)
                    ->exists();

                if (!$userHasAchievement) {
                    // Create record in user_achievements table
                    UserAchievement::create([
                        'user_id' => $user->id,
                        'achievement_id' => $achievement->id,
                        'achieved_at' => now()
                    ]);
                }
            }
        }
    }
}
