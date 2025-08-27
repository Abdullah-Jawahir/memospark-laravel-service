<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Deck;
use App\Models\Document;
use App\Models\FlashcardReview;
use App\Models\StudyMaterial;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
  /**
   * Get admin dashboard overview statistics
   */
  public function overview(Request $request)
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'Supabase user not found'], 401);
    }

    // Verify admin role (you'll need to implement role checking)
    // For now, we'll assume the middleware handles this

    $today = Carbon::today();
    $thisMonth = Carbon::now()->startOfMonth();

    // Total users count
    $totalUsers = User::count();

    // Active decks count
    $activeDecks = Deck::count();

    // Total study sessions (flashcard reviews)
    $totalStudySessions = FlashcardReview::count();

    // Monthly growth calculation
    $thisMonthUsers = User::where('created_at', '>=', $thisMonth)->count();
    $lastMonthUsers = User::whereBetween('created_at', [
      Carbon::now()->subMonth()->startOfMonth(),
      Carbon::now()->subMonth()->endOfMonth()
    ])->count();

    $monthlyGrowth = $lastMonthUsers > 0
      ? round((($thisMonthUsers - $lastMonthUsers) / $lastMonthUsers) * 100, 1)
      : 0;

    // System health (simplified - you can expand this)
    $systemHealth = 98.9; // This could be calculated based on error rates, uptime, etc.

    return response()->json([
      'total_users' => $totalUsers,
      'active_decks' => $activeDecks,
      'total_study_sessions' => $totalStudySessions,
      'monthly_growth' => $monthlyGrowth > 0 ? "+{$monthlyGrowth}%" : "{$monthlyGrowth}%",
      'system_health' => "{$systemHealth}%"
    ]);
  }

  /**
   * Get recent user activity
   */
  public function recentActivity(Request $request)
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'Supabase user not found'], 401);
    }

    $activities = [];

    // Recent user registrations
    $recentUsers = User::latest('created_at')->take(5)->get();
    foreach ($recentUsers as $user) {
      // Safely handle the created_at field
      $createdAt = $user->created_at;
      if (is_string($createdAt)) {
        $createdAt = Carbon::parse($createdAt);
      }

      $activities[] = [
        'action' => 'New user registered',
        'user' => $user->email,
        'time' => $createdAt->diffForHumans(),
        'type' => 'registration'
      ];
    }

    // Recent deck creations
    $recentDecks = Deck::with('user')
      ->latest('created_at')
      ->take(5)
      ->get();

    foreach ($recentDecks as $deck) {
      $user = User::find($deck->user_id);

      // Safely handle the created_at field
      $createdAt = $deck->created_at;
      if (is_string($createdAt)) {
        $createdAt = Carbon::parse($createdAt);
      }

      $activities[] = [
        'action' => 'Deck created',
        'user' => $user ? $user->email : 'Unknown user',
        'time' => $createdAt->diffForHumans(),
        'type' => 'deck_creation',
        'details' => $deck->name
      ];
    }

    // Recent study sessions
    $recentReviews = FlashcardReview::with(['studyMaterial'])
      ->latest('reviewed_at')
      ->take(5)
      ->get();

    foreach ($recentReviews as $review) {
      $user = User::find($review->user_id);

      // Safely handle the reviewed_at field
      $reviewedAt = $review->reviewed_at;
      if (is_string($reviewedAt)) {
        $reviewedAt = \Carbon\Carbon::parse($reviewedAt);
      }

      $activities[] = [
        'action' => 'Study session completed',
        'user' => $user ? $user->email : 'Unknown user',
        'time' => $reviewedAt->diffForHumans(),
        'type' => 'study_session'
      ];
    }

    // Sort all activities by time (most recent first)
    usort($activities, function ($a, $b) {
      return strtotime($b['time']) - strtotime($a['time']);
    });

    // Return the most recent 10 activities
    return response()->json(array_slice($activities, 0, 10));
  }

  /**
   * Get user analytics
   */
  public function userAnalytics(Request $request)
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'Supabase user not found'], 401);
    }

    $analytics = [
      'total_registered_users' => User::count(),
      'active_users_today' => FlashcardReview::whereDate('reviewed_at', Carbon::today())
        ->distinct('user_id')
        ->count(),
      'active_users_this_week' => FlashcardReview::whereBetween('reviewed_at', [
        Carbon::now()->startOfWeek(),
        Carbon::now()->endOfWeek()
      ])->distinct('user_id')->count(),
      'user_registrations_this_month' => User::where('created_at', '>=', Carbon::now()->startOfMonth())->count()
    ];

    return response()->json($analytics);
  }

  /**
   * Get all users with pagination
   */
  public function getUsers(Request $request)
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'Supabase user not found'], 401);
    }

    $perPage = $request->get('per_page', 15);
    $search = $request->get('search');

    $query = User::query()->withCount('decks');

    if ($search) {
      $query->where(function ($q) use ($search) {
        $q->where('name', 'like', "%{$search}%")
          ->orWhere('email', 'like', "%{$search}%");
      });
    }

    $users = $query->orderBy('created_at', 'desc')
      ->paginate($perPage);

    return response()->json($users);
  }

  /**
   * Get system statistics for dashboard
   */
  public function systemStats(Request $request)
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'Supabase user not found'], 401);
    }

    $stats = [
      'total_documents' => Document::count(),
      'total_study_materials' => StudyMaterial::count(),
      'total_flashcards' => StudyMaterial::where('type', 'flashcard')->count(),
      'avg_session_time' => FlashcardReview::avg('study_time') ?: 0,
      'most_active_day' => $this->getMostActiveDay(),
      'popular_content_types' => $this->getPopularContentTypes()
    ];

    return response()->json($stats);
  }

  private function getMostActiveDay()
  {
    $dayActivity = FlashcardReview::select(
      DB::raw('DAYNAME(reviewed_at) as day'),
      DB::raw('COUNT(*) as count')
    )
      ->groupBy(DB::raw('DAYNAME(reviewed_at)'))
      ->orderBy('count', 'desc')
      ->first();

    return $dayActivity ? $dayActivity->day : 'No data';
  }

  private function getPopularContentTypes()
  {
    return Document::select('content_type', DB::raw('COUNT(*) as count'))
      ->groupBy('content_type')
      ->orderBy('count', 'desc')
      ->take(5)
      ->get()
      ->mapWithKeys(function ($item) {
        return [$item->content_type => $item->count];
      })
      ->toArray();
  }

  /**
   * Get admin profile information
   */
  public function getProfile(Request $request)
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['local_user'])) {
      return response()->json(['error' => 'Unauthorized'], 401);
    }

    $localUser = $supabaseUser['local_user'];

    return response()->json([
      'id' => $localUser->id,
      'name' => $localUser->name,
      'email' => $localUser->email,
      'user_type' => $localUser->user_type,
      'created_at' => $localUser->created_at,
      'updated_at' => $localUser->updated_at
    ]);
  }

  /**
   * Update admin profile
   */
  public function updateProfile(Request $request)
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['local_user'])) {
      return response()->json(['error' => 'Unauthorized'], 401);
    }

    $request->validate([
      'name' => 'required|string|max:255',
      'email' => 'required|email|max:255|unique:users,email,' . $supabaseUser['local_user']->id,
    ]);

    $localUser = $supabaseUser['local_user'];

    $localUser->update([
      'name' => $request->name,
      'email' => $request->email,
    ]);

    return response()->json([
      'message' => 'Profile updated successfully',
      'user' => [
        'id' => $localUser->id,
        'name' => $localUser->name,
        'email' => $localUser->email,
        'user_type' => $localUser->user_type,
      ]
    ]);
  }

  /**
   * Update user role (make user admin or student)
   */
  public function updateUserRole(Request $request)
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['local_user'])) {
      return response()->json(['error' => 'Unauthorized'], 401);
    }

    $request->validate([
      'email' => 'required|email|exists:users,email',
      'user_type' => 'required|in:student,admin'
    ]);

    $targetUser = User::where('email', $request->email)->first();

    if (!$targetUser) {
      return response()->json(['error' => 'User not found'], 404);
    }

    $targetUser->update(['user_type' => $request->user_type]);

    return response()->json([
      'message' => "User role updated to {$request->user_type} successfully",
      'user' => [
        'id' => $targetUser->id,
        'name' => $targetUser->name,
        'email' => $targetUser->email,
        'user_type' => $targetUser->user_type,
      ]
    ]);
  }

  /**
   * Update user details
   */
  public function updateUser(Request $request, $id)
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'Supabase user not found'], 401);
    }

    $request->validate([
      'name' => 'required|string|max:255',
      'email' => 'required|email|unique:users,email,' . $id,
      'user_type' => 'required|in:student,admin',
      'points' => 'nullable|integer|min:0',
    ]);

    $user = User::find($id);
    if (!$user) {
      return response()->json(['error' => 'User not found'], 404);
    }

    $user->update($request->only(['name', 'email', 'user_type', 'points']));

    return response()->json([
      'message' => 'User updated successfully',
      'user' => $user->refresh()
    ]);
  }

  /**
   * Deactivate user (we'll add an is_active field)
   */
  public function deactivateUser(Request $request, $id)
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'Supabase user not found'], 401);
    }

    $user = User::find($id);
    if (!$user) {
      return response()->json(['error' => 'User not found'], 404);
    }

    // For now, we'll mark them with negative points or add a deactivation timestamp
    $user->update(['points' => -1]); // Temporary way to mark as deactivated

    return response()->json([
      'message' => 'User deactivated successfully',
      'user' => $user->refresh()
    ]);
  }

  /**
   * Activate user
   */
  public function activateUser(Request $request, $id)
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'Supabase user not found'], 401);
    }

    $user = User::find($id);
    if (!$user) {
      return response()->json(['error' => 'User not found'], 404);
    }

    // Restore user (set points to 0 if they were -1)
    if ($user->points === -1) {
      $user->update(['points' => 0]);
    }

    return response()->json([
      'message' => 'User activated successfully',
      'user' => $user->refresh()
    ]);
  }

  /**
   * Update admin password via Supabase
   */
  public function updatePassword(Request $request)
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'Supabase user not found'], 401);
    }

    $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
      'current_password' => 'required|string',
      'new_password' => 'required|string|min:8|confirmed',
    ]);

    if ($validator->fails()) {
      return response()->json(['errors' => $validator->errors()], 422);
    }

    // Debug: Check if service role key is set
    $serviceRoleKey = env('SUPABASE_SERVICE_ROLE_KEY');
    if (!$serviceRoleKey || $serviceRoleKey === 'your_service_role_key_here') {
      return response()->json([
        'error' => 'SUPABASE_SERVICE_ROLE_KEY is not properly configured. Please set it in your .env file.'
      ], 500);
    }

    try {
      // First verify current password by attempting to sign in
      $signInResponse = \Illuminate\Support\Facades\Http::withHeaders([
        'apikey' => env('SUPABASE_KEY'),
        'Content-Type' => 'application/json',
      ])->post(env('SUPABASE_URL') . '/auth/v1/token?grant_type=password', [
        'email' => $supabaseUser['email'],
        'password' => $request->current_password,
      ]);

      if (!$signInResponse->successful()) {
        return response()->json(['errors' => ['current_password' => ['Current password is incorrect']]], 422);
      }

      // Update password using Supabase admin API
      $updateResponse = \Illuminate\Support\Facades\Http::withHeaders([
        'apikey' => env('SUPABASE_SERVICE_ROLE_KEY'),
        'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_ROLE_KEY'),
        'Content-Type' => 'application/json',
      ])->put(env('SUPABASE_URL') . '/auth/v1/admin/users/' . $supabaseUser['id'], [
        'password' => $request->new_password,
      ]);

      if (!$updateResponse->successful()) {
        \Illuminate\Support\Facades\Log::error('Admin password update failed', [
          'status' => $updateResponse->status(),
          'response' => $updateResponse->body(),
          'user_id' => $supabaseUser['id']
        ]);
        return response()->json([
          'error' => 'Failed to update password',
          'details' => $updateResponse->body()
        ], 500);
      }

      return response()->json(['message' => 'Password updated successfully']);
    } catch (\Exception $e) {
      \Illuminate\Support\Facades\Log::error('Admin password update error: ' . $e->getMessage());
      return response()->json(['error' => 'Failed to update password'], 500);
    }
  }
}
