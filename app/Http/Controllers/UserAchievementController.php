<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserAchievement;

class UserAchievementController extends Controller
{
    public function store(Request $request)
    {
        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }
        $request->validate([
            'achievement_id' => 'required|exists:achievements,id',
        ]);
        $userAchievement = UserAchievement::create([
            'user_id' => $supabaseUser['id'],
            'achievement_id' => $request->achievement_id,
            'achieved_at' => now(),
        ]);
        return response()->json($userAchievement, 201);
    }
}
