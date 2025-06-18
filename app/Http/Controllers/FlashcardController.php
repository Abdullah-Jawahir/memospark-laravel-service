<?php

namespace App\Http\Controllers;

use App\Services\FastApiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
      'language' => 'required|string|in:en,si,ta'
    ]);

    try {
      $file = $request->file('file');
      $language = $request->input('language');

      $result = $this->fastApiService->processFile($file, $language);

      return response()->json([
        'success' => true,
        'data' => $result['generated_cards']
      ]);
    } catch (\Exception $e) {
      return response()->json([
        'success' => false,
        'message' => $e->getMessage()
      ], 500);
    }
  }
}
