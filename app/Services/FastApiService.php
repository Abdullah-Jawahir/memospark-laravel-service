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
}
