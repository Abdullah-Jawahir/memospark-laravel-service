<?php

/**
 * Test Script for Laravel Search Flashcards Integration
 * 
 * This script tests the Laravel endpoints that connect to the FastAPI service.
 * Run this from the Laravel project root directory.
 */

require_once 'vendor/autoload.php';

use App\Services\FastApiService;
use App\Jobs\GenerateSearchFlashcards;
use Illuminate\Support\Facades\Cache;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Laravel Search Flashcards Integration Test ===\n\n";

// Test 1: FastAPI Service Health Check
echo "1. Testing FastAPI Service Health Check...\n";
try {
  $fastApiService = new FastApiService();
  $health = $fastApiService->checkSearchFlashcardsHealth();

  if (isset($health['status']) && $health['status'] === 'healthy') {
    echo "✓ FastAPI service is healthy\n";
    echo "  - Service: {$health['service']}\n";
    echo "  - Flashcard Generator: {$health['flashcard_generator']}\n";
    echo "  - Model Manager: {$health['model_manager']}\n\n";
  } else {
    echo "✗ FastAPI service health check failed\n";
    echo "  Response: " . json_encode($health) . "\n\n";
  }
} catch (Exception $e) {
  echo "✗ FastAPI service health check error: " . $e->getMessage() . "\n\n";
}

// Test 2: Get Suggested Topics
echo "2. Testing Suggested Topics Retrieval...\n";
try {
  $topics = $fastApiService->getSuggestedTopics();

  if (is_array($topics) && count($topics) > 0) {
    echo "✓ Retrieved " . count($topics) . " suggested topics\n";
    echo "  Sample topics: " . implode(', ', array_slice($topics, 0, 5)) . "...\n\n";
  } else {
    echo "✗ Failed to retrieve suggested topics\n";
    echo "  Response: " . json_encode($topics) . "\n\n";
  }
} catch (Exception $e) {
  echo "✗ Suggested topics error: " . $e->getMessage() . "\n\n";
}

// Test 3: Test Job Dispatch (without actually running the job)
echo "3. Testing Job Dispatch...\n";
try {
  $jobId = 'test-' . uniqid();
  $topic = 'Test Topic';
  $description = 'Test description for testing purposes';
  $difficulty = 'beginner';
  $count = 5;
  $userId = 'test-user-123';

  // Create job instance (don't dispatch to avoid actual processing)
  $job = new GenerateSearchFlashcards($jobId, $topic, $description, $difficulty, $count, $userId);

  echo "✓ Job instance created successfully\n";
  echo "  - Job ID: {$jobId}\n";
  echo "  - Topic: {$topic}\n";
  echo "  - Difficulty: {$difficulty}\n";
  echo "  - Count: {$count}\n";
  echo "  - User ID: {$userId}\n\n";

  // Test job tags
  $tags = $job->tags();
  echo "  - Job Tags: " . implode(', ', $tags) . "\n\n";
} catch (Exception $e) {
  echo "✗ Job creation error: " . $e->getMessage() . "\n\n";
}

// Test 4: Test Cache Operations
echo "4. Testing Cache Operations...\n";
try {
  $testKey = 'search_flashcards_job_test_cache';
  $testData = [
    'status' => 'testing',
    'message' => 'Test cache data',
    'topic' => 'Test Topic',
    'timestamp' => now()->toISOString()
  ];

  // Store test data
  Cache::put($testKey, $testData, now()->addMinutes(5));
  echo "✓ Test data stored in cache\n";

  // Retrieve test data
  $retrievedData = Cache::get($testKey);
  if ($retrievedData && $retrievedData['status'] === 'testing') {
    echo "✓ Test data retrieved from cache successfully\n";
    echo "  - Status: {$retrievedData['status']}\n";
    echo "  - Topic: {$retrievedData['topic']}\n";
  } else {
    echo "✗ Failed to retrieve test data from cache\n";
  }

  // Clean up
  Cache::forget($testKey);
  echo "✓ Test cache data cleaned up\n\n";
} catch (Exception $e) {
  echo "✗ Cache operations error: " . $e->getMessage() . "\n\n";
}

// Test 5: Test FastAPI Service Methods
echo "5. Testing FastAPI Service Methods...\n";
try {
  // Test generateSearchFlashcards method (this will actually call FastAPI)
  echo "  Testing generateSearchFlashcards method...\n";

  // Note: This is a real API call, so it might take time
  $result = $fastApiService->generateSearchFlashcards(
    'Python Programming',
    'Basic concepts and syntax',
    'beginner',
    3
  );

  if (isset($result['flashcards']) && is_array($result['flashcards'])) {
    echo "✓ Flashcard generation successful\n";
    echo "  - Topic: {$result['topic']}\n";
    echo "  - Flashcards generated: " . count($result['flashcards']) . "\n";
    echo "  - Difficulty: {$result['difficulty']}\n";

    // Show first flashcard
    if (count($result['flashcards']) > 0) {
      $firstCard = $result['flashcards'][0];
      echo "  - Sample flashcard:\n";
      echo "    Q: {$firstCard['question']}\n";
      echo "    A: {$firstCard['answer']}\n";
    }
  } else {
    echo "✗ Flashcard generation failed\n";
    echo "  Response: " . json_encode($result) . "\n";
  }
} catch (Exception $e) {
  echo "✗ FastAPI service test error: " . $e->getMessage() . "\n";
  echo "  This might be expected if FastAPI service is not running\n";
}

echo "\n=== Test Summary ===\n";
echo "The Laravel integration with FastAPI search flashcards has been tested.\n";
echo "If all tests passed, your integration is working correctly.\n";
echo "\nNext steps:\n";
echo "1. Ensure your queue worker is running: php artisan queue:work\n";
echo "2. Test the actual API endpoints with proper authentication\n";
echo "3. Monitor job processing and status updates\n";
echo "\nFor detailed API usage, see: SEARCH_FLASHCARDS_LARAVEL_API.md\n";
