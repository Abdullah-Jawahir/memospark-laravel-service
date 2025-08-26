<?php

namespace App\Http\Controllers;

use App\Models\Deck;
use App\Models\User;
use App\Models\Document;
use Illuminate\Http\Request;
use App\Models\StudyMaterial;
use Illuminate\Support\Carbon;
use App\Models\FlashcardReview;
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
    $appUser = User::firstOrCreate(
      ['email' => $supabaseUser['email']],
      [
        'name' => $supabaseUser['user_metadata']['full_name'] ?? ($supabaseUser['email'] ?? 'User'),
        'user_type' => 'student',
        'password' => null,
      ]
    );
    $userId = $appUser->id;

    // Verify the study material belongs to a document owned by this user (by id or email)
    $studyMaterial = StudyMaterial::with(['document.deck'])->find($request->study_material_id);

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
      if (!$ownedByUser && $studyMaterial->document->deck && method_exists($studyMaterial->document->deck, 'getRelation')) {
        $deckUser = $studyMaterial->document->deck->getRelation('user') ?? null;
        if ($deckUser) {
          $ownedByUser = ($deckUser->id === $userId) || (isset($supabaseUser['email']) && $deckUser->email === $supabaseUser['email']);
        }
      }
    }
    if (!$studyMaterial || !$ownedByUser) {
      return response()->json(['error' => 'Study material not found or access denied'], 404);
    }

    // Create a new review per attempt (no upsert)
    $review = FlashcardReview::create([
      'user_id' => $userId,
      'study_material_id' => $request->study_material_id,
      'rating' => $request->rating,
      'study_time' => $request->study_time,
      'reviewed_at' => now(),
      'session_id' => $request->session_id,
    ]);

    return response()->json([
      'message' => 'Study session recorded successfully',
      'review' => [
        'id' => $review->id,
        'rating' => $review->rating,
        'study_time' => $review->study_time,
        'reviewed_at' => $review->reviewed_at,
      ]
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
    $appUser = User::firstOrCreate(
      ['email' => $supabaseUser['email']],
      [
        'name' => $supabaseUser['user_metadata']['full_name'] ?? ($supabaseUser['email'] ?? 'User'),
        'user_type' => 'student',
        'password' => null,
      ]
    );
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
    $appUser = User::firstOrCreate(
      ['email' => $supabaseUser['email']],
      [
        'name' => $supabaseUser['user_metadata']['full_name'] ?? ($supabaseUser['email'] ?? 'User'),
        'user_type' => 'student',
        'password' => null,
      ]
    );
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
}
