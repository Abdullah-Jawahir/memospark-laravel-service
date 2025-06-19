<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Document;
use App\Http\Middleware\GuestDocumentAccess;

class GuestDocumentStatus
{
  public function handle(Request $request, Closure $next)
  {
    $documentId = $request->route('id');
    $document = Document::find($documentId);
    if (!$document) {
      return response()->json(['message' => 'Document not found'], 404);
    }
    $guestIdentifier = $request->ip();
    if (!GuestDocumentAccess::hasGuestAccessToDocument($guestIdentifier, $documentId, 'ip')) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }
    return $next($request);
  }
}
