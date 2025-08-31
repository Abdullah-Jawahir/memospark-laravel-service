<?php

namespace App\Jobs;

use App\Services\FastApiService;
use App\Models\SearchFlashcardSearch;
use App\Models\SearchFlashcardResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GenerateSearchFlashcards implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  protected $jobId;
  protected $topic;
  protected $description;
  protected $difficulty;
  protected $count;
  protected $userId;

  /**
   * Create a new job instance.
   */
  public function __construct($jobId, $topic, $description = null, $difficulty = 'beginner', $count = 10, $userId = null)
  {
    $this->jobId = $jobId;
    $this->topic = $topic;
    $this->description = $description;
    $this->difficulty = $difficulty;
    $this->count = $count;
    $this->userId = $userId;
  }

  /**
   * Execute the job.
   */
  public function handle(FastApiService $fastApiService): void
  {
    $cacheKey = "search_flashcards_job_{$this->jobId}";

    try {
      // Create search record in database
      $searchRecord = SearchFlashcardSearch::create([
        'user_id' => $this->userId,
        'topic' => $this->topic,
        'description' => $this->description,
        'difficulty' => $this->difficulty,
        'requested_count' => $this->count,
        'job_id' => $this->jobId,
        'status' => 'processing',
        'started_at' => now()
      ]);

      // Update job status to processing
      $this->updateJobStatus($cacheKey, 'processing', 'Generating flashcards...');

      Log::channel('fastapi')->info('Starting search flashcards generation', [
        'job_id' => $this->jobId,
        'topic' => $this->topic,
        'description' => $this->description,
        'difficulty' => $this->difficulty,
        'count' => $this->count,
        'user_id' => $this->userId
      ]);

      // Add a small delay to ensure the frontend can see the processing status
      sleep(1);

      // Call FastAPI service to generate flashcards
      $result = $fastApiService->generateSearchFlashcards(
        $this->topic,
        $this->description,
        $this->difficulty,
        $this->count
      );

      // Add another delay to ensure proper queue behavior
      sleep(1);

      // Save flashcards to database
      if (isset($result['flashcards']) && is_array($result['flashcards'])) {
        $flashcards = [];
        foreach ($result['flashcards'] as $index => $flashcard) {
          $flashcards[] = [
            'search_id' => $searchRecord->id,
            'question' => $flashcard['question'],
            'answer' => $flashcard['answer'],
            'type' => $flashcard['type'] ?? 'Q&A',
            'difficulty' => $flashcard['difficulty'] ?? $this->difficulty,
            'order_index' => $index + 1,
            'created_at' => now(),
            'updated_at' => now()
          ];
        }

        // Bulk insert flashcards
        SearchFlashcardResult::insert($flashcards);

        // Update search record status
        $searchRecord->update([
          'status' => 'completed',
          'completed_at' => now()
        ]);

        // Store successful result in cache
        $this->updateJobStatus($cacheKey, 'completed', 'Flashcards generated successfully', [
          'result' => $result,
          'completed_at' => now()->toISOString(),
          'topic' => $this->topic,
          'difficulty' => $this->difficulty,
          'count' => $this->count,
          'user_id' => $this->userId,
          'search_id' => $searchRecord->id
        ]);

        Log::channel('fastapi')->info('Search flashcards generation completed', [
          'job_id' => $this->jobId,
          'topic' => $this->topic,
          'flashcards_count' => count($result['flashcards']),
          'search_id' => $searchRecord->id
        ]);
      } else {
        throw new \Exception('No flashcards generated from FastAPI service');
      }
    } catch (\Exception $e) {
      $errorMessage = 'Failed to generate flashcards: ' . $e->getMessage();

      Log::channel('fastapi')->error('Search flashcards generation failed', [
        'job_id' => $this->jobId,
        'topic' => $this->topic,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      // Update search record status if it exists
      if (isset($searchRecord)) {
        $searchRecord->update([
          'status' => 'failed',
          'error_message' => $e->getMessage()
        ]);
      }

      // Store failed result in cache
      $this->updateJobStatus($cacheKey, 'failed', $errorMessage, [
        'error' => $e->getMessage(),
        'failed_at' => now()->toISOString(),
        'topic' => $this->topic,
        'difficulty' => $this->difficulty,
        'count' => $this->count,
        'user_id' => $this->userId,
        'search_id' => $searchRecord->id ?? null
      ]);
    }
  }

  /**
   * Update job status in cache
   */
  protected function updateJobStatus($cacheKey, $status, $message, $data = [])
  {
    $jobData = [
      'status' => $status,
      'message' => $message,
      'updated_at' => now()->toISOString(),
      'topic' => $this->topic,
      'difficulty' => $this->difficulty,
      'count' => $this->count,
      'user_id' => $this->userId
    ];

    // Merge additional data
    $jobData = array_merge($jobData, $data);

    // Store in cache for 1 hour
    Cache::put($cacheKey, $jobData, now()->addHour());
  }

  /**
   * Get the tags that should be assigned to the job.
   */
  public function tags()
  {
    return [
      'search_flashcards',
      'topic:' . $this->topic,
      'user:' . ($this->userId ?? 'guest')
    ];
  }

  /**
   * Handle a job failure.
   */
  public function failed(\Throwable $exception)
  {
    $cacheKey = "search_flashcards_job_{$this->jobId}";

    Log::channel('fastapi')->error('Search flashcards job failed', [
      'job_id' => $this->jobId,
      'topic' => $this->topic,
      'error' => $exception->getMessage(),
      'trace' => $exception->getTraceAsString()
    ]);

    // Update job status to failed
    $this->updateJobStatus($cacheKey, 'failed', 'Job failed: ' . $exception->getMessage(), [
      'error' => $exception->getMessage(),
      'failed_at' => now()->toISOString(),
      'topic' => $this->topic,
      'difficulty' => $this->difficulty,
      'count' => $this->count,
      'user_id' => $this->userId
    ]);
  }
}
