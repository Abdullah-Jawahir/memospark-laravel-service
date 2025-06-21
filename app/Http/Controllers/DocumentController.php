<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\GuestUpload;
use App\Services\FastApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class DocumentController extends Controller
{
  protected $fastApiService;

  public function __construct(FastApiService $fastApiService)
  {
    $this->fastApiService = $fastApiService;
  }

  public function upload(Request $request)
  {
    $request->validate([
      'file' => 'required|file|mimes:pdf,docx,pptx,jpg,jpeg,png|max:10240', // 10MB max
      'language' => 'required|in:en,si,ta',
      'is_guest' => 'required|in:0,1,true,false',
      'deck_name' => 'required|string|max:255',
      'card_types' => 'nullable|array',
      'card_types.*' => 'in:flashcard,exercise,quiz',
      'difficulty' => 'nullable|in:beginner,intermediate,advanced'
    ]);

    $file = $request->file('file');
    $originalFilename = $file->getClientOriginalName();
    $extension = $file->getClientOriginalExtension();

    // Generate a unique filename
    $filename = Str::uuid() . '.' . $extension;

    // Store the file
    $path = $file->storeAs('documents', $filename, 'private');

    // Convert is_guest to boolean
    $isGuest = filter_var($request->is_guest, FILTER_VALIDATE_BOOLEAN);

    // Get user ID from Supabase if authenticated
    $userId = null;
    if (!$isGuest && $request->has('supabase_user')) {
      $userId = $request->supabase_user['id'];
    }

    // Create or find deck for the user
    $deck = null;
    if ($userId) {
      $deck = \App\Models\Deck::firstOrCreate([
        'user_id' => $userId,
        'name' => $request->deck_name
      ]);
    }

    // Default card types if not provided
    $cardTypes = $request->card_types ?? ['flashcard'];
    if (empty($cardTypes)) {
      $cardTypes = ['flashcard'];
    }

    $difficulty = $request->difficulty ?? 'beginner';

    // Create document record
    $document = Document::create([
      'user_id' => $userId,
      'deck_id' => $deck ? $deck->id : null,
      'original_filename' => $originalFilename,
      'storage_path' => $path,
      'file_type' => $extension,
      'language' => $request->language,
      'status' => 'processing',
      'metadata' => [
        'size' => $file->getSize(),
        'mime_type' => $file->getMimeType(),
        'is_guest' => $isGuest,
        'deck_name' => $request->deck_name,
        'card_types' => $cardTypes,
        'difficulty' => $difficulty
      ]
    ]);

    // If this is a guest upload, track it
    if ($isGuest && $request->has('guest_identifier')) {
      GuestUpload::createGuestUpload(
        $request->guest_identifier,
        $document->id,
        'ip'
      );
    }

    // Process the document asynchronously, pass card_types and difficulty
    ProcessDocument::dispatch(
      $document->id,
      $path,
      $originalFilename,
      $request->language,
      $cardTypes,
      $difficulty
    )->delay(now()->addSeconds(20));

    return response()->json([
      'message' => 'File uploaded successfully',
      'document_id' => $document->id,
      'is_guest' => $isGuest
    ]);
  }

  public function status($id)
  {
    Log::channel('fastapi')->info('Document status check called', [
      'document_id' => $id
    ]);
    try {
      $document = Document::findOrFail($id);
      Log::channel('fastapi')->info('Document status found', [
        'document_id' => $id,
        'status' => $document->status,
        'metadata' => $document->metadata
      ]);
      return response()->json([
        'status' => $document->status,
        'metadata' => $document->metadata
      ]);
    } catch (\Exception $e) {
      Log::channel('fastapi')->error('Error in document status check', [
        'document_id' => $id,
        'error' => $e->getMessage()
      ]);
      throw $e;
    }
  }
}
