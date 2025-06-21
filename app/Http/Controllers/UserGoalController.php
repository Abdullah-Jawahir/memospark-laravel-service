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
        ]);
        $goal = UserGoal::create([
            'user_id' => $supabaseUser['id'],
            'daily_goal' => $request->daily_goal,
        ]);
        return response()->json($goal, 201);
    }
}
