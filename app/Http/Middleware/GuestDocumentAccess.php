<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\GuestUpload;

class GuestDocumentAccess
{
  public function handle(Request $request, Closure $next)
  {
    // Check if this is a guest upload
    if ($request->has('is_guest') && filter_var($request->is_guest, FILTER_VALIDATE_BOOLEAN)) {
      // Get guest identifier (IP address)
      $guestIdentifier = $request->ip();

      // Check if this guest has already uploaded a document
      if (GuestUpload::hasGuestUploaded($guestIdentifier, 'ip')) {
        return response()->json([
          'error' => 'Guest users can only upload one document. Please sign up to upload more documents.',
          'code' => 'GUEST_LIMIT_EXCEEDED'
        ], 403);
      }

      // Add guest identifier to request for later use
      $request->merge(['guest_identifier' => $guestIdentifier]);
    }

    return $next($request);
  }

  public static function hasGuestAccessToDocument($guestIdentifier, $documentId, $type = 'ip')
  {
    return GuestUpload::where('guest_identifier', $guestIdentifier)
      ->where('identifier_type', $type)
      ->where('document_id', $documentId)
      ->exists();
  }
}
