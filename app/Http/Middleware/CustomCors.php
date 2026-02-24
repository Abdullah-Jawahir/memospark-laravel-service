<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomCors
{
  public function handle(Request $request, Closure $next): Response
  {
    // Handle preflight
    if ($request->getMethod() === 'OPTIONS') {
      return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept')
        ->header('Access-Control-Max-Age', '3600');
    }

    $response = $next($request);

    // Add CORS headers to actual responses
    $response->headers->set('Access-Control-Allow-Origin', '*');
    $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
    $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept');
    $response->headers->set('Access-Control-Max-Age', '3600');

    return $response;
  }
}
