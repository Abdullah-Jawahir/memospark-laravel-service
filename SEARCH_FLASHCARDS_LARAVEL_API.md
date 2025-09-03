# Search Flashcards Laravel API Documentation

## Overview

The Search Flashcards Laravel API provides endpoints for generating educational flashcards based on search topics. This API acts as a bridge between the frontend and the FastAPI service, using Laravel jobs for asynchronous processing.

## Base URL

```
http://localhost:8000/api
```

## Authentication

All endpoints require Supabase authentication. Include the `supabase_user` in your request headers or middleware.

## Endpoints

### 1. Generate Flashcards from Topic

**POST** `/search-flashcards/generate`

Starts a background job to generate flashcards based on a search topic.

#### Request Body

```json
{
  "topic": "string (required, min 3 characters, max 255)",
  "description": "string (optional, max 1000 characters)",
  "difficulty": "string (optional, default: 'beginner')",
  "count": "integer (optional, default: 10, range: 1-20)"
}
```

#### Request Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `topic` | string | Yes | - | The educational topic to search for |
| `description` | string | No | null | Additional context or description |
| `difficulty` | string | No | "beginner" | Difficulty level: "beginner", "intermediate", or "advanced" |
| `count` | integer | No | 10 | Number of flashcards to generate (1-20) |

#### Response

**Success Response (202 Accepted)**

```json
{
  "success": true,
  "message": "Flashcard generation job started",
  "data": {
    "job_id": "uuid-string",
    "status": "queued",
    "message": "Job has been queued and will start processing shortly",
    "estimated_time": "5-15 minutes depending on topic complexity"
  }
}
```

**Error Responses**

- **422 Unprocessable Entity**: Validation errors
- **500 Internal Server Error**: Server-side errors

#### Example Usage

```bash
curl -X POST "http://localhost:8000/api/search-flashcards/generate" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your-supabase-token" \
  -d '{
    "topic": "Quantum Physics",
    "description": "Basic principles and concepts",
    "difficulty": "intermediate",
    "count": 15
  }'
```

### 2. Check Job Status

**GET** `/search-flashcards/job/{jobId}/status`

Checks the status of a flashcard generation job.

#### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `jobId` | string | The UUID of the job to check |

#### Response

**Success Response (200 OK)**

```json
{
  "success": true,
  "message": "Job status retrieved successfully",
  "data": {
    "status": "completed",
    "message": "Flashcards generated successfully",
    "updated_at": "2024-01-15T10:30:00.000000Z",
    "topic": "Quantum Physics",
    "difficulty": "intermediate",
    "count": 15,
    "user_id": "user-uuid",
    "result": {
      "topic": "Quantum Physics",
      "description": "Basic principles and concepts",
      "flashcards": [...],
      "total_count": 15,
      "difficulty": "intermediate",
      "message": "Successfully generated 15 flashcards for 'Quantum Physics'"
    },
    "completed_at": "2024-01-15T10:25:00.000000Z"
  }
}
```

**Job Status Values**

- `queued`: Job is waiting to be processed
- `processing`: Job is currently being processed
- `completed`: Job completed successfully
- `failed`: Job failed with an error
- `not_found`: Job ID not found or expired

#### Example Usage

```bash
curl -X GET "http://localhost:8000/api/search-flashcards/job/123e4567-e89b-12d3-a456-426614174000/status" \
  -H "Authorization: Bearer your-supabase-token"
```

### 3. Get Suggested Topics

**GET** `/search-flashcards/topics`

Returns a list of suggested educational topics for flashcard generation.

#### Response

**Success Response (200 OK)**

```json
{
  "success": true,
  "message": "Suggested topics retrieved successfully",
  "data": [
    "Mathematics",
    "Physics",
    "Chemistry",
    "Biology",
    "History",
    "Geography",
    "Literature",
    "Computer Science",
    "Economics",
    "Psychology",
    "Philosophy",
    "Art History",
    "Music Theory",
    "Foreign Languages",
    "Environmental Science",
    "Astronomy",
    "Anatomy",
    "World Religions",
    "Political Science",
    "Sociology"
  ]
}
```

#### Example Usage

```bash
curl -X GET "http://localhost:8000/api/search-flashcards/topics" \
  -H "Authorization: Bearer your-supabase-token"
```

### 4. Check Service Health

**GET** `/search-flashcards/health`

Checks the health status of the FastAPI search flashcards service.

#### Response

**Success Response (200 OK)**

```json
{
  "success": true,
  "message": "Health check completed",
  "data": {
    "status": "healthy",
    "service": "search-flashcards",
    "flashcard_generator": "working",
    "model_manager": "available"
  }
}
```

**Unhealthy Response (500 Internal Server Error)**

```json
{
  "success": false,
  "message": "Health check failed",
  "error": "Could not connect to FastAPI server",
  "data": {
    "status": "unhealthy",
    "service": "search-flashcards",
    "error": "Could not connect to FastAPI server"
  }
}
```

#### Example Usage

```bash
curl -X GET "http://localhost:8000/api/search-flashcards/health" \
  -H "Authorization: Bearer your-supabase-token"
```

### 5. Get User Jobs

**GET** `/search-flashcards/user-jobs`

Gets information about the current user's search flashcard jobs.

#### Response

**Success Response (200 OK)**

```json
{
  "success": true,
  "message": "User jobs retrieval",
  "data": {
    "note": "Job tracking is currently limited. Use the job_id from the generation response to check status.",
    "user_id": "user-uuid"
  }
}
```

**Unauthorized Response (401 Unauthorized)**

```json
{
  "success": false,
  "message": "Authentication required"
}
```

#### Example Usage

```bash
curl -X GET "http://localhost:8000/api/search-flashcards/user-jobs" \
  -H "Authorization: Bearer your-supabase-token"
```

## Job Processing Flow

### 1. Job Submission
```
Frontend → Laravel API → Job Queue → FastAPI Service
```

### 2. Job Status Tracking
```
Frontend → Laravel API → Cache → Job Status
```

### 3. Result Retrieval
```
Frontend → Laravel API → Cache → Generated Flashcards
```

## Job Status Lifecycle

1. **Queued**: Job is dispatched and waiting in the queue
2. **Processing**: Job is actively being processed by FastAPI
3. **Completed**: Job finished successfully, flashcards are ready
4. **Failed**: Job encountered an error during processing

## Error Handling

### Common Error Scenarios

1. **Validation Errors**: Invalid input parameters
2. **Authentication Errors**: Missing or invalid Supabase token
3. **Job Not Found**: Job ID expired or doesn't exist
4. **FastAPI Connection Errors**: FastAPI service unavailable
5. **Processing Errors**: AI model failures or timeouts

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

## Rate Limiting

All endpoints are protected by Laravel's built-in rate limiting middleware (`throttle:api`).

## Caching

Job results are cached for 1 hour to allow for status checking and result retrieval.

## Frontend Integration

### JavaScript Example

```javascript
class SearchFlashcardsAPI {
    constructor(baseURL = 'http://localhost:8000/api') {
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

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.message || 'Failed to generate flashcards');
        }

        return await response.json();
    }

    async checkJobStatus(jobId, token) {
        const response = await fetch(`${this.baseURL}/search-flashcards/job/${jobId}/status`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.message || 'Failed to check job status');
        }

        return await response.json();
    }

    async getSuggestedTopics(token) {
        const response = await fetch(`${this.baseURL}/search-flashcards/topics`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.message || 'Failed to get suggested topics');
        }

        return await response.json();
    }
}

// Usage
const api = new SearchFlashcardsAPI();
const token = 'your-supabase-token';

// Start generation
const job = await api.generateFlashcards({
    topic: 'Machine Learning',
    difficulty: 'beginner',
    count: 10
}, token);

console.log('Job started:', job.data.job_id);

// Check status
const status = await api.checkJobStatus(job.data.job_id, token);
console.log('Job status:', status.data.status);
```

## Testing

### Test the Endpoints

1. **Health Check**: Verify FastAPI service is running
2. **Generate Flashcards**: Start a flashcard generation job
3. **Check Status**: Monitor job progress
4. **Retrieve Results**: Get generated flashcards when complete

### Example Test Flow

```bash
# 1. Check health
curl -X GET "http://localhost:8000/api/search-flashcards/health" \
  -H "Authorization: Bearer your-token"

# 2. Generate flashcards
curl -X POST "http://localhost:8000/api/search-flashcards/generate" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your-token" \
  -d '{"topic": "Python Programming", "count": 5}'

# 3. Check job status (replace with actual job ID)
curl -X GET "http://localhost:8000/api/search-flashcards/job/job-uuid/status" \
  -H "Authorization: Bearer your-token"
```

## Configuration

### Environment Variables

Ensure these are set in your `.env` file:

```env
FASTAPI_URL=http://localhost:8001
QUEUE_CONNECTION=database
```

### Queue Configuration

The system uses Laravel's database queue driver. Ensure you have:

1. Run queue migrations: `php artisan queue:table && php artisan migrate`
2. Started queue worker: `php artisan queue:work`

## Monitoring

### Queue Monitoring

Monitor job processing with:

```bash
# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

### Logs

All operations are logged to the `fastapi` channel. Check logs for debugging:

```bash
tail -f storage/logs/fastapi.log
```

## Troubleshooting

### Common Issues

1. **Jobs Not Processing**: Ensure queue worker is running
2. **FastAPI Connection Errors**: Check FastAPI service status
3. **Authentication Errors**: Verify Supabase token
4. **Job Status Not Found**: Check cache configuration

### Debug Steps

1. Check queue worker status
2. Verify FastAPI service health
3. Review Laravel logs
4. Check cache configuration
5. Verify authentication middleware
