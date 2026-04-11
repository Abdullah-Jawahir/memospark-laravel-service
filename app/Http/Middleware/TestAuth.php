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

      $localUser = $this->resolveLocalUser($mockUserData);

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

  private function resolveLocalUser(array $mockUserData): User
  {
    $localUser = User::where('supabase_user_id', $mockUserData['id'])->first();

    if (!$localUser) {
      $localUser = User::where('email', $mockUserData['email'])->first();
    }

    if (!$localUser) {
      return User::create([
        'supabase_user_id' => $mockUserData['id'],
        'name' => $mockUserData['user_metadata']['full_name'],
        'email' => $mockUserData['email'],
        'user_type' => 'student',
        'password' => null,
      ]);
    }

    $updates = [];

    if ($localUser->supabase_user_id !== $mockUserData['id']) {
      $updates['supabase_user_id'] = $mockUserData['id'];
    }

    if ($localUser->name !== $mockUserData['user_metadata']['full_name']) {
      $updates['name'] = $mockUserData['user_metadata']['full_name'];
    }

    if ($localUser->email !== $mockUserData['email']) {
      $updates['email'] = $mockUserData['email'];
    }

    if (!empty($updates)) {
      $localUser->update($updates);
    }

    return $localUser;
  }
}
