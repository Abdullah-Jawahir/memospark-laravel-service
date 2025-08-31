<?php

/**
 * Simple Health Check Test
 * 
 * This script tests just the health check endpoint to ensure it's working.
 */

$baseUrl = 'http://localhost:8000/api';

echo "🧪 Testing Health Check Endpoint\n";
echo "================================\n\n";

// Test health check without authentication
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/search-flashcards/health');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "URL: {$baseUrl}/search-flashcards/health\n";
echo "HTTP Code: {$httpCode}\n";

if ($error) {
  echo "❌ cURL Error: {$error}\n";
} else {
  echo "Response: {$response}\n\n";

  if ($httpCode === 200) {
    echo "✅ Health check passed!\n";
    $data = json_decode($response, true);
    if ($data && isset($data['data']['status'])) {
      echo "Service Status: {$data['data']['status']}\n";
    }
  } else {
    echo "❌ Health check failed with HTTP {$httpCode}\n";
  }
}

echo "\n";
echo "If this fails, make sure:\n";
echo "1. Laravel service is running: php artisan serve\n";
echo "2. FastAPI service is running: python -m uvicorn app.main:app --reload --port 8001\n";
echo "3. Database is accessible\n";
