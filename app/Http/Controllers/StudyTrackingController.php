<?php

namespace App\Http\Controllers;

use App\Models\Deck;
use App\Models\User;
use App\Models\Document;
use Illuminate\Http\Request;
use App\Models\StudyMaterial;
use Illuminate\Support\Carbon;
use App\Models\FlashcardReview;
use App\Models\Achievement;
use App\Models\UserAchievement;
use Illuminate\Support\Facades\Log;

class StudyTrackingController extends Controller
{
  /**
   * Record a flashcard review/study session
   */
  public function recordReview(Request $request)
  {
    $request->validate([
      'study_material_id' => 'required|exists:study_materials,id',
      'rating' => 'required|in:again,hard,good,easy',
      'study_time' => 'required|integer|min:1', // Study time in seconds
      'session_id' => 'nullable|string', // Optional session identifier
    ]);

    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['email'])) {
      return response()->json(['error' => 'Supabase user not found'], 401);
    }
    // Map Supabase user to local users table (int id)
    $userRole = $supabaseUser['role'] ?? 'student';
    $appUser = User::firstOrCreate(
      ['email' => $supabaseUser['email']],
      [
        'name' => $supabaseUser['user_metadata']['full_name'] ?? ($supabaseUser['email'] ?? 'User'),
        'user_type' => $userRole,
        'password' => null,
      ]
    );

    // Update user_type if it has changed
    if ($appUser->user_type !== $userRole) {
      $appUser->update(['user_type' => $userRole]);
    }
    $userId = $appUser->id;

    // Get the current session ID from the request
    $currentSessionId = $request->session_id;

    // Verify the study material belongs to a document owned by this user (by id or email)
    $studyMaterial = StudyMaterial::with(['document.deck.user'])->find($request->study_material_id);

    Log::info($studyMaterial ? $studyMaterial->toArray() : null);

    Log::info($userId);
    $ownedByUser = false;
    if ($studyMaterial && $studyMaterial->document) {
      $documentOwnerSupabaseId = $studyMaterial->document->user_id ?? null; // Supabase UUID
      $deckOwnerSupabaseId = $studyMaterial->document->deck->user_id ?? null; // Supabase UUID

      // Prefer strict check against Supabase user id if available
      if (!empty($supabaseUser['id'])) {
        $ownedByUser = ($documentOwnerSupabaseId === $supabaseUser['id']) || ($deckOwnerSupabaseId === $supabaseUser['id']);
      }

      // Fallback: if deck->user relation is available, also validate via local user/email mapping
      if (!$ownedByUser && $studyMaterial->document->deck && $studyMaterial->document->deck->user) {
        $deckUser = $studyMaterial->document->deck->user;
        $ownedByUser = ($deckUser->id === $userId) || (isset($supabaseUser['email']) && $deckUser->email === $supabaseUser['email']);
      }
    }
    if (!$studyMaterial || !$ownedByUser) {
      return response()->json(['error' => 'Study material not found or access denied'], 404);
    }

    // Check if this card was previously rated as "hard" in the same session
    $previousRating = null;
    $ratingChanged = false;

    if ($currentSessionId) {
      $previousReview = FlashcardReview::where('user_id', $userId)
        ->where('study_material_id', $request->study_material_id)
        ->where('session_id', $currentSessionId)
        ->orderByDesc('reviewed_at')
        ->first();

      if ($previousReview) {
        $previousRating = $previousReview->rating;
        // Check if the rating changed from "hard" to "good" or "easy"
        if ($previousRating === 'hard' && ($request->rating === 'good' || $request->rating === 'easy')) {
          $ratingChanged = true;
        }
      }
    }

    // Create a new review per attempt
    $review = FlashcardReview::create([
      'user_id' => $userId,
      'study_material_id' => $request->study_material_id,
      'rating' => $request->rating,
      'study_time' => $request->study_time,
      'reviewed_at' => now(),
      'session_id' => $request->session_id,
    ]);

    // Award points based on rating and time, then unlock achievements
    $ratingPointsMap = [
      'again' => 1,
      'hard' => 2,
      'good' => 3,
      'easy' => 4,
    ];
    $earnedPoints = $ratingPointsMap[$request->rating] ?? 1;
    // Small time bonus: +1 point per full 60 seconds
    $earnedPoints += intdiv((int)$request->study_time, 60);

    // Increment user points
    $appUser->points = (int)($appUser->points ?? 0) + $earnedPoints;
    $appUser->save();

    // Unlock any point-based achievements
    $eligible = Achievement::where(function ($q) {
      $q->whereNull('criteria')->orWhere('criteria', 'points');
    })
      ->where('points', '>', 0)
      ->where('points', '<=', $appUser->points)
      ->get();

    foreach ($eligible as $achievement) {
      $already = UserAchievement::where('user_id', $appUser->id)
        ->where('achievement_id', $achievement->id)
        ->exists();
      if (!$already) {
        UserAchievement::create([
          'user_id' => $appUser->id,
          'achievement_id' => $achievement->id,
          'achieved_at' => now(),
        ]);
      }
    }

    // Calculate session statistics
    $sessionStats = null;
    if ($currentSessionId) {
      // Get counts for this session
      $sessionStats = [
        'total' => FlashcardReview::where('user_id', $userId)
          ->where('session_id', $currentSessionId)
          ->distinct('study_material_id')
          ->count(),
        'hard_count' => FlashcardReview::where('user_id', $userId)
          ->where('session_id', $currentSessionId)
          ->where('rating', 'hard')
          ->whereRaw('id IN (
            SELECT MAX(id) FROM flashcard_reviews 
            WHERE user_id = ? AND session_id = ?
            GROUP BY study_material_id
          )', [$userId, $currentSessionId])
          ->count(),
        'good_or_easy_count' => FlashcardReview::where('user_id', $userId)
          ->where('session_id', $currentSessionId)
          ->whereIn('rating', ['good', 'easy'])
          ->whereRaw('id IN (
            SELECT MAX(id) FROM flashcard_reviews 
            WHERE user_id = ? AND session_id = ?
            GROUP BY study_material_id
          )', [$userId, $currentSessionId])
          ->count(),
        'rating_changed' => $ratingChanged,
        'previous_rating' => $previousRating,
      ];
    }

    return response()->json([
      'message' => 'Study session recorded successfully',
      'review' => [
        'id' => $review->id,
        'rating' => $review->rating,
        'study_time' => $review->study_time,
        'reviewed_at' => $review->reviewed_at,
      ],
      'earned_points' => $earnedPoints,
      'total_points' => $appUser->points,
      'session_stats' => $sessionStats,
    ]);
  }

  /**
   * Start a study session
   */
  public function startSession(Request $request)
  {
    $request->validate([
      'deck_id' => 'required|exists:decks,id',
    ]);

    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['email'])) {
      return response()->json(['error' => 'Supabase user not found'], 401);
    }
    $userRole = $supabaseUser['role'] ?? 'student';
    $appUser = User::firstOrCreate(
      ['email' => $supabaseUser['email']],
      [
        'name' => $supabaseUser['user_metadata']['full_name'] ?? ($supabaseUser['email'] ?? 'User'),
        'user_type' => $userRole,
        'password' => null,
      ]
    );

    // Update user_type if it has changed
    if ($appUser->user_type !== $userRole) {
      $appUser->update(['user_type' => $userRole]);
    }
    $userId = $appUser->id;

    // Verify the deck belongs to this user
    $deck = Deck::with(['user'])->find($request->deck_id);

    if (!$deck) {
      return response()->json(['error' => 'Deck not found or access denied'], 404);
    }

    $sessionId = uniqid('session_' . $userId . '_', true);
    $startTime = now();

    return response()->json([
      'message' => 'Study session started',
      'session' => [
        'session_id' => $sessionId,
        'deck_id' => $deck->id,
        'deck_name' => $deck->name,
        'started_at' => $startTime,
        'total_cards' => $deck->studyMaterials->where('type', 'flashcard')->count(),
      ]
    ]);
  }

  /**
   * Get study statistics for a user
   */
  public function getStats(Request $request)
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['email'])) {
      return response()->json(['error' => 'Supabase user not found'], 401);
    }
    $userRole = $supabaseUser['role'] ?? 'student';
    $appUser = User::firstOrCreate(
      ['email' => $supabaseUser['email']],
      [
        'name' => $supabaseUser['user_metadata']['full_name'] ?? ($supabaseUser['email'] ?? 'User'),
        'user_type' => $userRole,
        'password' => null,
      ]
    );

    // Update user_type if it has changed
    if ($appUser->user_type !== $userRole) {
      $appUser->update(['user_type' => $userRole]);
    }
    $userId = $appUser->id;

    $today = Carbon::today();
    $thisWeek = Carbon::now()->startOfWeek();
    $thisMonth = Carbon::now()->startOfMonth();

    // Today's stats
    $todayStats = FlashcardReview::where('user_id', $userId)
      ->whereDate('reviewed_at', $today)
      ->selectRaw('
                COUNT(*) as cards_studied,
                SUM(study_time) as total_study_time,
                COUNT(DISTINCT study_material_id) as unique_cards
            ')
      ->first();

    // This week's stats
    $weekStats = FlashcardReview::where('user_id', $userId)
      ->where('reviewed_at', '>=', $thisWeek)
      ->selectRaw('
                COUNT(*) as cards_studied,
                SUM(study_time) as total_study_time,
                COUNT(DISTINCT study_material_id) as unique_cards
            ')
      ->first();

    // This month's stats
    $monthStats = FlashcardReview::where('user_id', $userId)
      ->where('reviewed_at', '>=', $thisMonth)
      ->selectRaw('
                COUNT(*) as cards_studied,
                SUM(study_time) as total_study_time,
                COUNT(DISTINCT study_material_id) as unique_cards
            ')
      ->first();

    // Overall stats
    $overallStats = FlashcardReview::where('user_id', $userId)
      ->selectRaw('
                COUNT(*) as total_reviews,
                SUM(study_time) as total_study_time,
                COUNT(DISTINCT study_material_id) as total_cards_studied
            ')
      ->first();

    return response()->json([
      'today' => [
        'cards_studied' => $todayStats->cards_studied ?? 0,
        'study_time' => $this->formatStudyTime($todayStats->total_study_time ?? 0),
        'unique_cards' => $todayStats->unique_cards ?? 0,
      ],
      'this_week' => [
        'cards_studied' => $weekStats->cards_studied ?? 0,
        'study_time' => $this->formatStudyTime($weekStats->total_study_time ?? 0),
        'unique_cards' => $weekStats->unique_cards ?? 0,
      ],
      'this_month' => [
        'cards_studied' => $monthStats->cards_studied ?? 0,
        'study_time' => $this->formatStudyTime($monthStats->total_study_time ?? 0),
        'unique_cards' => $monthStats->unique_cards ?? 0,
      ],
      'overall' => [
        'total_reviews' => $overallStats->total_reviews ?? 0,
        'total_study_time' => $this->formatStudyTime($overallStats->total_study_time ?? 0),
        'total_cards_studied' => $overallStats->total_cards_studied ?? 0,
      ],
    ]);
  }

  /**
   * Get recent study activity
   */
  public function getRecentActivity(Request $request)
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'Supabase user not found'], 401);
    }
    $userId = $supabaseUser['id'];

    $recentActivity = FlashcardReview::where('user_id', $userId)
      ->with(['studyMaterial.document.deck'])
      ->orderBy('reviewed_at', 'desc')
      ->take(10)
      ->get()
      ->map(function ($review) {
        $content = $review->studyMaterial->content;
        if (is_array($content)) {
          $content = json_encode($content);
        }
        $snippet = is_string($content) ? substr($content, 0, 100) . '...' : '';
        return [
          'id' => $review->id,
          'deck_name' => $review->studyMaterial->document->deck->name ?? 'Unknown Deck',
          'rating' => $review->rating,
          'study_time' => $this->formatStudyTime($review->study_time),
          'reviewed_at' => $review->reviewed_at->diffForHumans(),
          'card_content' => $snippet,
        ];
      });

    return response()->json($recentActivity);
  }

  /**
   * Format study time from seconds to human readable format
   */
  private function formatStudyTime($seconds)
  {
    if ($seconds < 60) {
      return $seconds . 's';
    }

    $minutes = intval($seconds / 60);
    $remainingSeconds = $seconds % 60;

    if ($minutes < 60) {
      return $minutes . 'm ' . $remainingSeconds . 's';
    }

    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;

    return $hours . 'h ' . $remainingMinutes . 'm';
  }

  /**
   * Enrich study materials with database IDs for rating functionality
   * This is used when study materials are loaded from localStorage but need database IDs
   */
  public function enrichMaterials(Request $request)
  {
    $request->validate([
      'deck_name' => 'required|string',
      'materials' => 'required|array',
      'materials.flashcards' => 'array',
      'materials.quizzes' => 'array',
      'materials.exercises' => 'array',
    ]);

    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'Supabase user not found'], 401);
    }

    $userId = $supabaseUser['id'];
    $deckName = $request->input('deck_name');
    $materials = $request->input('materials');

    try {
      // Find the deck by name and user
      $deck = Deck::where('user_id', $userId)
        ->where('name', $deckName)
        ->first();

      if (!$deck) {
        return response()->json(['error' => 'Deck not found'], 404);
      }

      // Get study materials from database for this deck
      $studyMaterials = StudyMaterial::with('document')
        ->whereHas('document', function ($q) use ($deck) {
          $q->where('deck_id', $deck->id);
        })
        ->orderBy('id')
        ->get();

      // Enrich materials with database IDs by matching content
      $enrichedMaterials = [
        'flashcards' => $this->enrichMaterialType($materials['flashcards'] ?? [], $studyMaterials, 'flashcard'),
        'quizzes' => $this->enrichMaterialType($materials['quizzes'] ?? [], $studyMaterials, 'quiz'),
        'exercises' => $this->enrichMaterialType($materials['exercises'] ?? [], $studyMaterials, 'exercise'),
      ];

      return response()->json([
        'success' => true,
        'materials' => $enrichedMaterials,
        'deck' => [
          'id' => $deck->id,
          'name' => $deck->name,
        ]
      ]);
    } catch (\Exception $e) {
      Log::error('Error enriching study materials', [
        'error' => $e->getMessage(),
        'user_id' => $userId,
        'deck_name' => $deckName
      ]);

      return response()->json(['error' => 'Failed to enrich materials'], 500);
    }
  }

  /**
   * Enrich a specific material type with database IDs
   */
  private function enrichMaterialType(array $localMaterials, $studyMaterials, string $type): array
  {
    $enriched = [];
    $dbMaterials = $studyMaterials->where('type', $type);

    foreach ($localMaterials as $localMaterial) {
      $enrichedMaterial = $localMaterial;

      // Try to find matching database material by content
      $matchingDbMaterial = $dbMaterials->first(function ($dbMaterial) use ($localMaterial, $type) {
        $dbContent = $dbMaterial->content;

        if ($type === 'flashcard') {
          // For flashcards, match by question and answer
          if (is_array($dbContent) && isset($dbContent['question'], $dbContent['answer'])) {
            return $dbContent['question'] === ($localMaterial['question'] ?? '') &&
              $dbContent['answer'] === ($localMaterial['answer'] ?? '');
          }
        } elseif ($type === 'quiz') {
          // For quizzes, match by question
          if (is_array($dbContent) && isset($dbContent['question'])) {
            return $dbContent['question'] === ($localMaterial['question'] ?? '');
          }
        } elseif ($type === 'exercise') {
          // For exercises, match by instruction
          if (is_array($dbContent) && isset($dbContent['instruction'])) {
            return $dbContent['instruction'] === ($localMaterial['instruction'] ?? '');
          }
        }

        return false;
      });

      if ($matchingDbMaterial) {
        $enrichedMaterial['id'] = $matchingDbMaterial->id;
      }

      $enriched[] = $enrichedMaterial;
    }

    return $enriched;
  }
}
