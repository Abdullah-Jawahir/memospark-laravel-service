<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FlashcardController;
use App\Http\Middleware\SupabaseAuth;

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
