<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;

class TestAuth
{
  public function handle(Request $request, Closure $next)
  {
    $token = $request->bearerToken();

    if (!$token) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }

    // For testing purposes, accept any token that starts with 'test-token-'
    if (str_starts_with($token, 'test-token-')) {
      $testUserId = str_replace('test-token-', '', $token);

      // Create a mock user data structure similar to what SupabaseAuth would provide
      $mockUserData = [
        'id' => $testUserId,
        'email' => 'test-' . $testUserId . '@example.com',
        'user_metadata' => [
          'full_name' => 'Test User ' . $testUserId
        ]
      ];

      // Try to find or create a test user in the local database
      $localUser = User::where('email', $mockUserData['email'])->first();

      if (!$localUser) {
        // Create a test user
        $localUser = User::create([
          'supabase_user_id' => $testUserId,
          'name' => $mockUserData['user_metadata']['full_name'],
          'email' => $mockUserData['email'],
          'user_type' => 'student',
          'password' => null,
        ]);
      }

      // Prepare user data for the request (same structure as SupabaseAuth)
      $supabaseUser = [
        'id' => $mockUserData['id'],
        'email' => $mockUserData['email'],
        'user_metadata' => $mockUserData['user_metadata'],
        'role' => $localUser->user_type,
        'local_user' => $localUser
      ];

      // Store the user data in the request for later use
      $request->merge(['supabase_user' => $supabaseUser]);
      return $next($request);
    }

    return response()->json(['message' => 'Invalid test token'], 401);
  }
}
