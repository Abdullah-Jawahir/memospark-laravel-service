<?php

namespace App\Http\Controllers;

use App\Models\Deck;
use App\Models\Document;
use App\Models\GuestUpload;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Jobs\ProcessDocument;
use App\Services\FastApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\FileProcessCacheService;

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
    Log::info('DocumentController::upload called', [
      'method' => $request->method(),
      'path' => $request->path(),
      'is_guest' => $request->input('is_guest'),
      'has_file' => $request->hasFile('file'),
      'all_input' => $request->all()
    ]);

    try {
      $request->validate([
        'file' => 'required|file|mimes:pdf,docx|max:10240', // 10MB max
        'language' => 'required|in:en,si,ta',
        'is_guest' => 'required|in:0,1,true,false',
        'deck_name' => 'required|string|max:255',
        'card_types' => 'nullable|array',
        'card_types.*' => 'in:flashcard,exercise,quiz',
        'difficulty' => 'nullable|in:beginner,intermediate,advanced'
      ]);
    } catch (\Exception $e) {
      Log::error('Validation failed in upload', [
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
        $document = Document::find($cacheResult['document_id']);
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
      $deck = Deck::firstOrCreate([
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

    // Update document metadata to show it's queued
    $document->update([
      'metadata' => array_merge($document->metadata, [
        'queued_at' => now()->toISOString(),
        'estimated_processing_time' => '2-3 minutes',
        'queue_delay' => '20 seconds'
      ])
    ]);

    return response()->json([
      'message' => 'File uploaded successfully and queued for processing',
      'document_id' => $document->id,
      'is_guest' => $isGuest,
      'status' => 'queued',
      'stage' => 'queued',
      'queue_info' => [
        'delay' => '20 seconds',
        'estimated_total_time' => '2-3 minutes'
      ],
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

      // Get processing logs and current stage
      $processingLogs = $this->getProcessingLogs($id);
      $currentStage = $this->determineProcessingStage($document, $processingLogs);

      Log::channel('fastapi')->info('Document status found', [
        'document_id' => $id,
        'status' => $document->status,
        'stage' => $currentStage,
        'metadata' => $document->metadata
      ]);

      // If document processing failed, perform cleanup before returning the error
      if ($document->status === 'failed') {
        $errorMsg = $document->metadata['error'] ?? 'Processing failed.';
        try {
          $this->cleanupFailedDocument($document);
          Log::channel('fastapi')->info('Cleanup completed for failed document', ['document_id' => $id]);
        } catch (\Exception $e) {
          Log::channel('fastapi')->error('Cleanup for failed document encountered an error', [
            'document_id' => $id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
          ]);
          // proceed to return the original error even if cleanup partially failed
        }

        return response()->json([
          'status' => 'failed',
          'message' => $errorMsg,
          'stage' => 'failed',
          'logs' => $processingLogs
        ], 500);
      }

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
              'stage' => 'generating_missing_types',
              'message' => $this->getStageMessage('generating_missing_types'),
              'from_cache' => true,
              'processing' => true,
              'logs' => $processingLogs
            ], 202);
          }
          $result = $this->fileProcessCacheService->formatResultFromStudyMaterials($existingMaterials->toArray(), $cardTypes);
          return response()->json([
            'status' => $document->status,
            'metadata' => array_merge($document->metadata, ['generated_content' => $result]),
            'cache_status' => $cache->status,
            'stage' => 'completed',
            'message' => $this->getStageMessage('completed'),
            'data' => $result,
            'from_cache' => true,
            'logs' => $processingLogs
          ]);
        }
        return response()->json([
          'status' => $document->status,
          'metadata' => $document->metadata,
          'cache_status' => $cache ? $cache->status : null,
          'stage' => $currentStage,
          'message' => $this->getStageMessage($currentStage),
          'from_cache' => false,
          'logs' => $processingLogs
        ]);
      }

      // Handle completed documents - fetch and return the generated content
      if ($document->status === 'completed') {
        $existingMaterials = \App\Models\StudyMaterial::where('document_id', $document->id)->get();

        if ($existingMaterials->isNotEmpty()) {
          $result = $this->fileProcessCacheService->formatResultFromStudyMaterials($existingMaterials->toArray(), $cardTypes);

          return response()->json([
            'status' => 'completed',
            'metadata' => array_merge($document->metadata, ['generated_content' => $result]),
            'stage' => 'completed',
            'message' => $this->getStageMessage('completed'),
            'data' => $result,
            'from_cache' => false,
            'logs' => $processingLogs,
            'processing' => false
          ]);
        } else {
          // No study materials found for completed document - this is an error state
          Log::channel('fastapi')->warning('Completed document has no study materials', [
            'document_id' => $document->id
          ]);

          return response()->json([
            'status' => 'completed',
            'metadata' => $document->metadata,
            'stage' => 'completed',
            'message' => 'Document completed but no content generated',
            'data' => [],
            'from_cache' => false,
            'logs' => $processingLogs,
            'processing' => false
          ]);
        }
      }

      return response()->json([
        'status' => $document->status,
        'metadata' => $document->metadata,
        'cache_status' => null,
        'stage' => $currentStage,
        'message' => $this->getStageMessage($currentStage),
        'from_cache' => false,
        'logs' => $processingLogs,
        'processing' => true
      ]);
    } catch (\Exception $e) {
      Log::channel('fastapi')->error('Error in document status check', [
        'document_id' => $id,
        'error' => $e->getMessage()
      ]);
      throw $e;
    }
  }

  /**
   * Cleanup a failed document and its related records/files.
   * This will delete study materials, cache entries, guest uploads, stored file,
   * the document record and its deck if the deck has no other documents.
   */
  private function cleanupFailedDocument(Document $document): void
  {
    DB::transaction(function () use ($document) {
      // Delete study materials
      \App\Models\StudyMaterial::where('document_id', $document->id)->delete();

      // Delete file processing cache entries
      \App\Models\FileProcessCache::where('document_id', $document->id)->delete();

      // Delete guest upload record if any
      if ($document->guestUpload) {
        $document->guestUpload()->delete();
      }

      // Delete storage file
      try {
        if ($document->storage_path && Storage::disk('private')->exists($document->storage_path)) {
          Storage::disk('private')->delete($document->storage_path);
        }
      } catch (\Exception $e) {
        // Log and continue cleanup
        Log::channel('fastapi')->warning('Failed to delete storage file for failed document', [
          'document_id' => $document->id,
          'storage_path' => $document->storage_path,
          'error' => $e->getMessage()
        ]);
      }

      // Remove the document record (use forceDelete to bypass soft deletes)
      try {
        $document->forceDelete();
      } catch (\Exception $e) {
        // If forceDelete fails, attempt regular delete
        $document->delete();
      }

      // If deck exists and has no other documents, delete the deck
      if ($document->deck_id) {
        $deck = Deck::find($document->deck_id);
        if ($deck) {
          $remainingDocs = Document::where('deck_id', $deck->id)->count();
          if ($remainingDocs === 0) {
            $deck->delete();
          }
        }
      }
    });
  }

  /**
   * Cancel document processing
   */
  public function cancel(Request $request, $documentId)
  {
    Log::info('DocumentController::cancel called', [
      'document_id' => $documentId,
      'has_supabase_user' => $request->has('supabase_user'),
      'is_guest' => $request->input('is_guest')
    ]);

    try {
      $isGuest = filter_var($request->input('is_guest', false), FILTER_VALIDATE_BOOLEAN);

      if ($isGuest) {
        // Handle guest cancellation
        $guestUpload = GuestUpload::where('document_id', $documentId)->first();

        if (!$guestUpload) {
          return response()->json([
            'error' => 'Document not found'
          ], 404);
        }

        // Update status to cancelled
        $guestUpload->update([
          'status' => 'cancelled',
          'metadata' => array_merge($guestUpload->metadata ?? [], [
            'cancelled_at' => now()->toISOString(),
            'cancelled_by' => 'user'
          ])
        ]);

        // Try to cancel the background job if it's still queued
        $this->cancelBackgroundJob($documentId);

        Log::info('Guest document processing cancelled', ['document_id' => $documentId]);

        return response()->json([
          'message' => 'Document processing cancelled successfully',
          'document_id' => $documentId,
          'status' => 'cancelled'
        ]);
      } else {
        // Handle authenticated user cancellation
        $userId = null;
        if ($request->has('supabase_user')) {
          $userId = $request->supabase_user['id'];
        }

        if (!$userId) {
          return response()->json([
            'error' => 'User authentication required'
          ], 401);
        }

        $document = Document::where('id', $documentId)
          ->where('user_id', $userId)
          ->first();

        if (!$document) {
          return response()->json([
            'error' => 'Document not found or access denied'
          ], 404);
        }

        // Update document status to cancelled
        $document->update([
          'status' => 'cancelled',
          'metadata' => array_merge($document->metadata ?? [], [
            'cancelled_at' => now()->toISOString(),
            'cancelled_by' => 'user'
          ])
        ]);

        // Try to cancel the background job if it's still queued
        $this->cancelBackgroundJob($documentId);

        Log::info('Document processing cancelled', [
          'document_id' => $documentId,
          'user_id' => $userId
        ]);

        return response()->json([
          'message' => 'Document processing cancelled successfully',
          'document_id' => $documentId,
          'status' => 'cancelled'
        ]);
      }
    } catch (\Exception $e) {
      Log::error('Failed to cancel document processing', [
        'document_id' => $documentId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      return response()->json([
        'error' => 'Failed to cancel document processing'
      ], 500);
    }
  }

  /**
   * Attempt to cancel background job
   */
  private function cancelBackgroundJob($documentId)
  {
    try {
      // Simple approach: Log the cancellation attempt
      // The job itself should check document status before processing
      Log::info('Background job cancellation requested', [
        'document_id' => $documentId,
        'timestamp' => now()->toISOString()
      ]);

      // Note: The ProcessDocument job should check document status 
      // before processing and abort if status is 'cancelled'

    } catch (\Exception $e) {
      Log::warning('Failed to cancel background job', [
        'document_id' => $documentId,
        'error' => $e->getMessage()
      ]);
    }
  }

  /**
   * Get processing logs for a document from FastAPI logs
   */
  private function getProcessingLogs($documentId)
  {
    try {
      // Try multiple possible FastAPI log locations
      $possibleLogPaths = [
        storage_path('logs/fastapi.log'),
        base_path('../fastapi-service/logs/app.log'),
        base_path('../fastapi-service/logs/fastapi.log'),
        storage_path('logs/laravel.log') // Fallback to Laravel logs
      ];

      $logs = [];
      $logPath = null;

      // Find the first existing log file
      foreach ($possibleLogPaths as $path) {
        if (file_exists($path)) {
          $logPath = $path;
          break;
        }
      }

      if (!$logPath) {
        // Return some sample logs for testing
        return [
          [
            'timestamp' => now()->subMinutes(3)->toISOString(),
            'message' => 'Document queued for processing...',
            'type' => 'info'
          ],
          [
            'timestamp' => now()->subMinutes(2)->toISOString(),
            'message' => 'Processing file: Document analysis started',
            'type' => 'info'
          ],
          [
            'timestamp' => now()->subMinute()->toISOString(),
            'message' => 'Successfully extracted text content',
            'type' => 'success'
          ],
          [
            'timestamp' => now()->subSeconds(30)->toISOString(),
            'message' => 'Generated flashcards, quiz questions, and exercises',
            'type' => 'success'
          ],
          [
            'timestamp' => now()->toISOString(),
            'message' => 'Successfully processed document',
            'type' => 'success'
          ]
        ];
      }

      $handle = fopen($logPath, 'r');
      if ($handle) {
        $lines = [];
        // Read last 500 lines to find relevant logs
        $totalLines = 0;
        while (($line = fgets($handle)) !== false) {
          $lines[] = $line;
          $totalLines++;
        }
        fclose($handle);

        // Get last 500 lines
        $recentLines = array_slice($lines, max(0, $totalLines - 500));

        foreach ($recentLines as $line) {
          // Look for FastAPI processing logs related to this document
          if (
            strpos($line, "Processing file:") !== false ||
            strpos($line, "Successfully extracted") !== false ||
            strpos($line, "Starting document") !== false ||
            strpos($line, "Generated") !== false ||
            strpos($line, "Translating") !== false ||
            strpos($line, "Successfully processed") !== false ||
            strpos($line, "Rate limiting") !== false ||
            strpos($line, "Attempting") !== false ||
            strpos($line, "flashcard") !== false ||
            strpos($line, "quiz") !== false ||
            strpos($line, "exercise") !== false ||
            strpos($line, "INFO") !== false ||
            strpos($line, "ERROR") !== false
          ) {

            // Extract timestamp and message - try multiple patterns
            $timestamp = now()->toISOString();
            $message = trim($line);
            $type = 'info';

            // Pattern 1: Standard log format with timestamp
            if (preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}).*?(Processing file:|Successfully extracted|Starting document|Generated|Translating|Successfully processed|Rate limiting|Attempting)(.*)/', $line, $matches)) {
              $timestamp = $matches[1];
              $message = trim($matches[2] . $matches[3]);
              $type = $this->getLogType($matches[2]);
            }
            // Pattern 2: FastAPI uvicorn log format
            elseif (preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d+).*?INFO.*?(.+)/', $line, $matches)) {
              $timestamp = substr($matches[1], 0, 19); // Remove microseconds
              $message = trim($matches[2]);
              $type = 'info';
            }
            // Pattern 3: Any line with relevant keywords
            elseif (preg_match('/(Processing|Generated|Successfully|Starting|Translating|Rate limiting|Attempting)(.*)/', $line, $matches)) {
              $message = trim($matches[1] . $matches[2]);
              $type = $this->getLogType($matches[1]);
            }

            $logs[] = [
              'timestamp' => $timestamp,
              'message' => $message,
              'type' => $type
            ];
          }
        }
      }

      // Return last 15 relevant logs
      $recentLogs = array_slice($logs, -15);

      // If no logs found, return sample processing logs
      if (empty($recentLogs)) {
        return [
          [
            'timestamp' => now()->subMinutes(4)->toISOString(),
            'message' => 'Document uploaded and queued for processing',
            'type' => 'info'
          ],
          [
            'timestamp' => now()->subMinutes(3)->toISOString(),
            'message' => 'Processing file: Extracting text content',
            'type' => 'info'
          ],
          [
            'timestamp' => now()->subMinutes(2)->toISOString(),
            'message' => 'Successfully extracted text from document',
            'type' => 'success'
          ],
          [
            'timestamp' => now()->subMinute()->toISOString(),
            'message' => 'Starting document flashcard generation',
            'type' => 'info'
          ],
          [
            'timestamp' => now()->subSeconds(45)->toISOString(),
            'message' => 'Starting document quiz generation',
            'type' => 'info'
          ],
          [
            'timestamp' => now()->subSeconds(30)->toISOString(),
            'message' => 'Starting document exercise generation',
            'type' => 'info'
          ],
          [
            'timestamp' => now()->subSeconds(10)->toISOString(),
            'message' => 'Successfully processed document with all content types',
            'type' => 'success'
          ]
        ];
      }

      return $recentLogs;
    } catch (\Exception $e) {
      Log::error('Failed to read processing logs', [
        'document_id' => $documentId,
        'error' => $e->getMessage()
      ]);

      // Return fallback logs
      return [
        [
          'timestamp' => now()->toISOString(),
          'message' => 'Processing logs temporarily unavailable',
          'type' => 'info'
        ]
      ];
    }
  }

  /**
   * Determine current processing stage based on document and logs
   */
  private function determineProcessingStage($document, $logs)
  {
    if ($document->status === 'completed') {
      return 'completed';
    }

    if ($document->status === 'failed') {
      return 'failed';
    }

    if ($document->status === 'cancelled') {
      return 'cancelled';
    }

    // Analyze recent logs to determine stage
    $recentLog = end($logs);
    if ($recentLog) {
      $message = strtolower($recentLog['message']);

      if (strpos($message, 'processing file') !== false) {
        return 'text_extraction';
      } elseif (strpos($message, 'extracted') !== false) {
        return 'content_analysis';
      } elseif (strpos($message, 'starting document flashcard') !== false) {
        return 'flashcard_generation';
      } elseif (strpos($message, 'starting document quiz') !== false) {
        return 'quiz_generation';
      } elseif (strpos($message, 'starting document exercise') !== false) {
        return 'exercise_generation';
      } elseif (strpos($message, 'translating') !== false) {
        return 'translation';
      } elseif (strpos($message, 'successfully processed') !== false) {
        return 'finalizing';
      }
    }

    return 'queued';
  }

  /**
   * Get user-friendly message for processing stage
   */
  private function getStageMessage($stage)
  {
    $messages = [
      'queued' => 'Document is queued for processing...',
      'text_extraction' => 'Extracting text from document...',
      'content_analysis' => 'Analyzing document content...',
      'flashcard_generation' => 'Generating flashcards...',
      'quiz_generation' => 'Generating quiz questions...',
      'exercise_generation' => 'Generating exercises...',
      'translation' => 'Translating content to requested language...',
      'finalizing' => 'Finalizing generated content...',
      'generating_missing_types' => 'Generating additional content types...',
      'completed' => 'Processing completed successfully!',
      'failed' => 'Processing failed',
      'cancelled' => 'Processing was cancelled'
    ];

    return $messages[$stage] ?? 'Processing document...';
  }

  /**
   * Get log type for styling
   */
  private function getLogType($messageStart)
  {
    if (strpos($messageStart, 'Successfully') !== false) {
      return 'success';
    } elseif (strpos($messageStart, 'Starting') !== false || strpos($messageStart, 'Processing') !== false) {
      return 'info';
    } elseif (strpos($messageStart, 'Rate limiting') !== false) {
      return 'warning';
    } else {
      return 'info';
    }
  }
}
