<?php

namespace App\Http\Controllers;

use App\Services\FastApiService;
use App\Services\FileProcessCacheService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\FileProcessCache;
use App\Models\StudyMaterial;
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

  /**
   * Update a single flashcard within a StudyMaterial
   *
   * @param Request $request
   * @param int $materialId
   * @param int $cardIndex
   * @return JsonResponse
   */
  public function updateFlashcard(Request $request, $materialId, $cardIndex): JsonResponse
  {
    $request->validate([
      'question' => 'required|string|max:1000',
      'answer' => 'required|string|max:2000',
      'difficulty' => 'sometimes|string|in:beginner,intermediate,advanced',
      'type' => 'sometimes|string|in:flashcard,multiple_choice,true_false'
    ]);

    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'User not authenticated'], 401);
    }

    try {
      // Find the StudyMaterial and ensure user owns it via deck
      $studyMaterial = StudyMaterial::whereHas('document', function ($query) use ($supabaseUser) {
        $query->whereHas('deck', function ($deckQuery) use ($supabaseUser) {
          $deckQuery->where('user_id', $supabaseUser['id']);
        });
      })->findOrFail($materialId);

      $content = $studyMaterial->content;

      // Handle both single card format and array format
      if (isset($content['question']) && $cardIndex == 0) {
        // Single card format - update directly
        $content['question'] = $request->input('question');
        $content['answer'] = $request->input('answer');

        if ($request->has('difficulty')) {
          $content['difficulty'] = $request->input('difficulty');
        }
        if ($request->has('type')) {
          $content['type'] = $request->input('type');
        }

        $cardData = $content;
      } else {
        // Array format - update by index
        if (!isset($content[$cardIndex])) {
          return response()->json(['error' => 'Card not found'], 404);
        }

        $content[$cardIndex]['question'] = $request->input('question');
        $content[$cardIndex]['answer'] = $request->input('answer');

        if ($request->has('difficulty')) {
          $content[$cardIndex]['difficulty'] = $request->input('difficulty');
        }
        if ($request->has('type')) {
          $content[$cardIndex]['type'] = $request->input('type');
        }

        $cardData = $content[$cardIndex];
      }

      $studyMaterial->content = $content;
      $studyMaterial->save();

      return response()->json([
        'success' => true,
        'data' => $cardData,
        'message' => 'Flashcard updated successfully'
      ]);
    } catch (\Exception $e) {
      return response()->json([
        'success' => false,
        'error' => 'Failed to update flashcard: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Delete a single flashcard from a StudyMaterial
   *
   * @param Request $request
   * @param int $materialId
   * @param int $cardIndex
   * @return JsonResponse
   */
  public function deleteFlashcard(Request $request, $materialId, $cardIndex): JsonResponse
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'User not authenticated'], 401);
    }

    try {
      // Find the StudyMaterial and ensure user owns it via deck
      $studyMaterial = StudyMaterial::whereHas('document', function ($query) use ($supabaseUser) {
        $query->whereHas('deck', function ($deckQuery) use ($supabaseUser) {
          $deckQuery->where('user_id', $supabaseUser['id']);
        });
      })->findOrFail($materialId);

      $content = $studyMaterial->content;

      // Handle both single card format and array format
      if (isset($content['question']) && $cardIndex == 0) {
        // Single card format - delete the entire StudyMaterial
        $studyMaterial->delete();

        return response()->json([
          'success' => true,
          'message' => 'Flashcard deleted successfully',
          'remaining_cards' => 0
        ]);
      } else {
        // Array format - remove by index
        if (!isset($content[$cardIndex])) {
          return response()->json(['error' => 'Card not found'], 404);
        }

        // Remove the card
        array_splice($content, $cardIndex, 1);

        $studyMaterial->content = $content;
        $studyMaterial->save();

        return response()->json([
          'success' => true,
          'message' => 'Flashcard deleted successfully',
          'remaining_cards' => count($content)
        ]);
      }
    } catch (\Exception $e) {
      return response()->json([
        'success' => false,
        'error' => 'Failed to delete flashcard: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Add a new flashcard to a StudyMaterial
   *
   * @param Request $request
   * @param int $materialId
   * @return JsonResponse
   */
  public function addFlashcard(Request $request, $materialId): JsonResponse
  {
    // Get the card type to determine validation rules
    $cardType = $request->input('type', 'flashcard');

    // Define validation rules based on card type
    $validationRules = [
      'answer' => 'required|string|max:2000',
      'difficulty' => 'sometimes|string|in:beginner,intermediate,advanced',
      'type' => 'sometimes|string|in:flashcard,quiz,exercise,multiple_choice,true_false,fill_blank'
    ];

    // Add specific field validation based on card type
    if ($cardType === 'exercise') {
      $validationRules['instruction'] = 'required|string|max:1000';
    } else {
      $validationRules['question'] = 'required|string|max:1000';
    }

    // Add options validation for quiz types
    if ($cardType === 'quiz' || $cardType === 'multiple_choice') {
      $validationRules['options'] = 'sometimes|array|min:2|max:6';
      $validationRules['options.*'] = 'string|max:500';
    }

    $request->validate($validationRules);

    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'User not authenticated'], 401);
    }

    try {
      // Find a reference StudyMaterial to get the document_id and ensure user owns it
      $referenceMaterial = StudyMaterial::whereHas('document', function ($query) use ($supabaseUser) {
        $query->whereHas('deck', function ($deckQuery) use ($supabaseUser) {
          $deckQuery->where('user_id', $supabaseUser['id']);
        });
      })->findOrFail($materialId);

      // Create new card content based on type
      $newCardContent = [
        'type' => $cardType === 'flashcard' ? 'Q&A' : $cardType,
        'answer' => $request->input('answer'),
        'difficulty' => $request->input('difficulty', 'intermediate')
      ];

      // Add type-specific fields
      if ($cardType === 'exercise') {
        $newCardContent['instruction'] = $request->input('instruction');
      } else {
        $newCardContent['question'] = $request->input('question');
      }

      // Add options for quiz types
      if ($cardType === 'quiz' || $cardType === 'multiple_choice') {
        $newCardContent['options'] = $request->input('options', []);
      }

      // Create new StudyMaterial record
      $newStudyMaterial = StudyMaterial::create([
        'document_id' => $referenceMaterial->document_id,
        'type' => $request->input('type', 'flashcard'),
        'content' => $newCardContent
      ]);

      return response()->json([
        'success' => true,
        'data' => [
          'id' => $newStudyMaterial->id,
          'question' => $newCardContent['question'] ?? null,
          'instruction' => $newCardContent['instruction'] ?? null,
          'answer' => $newCardContent['answer'],
          'difficulty' => $newCardContent['difficulty'],
          'options' => $newCardContent['options'] ?? null,
          'type' => $newStudyMaterial->type
        ],
        'message' => ucfirst($cardType) . ' added successfully'
      ]);
    } catch (\Exception $e) {
      return response()->json([
        'success' => false,
        'error' => 'Failed to add flashcard: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Get all flashcards for a specific StudyMaterial
   *
   * @param Request $request
   * @param int $materialId
   * @return JsonResponse
   */
  public function getFlashcards(Request $request, $materialId): JsonResponse
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'User not authenticated'], 401);
    }

    try {
      // Find the StudyMaterial and ensure user owns it via deck
      $studyMaterial = StudyMaterial::whereHas('document', function ($query) use ($supabaseUser) {
        $query->whereHas('deck', function ($deckQuery) use ($supabaseUser) {
          $deckQuery->where('user_id', $supabaseUser['id']);
        });
      })->findOrFail($materialId);

      return response()->json([
        'success' => true,
        'data' => $studyMaterial->content,
        'material_type' => $studyMaterial->type,
        'language' => $studyMaterial->language
      ]);
    } catch (\Exception $e) {
      return response()->json([
        'success' => false,
        'error' => 'Failed to get flashcards: ' . $e->getMessage()
      ], 500);
    }
  }
}
