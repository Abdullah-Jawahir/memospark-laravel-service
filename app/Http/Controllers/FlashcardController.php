<?php

namespace App\Http\Controllers;

use App\Services\FastApiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\FileProcessCache;
use Illuminate\Support\Facades\DB;

class FlashcardController extends Controller
{
  protected $fastApiService;

  public function __construct(FastApiService $fastApiService)
  {
    $this->fastApiService = $fastApiService;
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

    // Normalize card types for consistent hashing
    sort($cardTypes);
    $cardTypesJson = json_encode($cardTypes);
    $cardTypesHash = hash('sha256', $cardTypesJson);
    $fileHash = hash_file('sha256', $file->getRealPath());

    // Use DB transaction to avoid race conditions
    return DB::transaction(function () use ($fileHash, $language, $cardTypes, $cardTypesJson, $cardTypesHash, $difficulty, $file, $request) {
      $cache = FileProcessCache::where([
        'file_hash' => $fileHash,
        'language' => $language,
        'difficulty' => $difficulty,
        'card_types_hash' => $cardTypesHash,
      ])->lockForUpdate()->first();

      if ($cache) {
        if ($cache->status === 'done') {
          return response()->json([
            'success' => true,
            'data' => $cache->result['generated_cards'] ?? $cache->result
          ]);
        }
        if ($cache->status === 'processing') {
          return response()->json([
            'success' => false,
            'message' => 'Processing in progress. Please try again later.'
          ], 202);
        }
      }

      // Not cached, create entry and process
      $cache = FileProcessCache::create([
        'file_hash' => $fileHash,
        'language' => $language,
        'difficulty' => $difficulty,
        'card_types' => $cardTypes,
        'card_types_hash' => $cardTypesHash,
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
    sort($cardTypes);
    $cardTypesJson = json_encode($cardTypes);
    $cardTypesHash = hash('sha256', $cardTypesJson);

    $cache = FileProcessCache::where([
      'file_hash' => $fileHash,
      'language' => $language,
      'difficulty' => $difficulty,
      'card_types_hash' => $cardTypesHash,
    ])->first();

    if (!$cache) {
      return response()->json([
        'status' => 'not_found',
        'message' => 'No processing record found for this file and parameters.'
      ], 404);
    }

    return response()->json([
      'status' => $cache->status,
      'result' => $cache->status === 'done' ? ($cache->result['generated_cards'] ?? $cache->result) : null,
      'message' => $cache->status === 'failed' ? 'Processing failed.' : null
    ]);
  }
}
