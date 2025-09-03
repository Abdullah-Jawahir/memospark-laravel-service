# Search Flashcards Laravel Integration

## Overview

This document describes the Laravel integration for the Search Flashcards feature, which connects your Laravel backend to the FastAPI service for generating educational flashcards from search topics.

## Architecture

```
Frontend → Laravel API → Laravel Jobs → FastAPI Service → AI Models → Flashcards
```

### Components

1. **SearchFlashcardsController** - Handles HTTP requests and job management
2. **GenerateSearchFlashcards Job** - Background job for asynchronous processing
3. **FastApiService** - Service layer for communicating with FastAPI
4. **Cache System** - Stores job status and results
5. **Queue System** - Manages background job processing

## Installation & Setup

### Prerequisites

- Laravel 8+ application
- FastAPI service running (see FastAPI documentation)
- Database queue driver configured
- Cache system configured

### Setup Steps

1. **Copy Files**: Ensure all new files are in place:
   - `app/Jobs/GenerateSearchFlashcards.php`
   - `app/Http/Controllers/SearchFlashcardsController.php`
   - Updated `app/Services/FastApiService.php`

2. **Configure Routes**: Routes are automatically added to `routes/api.php`

3. **Environment Variables**: Set in your `.env` file:
   ```env
   FASTAPI_URL=http://localhost:8001
   QUEUE_CONNECTION=database
   CACHE_DRIVER=file
   ```

4. **Database Queue Setup**:
   ```bash
   php artisan queue:table
   php artisan migrate
   ```

5. **Start Queue Worker**:
   ```bash
   php artisan queue:work
   ```

## API Endpoints

### Base URL
```
http://localhost:8000/api
```

### Available Endpoints

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/search-flashcards/generate` | Start flashcard generation | Yes |
| GET | `/search-flashcards/job/{jobId}/status` | Check job status | Yes |
| GET | `/search-flashcards/topics` | Get suggested topics | Yes |
| GET | `/search-flashcards/health` | Check service health | Yes |
| GET | `/search-flashcards/user-jobs` | Get user jobs info | Yes |

## Usage Examples

### 1. Generate Flashcards

```php
// In your controller or service
use App\Jobs\GenerateSearchFlashcards;

// Dispatch the job
$jobId = Str::uuid()->toString();
GenerateSearchFlashcards::dispatch(
    $jobId,
    'Machine Learning',
    'Introduction to basic concepts',
    'beginner',
    10,
    $userId
);

// Return job ID to frontend
return response()->json([
    'job_id' => $jobId,
    'status' => 'queued'
]);
```

### 2. Check Job Status

```php
use Illuminate\Support\Facades\Cache;

$cacheKey = "search_flashcards_job_{$jobId}";
$jobData = Cache::get($cacheKey);

if ($jobData) {
    $status = $jobData['status'];
    $message = $jobData['message'];
    
    if ($status === 'completed') {
        $flashcards = $jobData['result']['flashcards'];
        // Process flashcards
    }
}
```

### 3. Frontend Integration

```javascript
class SearchFlashcardsAPI {
    constructor(baseURL) {
        this.baseURL = baseURL;
    }

    async generateFlashcards(request, token) {
        const response = await fetch(`${this.baseURL}/search-flashcards/generate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify(request)
        });

        return await response.json();
    }

    async checkJobStatus(jobId, token) {
        const response = await fetch(`${this.baseURL}/search-flashcards/job/${jobId}/status`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });

        return await response.json();
    }
}

// Usage
const api = new SearchFlashcardsAPI('http://localhost:8000/api');
const token = 'your-supabase-token';

// Start generation
const job = await api.generateFlashcards({
    topic: 'Python Programming',
    difficulty: 'beginner',
    count: 10
}, token);

console.log('Job started:', job.data.job_id);

// Poll for status
const checkStatus = async () => {
    const status = await api.checkJobStatus(job.data.job_id, token);
    
    if (status.data.status === 'completed') {
        console.log('Flashcards ready:', status.data.result.flashcards);
        return;
    }
    
    if (status.data.status === 'failed') {
        console.error('Job failed:', status.data.message);
        return;
    }
    
    // Check again in 5 seconds
    setTimeout(checkStatus, 5000);
};

checkStatus();
```

## Job Processing Flow

### 1. Job Submission
```
Frontend → Laravel Controller → Job Queue → Background Processing
```

### 2. Job Execution
```
Job → FastAPI Service → AI Model → Flashcard Generation → Cache Storage
```

### 3. Status Updates
```
Job Progress → Cache Updates → Frontend Polling → Status Display
```

### 4. Result Retrieval
```
Frontend → Cache → Generated Flashcards → Study Integration
```

## Job Status Lifecycle

| Status | Description | Next Action |
|--------|-------------|-------------|
| `queued` | Job is waiting in the queue | Wait for processing to start |
| `processing` | Job is actively being processed | Continue waiting |
| `completed` | Job finished successfully | Retrieve flashcards |
| `failed` | Job encountered an error | Check error message |

## Configuration

### Queue Configuration

The system uses Laravel's database queue driver. Configure in `config/queue.php`:

```php
'database' => [
    'driver' => 'database',
    'table' => 'jobs',
    'queue' => 'default',
    'retry_after' => 90,
    'after_commit' => false,
],
```

### Cache Configuration

Job results are cached for 1 hour. Configure in `config/cache.php`:

```php
'default' => env('CACHE_DRIVER', 'file'),
'stores' => [
    'file' => [
        'driver' => 'file',
        'path' => storage_path('framework/cache/data'),
    ],
],
```

### FastAPI Service Configuration

Configure the FastAPI service URL in `config/services.php`:

```php
'fastapi' => [
    'url' => env('FASTAPI_URL', 'http://localhost:8001'),
],
```

## Monitoring & Debugging

### Queue Monitoring

```bash
# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush

# Monitor queue
php artisan queue:work --verbose
```

### Logs

All operations are logged to the `fastapi` channel. Check logs in:

```
storage/logs/fastapi.log
```

### Cache Monitoring

```bash
# Clear cache
php artisan cache:clear

# Check cache keys
php artisan tinker
>>> Cache::get('search_flashcards_job_*')
```

## Testing

### Run Integration Tests

```bash
# Test the Laravel integration
php test_search_flashcards_laravel.php

# Test individual components
php artisan tinker
>>> $service = new App\Services\FastApiService();
>>> $service->checkSearchFlashcardsHealth();
```

### Test API Endpoints

```bash
# Health check
curl -X GET "http://localhost:8000/api/search-flashcards/health" \
  -H "Authorization: Bearer your-token"

# Generate flashcards
curl -X POST "http://localhost:8000/api/search-flashcards/generate" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your-token" \
  -d '{"topic": "Test Topic", "count": 5}'
```

## Error Handling

### Common Errors

1. **FastAPI Connection Errors**
   - Check if FastAPI service is running
   - Verify `FASTAPI_URL` in `.env`
   - Check network connectivity

2. **Queue Processing Errors**
   - Ensure queue worker is running
   - Check database connection
   - Review failed jobs

3. **Authentication Errors**
   - Verify Supabase authentication
   - Check middleware configuration
   - Validate token format

4. **Cache Errors**
   - Check cache driver configuration
   - Verify storage permissions
   - Monitor disk space

### Error Response Format

```json
{
  "success": false,
  "message": "Error description",
  "error": "Detailed error message",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

## Performance Considerations

### Response Times

- **Job Submission**: < 100ms
- **Job Processing**: 5-15 minutes (FastAPI dependent)
- **Status Checks**: < 50ms
- **Result Retrieval**: < 100ms

### Optimization Tips

1. **Queue Workers**: Run multiple queue workers for parallel processing
2. **Cache TTL**: Adjust cache expiration based on your needs
3. **Batch Processing**: Consider batching multiple topic requests
4. **Async Frontend**: Implement proper loading states and progress indicators

## Security

### Authentication

- All endpoints require Supabase authentication
- User ID is tracked for job management
- Rate limiting is applied via `throttle:api` middleware

### Input Validation

- Topic length: 3-255 characters
- Description length: 0-1000 characters
- Difficulty: beginner, intermediate, advanced
- Count: 1-20 flashcards

### Rate Limiting

- API endpoints are protected by Laravel's built-in rate limiting
- Configure limits in `app/Http/Kernel.php`

## Troubleshooting

### Jobs Not Processing

1. Check if queue worker is running:
   ```bash
   php artisan queue:work
   ```

2. Verify queue configuration:
   ```bash
   php artisan config:cache
   php artisan queue:restart
   ```

3. Check database connection:
   ```bash
   php artisan migrate:status
   ```

### FastAPI Connection Issues

1. Verify FastAPI service is running
2. Check `FASTAPI_URL` in `.env`
3. Test connectivity:
   ```bash
   curl http://localhost:8001/api/v1/search-flashcards/health
   ```

### Cache Issues

1. Check cache driver configuration
2. Verify storage permissions
3. Clear and rebuild cache:
   ```bash
   php artisan cache:clear
   php artisan config:cache
   ```

## Future Enhancements

### Planned Features

1. **Job Persistence**: Store job information in database
2. **Progress Tracking**: Real-time progress updates
3. **Batch Processing**: Handle multiple topics simultaneously
4. **Result Storage**: Save generated flashcards to database
5. **User Preferences**: Remember user's preferred settings

### API Improvements

1. **WebSocket Support**: Real-time status updates
2. **File Export**: Download flashcards in various formats
3. **Sharing**: Share generated flashcards with other users
4. **Analytics**: Track usage patterns and performance

## Support

### Documentation

- API Documentation: `SEARCH_FLASHCARDS_LARAVEL_API.md`
- FastAPI Documentation: `../fastapi-service/SEARCH_FLASHCARDS_API.md`
- Test Script: `test_search_flashcards_laravel.php`

### Issues

Report issues through your project's issue tracking system.

### Questions

For questions about implementation or usage, refer to the documentation or create an issue.
