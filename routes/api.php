<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FlashcardController;
use App\Http\Controllers\FlashcardReviewController;
use App\Http\Middleware\SupabaseAuth;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeckController;
use App\Http\Controllers\UserGoalController;
use App\Http\Controllers\UserAchievementController;
use App\Http\Controllers\StudyTrackingController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ProfileController;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
  return $request->user();
});

// Document upload route for guest users
Route::middleware(['throttle:api', 'guest.document'])->group(function () {
  Route::post('/guest/documents/upload', [DocumentController::class, 'upload']);
});

// Document upload route for authenticated users
Route::middleware(['throttle:api', SupabaseAuth::class])->group(function () {
  Route::post('/documents/upload', [DocumentController::class, 'upload']);
});

// Document status route - allow both authenticated and guest users
Route::middleware(['throttle:api'])->group(function () {
  Route::get('/documents/{id}/status', [DocumentController::class, 'status']);
});

// Document status route for guest users
Route::middleware(['throttle:api', 'guest.document.status'])->group(function () {
  Route::get('/guest/documents/{id}/status', [DocumentController::class, 'status']);
});

Route::post('/flashcards/process', [FlashcardController::class, 'processFile']);

Route::middleware(['throttle:api', SupabaseAuth::class])->group(function () {
  Route::post('/flashcard-reviews', [FlashcardReviewController::class, 'store']);
  Route::get('/flashcard-reviews', [FlashcardReviewController::class, 'index']);
});

Route::middleware('supabase.auth')->group(function () {
  Route::get('dashboard', [DashboardController::class, 'dashboard']);
  Route::get('dashboard/user-info', [DashboardController::class, 'userInfo']);
  Route::get('dashboard/overview', [DashboardController::class, 'overview']);
  Route::get('dashboard/recent-decks', [DashboardController::class, 'recentDecks']);
  Route::get('dashboard/todays-goal', [DashboardController::class, 'todaysGoal']);
  Route::get('dashboard/achievements', [DashboardController::class, 'achievements']);

  // User profile endpoint - returns role info from our local database
  Route::get('user/profile', function (Request $request) {
    $supabaseUser = $request->get('supabase_user');
    if (!$supabaseUser || !isset($supabaseUser['local_user'])) {
      return response()->json(['error' => 'User not found'], 401);
    }

    $localUser = $supabaseUser['local_user'];

    return response()->json([
      'id' => $localUser->id,
      'full_name' => $localUser->name,
      'email' => $localUser->email,
      'role' => $localUser->user_type, // Use local user_type as role
      'created_at' => $localUser->created_at,
      'updated_at' => $localUser->updated_at,
    ]);
  });

  Route::post('decks', [DeckController::class, 'store']);
  Route::get('decks', [DeckController::class, 'index']);
  Route::get('decks/{deck}/materials', [DeckController::class, 'materials']);
  Route::post('user-goals', [UserGoalController::class, 'store']);
  Route::post('user-achievements', [UserAchievementController::class, 'store']);

  // Study tracking endpoints
  Route::post('study/start-session', [StudyTrackingController::class, 'startSession']);
  Route::post('study/record-review', [StudyTrackingController::class, 'recordReview']);
  Route::get('study/stats', [StudyTrackingController::class, 'getStats']);
  Route::get('study/recent-activity', [StudyTrackingController::class, 'getRecentActivity']);
});

// Test endpoint to verify authentication
Route::get('test-auth', function () {
  return response()->json(['message' => 'Public endpoint working']);
});

// Setup endpoint to make a user admin (only use this once for initial setup)
Route::post('setup-admin', function (Request $request) {
  $request->validate([
    'email' => 'required|email|exists:users,email',
    'setup_key' => 'required|string'
  ]);

  // Simple security check - you should change this key
  if ($request->setup_key !== 'memospark-admin-setup-2025') {
    return response()->json(['error' => 'Invalid setup key'], 403);
  }

  $user = User::where('email', $request->email)->first();
  $user->update(['user_type' => 'admin']);

  return response()->json([
    'message' => 'User has been made admin successfully',
    'user' => [
      'email' => $user->email,
      'user_type' => $user->user_type
    ]
  ]);
});

// Temporary endpoint to fix admin supabase user ID
Route::middleware(['supabase.auth'])->post('fix-admin-supabase-id', function (Request $request) {
  $supabaseUser = $request->get('supabase_user');
  $localUser = $supabaseUser['local_user'] ?? null;

  if ($localUser && $localUser->user_type === 'admin') {
    $realSupabaseId = $supabaseUser['id'];
    $localUser->update(['supabase_user_id' => $realSupabaseId]);

    return response()->json([
      'message' => 'Admin Supabase user ID updated successfully',
      'old_id' => 'admin-supabase-id-placeholder',
      'new_id' => $realSupabaseId
    ]);
  }

  return response()->json(['error' => 'Not an admin user'], 403);
});

// Admin routes - protected by both supabase auth and admin role
Route::middleware(['supabase.auth', 'admin.auth'])->prefix('admin')->group(function () {
  Route::get('/overview', [AdminController::class, 'overview']);
  Route::get('/recent-activity', [AdminController::class, 'recentActivity']);
  Route::get('/user-analytics', [AdminController::class, 'userAnalytics']);
  Route::get('/users', [AdminController::class, 'getUsers']);
  Route::get('/system-stats', [AdminController::class, 'systemStats']);

  // Admin profile management
  Route::get('/profile', [AdminController::class, 'getProfile']);
  Route::put('/profile', [AdminController::class, 'updateProfile']);
  Route::put('/profile/password', [AdminController::class, 'updatePassword']);
  Route::put('/users/role', [AdminController::class, 'updateUserRole']);
  Route::put('/users/{id}', [AdminController::class, 'updateUser']);
  Route::put('/users/{id}/deactivate', [AdminController::class, 'deactivateUser']);
  Route::put('/users/{id}/activate', [AdminController::class, 'activateUser']);
});

// Student profile management routes
Route::middleware(['supabase.auth'])->prefix('profile')->group(function () {
  Route::get('/', [ProfileController::class, 'getProfile']);
  Route::put('/', [ProfileController::class, 'updateProfile']);
  Route::put('/password', [ProfileController::class, 'updatePassword']);
});
