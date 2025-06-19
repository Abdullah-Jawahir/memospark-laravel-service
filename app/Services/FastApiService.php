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
   * @return array
   * @throws \Exception
   */
  public function processFile(UploadedFile $file, string $language = 'en')
  {
    try {
      $response = Http::timeout(120) // 2 minutes timeout
        ->attach(
          'file',
          file_get_contents($file->getRealPath()),
          $file->getClientOriginalName(),
          ['Content-Type' => $file->getMimeType()]
        )->post("{$this->baseUrl}/process-file", [
          'language' => $language
        ]);

      if ($response->failed()) {
        Log::error('FastAPI request failed', [
          'status' => $response->status(),
          'body' => $response->body()
        ]);
        throw new \Exception('Failed to process file: ' . $response->body());
      }

      $json = $response->json();
      Log::info('FastAPI raw response', ['json' => $json]);
      return $json;
    } catch (\Exception $e) {
      Log::error('Error processing file with FastAPI', [
        'error' => $e->getMessage(),
        'file' => $file->getClientOriginalName()
      ]);
      throw $e;
    }
  }
}
