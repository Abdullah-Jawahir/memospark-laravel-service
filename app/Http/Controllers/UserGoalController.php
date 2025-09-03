<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserGoal;

class UserGoalController extends Controller
{
    public function store(Request $request)
    {
        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }

        $request->validate([
            'daily_goal' => 'required|integer|min:1',
            'goal_type' => 'sometimes|in:cards_studied,study_time,decks_completed',
            'description' => 'sometimes|string|max:255',
        ]);

        $goal = UserGoal::create([
            'user_id' => $supabaseUser['id'],
            'daily_goal' => $request->daily_goal,
            'goal_type' => $request->goal_type ?? 'cards_studied',
            'description' => $request->description ?? ($request->goal_type === 'study_time' ? 'Daily study time goal' : 'Daily cards study goal'),
        ]);

        return response()->json($goal, 201);
    }

    public function update(Request $request, $id)
    {
        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }

        $goal = UserGoal::where('id', $id)
            ->where('user_id', $supabaseUser['id'])
            ->first();

        if (!$goal) {
            return response()->json(['error' => 'Goal not found or access denied'], 404);
        }

        $request->validate([
            'daily_goal' => 'sometimes|integer|min:1',
            'goal_type' => 'sometimes|in:cards_studied,study_time,decks_completed',
            'description' => 'sometimes|string|max:255',
        ]);

        $goal->update($request->only(['daily_goal', 'goal_type', 'description']));

        return response()->json($goal);
    }

    public function index(Request $request)
    {
        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }

        $goals = UserGoal::where('user_id', $supabaseUser['id'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($goals);
    }
}
