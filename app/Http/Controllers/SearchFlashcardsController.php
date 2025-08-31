<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateSearchFlashcards;
use App\Services\FastApiService;
use App\Models\SearchFlashcardSearch;
use App\Models\SearchFlashcardResult;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class SearchFlashcardsController extends Controller
{
  protected $fastApiService;

  public function __construct(FastApiService $fastApiService)
  {
    $this->fastApiService = $fastApiService;
  }

  /**
   * Generate flashcards from a search topic
   *
   * @param Request $request
   * @return JsonResponse
   */
  public function generateFlashcards(Request $request): JsonResponse
  {
    try {
      // Validate request
      $validator = Validator::make($request->all(), [
        'topic' => 'required|string|min:3|max:255',
        'description' => 'nullable|string|max:1000',
        'difficulty' => 'nullable|string|in:beginner,intermediate,advanced',
        'count' => 'nullable|integer|min:1|max:20',
      ]);

      if ($validator->fails()) {
        return response()->json([
          'success' => false,
          'message' => 'Validation failed',
          'errors' => $validator->errors()
        ], 422);
      }

      $topic = $request->input('topic');
      $description = $request->input('description');
      $difficulty = $request->input('difficulty', 'beginner');
      $count = $request->input('count', 10);
      $supabaseUser = $request->get('supabase_user');
      $userId = $supabaseUser && isset($supabaseUser['id']) ? $supabaseUser['id'] : null; // Get authenticated user ID if available

      // Generate unique job ID
      $jobId = Str::uuid()->toString();

      // Dispatch the job
      GenerateSearchFlashcards::dispatch($jobId, $topic, $description, $difficulty, $count, $userId);

      Log::channel('fastapi')->info('Search flashcards job dispatched', [
        'job_id' => $jobId,
        'topic' => $topic,
        'difficulty' => $difficulty,
        'count' => $count,
        'user_id' => $userId
      ]);

      return response()->json([
        'success' => true,
        'message' => 'Flashcard generation job started',
        'data' => [
          'job_id' => $jobId,
          'status' => 'queued',
          'message' => 'Job has been queued and will start processing shortly',
          'estimated_time' => '5-15 minutes depending on topic complexity'
        ]
      ], 202);
    } catch (\Exception $e) {
      Log::channel('fastapi')->error('Error in generateFlashcards controller', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Failed to start flashcard generation',
        'error' => $e->getMessage()
      ], 500);
    }
  }

  /**
   * Check job status
   *
   * @param string $jobId
   * @return JsonResponse
   */
  public function checkJobStatus(string $jobId): JsonResponse
  {
    try {
      $cacheKey = "search_flashcards_job_{$jobId}";
      $jobData = Cache::get($cacheKey);

      if (!$jobData) {
        return response()->json([
          'success' => false,
          'message' => 'Job not found or expired',
          'data' => [
            'job_id' => $jobId,
            'status' => 'not_found'
          ]
        ], 404);
      }

      return response()->json([
        'success' => true,
        'message' => 'Job status retrieved successfully',
        'data' => $jobData
      ]);
    } catch (\Exception $e) {
      Log::channel('fastapi')->error('Error in checkJobStatus controller', [
        'job_id' => $jobId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Failed to check job status',
        'error' => $e->getMessage()
      ], 500);
    }
  }

  /**
   * Get suggested topics
   *
   * @return JsonResponse
   */
  public function getSuggestedTopics(): JsonResponse
  {
    try {
      $topics = $this->fastApiService->getSuggestedTopics();

      return response()->json([
        'success' => true,
        'message' => 'Suggested topics retrieved successfully',
        'data' => $topics
      ]);
    } catch (\Exception $e) {
      Log::channel('fastapi')->error('Error in getSuggestedTopics controller', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Failed to retrieve suggested topics',
        'error' => $e->getMessage()
      ], 500);
    }
  }

  /**
   * Check service health
   *
   * @return JsonResponse
   */
  public function checkHealth(): JsonResponse
  {
    try {
      $health = $this->fastApiService->checkSearchFlashcardsHealth();

      return response()->json([
        'success' => true,
        'message' => 'Health check completed',
        'data' => $health
      ]);
    } catch (\Exception $e) {
      Log::channel('fastapi')->error('Error in checkHealth controller', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      // Return 200 OK but with unhealthy status for FastAPI connection issues
      // This allows the health check to work even when FastAPI is down
      return response()->json([
        'success' => true,
        'message' => 'Health check completed (FastAPI service unavailable)',
        'data' => [
          'status' => 'unhealthy',
          'service' => 'search-flashcards',
          'laravel_status' => 'healthy',
          'fastapi_status' => 'unavailable',
          'error' => $e->getMessage()
        ]
      ]);
    }
  }

  /**
   * Get all active jobs for a user
   *
   * @param Request $request
   * @return JsonResponse
   */
  public function getUserJobs(Request $request): JsonResponse
  {
    try {
      $supabaseUser = $request->get('supabase_user');
      $userId = $supabaseUser && isset($supabaseUser['id']) ? $supabaseUser['id'] : null;

      if (!$userId) {
        return response()->json([
          'success' => false,
          'message' => 'Authentication required'
        ], 401);
      }

      // Get all cache keys for this user's jobs
      $pattern = "search_flashcards_job_*";
      $keys = Cache::get($pattern);

      $userJobs = [];

      // This is a simplified approach - in production you might want to store job IDs in a database
      // For now, we'll return a message about this limitation
      return response()->json([
        'success' => true,
        'message' => 'User jobs retrieval',
        'data' => [
          'note' => 'Job tracking is currently limited. Use the job_id from the generation response to check status.',
          'user_id' => $userId
        ]
      ]);
    } catch (\Exception $e) {
      Log::channel('fastapi')->error('Error in getUserJobs controller', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Failed to retrieve user jobs',
        'error' => $e->getMessage()
      ], 500);
    }
  }

  /**
   * Get search history for the authenticated user
   *
   * @param Request $request
   * @return JsonResponse
   */
  public function getSearchHistory(Request $request): JsonResponse
  {
    try {
      $supabaseUser = $request->get('supabase_user');
      $userId = $supabaseUser && isset($supabaseUser['id']) ? $supabaseUser['id'] : null;

      if (!$userId) {
        return response()->json([
          'success' => false,
          'message' => 'Authentication required'
        ], 401);
      }

      $perPage = $request->input('per_page', 10);
      $status = $request->input('status'); // Optional filter by status
      $topic = $request->input('topic'); // Optional filter by topic

      $query = SearchFlashcardSearch::forUser($userId)
        ->with(['flashcards' => function ($query) {
          $query->select('id', 'search_id', 'question', 'answer', 'type', 'difficulty', 'order_index');
        }])
        ->with(['latestStudySession' => function ($query) {
          $query->select('id', 'search_id', 'started_at', 'completed_at', 'total_flashcards', 'studied_flashcards', 'correct_answers', 'incorrect_answers');
        }]);

      // Apply filters
      if ($status) {
        $query->where('status', $status);
      }

      if ($topic) {
        $query->where('topic', 'like', "%{$topic}%");
      }

      $searches = $query->orderBy('created_at', 'desc')
        ->paginate($perPage);

      // Transform the data to include study statistics
      $searches->getCollection()->transform(function ($search) {
        $search->study_stats = $search->study_stats;
        $search->flashcards_count = $search->flashcards_count;
        $search->has_been_studied = $search->hasBeenStudied();
        return $search;
      });

      return response()->json([
        'success' => true,
        'message' => 'Search history retrieved successfully',
        'data' => $searches
      ]);
    } catch (\Exception $e) {
      Log::channel('fastapi')->error('Error in getSearchHistory controller', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Failed to retrieve search history',
        'error' => $e->getMessage()
      ], 500);
    }
  }

  /**
   * Get a specific search with its flashcards
   *
   * @param int $searchId
   * @param Request $request
   * @return JsonResponse
   */
  public function getSearchDetails(int $searchId, Request $request): JsonResponse
  {
    try {
      $supabaseUser = $request->get('supabase_user');
      $userId = $supabaseUser && isset($supabaseUser['id']) ? $supabaseUser['id'] : null;

      if (!$userId) {
        return response()->json([
          'success' => false,
          'message' => 'Authentication required'
        ], 401);
      }

      $search = SearchFlashcardSearch::forUser($userId)
        ->with(['flashcards' => function ($query) {
          $query->orderBy('order_index');
        }])
        ->with(['studySessions' => function ($query) {
          $query->orderBy('created_at', 'desc');
        }])
        ->find($searchId);

      if (!$search) {
        return response()->json([
          'success' => false,
          'message' => 'Search not found',
          'data' => null
        ], 404);
      }

      // Add computed attributes
      $search->study_stats = $search->study_stats;
      $search->flashcards_count = $search->flashcards_count;
      $search->has_been_studied = $search->hasBeenStudied();

      return response()->json([
        'success' => true,
        'message' => 'Search details retrieved successfully',
        'data' => $search
      ]);
    } catch (\Exception $e) {
      Log::channel('fastapi')->error('Error in getSearchDetails controller', [
        'search_id' => $searchId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Failed to retrieve search details',
        'error' => $e->getMessage()
      ], 500);
    }
  }

  /**
   * Get recent searches for the authenticated user
   *
   * @param Request $request
   * @return JsonResponse
   */
  public function getRecentSearches(Request $request): JsonResponse
  {
    try {
      $supabaseUser = $request->get('supabase_user');
      $userId = $supabaseUser && isset($supabaseUser['id']) ? $supabaseUser['id'] : null;

      if (!$userId) {
        return response()->json([
          'success' => false,
          'message' => 'Authentication required'
        ], 401);
      }

      $limit = $request->input('limit', 5);
      $days = $request->input('days', 30);

      $recentSearches = SearchFlashcardSearch::forUser($userId)
        ->recent($days)
        ->completed()
        ->with(['flashcards' => function ($query) {
          $query->select('id', 'search_id', 'question', 'answer', 'type', 'difficulty', 'order_index');
        }])
        ->orderBy('created_at', 'desc')
        ->limit($limit)
        ->get()
        ->map(function ($search) {
          return [
            'id' => $search->id,
            'topic' => $search->topic,
            'description' => $search->description,
            'difficulty' => $search->difficulty,
            'flashcards_count' => $search->flashcards_count,
            'created_at' => $search->created_at,
            'completed_at' => $search->completed_at,
            'has_been_studied' => $search->hasBeenStudied(),
            'study_stats' => $search->study_stats
          ];
        });

      return response()->json([
        'success' => true,
        'message' => 'Recent searches retrieved successfully',
        'data' => $recentSearches
      ]);
    } catch (\Exception $e) {
      Log::channel('fastapi')->error('Error in getRecentSearches controller', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Failed to retrieve recent searches',
        'error' => $e->getMessage()
      ], 500);
    }
  }

  /**
   * Get search statistics for the authenticated user
   *
   * @param Request $request
   * @return JsonResponse
   */
  public function getSearchStats(Request $request): JsonResponse
  {
    try {
      $supabaseUser = $request->get('supabase_user');
      $userId = $supabaseUser && isset($supabaseUser['id']) ? $supabaseUser['id'] : null;

      if (!$userId) {
        return response()->json([
          'success' => false,
          'message' => 'Authentication required'
        ], 401);
      }

      $days = $request->input('days', 30);

      // Get total searches
      $totalSearches = SearchFlashcardSearch::forUser($userId)->count();
      $completedSearches = SearchFlashcardSearch::forUser($userId)->completed()->count();
      $failedSearches = SearchFlashcardSearch::forUser($userId)->failed()->count();

      // Get recent searches count
      $recentSearches = SearchFlashcardSearch::forUser($userId)->recent($days)->count();

      // Get total flashcards generated
      $totalFlashcards = SearchFlashcardSearch::forUser($userId)
        ->completed()
        ->withCount('flashcards')
        ->get()
        ->sum('flashcards_count');

      // Get most popular topics
      $popularTopics = SearchFlashcardSearch::forUser($userId)
        ->completed()
        ->selectRaw('topic, COUNT(*) as count')
        ->groupBy('topic')
        ->orderBy('count', 'desc')
        ->limit(5)
        ->get();

      // Get difficulty distribution
      $difficultyDistribution = SearchFlashcardSearch::forUser($userId)
        ->completed()
        ->selectRaw('difficulty, COUNT(*) as count')
        ->groupBy('difficulty')
        ->get()
        ->keyBy('difficulty');

      $stats = [
        'total_searches' => $totalSearches,
        'completed_searches' => $completedSearches,
        'failed_searches' => $failedSearches,
        'recent_searches' => $recentSearches,
        'total_flashcards' => $totalFlashcards,
        'success_rate' => $totalSearches > 0 ? round(($completedSearches / $totalSearches) * 100, 2) : 0,
        'popular_topics' => $popularTopics,
        'difficulty_distribution' => $difficultyDistribution,
        'period_days' => $days
      ];

      return response()->json([
        'success' => true,
        'message' => 'Search statistics retrieved successfully',
        'data' => $stats
      ]);
    } catch (\Exception $e) {
      Log::channel('fastapi')->error('Error in getSearchStats controller', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Failed to retrieve search statistics',
        'error' => $e->getMessage()
      ], 500);
    }
  }
}
