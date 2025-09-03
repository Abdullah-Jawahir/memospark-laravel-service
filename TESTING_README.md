# Testing Search Flashcards Endpoints

This guide explains how to test all the search flashcards endpoints using either the automated test script or Postman.

## 🚀 Quick Start

### Prerequisites

1. **Laravel Service Running**: `php artisan serve` (port 8000)
2. **FastAPI Service Running**: `python -m uvicorn app.main:app --reload --port 8001`
3. **Queue Worker Running**: `php artisan queue:work`
4. **Database Migrated**: `php artisan migrate`

### Option 1: Automated Testing (Recommended)

Run the comprehensive test script:

```bash
cd laravel-service
php test_search_flashcards_endpoints.php
```

This script will:
- ✅ Test all 10 endpoints
- 🔄 Generate multiple flashcard jobs
- 📊 Check job statuses
- 📚 Test history and statistics
- 🚫 Test error handling and validation
- 🔐 Test authentication requirements

### Option 2: Manual Testing with Postman

1. **Import Collection**: Import `Search_Flashcards_Postman_Collection.json` into Postman
2. **Set Environment**: Update the `base_url` variable if needed
3. **Run Tests**: Execute each request individually

## 📋 Test Coverage

### Core Endpoints
1. **Health Check** - Verify service status
2. **Suggested Topics** - Get topic suggestions
3. **Generate Flashcards** - Start flashcard generation
4. **Job Status** - Check generation progress

### History & Analytics
5. **Search History** - Get user search history
6. **Search Details** - Get specific search information
7. **Recent Searches** - Get recent completed searches
8. **Search Statistics** - Get user learning analytics

### Error Handling
9. **Invalid Data** - Test validation rules
10. **Authentication** - Test auth requirements

## 🧪 Test Scenarios

### 1. Basic Functionality
```bash
# Health check
curl -X GET "http://localhost:8000/api/search-flashcards/health" \
  -H "supabase_user: test-user-123"

# Get topics
curl -X GET "http://localhost:8000/api/search-flashcards/topics" \
  -H "supabase_user: test-user-123"
```

### 2. Flashcard Generation
```bash
# Generate flashcards
curl -X POST "http://localhost:8000/api/search-flashcards/generate" \
  -H "Content-Type: application/json" \
  -H "supabase_user: test-user-123" \
  -d '{
    "topic": "Python Programming",
    "description": "Basic concepts and syntax",
    "difficulty": "beginner",
    "count": 10
  }'

# Check job status (replace with actual job ID)
curl -X GET "http://localhost:8000/api/search-flashcards/job/job-uuid/status" \
  -H "supabase_user: test-user-123"
```

### 3. History & Analytics
```bash
# Get search history
curl -X GET "http://localhost:8000/api/search-flashcards/history?per_page=10" \
  -H "supabase_user: test-user-123"

# Get recent searches
curl -X GET "http://localhost:8000/api/search-flashcards/recent?limit=5&days=7" \
  -H "supabase_user: test-user-123"

# Get statistics
curl -X GET "http://localhost:8000/api/search-flashcards/stats?days=30" \
  -H "supabase_user: test-user-123"
```

## 🔍 Expected Results

### Successful Responses
- **Health Check**: `200 OK` with service status
- **Topics**: `200 OK` with array of suggested topics
- **Generate**: `202 Accepted` with job ID
- **Status**: `200 OK` with job status and progress
- **History**: `200 OK` with paginated search results
- **Statistics**: `200 OK` with user analytics

### Error Responses
- **Invalid Data**: `422 Unprocessable Entity` with validation errors
- **No Auth**: `401 Unauthorized`
- **Not Found**: `404 Not Found` for invalid IDs

## 🐛 Troubleshooting

### Common Issues

1. **Health Check Fails**
   - Ensure FastAPI service is running on port 8001
   - Check network connectivity between services

2. **Jobs Not Processing**
   - Verify queue worker is running: `php artisan queue:work`
   - Check queue configuration in `.env`
   - Monitor failed jobs: `php artisan queue:failed`

3. **Authentication Errors**
   - Ensure `supabase_user` header is set
   - Check middleware configuration
   - Verify user authentication logic

4. **Database Errors**
   - Run migrations: `php artisan migrate`
   - Check database connection
   - Verify table structure

### Debug Steps

1. **Check Service Status**
   ```bash
   # Laravel
   php artisan serve --port=8000
   
   # FastAPI
   python -m uvicorn app.main:app --reload --port 8001
   
   # Queue Worker
   php artisan queue:work --verbose
   ```

2. **Check Logs**
   ```bash
   # Laravel logs
   tail -f storage/logs/laravel.log
   
   # FastAPI logs
   tail -f storage/logs/fastapi.log
   ```

3. **Check Database**
   ```bash
   # Database status
   php artisan tinker
   >>> DB::connection()->getPdo()
   
   # Check tables
   php artisan migrate:status
   ```

## 📊 Monitoring

### Queue Monitoring
```bash
# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush

# Monitor queue with verbose output
php artisan queue:work --verbose
```

### Cache Management
```bash
# Clear cache
php artisan cache:clear

# Check cache keys
php artisan tinker
>>> Cache::get('search_flashcards_job_*')
```

## 🎯 Next Steps

After successful testing:

1. **Review Results**: Check all endpoints return expected responses
2. **Monitor Jobs**: Watch flashcard generation progress
3. **Verify Data**: Check database for generated content
4. **Frontend Integration**: Move to frontend development
5. **Production Testing**: Test with real user scenarios

## 📚 Additional Resources

- **API Documentation**: `SEARCH_FLASHCARDS_LARAVEL_API.md`
- **History API**: `SEARCH_FLASHCARDS_HISTORY_API.md`
- **Complete Guide**: `SEARCH_FLASHCARDS_COMPLETE_README.md`
- **Migration File**: `database/migrations/2025_08_29_175134_create_search_flashcards_tables.php`

## 🤝 Support

If you encounter issues:

1. Check the troubleshooting section above
2. Review error logs for specific error messages
3. Verify all services are running correctly
4. Check database migration status
5. Ensure proper authentication setup

---

**Happy Testing! 🎉**
