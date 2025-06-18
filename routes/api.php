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

// Document routes
Route::middleware(['throttle:api', SupabaseAuth::class])->group(function () {
  Route::post('/documents/upload', [DocumentController::class, 'upload']);
  Route::get('/documents/{id}/status', [DocumentController::class, 'status']);
});

Route::post('/flashcards/process', [FlashcardController::class, 'processFile']);
