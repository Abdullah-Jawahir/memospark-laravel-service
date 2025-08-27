<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminAuth
{
  public function handle(Request $request, Closure $next)
  {
    // Check if we have a valid Supabase user with local user data
    $supabaseUser = $request->get('supabase_user');

    if (!$supabaseUser || !isset($supabaseUser['local_user'])) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }

    $localUser = $supabaseUser['local_user'];

    // Check if user has admin role in our local database
    if ($localUser->user_type !== 'admin') {
      return response()->json(['message' => 'Access denied. Admin role required.'], 403);
    }

    return $next($request);
  }
}
