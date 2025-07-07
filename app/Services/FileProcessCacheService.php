<?php

namespace App\Services;

use App\Models\FileProcessCache;
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
   * Check cache or process file if not cached (for controllers)
   * Returns: [status, result|null, message|null, file_hash, card_types_hash]
   */
  public function checkOrProcessFile(UploadedFile $file, string $language, array $cardTypes, string $difficulty = 'beginner')
  {
    sort($cardTypes);
    $cardTypesJson = json_encode($cardTypes);
    $cardTypesHash = hash('sha256', $cardTypesJson);
    $fileHash = hash_file('sha256', $file->getRealPath());

    return DB::transaction(function () use ($file, $language, $cardTypes, $difficulty, $fileHash, $cardTypesHash) {
      $cache = FileProcessCache::where([
        'file_hash' => $fileHash,
        'language' => $language,
        'difficulty' => $difficulty,
        'card_types_hash' => $cardTypesHash,
      ])->lockForUpdate()->first();

      if ($cache) {
        if ($cache->status === 'done') {
          return [
            'status' => 'done',
            'result' => $cache->result['generated_cards'] ?? $cache->result,
            'message' => null,
            'file_hash' => $fileHash,
            'card_types_hash' => $cardTypesHash,
          ];
        }
        if ($cache->status === 'processing') {
          return [
            'status' => 'processing',
            'result' => null,
            'message' => 'Processing in progress. Please try again later.',
            'file_hash' => $fileHash,
            'card_types_hash' => $cardTypesHash,
          ];
        }
        if ($cache->status === 'failed') {
          return [
            'status' => 'failed',
            'result' => null,
            'message' => 'Processing failed. Please try again.',
            'file_hash' => $fileHash,
            'card_types_hash' => $cardTypesHash,
          ];
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
        return [
          'status' => 'done',
          'result' => $result['generated_cards'] ?? $result,
          'message' => null,
          'file_hash' => $fileHash,
          'card_types_hash' => $cardTypesHash,
        ];
      } catch (\Exception $e) {
        $cache->update(['status' => 'failed']);
        return [
          'status' => 'failed',
          'result' => null,
          'message' => $e->getMessage(),
          'file_hash' => $fileHash,
          'card_types_hash' => $cardTypesHash,
        ];
      }
    });
  }

  /**
   * For jobs: process and cache file by hash/params (file already stored)
   * Returns: [status, result|null, message|null]
   */
  public function processAndCacheFile(string $filePath, string $originalFilename, string $language, array $cardTypes, string $difficulty = 'beginner')
  {
    sort($cardTypes);
    $cardTypesJson = json_encode($cardTypes);
    $cardTypesHash = hash('sha256', $cardTypesJson);
    $fileHash = hash_file('sha256', $filePath);

    return DB::transaction(function () use ($filePath, $originalFilename, $language, $cardTypes, $difficulty, $fileHash, $cardTypesHash) {
      $cache = FileProcessCache::where([
        'file_hash' => $fileHash,
        'language' => $language,
        'difficulty' => $difficulty,
        'card_types_hash' => $cardTypesHash,
      ])->lockForUpdate()->first();

      if ($cache && $cache->status === 'done') {
        return [
          'status' => 'done',
          'result' => $cache->result['generated_cards'] ?? $cache->result,
          'message' => null,
        ];
      }
      if ($cache && $cache->status === 'processing') {
        return [
          'status' => 'processing',
          'result' => null,
          'message' => 'Processing in progress. Please try again later.',
        ];
      }
      if ($cache && $cache->status === 'failed') {
        return [
          'status' => 'failed',
          'result' => null,
          'message' => 'Processing failed. Please try again.',
        ];
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
}
