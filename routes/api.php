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

  Route::post('decks', [DeckController::class, 'store']);
  Route::post('user-goals', [UserGoalController::class, 'store']);
  Route::post('user-achievements', [UserAchievementController::class, 'store']);
});

// Test endpoint to verify authentication
Route::get('test-auth', function () {
  return response()->json(['message' => 'Public endpoint working']);
});
