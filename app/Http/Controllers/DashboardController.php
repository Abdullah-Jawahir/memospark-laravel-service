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

        // Study time today (sum of review sessions' durations, here just count as cards * 3min as a placeholder)
        $studyTimeMinutes = $cardsStudiedToday * 3;
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
            $total = $deck->studyMaterials->count();
            $reviewed = $deck->studyMaterials->filter(function ($sm) use ($userId) {
                return $sm->reviews->where('user_id', $userId)->count() > 0;
            })->count();
            $progress = $total > 0 ? round(($reviewed / $total) * 100) : 0;
            $lastStudied = $deck->studyMaterials->flatMap->reviews
                ->where('user_id', $userId)
                ->sortByDesc('reviewed_at')
                ->first()?->reviewed_at;
            return [
                'name' => $deck->name,
                'card_count' => $total,
                'last_studied' => $lastStudied,
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
        return response()->json([
            'studied' => $cardsStudiedToday,
            'goal' => $dailyGoal,
            'remaining' => $remaining,
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
}
