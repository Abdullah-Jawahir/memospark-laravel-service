<?php

namespace App\Http\Controllers;

use App\Models\GoalType;
use App\Models\UserGoal;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StudentGoalController extends Controller
{
    /**
     * Get available goal types for students
     */
    public function getGoalTypes(Request $request)
    {
        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }

        try {
            $goalTypes = GoalType::where('is_active', true)
                ->orderBy('category')
                ->orderBy('name')
                ->get();

            return response()->json($goalTypes);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Get goal types error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch goal types'], 500);
        }
    }

    /**
     * Get user's goals
     */
    public function getUserGoals(Request $request)
    {
        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }

        $userId = $supabaseUser['id'];

        try {
            $userGoals = UserGoal::where('user_id', $userId)
                ->with('goalType')
                ->get();

            return response()->json($userGoals);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Get user goals error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch user goals'], 500);
        }
    }

    /**
     * Create or update a user goal
     */
    public function setGoal(Request $request)
    {
        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }

        $userId = $supabaseUser['id'];

        $request->validate([
            'goal_type_id' => 'required|exists:goal_types,id',
            'target_value' => 'required|integer|min:1|max:1000',
        ]);

        try {
            // Check if user already has this goal type
            $existingGoal = UserGoal::where('user_id', $userId)
                ->where('goal_type_id', $request->goal_type_id)
                ->first();

            if ($existingGoal) {
                // Update existing goal
                $existingGoal->update([
                    'target_value' => $request->target_value,
                    'is_active' => true,
                ]);
                $userGoal = $existingGoal;
            } else {
                // Create new goal
                $userGoal = UserGoal::create([
                    'user_id' => $userId,
                    'goal_type_id' => $request->goal_type_id,
                    'target_value' => $request->target_value,
                    'current_value' => 0,
                    'is_active' => true,
                ]);
            }

            // Load the goal type relationship
            $userGoal->load('goalType');

            return response()->json([
                'message' => 'Goal set successfully',
                'goal' => $userGoal
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Set goal error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to set goal'], 500);
        }
    }

    /**
     * Create a custom goal type for the user
     */
    public function createCustomGoalType(Request $request)
    {
        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }

        $userId = $supabaseUser['id'];

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:500',
            'unit' => 'required|string|max:50',
            'category' => 'required|in:study,engagement,achievement,time',
            'target_value' => 'required|integer|min:1|max:1000',
            'min_value' => 'integer|min:0',
            'max_value' => 'integer|min:1|max:10000',
        ]);

        try {
            // Create custom goal type
            $goalType = GoalType::create([
                'id' => Str::uuid(),
                'name' => $request->name . ' (Custom)',
                'description' => $request->description,
                'unit' => $request->unit,
                'category' => $request->category,
                'is_active' => false, // Custom goals are only for the creating user
                'default_value' => $request->target_value,
                'min_value' => $request->min_value ?? 1,
                'max_value' => $request->max_value ?? 1000,
            ]);

            // Create user goal with this custom type
            $userGoal = UserGoal::create([
                'user_id' => $userId,
                'goal_type_id' => $goalType->id,
                'target_value' => $request->target_value,
                'current_value' => 0,
                'is_active' => true,
            ]);

            $userGoal->load('goalType');

            return response()->json([
                'message' => 'Custom goal created successfully',
                'goal_type' => $goalType,
                'user_goal' => $userGoal
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Create custom goal error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create custom goal'], 500);
        }
    }

    /**
     * Toggle goal active status
     */
    public function toggleGoal(Request $request, $goalId)
    {
        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }

        $userId = $supabaseUser['id'];

        try {
            $userGoal = UserGoal::where('user_id', $userId)
                ->where('id', $goalId)
                ->first();

            if (!$userGoal) {
                return response()->json(['error' => 'Goal not found'], 404);
            }

            $userGoal->update(['is_active' => !$userGoal->is_active]);

            return response()->json([
                'message' => 'Goal status updated successfully',
                'goal' => $userGoal
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Toggle goal error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to update goal status'], 500);
        }
    }

    /**
     * Delete a user goal
     */
    public function deleteGoal(Request $request, $goalId)
    {
        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }

        $userId = $supabaseUser['id'];

        try {
            $userGoal = UserGoal::where('user_id', $userId)
                ->where('id', $goalId)
                ->first();

            if (!$userGoal) {
                return response()->json(['error' => 'Goal not found'], 404);
            }

            $userGoal->delete();

            return response()->json(['message' => 'Goal deleted successfully']);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Delete goal error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to delete goal'], 500);
        }
    }
}
