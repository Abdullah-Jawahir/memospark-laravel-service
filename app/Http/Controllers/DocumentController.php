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
    \Log::info('DocumentController::upload called', [
      'method' => $request->method(),
      'path' => $request->path(),
      'is_guest' => $request->input('is_guest'),
      'has_file' => $request->hasFile('file'),
      'all_input' => $request->all()
    ]);

    try {
      $request->validate([
        'file' => 'required|file|mimes:pdf,docx,pptx,jpg,jpeg,png|max:10240', // 10MB max
        'language' => 'required|in:en,si,ta',
        'is_guest' => 'required|in:0,1,true,false',
        'deck_name' => 'required|string|max:255',
        'card_types' => 'nullable|array',
        'card_types.*' => 'in:flashcard,exercise,quiz',
        'difficulty' => 'nullable|in:beginner,intermediate,advanced'
      ]);
    } catch (\Exception $e) {
      \Log::error('Validation failed in upload', [
        'error' => $e->getMessage(),
        'input' => $request->all()
      ]);
      throw $e;
    }

    $file = $request->file('file');
    $cardTypes = $request->card_types ?? ['flashcard'];
    if (empty($cardTypes)) {
      $cardTypes = ['flashcard'];
    }
    $difficulty = $request->difficulty ?? 'beginner';
    $language = $request->language;

    // Check cache before proceeding
    $cacheResult = $this->fileProcessCacheService->checkCacheEntry($file, $language, $cardTypes, $difficulty);
    if ($cacheResult['status'] === 'done') {
      $document = null;
      if ($cacheResult['document_id']) {
        $document = \App\Models\Document::find($cacheResult['document_id']);
      }
      if ($document) {
        // Check for missing types
        $existingMaterials = \App\Models\StudyMaterial::where('document_id', $document->id)->get();
        $existingTypes = $existingMaterials->pluck('type')->unique()->toArray();
        $missingTypes = array_diff($cardTypes, $existingTypes);
        if (count($missingTypes) > 0) {
          // Dispatch background job for missing types
          \App\Jobs\GenerateMissingCardTypes::dispatch(
            $document->id,
            array_values($missingTypes),
            $language,
            $difficulty,
            Storage::disk('private')->path($document->storage_path),
            $document->original_filename
          );
          return response()->json([
            'message' => 'File already processed, missing types are being generated',
            'document_id' => $document->id,
            'status' => 'processing',
            'from_cache' => true
          ], 202);
        }
        $result = $this->fileProcessCacheService->formatResultFromStudyMaterials($existingMaterials->toArray(), $cardTypes);
        return response()->json([
          'message' => 'File already processed (all requested types present)',
          'document_id' => $document->id,
          'status' => 'completed',
          'data' => $result,
          'from_cache' => true
        ]);
      } else {
        return response()->json([
          'message' => 'File already processed',
          'document_id' => $cacheResult['document_id'],
          'status' => 'completed',
          'data' => $cacheResult['result'],
          'from_cache' => true
        ]);
      }
    }
    if ($cacheResult['status'] === 'processing') {
      return response()->json([
        'message' => 'Processing in progress. Please try again later.',
        'status' => 'processing',
        'from_cache' => true
      ], 202);
    }
    // Note: 'failed' status is now handled by clearing cache in FileProcessCacheService
    // so we should not reach this condition anymore, but keeping it for safety
    if ($cacheResult['status'] === 'failed') {
      return response()->json([
        'message' => 'Processing failed. Please try again.',
        'status' => 'failed',
        'from_cache' => true
      ], 500);
    }

    $originalFilename = $file->getClientOriginalName();
    $extension = $file->getClientOriginalExtension();
    $filename = Str::uuid() . '.' . $extension;
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
    // Dispatch the job to handle all processing, caching, and study material creation
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
      'status' => 'processing',
      'from_cache' => false
    ]);
  }

  public function status($id, Request $request)
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

      // Get card_types from request or document metadata
      $cardTypes = $request->input('card_types', $document->metadata['card_types'] ?? ['flashcard']);
      if (!is_array($cardTypes)) {
        $cardTypes = [$cardTypes];
      }
      $difficulty = $document->metadata['difficulty'] ?? 'beginner';
      $language = $document->language;
      $file = null;
      $file_hash = null;
      if (isset($document->metadata['file_hash'])) {
        $file_hash = $document->metadata['file_hash'];
      } else {
        // Try to compute file hash if file exists
        $storagePath = Storage::disk('private')->path($document->storage_path);
        if (file_exists($storagePath)) {
          $file_hash = hash_file('sha256', $storagePath);
        }
      }
      if ($file_hash) {
        $cache = \App\Models\FileProcessCache::where([
          'file_hash' => $file_hash,
          'language' => $language,
          'difficulty' => $difficulty,
        ])->first();
        if ($cache && $cache->status === 'done') {
          // Check for missing types
          $existingMaterials = \App\Models\StudyMaterial::where('document_id', $cache->document_id)->get();
          $existingTypes = $existingMaterials->pluck('type')->unique()->toArray();
          $missingTypes = array_diff($cardTypes, $existingTypes);
          if (count($missingTypes) > 0) {
            // Dispatch background job for missing types
            \App\Jobs\GenerateMissingCardTypes::dispatch(
              $cache->document_id,
              array_values($missingTypes),
              $language,
              $difficulty,
              Storage::disk('private')->path($document->storage_path),
              $document->original_filename
            );
            return response()->json([
              'status' => 'processing',
              'metadata' => $document->metadata,
              'cache_status' => $cache->status,
              'from_cache' => true,
              'message' => 'Missing types are being generated',
              'processing' => true
            ], 202);
          }
          $result = $this->fileProcessCacheService->formatResultFromStudyMaterials($existingMaterials->toArray(), $cardTypes);
          return response()->json([
            'status' => $document->status,
            'metadata' => $document->metadata,
            'cache_status' => $cache->status,
            'data' => $result,
            'from_cache' => true
          ]);
        }
        return response()->json([
          'status' => $document->status,
          'metadata' => $document->metadata,
          'cache_status' => $cache ? $cache->status : null,
          'from_cache' => false
        ]);
      }
      return response()->json([
        'status' => $document->status,
        'metadata' => $document->metadata,
        'cache_status' => null,
        'from_cache' => false
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
