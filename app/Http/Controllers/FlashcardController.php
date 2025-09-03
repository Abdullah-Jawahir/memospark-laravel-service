<?php

namespace App\Http\Controllers;

use App\Services\FastApiService;
use App\Services\FileProcessCacheService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\FileProcessCache;
use Illuminate\Support\Facades\DB;

class FlashcardController extends Controller
{
  protected $fastApiService;
  protected $fileProcessCacheService;

  public function __construct(FastApiService $fastApiService, FileProcessCacheService $fileProcessCacheService)
  {
    $this->fastApiService = $fastApiService;
    $this->fileProcessCacheService = $fileProcessCacheService;
  }

  /**
   * Process uploaded file and generate flashcards
   *
   * @param Request $request
   * @return JsonResponse
   */
  public function processFile(Request $request): JsonResponse
  {
    $request->validate([
      'file' => 'required|file|mimes:pdf,docx,pptx|max:10240', // 10MB max
      'language' => 'required|string|in:en,si,ta',
      'card_types' => 'array',
      'card_types.*' => 'string',
      'difficulty' => 'string',
    ]);

    $file = $request->file('file');
    $language = $request->input('language', 'en');
    $cardTypes = $request->input('card_types', ['flashcard']);
    $difficulty = $request->input('difficulty', 'beginner');

    // Check cache first
    $cacheResult = $this->fileProcessCacheService->checkCacheEntry($file, $language, $cardTypes, $difficulty);

    if ($cacheResult['status'] === 'done') {
      return response()->json([
        'success' => true,
        'data' => $cacheResult['result']
      ]);
    }

    if ($cacheResult['status'] === 'processing') {
      return response()->json([
        'success' => false,
        'message' => 'Processing in progress. Please try again later.'
      ], 202);
    }

    // Note: 'failed' status is now handled by clearing cache in FileProcessCacheService
    // so we should not reach this condition anymore, but keeping it for safety  
    if ($cacheResult['status'] === 'failed') {
      return response()->json([
        'success' => false,
        'message' => 'Processing failed. Please try again.'
      ], 500);
    }

    // Not cached, create entry and process
    $fileHash = hash_file('sha256', $file->getRealPath());

    return DB::transaction(function () use ($fileHash, $language, $cardTypes, $difficulty, $file) {
      $cache = FileProcessCache::create([
        'file_hash' => $fileHash,
        'language' => $language,
        'difficulty' => $difficulty,
        'card_types' => $cardTypes,
        'card_types_hash' => hash('sha256', json_encode($cardTypes)), // Keep for backward compatibility
        'status' => 'processing',
      ]);

      try {
        $result = $this->fastApiService->processFile($file, $language, $cardTypes, $difficulty);
        $cache->update([
          'result' => $result,
          'status' => 'done',
        ]);
        return response()->json([
          'success' => true,
          'data' => $result['generated_cards'] ?? $result
        ]);
      } catch (\Exception $e) {
        $cache->update(['status' => 'failed']);
        return response()->json([
          'success' => false,
          'message' => $e->getMessage()
        ], 500);
      }
    });
  }

  /**
   * Check the processing status of a file upload
   *
   * @param Request $request
   * @return JsonResponse
   */
  public function checkStatus(Request $request): JsonResponse
  {
    $request->validate([
      'file_hash' => 'required|string|size:64',
      'language' => 'required|string|in:en,si,ta',
      'card_types' => 'array',
      'card_types.*' => 'string',
      'difficulty' => 'string',
    ]);

    $fileHash = $request->input('file_hash');
    $language = $request->input('language', 'en');
    $cardTypes = $request->input('card_types', ['flashcard']);
    $difficulty = $request->input('difficulty', 'beginner');

    $cache = FileProcessCache::where([
      'file_hash' => $fileHash,
      'language' => $language,
      'difficulty' => $difficulty,
    ])->first();

    if (!$cache) {
      return response()->json([
        'status' => 'not_found',
        'message' => 'No processing record found for this file and parameters.'
      ], 404);
    }

    if ($cache->status === 'done') {
      // Get study materials from database for requested card types
      $studyMaterials = $this->fileProcessCacheService->getStudyMaterialsForCardTypes($cache->document_id, $cardTypes);
      $result = $this->fileProcessCacheService->formatResultFromStudyMaterials($studyMaterials, $cardTypes);

      return response()->json([
        'status' => $cache->status,
        'result' => $result,
        'message' => null
      ]);
    }

    return response()->json([
      'status' => $cache->status,
      'result' => null,
      'message' => $cache->status === 'failed' ? 'Processing failed.' : null
    ]);
  }
}
