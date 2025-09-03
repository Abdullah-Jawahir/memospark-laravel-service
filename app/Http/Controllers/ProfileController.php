<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    /**
     * Get current user's profile
     */
    public function getProfile(Request $request)
    {
        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }

        $localUser = $supabaseUser['local_user'];

        return response()->json([
            'user' => [
                'id' => $localUser->id,
                'name' => $localUser->name,
                'email' => $localUser->email,
                'user_type' => $localUser->user_type,
                'points' => $localUser->points,
                'created_at' => $localUser->created_at,
                'updated_at' => $localUser->updated_at,
            ],
            'supabase_profile' => [
                'id' => $supabaseUser['id'],
                'email' => $supabaseUser['email'],
                'user_metadata' => $supabaseUser['user_metadata'] ?? [],
            ]
        ]);
    }

    /**
     * Update user profile (name and email)
     */
    public function updateProfile(Request $request)
    {
        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $supabaseUser['local_user']->id,
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $localUser = $supabaseUser['local_user'];

        // Update local database
        $localUser->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        // Update Supabase user metadata and email
        try {
            $this->updateSupabaseUser($supabaseUser['id'], [
                'email' => $request->email,
                'user_metadata' => array_merge(
                    $supabaseUser['user_metadata'] ?? [],
                    ['full_name' => $request->name]
                )
            ]);
        } catch (\Exception $e) {
            // Log the error but don't fail the request
            Log::warning('Failed to update Supabase user: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $localUser->refresh()
        ]);
    }

    /**
     * Update user password via Supabase
     */
    public function updatePassword(Request $request)
    {
        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Debug: Check if service role key is set
        $serviceRoleKey = env('SUPABASE_SERVICE_ROLE_KEY');
        if (!$serviceRoleKey || $serviceRoleKey === 'your_service_role_key_here') {
            return response()->json([
                'error' => 'SUPABASE_SERVICE_ROLE_KEY is not properly configured. Please set it in your .env file.'
            ], 500);
        }

        try {
            // First verify current password by attempting to sign in
            $signInResponse = Http::withHeaders([
                'apikey' => env('SUPABASE_KEY'),
                'Content-Type' => 'application/json',
            ])->post(rtrim(config('services.supabase.url'), '/') . '/auth/v1/token?grant_type=password', [
                'email' => $supabaseUser['email'],
                'password' => $request->current_password,
            ]);

            if (!$signInResponse->successful()) {
                return response()->json(['errors' => ['current_password' => ['Current password is incorrect']]], 422);
            }

            // Update password using Supabase admin API
            $updateResponse = Http::withHeaders([
                'apikey' => env('SUPABASE_SERVICE_ROLE_KEY'),
                'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_ROLE_KEY'),
                'Content-Type' => 'application/json',
            ])->put(rtrim(config('services.supabase.url'), '/') . '/auth/v1/admin/users/' . $supabaseUser['id'], [
                'password' => $request->new_password,
            ]);

            if (!$updateResponse->successful()) {
                Log::error('Supabase password update failed', [
                    'status' => $updateResponse->status(),
                    'response' => $updateResponse->body(),
                    'user_id' => $supabaseUser['id']
                ]);
                return response()->json([
                    'error' => 'Failed to update password',
                    'details' => $updateResponse->body()
                ], 500);
            }

            return response()->json(['message' => 'Password updated successfully']);
        } catch (\Exception $e) {
            Log::error('Password update error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to update password'], 500);
        }
    }

    /**
     * Helper method to update Supabase user
     */
    private function updateSupabaseUser($userId, $data)
    {
        $response = Http::withHeaders([
            'apikey' => env('SUPABASE_SERVICE_ROLE_KEY'),
            'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_ROLE_KEY'),
            'Content-Type' => 'application/json',
        ])->put(rtrim(config('services.supabase.url'), '/') . '/auth/v1/admin/users/' . $userId, $data);

        if (!$response->successful()) {
            throw new \Exception('Failed to update Supabase user: ' . $response->body());
        }

        return $response->json();
    }
}
