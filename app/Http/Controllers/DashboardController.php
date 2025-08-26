<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\Deck;
use App\Models\UserGoal;
use App\Models\Achievement;
use App\Models\UserAchievement;
use App\Models\FlashcardReview;
use App\Models\StudyMaterial;

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

        // Cards studied today
        $cardsStudiedToday = FlashcardReview::where('user_id', $userId)
            ->whereDate('reviewed_at', $today)
            ->count();

        // Current streak (days in a row with at least one review)
        $dates = FlashcardReview::where('user_id', $userId)
            ->selectRaw('DATE(reviewed_at) as date')
            ->distinct()
            ->orderByDesc('date')
            ->pluck('date');
        $streak = 0;
        $current = $today->copy();
        foreach ($dates as $date) {
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
        $reviewed = FlashcardReview::where('user_id', $userId)
            ->distinct('study_material_id')
            ->count('study_material_id');
        $overallProgress = $total > 0 ? round(($reviewed / $total) * 100) : 0;

        // Study time today (sum of actual study time from reviews)
        $studyTimeSeconds = FlashcardReview::where('user_id', $userId)
            ->whereDate('reviewed_at', $today)
            ->sum('study_time');
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
        $decks = Deck::where('user_id', $userId)
            ->with(['studyMaterials.reviews' => function ($q) use ($userId) {
                $q->where('user_id', $userId);
            }])
            ->orderByDesc('updated_at')
            ->take(3)
            ->get();

        $result = $decks->map(function ($deck) use ($userId) {
            // Count only flashcard type study materials
            $total = $deck->studyMaterials->where('type', 'flashcard')->count();
            $reviewed = $deck->studyMaterials->where('type', 'flashcard')->filter(function ($sm) use ($userId) {
                return $sm->reviews->where('user_id', $userId)->count() > 0;
            })->count();
            $progress = $total > 0 ? round(($reviewed / $total) * 100) : 0;
            $lastStudied = $deck->studyMaterials->flatMap->reviews
                ->where('user_id', $userId)
                ->sortByDesc('reviewed_at')
                ->first()?->reviewed_at;

            // Format last studied time
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
        $cardsStudiedToday = FlashcardReview::where('user_id', $userId)
            ->whereDate('reviewed_at', $today)
            ->count();
        $dailyGoal = $goal ? $goal->daily_goal : 50;
        $remaining = max(0, $dailyGoal - $cardsStudiedToday);

        // Calculate progress percentage
        $progressPercentage = $dailyGoal > 0 ? round(($cardsStudiedToday / $dailyGoal) * 100) : 0;

        // Get goal type and description
        $goalType = $goal ? $goal->goal_type : 'cards_studied';
        $goalDescription = $goal ? $goal->description : 'Study cards daily';

        return response()->json([
            'studied' => $cardsStudiedToday,
            'goal' => $dailyGoal,
            'remaining' => $remaining,
            'progress_percentage' => $progressPercentage,
            'goal_type' => $goalType,
            'goal_description' => $goalDescription,
            'is_completed' => $cardsStudiedToday >= $dailyGoal,
            'message' => $remaining > 0 ? "{$remaining} more cards to reach your daily goal!" : "Goal completed! Great job!"
        ]);
    }

    public function achievements(Request $request)
    {
        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }
        $userId = $supabaseUser['id'];
        $achievements = Achievement::join('user_achievements', 'achievements.id', '=', 'user_achievements.achievement_id')
            ->where('user_achievements.user_id', $userId)
            ->get(['achievements.*', 'user_achievements.achieved_at']);
        $result = $achievements->map(function ($a) {
            return [
                'name' => $a->name,
                'description' => $a->description,
                'icon' => $a->icon,
                'achieved_at' => $a->achieved_at,
            ];
        });
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
            $user = \App\Models\User::create([
                'id' => $userId,
                'name' => $supabaseUser['user_metadata']['full_name'] ?? $supabaseUser['email'] ?? 'User',
                'email' => $supabaseUser['email'],
                'user_type' => 'student',
                'password' => null
            ]);
        } else {
            // Update existing user with latest info
            $user->update([
                'name' => $supabaseUser['user_metadata']['full_name'] ?? $supabaseUser['email'] ?? 'User',
                'user_type' => 'student'
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
            $user = \App\Models\User::create([
                'id' => $userId,
                'name' => $supabaseUser['user_metadata']['full_name'] ?? $supabaseUser['email'] ?? 'User',
                'email' => $supabaseUser['email'],
                'user_type' => 'student',
                'password' => null
            ]);
        } else {
            // Update existing user with latest info
            $user->update([
                'name' => $supabaseUser['user_metadata']['full_name'] ?? $supabaseUser['email'] ?? 'User',
                'user_type' => 'student'
            ]);
        }

        $today = Carbon::today();

        // Cards studied today
        $cardsStudiedToday = FlashcardReview::where('user_id', $userId)
            ->whereDate('reviewed_at', $today)
            ->count();

        // Current streak
        $dates = FlashcardReview::where('user_id', $userId)
            ->selectRaw('DATE(reviewed_at) as date')
            ->distinct()
            ->orderByDesc('date')
            ->pluck('date');
        $streak = 0;
        $current = $today->copy();
        foreach ($dates as $date) {
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
        $reviewed = FlashcardReview::where('user_id', $userId)
            ->distinct('study_material_id')
            ->count('study_material_id');
        $overallProgress = $total > 0 ? round(($reviewed / $total) * 100) : 0;

        // Study time today
        $studyTimeSeconds = FlashcardReview::where('user_id', $userId)
            ->whereDate('reviewed_at', $today)
            ->sum('study_time');
        $studyTimeMinutes = intval($studyTimeSeconds / 60);
        $hours = intdiv($studyTimeMinutes, 60);
        $minutes = $studyTimeMinutes % 60;
        $studyTime = ($hours ? $hours . 'h ' : '') . $minutes . 'm';

        // Recent study decks
        $decks = Deck::where('user_id', $userId)
            ->with(['studyMaterials.reviews' => function ($q) use ($userId) {
                $q->where('user_id', $userId);
            }])
            ->orderByDesc('updated_at')
            ->take(3)
            ->get();

        $recentDecks = $decks->map(function ($deck) use ($userId) {
            // Count only flashcard type study materials
            $total = $deck->studyMaterials->where('type', 'flashcard')->count();
            $reviewed = $deck->studyMaterials->where('type', 'flashcard')->filter(function ($sm) use ($userId) {
                return $sm->reviews->where('user_id', $userId)->count() > 0;
            })->count();
            $progress = $total > 0 ? round(($reviewed / $total) * 100) : 0;
            $lastStudied = $deck->studyMaterials->flatMap->reviews
                ->where('user_id', $userId)
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

        // Today's goal
        $goal = UserGoal::where('user_id', $userId)->latest()->first();
        $dailyGoal = $goal ? $goal->daily_goal : 50;
        $remaining = max(0, $dailyGoal - $cardsStudiedToday);
        $progressPercentage = $dailyGoal > 0 ? round(($cardsStudiedToday / $dailyGoal) * 100) : 0;
        $goalType = $goal ? $goal->goal_type : 'cards_studied';
        $goalDescription = $goal ? $goal->description : 'Study cards daily';

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
            ]
        ]);
    }
}
