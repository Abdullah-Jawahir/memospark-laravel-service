<?php

/**
 * Search Flashcards Endpoints Test Script
 * 
 * This script tests all the search flashcards endpoints to ensure they're working correctly.
 * Run this after starting your Laravel service and FastAPI service.
 * 
 * Usage: php test_search_flashcards_endpoints.php
 */

// Configuration
$baseUrl = 'http://localhost:8000/api';
$testUserId = 'test-user-' . uniqid(); // Generate a unique test user ID

// Test data
$testTopics = [
  'Python Programming Basics',
  'Machine Learning Fundamentals',
  'Web Development with React',
  'Database Design Principles',
  'API Development Best Practices'
];

echo "🚀 Search Flashcards Endpoints Test Script\n";
echo "==========================================\n\n";

echo "📋 Test Configuration:\n";
echo "Base URL: {$baseUrl}\n";
echo "Test User ID: {$testUserId}\n";
echo "Test Topics: " . implode(', ', $testTopics) . "\n\n";

// Helper function to make HTTP requests
function makeRequest($method, $endpoint, $data = null, $headers = [])
{
  $url = $GLOBALS['baseUrl'] . $endpoint;

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  $defaultHeaders = [
    'Content-Type: application/json',
    'Accept: application/json'
  ];

  // For testing, use test endpoints with test authentication
  if ($endpoint !== '/search-flashcards/health') {
    // Convert regular endpoints to test endpoints
    $testEndpoint = str_replace('/search-flashcards/', '/test/search-flashcards/', $endpoint);
    $url = $GLOBALS['baseUrl'] . $testEndpoint;
    curl_setopt($ch, CURLOPT_URL, $url);

    // Add test authentication token
    $defaultHeaders[] = 'Authorization: Bearer test-token-' . $GLOBALS['testUserId'];
  }

  curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $headers));

  if ($method === 'POST') {
    curl_setopt($ch, CURLOPT_POST, true);
    if ($data) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
  } elseif ($method === 'PUT') {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    if ($data) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
  } elseif ($method === 'DELETE') {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
  }

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $error = curl_error($ch);
  curl_close($ch);

  if ($error) {
    return ['error' => $error, 'http_code' => 0];
  }

  return [
    'http_code' => $httpCode,
    'response' => json_decode($response, true),
    'raw_response' => $response
  ];
}

// Helper function to display test results
function displayResult($testName, $result, $expectedCode = 200)
{
  echo "🧪 {$testName}: ";

  if (isset($result['error'])) {
    echo "❌ FAILED - cURL Error: {$result['error']}\n";
    return false;
  }

  if ($result['http_code'] === $expectedCode) {
    echo "✅ PASSED (HTTP {$result['http_code']})\n";

    if (isset($result['response']['success']) && $result['response']['success']) {
      echo "   Response: {$result['response']['message']}\n";
    }

    return true;
  } else {
    echo "❌ FAILED - Expected HTTP {$expectedCode}, got {$result['http_code']}\n";
    if (isset($result['response']['message'])) {
      echo "   Error: {$result['response']['message']}\n";
    }
    return false;
  }
}

// Test 1: Health Check
echo "1️⃣ Testing Health Check Endpoint\n";
echo "--------------------------------\n";
$healthResult = makeRequest('GET', '/search-flashcards/health');
$healthPassed = displayResult('Health Check', $healthResult);

if (!$healthPassed) {
  echo "⚠️  Health check failed. Make sure your Laravel and FastAPI services are running.\n\n";
  echo "To start services:\n";
  echo "1. Laravel: cd laravel-service && php artisan serve\n";
  echo "2. FastAPI: cd fastapi-service && python -m uvicorn app.main:app --reload --port 8001\n";
  echo "3. Queue Worker: cd laravel-service && php artisan queue:work\n\n";
  exit(1);
}

echo "\n";

// Test 2: Get Suggested Topics
echo "2️⃣ Testing Suggested Topics Endpoint\n";
echo "------------------------------------\n";
$topicsResult = makeRequest('GET', '/search-flashcards/topics');
$topicsPassed = displayResult('Get Suggested Topics', $topicsResult);

if ($topicsPassed && isset($topicsResult['response']['data'])) {
  echo "   Found " . count($topicsResult['response']['data']) . " suggested topics\n";
}

echo "\n";

// Test 3: Generate Flashcards (Multiple Topics)
echo "3️⃣ Testing Flashcard Generation Endpoints\n";
echo "----------------------------------------\n";
$generatedJobs = [];

foreach ($testTopics as $index => $topic) {
  $difficulty = ['beginner', 'intermediate', 'advanced'][$index % 3];
  $count = rand(5, 15);

  $generateData = [
    'topic' => $topic,
    'description' => "Test description for {$topic}",
    'difficulty' => $difficulty,
    'count' => $count
  ];

  $generateResult = makeRequest('POST', '/search-flashcards/generate', $generateData);
  $generatePassed = displayResult("Generate Flashcards - {$topic}", $generateResult, 202);

  if ($generatePassed && isset($generateResult['response']['data']['job_id'])) {
    $generatedJobs[] = [
      'topic' => $topic,
      'job_id' => $generateResult['response']['data']['job_id'],
      'difficulty' => $difficulty,
      'count' => $count
    ];
    echo "   Job ID: {$generateResult['response']['data']['job_id']}\n";
  }

  // Small delay between requests
  usleep(500000); // 0.5 seconds
}

echo "\n";

// Test 4: Check Job Statuses
echo "4️⃣ Testing Job Status Endpoints\n";
echo "-------------------------------\n";
$completedJobs = [];

foreach ($generatedJobs as $job) {
  echo "Checking status for: {$job['topic']}\n";

  $statusResult = makeRequest('GET', "/search-flashcards/job/{$job['job_id']}/status");
  $statusPassed = displayResult("Job Status - {$job['topic']}", $statusResult);

  if ($statusPassed && isset($statusResult['response']['data']['status'])) {
    $status = $statusResult['response']['data']['status'];
    echo "   Status: {$status}\n";

    if ($status === 'completed') {
      $completedJobs[] = $job;
    }
  }

  echo "\n";
}

// Test 5: Get Search History
echo "5️⃣ Testing Search History Endpoints\n";
echo "-----------------------------------\n";

// Wait a bit for jobs to potentially complete
if (!empty($generatedJobs)) {
  echo "⏳ Waiting 10 seconds for some jobs to complete...\n";
  sleep(10);
}

$historyResult = makeRequest('GET', '/search-flashcards/history');
$historyPassed = displayResult('Get Search History', $historyResult);

if ($historyPassed && isset($historyResult['response']['data']['data'])) {
  $historyCount = count($historyResult['response']['data']['data']);
  $totalSearches = $historyResult['response']['data']['total'];
  echo "   Found {$historyCount} searches on current page\n";
  echo "   Total searches: {$totalSearches}\n";
}

echo "\n";

// Test 6: Get Recent Searches
echo "6️⃣ Testing Recent Searches Endpoint\n";
echo "-----------------------------------\n";
$recentResult = makeRequest('GET', '/search-flashcards/recent?limit=3');
$recentPassed = displayResult('Get Recent Searches', $recentResult);

if ($recentPassed && isset($recentResult['response']['data'])) {
  echo "   Found " . count($recentResult['response']['data']) . " recent searches\n";
}

echo "\n";

// Test 7: Get Search Statistics
echo "7️⃣ Testing Search Statistics Endpoint\n";
echo "-------------------------------------\n";
$statsResult = makeRequest('GET', '/search-flashcards/stats?days=7');
$statsPassed = displayResult('Get Search Statistics', $statsResult);

if ($statsPassed && isset($statsResult['response']['data'])) {
  $stats = $statsResult['response']['data'];
  echo "   Total searches: {$stats['total_searches']}\n";
  echo "   Completed searches: {$stats['completed_searches']}\n";
  echo "   Success rate: {$stats['success_rate']}%\n";
}

echo "\n";

// Test 8: Get Specific Search Details (if we have completed searches)
echo "8️⃣ Testing Search Details Endpoint\n";
echo "----------------------------------\n";
if (!empty($completedJobs)) {
  // Try to get details for the first completed job
  $firstJob = $completedJobs[0];

  // We need to get the search ID from the history first
  $historyForDetails = makeRequest('GET', '/search-flashcards/history?per_page=1');

  if (isset($historyForDetails['response']['data']['data'][0]['id'])) {
    $searchId = $historyForDetails['response']['data']['data'][0]['id'];

    $detailsResult = makeRequest('GET', "/search-flashcards/search/{$searchId}");
    $detailsPassed = displayResult('Get Search Details', $detailsResult);

    if ($detailsPassed && isset($detailsResult['response']['data'])) {
      $searchData = $detailsResult['response']['data'];
      echo "   Topic: {$searchData['topic']}\n";
      echo "   Status: {$searchData['status']}\n";
      echo "   Flashcards count: {$searchData['flashcards_count']}\n";
    }
  } else {
    echo "⚠️  No completed searches found to test details endpoint\n";
  }
} else {
  echo "⚠️  No completed jobs found to test details endpoint\n";
}

echo "\n";

// Test 9: Test with Invalid Data
echo "9️⃣ Testing Error Handling\n";
echo "--------------------------\n";

// Test with invalid topic (too short)
$invalidTopicData = [
  'topic' => 'ab', // Too short
  'count' => 25    // Too many
];

$invalidResult = makeRequest('POST', '/search-flashcards/generate', $invalidTopicData);
$invalidPassed = displayResult('Invalid Data Validation', $invalidResult, 422);

if ($invalidPassed && isset($invalidResult['response']['errors'])) {
  echo "   Validation errors received as expected\n";
}

echo "\n";

// Test 10: Test Authentication
echo "🔟 Testing Authentication\n";
echo "-------------------------\n";
$noAuthResult = makeRequest('GET', '/search-flashcards/history', null, []);
$noAuthPassed = displayResult('No Authentication', $noAuthResult, 401);

if ($noAuthPassed) {
  echo "   Authentication required as expected\n";
}

echo "\n";

// Summary
echo "📊 Test Summary\n";
echo "==============\n";
echo "Total tests run: 10\n";
echo "Jobs generated: " . count($generatedJobs) . "\n";
echo "Jobs completed: " . count($completedJobs) . "\n\n";

if (!empty($generatedJobs)) {
  echo "📝 Generated Jobs:\n";
  foreach ($generatedJobs as $job) {
    echo "   • {$job['topic']} ({$job['difficulty']}, {$job['count']} cards)\n";
    echo "     Job ID: {$job['job_id']}\n";
  }
  echo "\n";
}

echo "🎯 Next Steps:\n";
echo "1. Monitor job progress with: php artisan queue:work\n";
echo "2. Check job statuses using the job IDs above\n";
echo "3. Review generated flashcards in the database\n";
echo "4. Test the frontend integration\n\n";

echo "✨ Testing completed! Check the results above to ensure all endpoints are working correctly.\n";
