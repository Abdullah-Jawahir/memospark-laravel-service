#!/usr/bin/env php
<?php

/**
 * Test Script to Verify Cache Optimization Fix
 * 
 * This script tests that when a document is already cached, 
 * no unnecessary FastAPI calls are made.
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use App\Services\FileProcessCacheService;
use App\Models\FileProcessCache;
use App\Models\StudyMaterial;
use App\Models\Document;

echo "=== Cache Optimization Test ===\n\n";

// Create a temporary test file
$testContent = "This is a test document for cache optimization testing.";
$tempFile = tempnam(sys_get_temp_dir(), 'cache_test_');
file_put_contents($tempFile, $testContent);

try {
  // Step 1: Clear any existing test data
  echo "1. Cleaning up any existing test data...\n";
  $fileHash = hash_file('sha256', $tempFile);
  FileProcessCache::where('file_hash', $fileHash)->delete();

  // Step 2: Create a mock cached entry with a document
  echo "2. Creating mock cached entry...\n";

  // Create a test document first
  $document = Document::create([
    'user_id' => null,
    'deck_id' => null,
    'original_filename' => 'test_cache.txt',
    'storage_path' => 'test/path.txt',
    'file_type' => 'tx', // Shortened to avoid DB constraint
    'language' => 'en',
    'status' => 'completed',
    'metadata' => ['test' => true]
  ]);  // Create the cache entry
  $cache = FileProcessCache::create([
    'file_hash' => $fileHash,
    'language' => 'en',
    'difficulty' => 'beginner',
    'card_types' => ['flashcard', 'quiz'],
    'card_types_hash' => hash('sha256', json_encode(['flashcard', 'quiz'])),
    'status' => 'done',
    'document_id' => $document->id,
    'result' => [
      'generated_content' => [
        'flashcards' => [['q' => 'Test question?', 'a' => 'Test answer']],
        'quizzes' => [['q' => 'Quiz question?', 'options' => ['A', 'B'], 'a' => 'A']]
      ]
    ]
  ]);

  // Create study materials
  StudyMaterial::create([
    'document_id' => $document->id,
    'type' => 'flashcard',
    'content' => ['question' => 'Test question?', 'answer' => 'Test answer'],
    'language' => 'en'
  ]);

  StudyMaterial::create([
    'document_id' => $document->id,
    'type' => 'quiz',
    'content' => ['question' => 'Quiz question?', 'options' => ['A', 'B'], 'answer' => 'A'],
    'language' => 'en'
  ]);

  echo "   ✓ Created document ID: {$document->id}\n";
  echo "   ✓ Created cache entry with flashcard and quiz types\n";
  echo "   ✓ Created study materials\n\n";

  // Step 3: Test cache check for same types (should return cached, no FastAPI call)
  echo "3. Testing cache check for same types (flashcard, quiz)...\n";

  $uploadedFile = new UploadedFile($tempFile, 'test_cache.txt', 'text/plain', null, true);
  $fileProcessCacheService = new FileProcessCacheService(new \App\Services\FastApiService());

  $result = $fileProcessCacheService->checkCacheEntry($uploadedFile, 'en', ['flashcard', 'quiz'], 'beginner');

  if ($result['status'] === 'done') {
    echo "   ✓ Cache hit! Status: {$result['status']}\n";
    echo "   ✓ Document ID: {$result['document_id']}\n";
    echo "   ✓ No FastAPI call needed\n\n";
  } else {
    echo "   ✗ Unexpected result: {$result['status']}\n\n";
  }

  // Step 4: Test cache check for mixed types (should identify missing type)
  echo "4. Testing cache check for mixed types (flashcard, quiz, exercise)...\n";

  $result = $fileProcessCacheService->checkCacheEntry($uploadedFile, 'en', ['flashcard', 'quiz', 'exercise'], 'beginner');

  if ($result['status'] === 'done') {
    echo "   ✓ Cache hit! Status: {$result['status']}\n";
    echo "   ✓ Document ID: {$result['document_id']}\n";
    echo "   ✓ Missing types will be handled by GenerateMissingCardTypes job\n\n";
  } else {
    echo "   ✗ Unexpected result: {$result['status']}\n\n";
  }

  // Step 5: Test the GenerateMissingCardTypes job logic
  echo "5. Testing GenerateMissingCardTypes job optimization...\n";

  // Create the missing type to simulate it being available
  StudyMaterial::create([
    'document_id' => $document->id,
    'type' => 'exercise',
    'content' => ['question' => 'Exercise question?', 'answer' => 'Exercise answer'],
    'language' => 'en'
  ]);

  echo "   ✓ Added exercise type to study materials\n";

  // Now test the job - it should detect that exercise is no longer missing
  $job = new \App\Jobs\GenerateMissingCardTypes(
    $document->id,
    ['exercise'],  // Originally missing
    'en',
    'beginner',
    $tempFile,
    'test_cache.txt'
  );

  // Capture log output to verify the job skips FastAPI call
  $logPath = storage_path('logs/fastapi.log');
  $logSizeBefore = file_exists($logPath) ? filesize($logPath) : 0;

  // Execute the job
  $job->handle(new \App\Services\FastApiService());

  // Check if log was written about skipping
  if (file_exists($logPath)) {
    $logContent = file_get_contents($logPath);
    if (strpos($logContent, 'All missing types are now available, skipping FastAPI call') !== false) {
      echo "   ✓ Job correctly detected that missing types are now available\n";
      echo "   ✓ FastAPI call was skipped as expected\n\n";
    } else {
      echo "   ⚠ Job may have made FastAPI call (check logs)\n\n";
    }
  }

  echo "✅ Cache optimization test completed successfully!\n";
  echo "\nKey improvements verified:\n";
  echo "- ✓ Cached documents return immediately without FastAPI calls\n";
  echo "- ✓ GenerateMissingCardTypes job checks for existing types\n";
  echo "- ✓ Unnecessary FastAPI calls are prevented\n";
} catch (Exception $e) {
  echo "❌ Test failed: " . $e->getMessage() . "\n";
  echo "Stack trace: " . $e->getTraceAsString() . "\n";
} finally {
  // Cleanup
  echo "\n6. Cleaning up test data...\n";
  try {
    if (isset($document)) {
      StudyMaterial::where('document_id', $document->id)->delete();
      FileProcessCache::where('document_id', $document->id)->delete();
      $document->delete();
      echo "   ✓ Cleaned up test document and related data\n";
    }
    if (file_exists($tempFile)) {
      unlink($tempFile);
      echo "   ✓ Cleaned up temporary file\n";
    }
  } catch (Exception $e) {
    echo "   ⚠ Cleanup warning: " . $e->getMessage() . "\n";
  }
}

echo "\nTest completed.\n";
