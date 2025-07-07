<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\GuestUpload;
use App\Services\FastApiService;
use App\Services\FileProcessCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class DocumentController extends Controller
{
  protected $fastApiService;
  protected $fileProcessCacheService;

  public function __construct(FastApiService $fastApiService, FileProcessCacheService $fileProcessCacheService)
  {
    $this->fastApiService = $fastApiService;
    $this->fileProcessCacheService = $fileProcessCacheService;
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
    $cardTypes = $request->card_types ?? ['flashcard'];
    if (empty($cardTypes)) {
      $cardTypes = ['flashcard'];
    }
    $difficulty = $request->difficulty ?? 'beginner';
    $language = $request->language;

    // Check cache before proceeding
    $cacheResult = $this->fileProcessCacheService->checkOrProcessFile($file, $language, $cardTypes, $difficulty);
    if ($cacheResult['status'] === 'done') {
      return response()->json([
        'message' => 'File already processed',
        'status' => 'done',
        'result' => $cacheResult['result'],
        'file_hash' => $cacheResult['file_hash'],
        'card_types_hash' => $cacheResult['card_types_hash'],
      ]);
    }
    if ($cacheResult['status'] === 'processing') {
      return response()->json([
        'message' => $cacheResult['message'],
        'status' => 'processing',
        'file_hash' => $cacheResult['file_hash'],
        'card_types_hash' => $cacheResult['card_types_hash'],
      ], 202);
    }
    if ($cacheResult['status'] === 'failed') {
      return response()->json([
        'message' => $cacheResult['message'],
        'status' => 'failed',
        'file_hash' => $cacheResult['file_hash'],
        'card_types_hash' => $cacheResult['card_types_hash'],
      ], 500);
    }

    // If not cached, proceed with document creation and job dispatch
    $originalFilename = $file->getClientOriginalName();
    $extension = $file->getClientOriginalExtension();
    $filename = \Illuminate\Support\Str::uuid() . '.' . $extension;
    $path = $file->storeAs('documents', $filename, 'private');
    $isGuest = filter_var($request->is_guest, FILTER_VALIDATE_BOOLEAN);
    $userId = null;
    if (!$isGuest && $request->has('supabase_user')) {
      $userId = $request->supabase_user['id'];
    }
    $deck = null;
    if ($userId) {
      $deck = \App\Models\Deck::firstOrCreate([
        'user_id' => $userId,
        'name' => $request->deck_name
      ]);
    }
    $document = Document::create([
      'user_id' => $userId,
      'deck_id' => $deck ? $deck->id : null,
      'original_filename' => $originalFilename,
      'storage_path' => $path,
      'file_type' => $extension,
      'language' => $language,
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
    if ($isGuest && $request->has('guest_identifier')) {
      GuestUpload::createGuestUpload(
        $request->guest_identifier,
        $document->id,
        'ip'
      );
    }
    ProcessDocument::dispatch(
      $document->id,
      $path,
      $originalFilename,
      $language,
      $cardTypes,
      $difficulty
    )->delay(now()->addSeconds(20));
    return response()->json([
      'message' => 'File uploaded successfully',
      'document_id' => $document->id,
      'is_guest' => $isGuest,
      'file_hash' => $cacheResult['file_hash'],
      'card_types_hash' => $cacheResult['card_types_hash'],
      'status' => 'processing'
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
