<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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

        // Ensure we have the required user data structure
        $supabaseUser = [
          'id' => $userData['id'] ?? null,
          'email' => $userData['email'] ?? null,
          'user_metadata' => $userData['user_metadata'] ?? [],
        ];

        // Validate that we have the essential user data
        if (!$supabaseUser['id'] || !$supabaseUser['email']) {
          return response()->json(['message' => 'Invalid user data from Supabase'], 401);
        }

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
