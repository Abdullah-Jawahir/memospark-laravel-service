<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FastApiService
{
  protected $baseUrl;

  public function __construct()
  {
    $this->baseUrl = config('services.fastapi.url', 'http://localhost:8001');
  }

  /**
   * Process a file and generate flashcards
   *
   * @param UploadedFile $file
   * @param string $language
   * @param array $cardTypes
   * @param string $difficulty
   * @return array
   * @throws \Exception
   */
  public function processFile(UploadedFile $file, string $language = 'en', array $cardTypes = ['flashcard'], string $difficulty = 'beginner')
  {
    try {
      // Log basic input details
      Log::channel('fastapi')->info('processFile called', [
        'filename' => $file->getClientOriginalName(),
        'language' => $language,
        'card_types' => $cardTypes,
        'difficulty' => $difficulty,
        'mime_type' => $file->getMimeType(),
        'size' => $file->getSize(),
      ]);

      // Prepare multipart form fields
      $formData = collect($cardTypes)->map(function ($type) {
        return ['name' => 'card_types', 'contents' => $type];
      })->prepend(
        ['name' => 'language', 'contents' => $language]
      )->push(
        ['name' => 'difficulty', 'contents' => $difficulty]
      )->all();

      Log::channel('fastapi')->info('Preparing to send request to FastAPI', [
        'url' => "{$this->baseUrl}/api/v1/process-file",
        'form_data' => $formData,
        'file_attached' => $file->getClientOriginalName()
      ]);

      // Send request to FastAPI
      $response = Http::timeout(500)
        ->asMultipart()
        ->attach(
          'file',
          file_get_contents($file->getRealPath()),
          $file->getClientOriginalName(),
          ['Content-Type' => $file->getMimeType()]
        )->post("{$this->baseUrl}/api/v1/process-file", $formData);

      Log::channel('fastapi')->info('FastAPI response received', [
        'status' => $response->status(),
        'body' => $response->body()
      ]);

      // Handle errors
      if ($response->failed()) {
        $msg = 'Failed to process file: HTTP ' . $response->status() . ' - ' . $response->body();
        Log::channel('fastapi')->error('FastAPI request failed', [
          'status' => $response->status(),
          'body' => $response->body(),
          'error' => $msg
        ]);
        throw new \Exception($msg);
      }

      // Decode and return response
      $json = $response->json();
      Log::channel('fastapi')->info('FastAPI JSON decoded', ['json' => $json]);

      return $json;
    } catch (\Exception $e) {
      $errorMsg = $e->getMessage() ?: 'Could not connect to FastAPI server';
      Log::channel('fastapi')->error('Exception thrown in FastApiService', [
        'error' => $errorMsg,
        'trace' => $e->getTraceAsString(),
      ]);
      throw new \Exception('FastApiService exception: ' . $errorMsg, 0, $e);
    }
  }

  /**
   * Generate flashcards from a search topic
   *
   * @param string $topic
   * @param string|null $description
   * @param string $difficulty
   * @param int $count
   * @return array
   * @throws \Exception
   */
  public function generateSearchFlashcards(string $topic, ?string $description = null, string $difficulty = 'beginner', int $count = 10)
  {
    try {
      // Log basic input details
      Log::channel('fastapi')->info('generateSearchFlashcards called', [
        'topic' => $topic,
        'description' => $description,
        'difficulty' => $difficulty,
        'count' => $count,
      ]);

      // Prepare request data
      $requestData = [
        'topic' => $topic,
        'difficulty' => $difficulty,
        'count' => $count,
      ];

      if ($description) {
        $requestData['description'] = $description;
      }

      Log::channel('fastapi')->info('Preparing to send request to FastAPI', [
        'url' => "{$this->baseUrl}/api/v1/search-flashcards",
        'request_data' => $requestData
      ]);

      // Send request to FastAPI
      $response = Http::timeout(300) // 5 minutes timeout for flashcard generation
        ->asJson()
        ->post("{$this->baseUrl}/api/v1/search-flashcards", $requestData);

      Log::channel('fastapi')->info('FastAPI response received', [
        'status' => $response->status(),
        'body' => $response->body()
      ]);

      // Handle errors
      if ($response->failed()) {
        $msg = 'Failed to generate search flashcards: HTTP ' . $response->status() . ' - ' . $response->body();
        Log::channel('fastapi')->error('FastAPI request failed', [
          'status' => $response->status(),
          'body' => $response->body(),
          'error' => $msg
        ]);
        throw new \Exception($msg);
      }

      // Decode and return response
      $json = $response->json();
      Log::channel('fastapi')->info('FastAPI JSON decoded', ['json' => $json]);

      return $json;
    } catch (\Exception $e) {
      $errorMsg = $e->getMessage() ?: 'Could not connect to FastAPI server';
      Log::channel('fastapi')->error('Exception thrown in generateSearchFlashcards', [
        'error' => $errorMsg,
        'trace' => $e->getTraceAsString(),
      ]);
      throw new \Exception('FastApiService exception: ' . $errorMsg, 0, $e);
    }
  }

  /**
   * Get suggested topics from FastAPI
   *
   * @return array
   * @throws \Exception
   */
  public function getSuggestedTopics()
  {
    try {
      Log::channel('fastapi')->info('getSuggestedTopics called');

      $response = Http::timeout(30)
        ->get("{$this->baseUrl}/api/v1/search-flashcards/topics");

      Log::channel('fastapi')->info('FastAPI suggested topics response received', [
        'status' => $response->status(),
        'body' => $response->body()
      ]);

      if ($response->failed()) {
        $msg = 'Failed to get suggested topics: HTTP ' . $response->status() . ' - ' . $response->body();
        Log::channel('fastapi')->error('FastAPI suggested topics request failed', [
          'status' => $response->status(),
          'body' => $response->body(),
          'error' => $msg
        ]);
        throw new \Exception($msg);
      }

      $json = $response->json();
      Log::channel('fastapi')->info('FastAPI suggested topics JSON decoded', ['json' => $json]);

      return $json;
    } catch (\Exception $e) {
      $errorMsg = $e->getMessage() ?: 'Could not connect to FastAPI server';
      Log::channel('fastapi')->error('Exception thrown in getSuggestedTopics', [
        'error' => $errorMsg,
        'trace' => $e->getTraceAsString(),
      ]);
      throw new \Exception('FastApiService exception: ' . $errorMsg, 0, $e);
    }
  }

  /**
   * Check FastAPI search flashcards service health
   *
   * @return array
   * @throws \Exception
   */
  public function checkSearchFlashcardsHealth()
  {
    try {
      Log::channel('fastapi')->info('checkSearchFlashcardsHealth called');

      $response = Http::timeout(30)
        ->get("{$this->baseUrl}/api/v1/search-flashcards/health");

      Log::channel('fastapi')->info('FastAPI health check response received', [
        'status' => $response->status(),
        'body' => $response->body()
      ]);

      if ($response->failed()) {
        $msg = 'Failed to check FastAPI health: HTTP ' . $response->status() . ' - ' . $response->body();
        Log::channel('fastapi')->error('FastAPI health check request failed', [
          'status' => $response->status(),
          'body' => $response->body(),
          'error' => $msg
        ]);
        throw new \Exception($msg);
      }

      $json = $response->json();
      Log::channel('fastapi')->info('FastAPI health check JSON decoded', ['json' => $json]);

      return $json;
    } catch (\Exception $e) {
      $errorMsg = $e->getMessage() ?: 'Could not connect to FastAPI server';
      Log::channel('fastapi')->error('Exception thrown in checkSearchFlashcardsHealth', [
        'error' => $errorMsg,
        'trace' => $e->getTraceAsString(),
      ]);
      throw new \Exception('FastApiService exception: ' . $errorMsg, 0, $e);
    }
  }
}
