<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\User;

class SupabaseAuth
{
  public function handle(Request $request, Closure $next)
  {
    $token = $request->bearerToken();

    if (!$token) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }

    try {
      // Verify the token with Supabase
      $response = Http::withHeaders([
        'apikey' => env('SUPABASE_KEY'),
        'Authorization' => 'Bearer ' . $token
      ])->get(env('SUPABASE_URL') . '/auth/v1/user');

      if ($response->successful()) {
        $userData = $response->json();

        // Get or create user in our local database
        $email = $userData['email'] ?? null;
        $userId = $userData['id'] ?? null;

        if (!$email || !$userId) {
          return response()->json(['message' => 'Invalid user data from Supabase'], 401);
        }

        // Find or create user in our local database
        $localUser = User::where('email', $email)->first();

        if (!$localUser) {
          // Create new user with default student role
          $localUser = User::create([
            'id' => $userId,
            'name' => $userData['user_metadata']['full_name'] ?? $email,
            'email' => $email,
            'user_type' => 'student', // Default to student
            'password' => null,
          ]);
        }

        // Prepare user data for the request
        $supabaseUser = [
          'id' => $userData['id'],
          'email' => $userData['email'],
          'user_metadata' => $userData['user_metadata'] ?? [],
          'role' => $localUser->user_type, // Use local user_type as role
          'local_user' => $localUser
        ];

        // Store the user data in the request for later use
        $request->merge(['supabase_user' => $supabaseUser]);
        return $next($request);
      }

      return response()->json(['message' => 'Invalid token'], 401);
    } catch (\Exception $e) {
      return response()->json(['message' => 'Authentication failed: ' . $e->getMessage()], 401);
    }
  }
}
