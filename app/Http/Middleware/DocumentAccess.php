<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Document;
use App\Http\Middleware\GuestDocumentAccess;

class DocumentAccess
{
  public function handle(Request $request, Closure $next)
  {
    $documentId = $request->route('id');

    // First, check if the document exists
    $document = Document::find($documentId);
    if (!$document) {
      return response()->json(['message' => 'Document not found'], 404);
    }

    // If the document has a user_id, it's an authenticated user's document
    // Check if user is authenticated via Supabase
    if ($document->user_id !== null) {
      $token = $request->bearerToken();

      if (!$token) {
        return response()->json(['message' => 'Unauthorized'], 401);
      }

      try {
        // Verify the token with Supabase
        $response = \Illuminate\Support\Facades\Http::withHeaders([
          'apikey' => config('services.supabase.key'),
          'Authorization' => 'Bearer ' . $token
        ])->get(rtrim(config('services.supabase.url'), '/') . '/auth/v1/user');

        if ($response->successful()) {
          $userData = $response->json();
          // Check if the authenticated user owns this document
          if ((string)$userData['id'] != (string)$document->user_id) {
            return response()->json(['message' => 'Unauthorized'], 401);
          }
          // Store the user data in the request for later use
          $request->merge(['supabase_user' => $userData]);
          return $next($request);
        }

        return response()->json(['message' => 'Invalid token'], 401);
      } catch (\Exception $e) {
        return response()->json(['message' => 'Authentication failed'], 401);
      }
    }

    // For guest documents, check if the current IP has access to this document
    $guestIdentifier = $request->ip();
    if (!GuestDocumentAccess::hasGuestAccessToDocument($guestIdentifier, $documentId, 'ip')) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Guest has access to this document, proceed
    return $next($request);
  }
}
