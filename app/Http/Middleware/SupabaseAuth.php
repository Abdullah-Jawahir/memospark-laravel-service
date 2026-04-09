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
    if (app()->environment('testing') && $request->user()) {
      $localUser = $request->user();

      $request->merge([
        'supabase_user' => [
          'id' => $localUser->supabase_user_id ?? (string) $localUser->id,
          'email' => $localUser->email,
          'user_metadata' => [
            'full_name' => $localUser->name,
          ],
          'role' => $localUser->user_type,
          'local_user' => $localUser,
        ],
      ]);

      return $next($request);
    }

    $token = $request->bearerToken();

    if (!$token) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }

    try {
      // Verify the token with Supabase
      $response = Http::withHeaders([
        'apikey' => config('services.supabase.key'),
        'Authorization' => 'Bearer ' . $token
      ])->get(rtrim(config('services.supabase.url'), '/') . '/auth/v1/user');

      if ($response->successful()) {
        $userData = $response->json();

        $email = $userData['email'] ?? null;
        $userId = $userData['id'] ?? null;

        if (!$email || !$userId) {
          return response()->json(['message' => 'Invalid user data from Supabase'], 401);
        }

        $localUser = $this->resolveLocalUser($userData);

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

  private function resolveLocalUser(array $userData): User
  {
    $email = $userData['email'] ?? null;
    $supabaseUserId = $userData['id'] ?? null;
    $name = $userData['user_metadata']['full_name'] ?? $email;

    $localUser = null;

    if (!empty($supabaseUserId)) {
      $localUser = User::where('supabase_user_id', $supabaseUserId)->first();
    }

    if (!$localUser && !empty($email)) {
      $localUser = User::where('email', $email)->first();
    }

    if (!$localUser) {
      return User::create([
        'supabase_user_id' => $supabaseUserId,
        'name' => $name,
        'email' => $email,
        'user_type' => 'student',
        'password' => null,
      ]);
    }

    $updates = [];

    if (!empty($supabaseUserId) && $localUser->supabase_user_id !== $supabaseUserId) {
      $updates['supabase_user_id'] = $supabaseUserId;
    }

    if (!empty($name) && $localUser->name !== $name) {
      $updates['name'] = $name;
    }

    if (!empty($email) && $localUser->email !== $email) {
      $updates['email'] = $email;
    }

    if (!empty($updates)) {
      $localUser->update($updates);
    }

    return $localUser;
  }
}
