<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Deck;
use App\Models\Document;
use App\Models\FlashcardReview;
use App\Models\StudyMaterial;
use App\Models\UserGoal;
use App\Models\GoalType;
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
   * Deactivate user (using email_verified_at null to mark deactivated)
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

    // Mark user as deactivated by setting email_verified_at to null
    $user->update([
      'email_verified_at' => null
    ]);

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

    // Restore user by setting email_verified_at to current timestamp
    $user->update([
      'email_verified_at' => now()
    ]);

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
      ])->post(rtrim(config('services.supabase.url'), '/') . '/auth/v1/token?grant_type=password', [
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
      ])->put(rtrim(config('services.supabase.url'), '/') . '/auth/v1/admin/users/' . $supabaseUser['id'], [
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

  /**
   * Get goals overview for admin dashboard
   */
  public function goalsOverview(Request $request)
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'Supabase user not found'], 401);
    }

    try {
      // Total users with goals set
      $totalUsersWithGoals = UserGoal::distinct('user_id')->count();

      // Total users without goals
      $totalUsersWithoutGoals = User::whereNotIn('id', function ($query) {
        $query->select('user_id')->from('user_goals')->distinct();
      })->count();

      // Average daily goal
      $averageDailyGoal = UserGoal::avg('daily_goal');

      // Most common goal range
      $goalDistribution = UserGoal::select(
        DB::raw('
          CASE 
            WHEN daily_goal <= 25 THEN "1-25"
            WHEN daily_goal <= 50 THEN "26-50"
            WHEN daily_goal <= 100 THEN "51-100"
            ELSE "100+"
          END as goal_range
        '),
        DB::raw('COUNT(*) as count')
      )
        ->groupBy('goal_range')
        ->orderBy('count', 'desc')
        ->get();

      // Recent goal updates (last 30 days)
      $recentGoalUpdates = UserGoal::where('updated_at', '>=', Carbon::now()->subDays(30))->count();

      return response()->json([
        'total_users_with_goals' => $totalUsersWithGoals,
        'total_users_without_goals' => $totalUsersWithoutGoals,
        'average_daily_goal' => round($averageDailyGoal, 1),
        'goal_distribution' => $goalDistribution,
        'recent_goal_updates' => $recentGoalUpdates,
        'total_users' => User::count()
      ]);
    } catch (\Exception $e) {
      \Illuminate\Support\Facades\Log::error('Goals overview error: ' . $e->getMessage());
      return response()->json(['error' => 'Failed to fetch goals overview'], 500);
    }
  }

  /**
   * Get detailed goal statistics
   */
  public function goalStatistics(Request $request)
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'Supabase user not found'], 401);
    }

    try {
      // Goals by user type
      $goalsByUserType = User::leftJoin('user_goals', 'users.id', '=', 'user_goals.user_id')
        ->select(
          'users.user_type',
          DB::raw('COUNT(user_goals.id) as goals_count'),
          DB::raw('AVG(user_goals.daily_goal) as avg_goal')
        )
        ->groupBy('users.user_type')
        ->get();

      // Goal trends over time (last 6 months)
      $goalTrends = UserGoal::select(
        DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
        DB::raw('COUNT(*) as goals_created'),
        DB::raw('AVG(daily_goal) as avg_goal')
      )
        ->where('created_at', '>=', Carbon::now()->subMonths(6))
        ->groupBy('month')
        ->orderBy('month')
        ->get();

      // Most active users (top 10 by goals set)
      $activeUsers = User::leftJoin('user_goals', 'users.id', '=', 'user_goals.user_id')
        ->select('users.name', 'users.email', 'user_goals.daily_goal', 'user_goals.updated_at')
        ->whereNotNull('user_goals.id')
        ->orderBy('user_goals.daily_goal', 'desc')
        ->limit(10)
        ->get();

      return response()->json([
        'goals_by_user_type' => $goalsByUserType,
        'goal_trends' => $goalTrends,
        'active_users' => $activeUsers
      ]);
    } catch (\Exception $e) {
      \Illuminate\Support\Facades\Log::error('Goal statistics error: ' . $e->getMessage());
      return response()->json(['error' => 'Failed to fetch goal statistics'], 500);
    }
  }

  /**
   * Update default goals (for future implementation of default recommendations)
   */
  public function updateDefaultGoals(Request $request)
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'Supabase user not found'], 401);
    }

    $request->validate([
      'student_default' => 'required|integer|min:1|max:200',
      'admin_default' => 'required|integer|min:1|max:200',
    ]);

    try {
      // Update the default value for Daily Flashcards goal type
      // We'll use the student_default as the main default since students are the primary users
      $dailyFlashcardsGoal = GoalType::where('name', 'Daily Flashcards')->first();

      if ($dailyFlashcardsGoal) {
        $dailyFlashcardsGoal->update(['default_value' => $request->student_default]);
      }

      // Store admin default in a way we can retrieve it
      // For now, we'll create or update a special "Admin Daily Flashcards" goal type
      $adminGoalType = GoalType::firstOrCreate(
        ['name' => 'Admin Daily Flashcards'],
        [
          'id' => \Illuminate\Support\Str::uuid(),
          'description' => 'Daily flashcard goal for admin users',
          'unit' => 'cards',
          'category' => 'study',
          'is_active' => false, // Hidden from general selection
          'default_value' => $request->admin_default,
          'min_value' => 1,
          'max_value' => 200
        ]
      );

      if ($adminGoalType->wasRecentlyCreated === false) {
        $adminGoalType->update(['default_value' => $request->admin_default]);
      }

      return response()->json([
        'message' => 'Default goals updated successfully',
        'defaults' => [
          'student_default' => $request->student_default,
          'admin_default' => $request->admin_default
        ]
      ]);
    } catch (\Exception $e) {
      \Illuminate\Support\Facades\Log::error('Update default goals error: ' . $e->getMessage());
      return response()->json(['error' => 'Failed to update default goals'], 500);
    }
  }

  /**
   * Get default goal values
   */
  public function getDefaultGoals(Request $request)
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'Supabase user not found'], 401);
    }

    try {
      $studentDefault = GoalType::where('name', 'Daily Flashcards')->value('default_value') ?? 50;
      $adminDefault = GoalType::where('name', 'Admin Daily Flashcards')->value('default_value') ?? 25;

      return response()->json([
        'student_default' => $studentDefault,
        'admin_default' => $adminDefault
      ]);
    } catch (\Exception $e) {
      \Illuminate\Support\Facades\Log::error('Get default goals error: ' . $e->getMessage());
      return response()->json(['error' => 'Failed to fetch default goals'], 500);
    }
  }

  /**
   * Get all goal types
   */
  public function getGoalTypes(Request $request)
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'Supabase user not found'], 401);
    }

    try {
      $goalTypes = GoalType::orderBy('category')
        ->orderBy('name')
        ->get();

      return response()->json($goalTypes);
    } catch (\Exception $e) {
      \Illuminate\Support\Facades\Log::error('Get goal types error: ' . $e->getMessage());
      return response()->json(['error' => 'Failed to fetch goal types'], 500);
    }
  }

  /**
   * Create a new goal type
   */
  public function createGoalType(Request $request)
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'Supabase user not found'], 401);
    }

    $request->validate([
      'name' => 'required|string|max:255',
      'description' => 'nullable|string',
      'unit' => 'required|string|max:50',
      'category' => 'required|in:study,engagement,achievement,time',
      'default_value' => 'required|integer|min:0',
      'min_value' => 'required|integer|min:0',
      'max_value' => 'required|integer|min:1'
    ]);

    try {
      $goalType = GoalType::create([
        'name' => $request->name,
        'description' => $request->description,
        'unit' => $request->unit,
        'category' => $request->category,
        'default_value' => $request->default_value,
        'min_value' => $request->min_value,
        'max_value' => $request->max_value,
        'is_active' => true
      ]);

      return response()->json($goalType, 201);
    } catch (\Exception $e) {
      \Illuminate\Support\Facades\Log::error('Create goal type error: ' . $e->getMessage());
      return response()->json(['error' => 'Failed to create goal type'], 500);
    }
  }

  /**
   * Update a goal type
   */
  public function updateGoalType(Request $request, $id)
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'Supabase user not found'], 401);
    }

    $request->validate([
      'name' => 'required|string|max:255',
      'description' => 'nullable|string',
      'unit' => 'required|string|max:50',
      'category' => 'required|in:study,engagement,achievement,time',
      'default_value' => 'required|integer|min:0',
      'min_value' => 'required|integer|min:0',
      'max_value' => 'required|integer|min:1'
    ]);

    try {
      $goalType = GoalType::findOrFail($id);

      $goalType->update([
        'name' => $request->name,
        'description' => $request->description,
        'unit' => $request->unit,
        'category' => $request->category,
        'default_value' => $request->default_value,
        'min_value' => $request->min_value,
        'max_value' => $request->max_value
      ]);

      return response()->json($goalType);
    } catch (\Exception $e) {
      \Illuminate\Support\Facades\Log::error('Update goal type error: ' . $e->getMessage());
      return response()->json(['error' => 'Failed to update goal type'], 500);
    }
  }

  /**
   * Delete a goal type
   */
  public function deleteGoalType(Request $request, $id)
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'Supabase user not found'], 401);
    }

    try {
      $goalType = GoalType::findOrFail($id);

      // Check if there are user goals using this type
      $userGoalsCount = UserGoal::where('goal_type_id', $id)->count();

      if ($userGoalsCount > 0) {
        // Instead of deleting, deactivate the goal type
        $goalType->update(['is_active' => false]);
        return response()->json([
          'message' => 'Goal type deactivated (cannot delete while in use)',
          'deactivated' => true
        ]);
      }

      $goalType->delete();
      return response()->json(['message' => 'Goal type deleted successfully']);
    } catch (\Exception $e) {
      \Illuminate\Support\Facades\Log::error('Delete goal type error: ' . $e->getMessage());
      return response()->json(['error' => 'Failed to delete goal type'], 500);
    }
  }

  /**
   * Get all user goals with relationships
   */
  public function getUserGoals(Request $request)
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'Supabase user not found'], 401);
    }

    try {
      $userGoals = UserGoal::with(['user:id,name,email,user_type', 'goalType'])
        ->whereNotNull('goal_type_id')
        ->orderBy('created_at', 'desc')
        ->get();

      return response()->json($userGoals);
    } catch (\Exception $e) {
      \Illuminate\Support\Facades\Log::error('Get user goals error: ' . $e->getMessage());
      return response()->json(['error' => 'Failed to fetch user goals'], 500);
    }
  }

  /**
   * Create a user goal
   */
  public function createUserGoal(Request $request)
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'Supabase user not found'], 401);
    }

    $request->validate([
      'user_id' => 'required|exists:users,id',
      'goal_type_id' => 'required|exists:goal_types,id',
      'target_value' => 'required|integer|min:1'
    ]);

    try {
      // Check if user already has this goal type
      $existingGoal = UserGoal::where('user_id', $request->user_id)
        ->where('goal_type_id', $request->goal_type_id)
        ->first();

      if ($existingGoal) {
        return response()->json(['error' => 'User already has this goal type'], 422);
      }

      $userGoal = UserGoal::create([
        'user_id' => $request->user_id,
        'goal_type_id' => $request->goal_type_id,
        'target_value' => $request->target_value,
        'current_value' => 0,
        'is_active' => true
      ]);

      // Load relationships for response
      $userGoal->load(['user:id,name,email,user_type', 'goalType']);

      return response()->json($userGoal, 201);
    } catch (\Exception $e) {
      \Illuminate\Support\Facades\Log::error('Create user goal error: ' . $e->getMessage());
      return response()->json(['error' => 'Failed to create user goal'], 500);
    }
  }

  /**
   * Update a user goal
   */
  public function updateUserGoal(Request $request, $id)
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'Supabase user not found'], 401);
    }

    $request->validate([
      'target_value' => 'required|integer|min:1'
    ]);

    try {
      $userGoal = UserGoal::findOrFail($id);

      $userGoal->update([
        'target_value' => $request->target_value
      ]);

      $userGoal->load(['user:id,name,email,user_type', 'goalType']);
      return response()->json($userGoal);
    } catch (\Exception $e) {
      \Illuminate\Support\Facades\Log::error('Update user goal error: ' . $e->getMessage());
      return response()->json(['error' => 'Failed to update user goal'], 500);
    }
  }

  /**
   * Delete a user goal
   */
  public function deleteUserGoal(Request $request, $id)
  {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['id'])) {
      return response()->json(['error' => 'Supabase user not found'], 401);
    }

    try {
      $userGoal = UserGoal::findOrFail($id);
      $userGoal->delete();

      return response()->json(['message' => 'User goal deleted successfully']);
    } catch (\Exception $e) {
      \Illuminate\Support\Facades\Log::error('Delete user goal error: ' . $e->getMessage());
      return response()->json(['error' => 'Failed to delete user goal'], 500);
    }
  }
}
