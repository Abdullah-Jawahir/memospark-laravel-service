<?php

namespace App\Services;

use App\Models\FileProcessCache;
use App\Models\StudyMaterial;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class FileProcessCacheService
{
  protected $fastApiService;

  public function __construct(FastApiService $fastApiService)
  {
    $this->fastApiService = $fastApiService;
  }

  /**
   * Check cache entry (for controllers)
   * Returns: [status, result|null, message|null, file_hash, document_id]
   */
  public function checkCacheEntry(UploadedFile $file, string $language, array $cardTypes, string $difficulty = 'beginner')
  {
    $fileHash = hash_file('sha256', $file->getRealPath());

    return DB::transaction(function () use ($file, $language, $cardTypes, $difficulty, $fileHash) {
      $cache = FileProcessCache::where([
        'file_hash' => $fileHash,
        'language' => $language,
        'difficulty' => $difficulty,
      ])->lockForUpdate()->first();

      if ($cache) {
        if ($cache->status === 'done') {
          // Get study materials from database for requested card types
          $studyMaterials = $this->getStudyMaterialsForCardTypes($cache->document_id, $cardTypes);

          return [
            'status' => 'done',
            'result' => $this->formatResultFromStudyMaterials($studyMaterials, $cardTypes),
            'message' => null,
            'file_hash' => $fileHash,
            'document_id' => $cache->document_id,
          ];
        }
        if ($cache->status === 'processing') {
          return [
            'status' => 'processing',
            'result' => null,
            'message' => 'Processing in progress. Please try again later.',
            'file_hash' => $fileHash,
            'document_id' => $cache->document_id,
          ];
        }
        if ($cache->status === 'failed') {
          return [
            'status' => 'failed',
            'result' => null,
            'message' => 'Processing failed. Please try again.',
            'file_hash' => $fileHash,
            'document_id' => $cache->document_id,
          ];
        }
      }

      // Not cached, do not create entry, just return not_cached status
      return [
        'status' => 'not_cached',
        'result' => null,
        'message' => 'No cache entry found.',
        'file_hash' => $fileHash,
        'document_id' => null,
      ];
    });
  }

  /**
   * For jobs: process and cache file by hash/params (file already stored)
   * Returns: [status, result|null, message|null]
   */
  public function processAndCacheFile(string $filePath, string $originalFilename, string $language, array $cardTypes, string $difficulty = 'beginner', int $documentId = null)
  {
    $fileHash = hash_file('sha256', $filePath);

    return DB::transaction(function () use ($filePath, $originalFilename, $language, $cardTypes, $difficulty, $fileHash, $documentId) {
      $cache = FileProcessCache::where([
        'file_hash' => $fileHash,
        'language' => $language,
        'difficulty' => $difficulty,
      ])->lockForUpdate()->first();

      if (!$cache) {
        // Create cache entry if it doesn't exist
        $cache = FileProcessCache::create([
          'file_hash' => $fileHash,
          'language' => $language,
          'difficulty' => $difficulty,
          'card_types' => $cardTypes, // Store the card types that were requested
          'card_types_hash' => hash('sha256', json_encode($cardTypes)), // Keep for backward compatibility
          'status' => 'processing',
          'document_id' => $documentId,
        ]);
      } else if ($cache->status === 'done') {
        // File already processed, get study materials for requested card types
        $studyMaterials = $this->getStudyMaterialsForCardTypes($cache->document_id, $cardTypes);

        return [
          'status' => 'done',
          'result' => $this->formatResultFromStudyMaterials($studyMaterials, $cardTypes),
          'message' => null,
        ];
      } else if ($cache->status === 'failed') {
        // Optionally allow re-processing, or return failed
        return [
          'status' => 'failed',
          'result' => null,
          'message' => 'Processing failed. Please try again.',
        ];
      }

      try {
        // Create UploadedFile from path
        $uploadedFile = new UploadedFile(
          $filePath,
          $originalFilename,
          mime_content_type($filePath),
          null,
          true
        );
        $result = $this->fastApiService->processFile($uploadedFile, $language, $cardTypes, $difficulty);
        $cache->update([
          'result' => $result,
          'status' => 'done',
          'document_id' => $documentId,
        ]);
        return [
          'status' => 'done',
          'result' => $result['generated_cards'] ?? $result,
          'message' => null,
        ];
      } catch (\Exception $e) {
        $cache->update(['status' => 'failed']);
        return [
          'status' => 'failed',
          'result' => null,
          'message' => $e->getMessage(),
        ];
      }
    });
  }

  /**
   * Ensure all requested card types are present for a document. If any are missing, generate them.
   * Returns: [status, result|null, message|null]
   */
  public function ensureCardTypesForDocument(
    $documentId,
    $filePath,
    $originalFilename,
    $language,
    $requestedCardTypes,
    $difficulty
  ) {
    // Get all study materials for this document
    $existingMaterials = StudyMaterial::where('document_id', $documentId)->get();
    $existingTypes = $existingMaterials->pluck('type')->unique()->toArray();
    $missingTypes = array_diff($requestedCardTypes, $existingTypes);

    $result = [];
    foreach ($requestedCardTypes as $type) {
      $result[$type] = $existingMaterials->where('type', $type)->pluck('content')->toArray();
    }

    if (count($missingTypes) > 0) {
      // Call FastAPI for missing types
      $uploadedFile = new \Illuminate\Http\UploadedFile(
        $filePath,
        $originalFilename,
        mime_content_type($filePath),
        null,
        true
      );
      $fastApiResult = $this->fastApiService->processFile($uploadedFile, $language, array_values($missingTypes), $difficulty);
      $generated = $fastApiResult['generated_content'] ?? $fastApiResult['generated_cards'] ?? $fastApiResult;
      // Save new study materials
      foreach ($missingTypes as $type) {
        if (!empty($generated[$type])) {
          foreach ($generated[$type] as $card) {
            StudyMaterial::create([
              'document_id' => $documentId,
              'type' => $type,
              'content' => $card,
              'language' => $language,
            ]);
            $result[$type][] = $card;
          }
        }
      }
      // Optionally, update the cache result (merge new types)
      $cache = FileProcessCache::where('document_id', $documentId)->first();
      if ($cache) {
        $cacheResult = $cache->result ?? [];
        $cacheResult['generated_content'] = array_merge($cacheResult['generated_content'] ?? [], $generated);
        $cacheResult['generated_cards'] = array_merge($cacheResult['generated_cards'] ?? [], $generated);
        $cache->update(['result' => $cacheResult]);
      }
    }

    return [
      'status' => 'done',
      'result' => [
        'generated_cards' => $result,
        'generated_content' => $result,
      ],
      'message' => null,
    ];
  }

  /**
   * Get study materials from database for specific card types
   */
  public function getStudyMaterialsForCardTypes(int $documentId, array $cardTypes): array
  {
    $studyMaterials = StudyMaterial::where('document_id', $documentId)
      ->whereIn('type', $cardTypes)
      ->get();

    return $studyMaterials->toArray();
  }

  /**
   * Format study materials into the expected result format
   */
  public function formatResultFromStudyMaterials(array $studyMaterials, array $cardTypes): array
  {
    $result = [];

    foreach ($cardTypes as $cardType) {
      $result[$cardType] = [];
    }

    foreach ($studyMaterials as $material) {
      $type = $material['type'];
      if (in_array($type, $cardTypes)) {
        $result[$type][] = $material['content'];
      }
    }

    return [
      'generated_cards' => $result,
      'generated_content' => $result,
    ];
  }
}
