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
        // Store the user data in the request for later use
        $request->merge(['supabase_user' => $response->json()]);
        return $next($request);
      }

      return response()->json(['message' => 'Invalid token'], 401);
    } catch (\Exception $e) {
      return response()->json(['message' => 'Authentication failed'], 401);
    }
  }
}
