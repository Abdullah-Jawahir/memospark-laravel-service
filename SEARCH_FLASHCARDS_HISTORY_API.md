# Search Flashcards History API Documentation

## Overview

The Search Flashcards History API provides endpoints for retrieving and managing user search history, generated flashcards, and study statistics. This API allows users to track their learning progress and access previously generated content.

## Base URL

```
http://localhost:8000/api
```

## Authentication

All endpoints require Supabase authentication. Include the `supabase_user` in your request headers or middleware.

## New History Endpoints

### 1. Get Search History

**GET** `/search-flashcards/history`

Retrieves the complete search history for the authenticated user with pagination and filtering options.

#### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `per_page` | integer | No | 10 | Number of results per page (1-100) |
| `status` | string | No | - | Filter by status: "queued", "processing", "completed", "failed" |
| `topic` | string | No | - | Filter by topic (partial match) |

#### Response

**Success Response (200 OK)**

```json
{
  "success": true,
  "message": "Search history retrieved successfully",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "user_id": "user-uuid",
        "topic": "Machine Learning",
        "description": "Introduction to basic concepts",
        "difficulty": "beginner",
        "requested_count": 10,
        "job_id": "job-uuid",
        "status": "completed",
        "started_at": "2024-01-15T10:00:00.000000Z",
        "completed_at": "2024-01-15T10:15:00.000000Z",
        "created_at": "2024-01-15T10:00:00.000000Z",
        "updated_at": "2024-01-15T10:15:00.000000Z",
        "flashcards": [
          {
            "id": 1,
            "search_id": 1,
            "question": "What is machine learning?",
            "answer": "Machine learning is a subset of AI...",
            "type": "Q&A",
            "difficulty": "beginner",
            "order_index": 1
          }
        ],
        "latest_study_session": {
          "id": 1,
          "search_id": 1,
          "started_at": "2024-01-15T11:00:00.000000Z",
          "total_flashcards": 10,
          "studied_flashcards": 8,
          "correct_answers": 7,
          "incorrect_answers": 1
        },
        "study_stats": {
          "total_sessions": 2,
          "total_studied": 18,
          "total_correct": 16,
          "total_incorrect": 2,
          "average_score": 88.89
        },
        "flashcards_count": 10,
        "has_been_studied": true
      }
    ],
    "first_page_url": "http://localhost:8000/api/search-flashcards/history?page=1",
    "from": 1,
    "last_page": 3,
    "last_page_url": "http://localhost:8000/api/search-flashcards/history?page=3",
    "next_page_url": "http://localhost:8000/api/search-flashcards/history?page=2",
    "path": "http://localhost:8000/api/search-flashcards/history",
    "per_page": 10,
    "prev_page_url": null,
    "to": 10,
    "total": 25
  }
}
```

#### Example Usage

```bash
# Get all search history
curl -X GET "http://localhost:8000/api/search-flashcards/history" \
  -H "Authorization: Bearer your-token"

# Get completed searches only
curl -X GET "http://localhost:8000/api/search-flashcards/history?status=completed" \
  -H "Authorization: Bearer your-token"

# Search for specific topics
curl -X GET "http://localhost:8000/api/search-flashcards/history?topic=machine" \
  -H "Authorization: Bearer your-token"

# Pagination
curl -X GET "http://localhost:8000/api/search-flashcards/history?per_page=5&page=2" \
  -H "Authorization: Bearer your-token"
```

### 2. Get Search Details

**GET** `/search-flashcards/search/{searchId}`

Retrieves detailed information about a specific search, including all generated flashcards and study sessions.

#### Path Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `searchId` | integer | The ID of the search to retrieve |

#### Response

**Success Response (200 OK)**

```json
{
  "success": true,
  "message": "Search details retrieved successfully",
  "data": {
    "id": 1,
    "user_id": "user-uuid",
    "topic": "Machine Learning",
    "description": "Introduction to basic concepts",
    "difficulty": "beginner",
    "requested_count": 10,
    "job_id": "job-uuid",
    "status": "completed",
    "started_at": "2024-01-15T10:00:00.000000Z",
    "completed_at": "2024-01-15T10:15:00.000000Z",
    "created_at": "2024-01-15T10:00:00.000000Z",
    "updated_at": "2024-01-15T10:15:00.000000Z",
    "flashcards": [
      {
        "id": 1,
        "search_id": 1,
        "question": "What is machine learning?",
        "answer": "Machine learning is a subset of AI...",
        "type": "Q&A",
        "difficulty": "beginner",
        "order_index": 1,
        "created_at": "2024-01-15T10:15:00.000000Z",
        "updated_at": "2024-01-15T10:15:00.000000Z"
      }
    ],
    "study_sessions": [
      {
        "id": 1,
        "search_id": 1,
        "user_id": "user-uuid",
        "started_at": "2024-01-15T11:00:00.000000Z",
        "completed_at": "2024-01-15T11:30:00.000000Z",
        "total_flashcards": 10,
        "studied_flashcards": 8,
        "correct_answers": 7,
        "incorrect_answers": 1,
        "study_data": {
          "session_duration": 1800,
          "average_time_per_card": 225
        },
        "created_at": "2024-01-15T11:00:00.000000Z",
        "updated_at": "2024-01-15T11:30:00.000000Z"
      }
    ],
    "study_stats": {
      "total_sessions": 2,
      "total_studied": 18,
      "total_correct": 16,
      "total_incorrect": 2,
      "average_score": 88.89
    },
    "flashcards_count": 10,
    "has_been_studied": true
  }
}
```

**Not Found Response (404)**

```json
{
  "success": false,
  "message": "Search not found",
  "data": null
}
```

#### Example Usage

```bash
curl -X GET "http://localhost:8000/api/search-flashcards/search/1" \
  -H "Authorization: Bearer your-token"
```

### 3. Get Recent Searches

**GET** `/search-flashcards/recent`

Retrieves recent completed searches for the authenticated user, useful for quick access to recent learning materials.

#### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `limit` | integer | No | 5 | Number of recent searches to retrieve (1-20) |
| `days` | integer | No | 30 | Number of days to look back |

#### Response

**Success Response (200 OK)**

```json
{
  "success": true,
  "message": "Recent searches retrieved successfully",
  "data": [
    {
      "id": 1,
      "topic": "Machine Learning",
      "description": "Introduction to basic concepts",
      "difficulty": "beginner",
      "flashcards_count": 10,
      "created_at": "2024-01-15T10:00:00.000000Z",
      "completed_at": "2024-01-15T10:15:00.000000Z",
      "has_been_studied": true,
      "study_stats": {
        "total_sessions": 2,
        "total_studied": 18,
        "total_correct": 16,
        "total_incorrect": 2,
        "average_score": 88.89
      }
    },
    {
      "id": 2,
      "topic": "Python Programming",
      "description": "Basic syntax and concepts",
      "difficulty": "beginner",
      "flashcards_count": 8,
      "created_at": "2024-01-14T14:00:00.000000Z",
      "completed_at": "2024-01-14T14:10:00.000000Z",
      "has_been_studied": false,
      "study_stats": {
        "total_sessions": 0,
        "total_studied": 0,
        "total_correct": 0,
        "total_incorrect": 0,
        "average_score": 0
      }
    }
  ]
}
```

#### Example Usage

```bash
# Get 5 most recent searches
curl -X GET "http://localhost:8000/api/search-flashcards/recent" \
  -H "Authorization: Bearer your-token"

# Get 10 recent searches from last 7 days
curl -X GET "http://localhost:8000/api/search-flashcards/recent?limit=10&days=7" \
  -H "Authorization: Bearer your-token"
```

### 4. Get Search Statistics

**GET** `/search-flashcards/stats`

Retrieves comprehensive statistics about the user's search and learning activities.

#### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `days` | integer | No | 30 | Number of days to include in recent statistics |

#### Response

**Success Response (200 OK)**

```json
{
  "success": true,
  "message": "Search statistics retrieved successfully",
  "data": {
    "total_searches": 25,
    "completed_searches": 23,
    "failed_searches": 2,
    "recent_searches": 8,
    "total_flashcards": 230,
    "success_rate": 92.0,
    "popular_topics": [
      {
        "topic": "Machine Learning",
        "count": 5
      },
      {
        "topic": "Python Programming",
        "count": 4
      },
      {
        "topic": "Mathematics",
        "count": 3
      },
      {
        "topic": "Physics",
        "count": 2
      },
      {
        "topic": "Chemistry",
        "count": 2
      }
    ],
    "difficulty_distribution": {
      "beginner": {
        "difficulty": "beginner",
        "count": 15
      },
      "intermediate": {
        "difficulty": "intermediate",
        "count": 7
      },
      "advanced": {
        "difficulty": "advanced",
        "count": 3
      }
    },
    "period_days": 30
  }
}
```

#### Example Usage

```bash
# Get statistics for last 30 days
curl -X GET "http://localhost:8000/api/search-flashcards/stats" \
  -H "Authorization: Bearer your-token"

# Get statistics for last 7 days
curl -X GET "http://localhost:8000/api/search-flashcards/stats?days=7" \
  -H "Authorization: Bearer your-token"
```

## Data Models

### SearchFlashcardSearch

```json
{
  "id": "integer",
  "user_id": "string (Supabase UUID)",
  "topic": "string",
  "description": "string|null",
  "difficulty": "enum: beginner|intermediate|advanced",
  "requested_count": "integer",
  "job_id": "string (UUID)",
  "status": "enum: queued|processing|completed|failed",
  "error_message": "string|null",
  "started_at": "datetime|null",
  "completed_at": "datetime|null",
  "created_at": "datetime",
  "updated_at": "datetime"
}
```

### SearchFlashcardResult

```json
{
  "id": "integer",
  "search_id": "integer",
  "question": "string",
  "answer": "string",
  "type": "string",
  "difficulty": "enum: beginner|intermediate|advanced",
  "order_index": "integer",
  "created_at": "datetime",
  "updated_at": "datetime"
}
```

### Study Statistics

```json
{
  "total_sessions": "integer",
  "total_studied": "integer",
  "total_correct": "integer",
  "total_incorrect": "integer",
  "average_score": "float (percentage)"
}
```

## Frontend Integration Examples

### JavaScript Class for History Management

```javascript
class SearchFlashcardsHistoryAPI {
    constructor(baseURL = 'http://localhost:8000/api') {
        this.baseURL = baseURL;
    }

    async getSearchHistory(params = {}, token) {
        const queryString = new URLSearchParams(params).toString();
        const response = await fetch(`${this.baseURL}/search-flashcards/history?${queryString}`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.message || 'Failed to get search history');
        }

        return await response.json();
    }

    async getSearchDetails(searchId, token) {
        const response = await fetch(`${this.baseURL}/search-flashcards/search/${searchId}`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.message || 'Failed to get search details');
        }

        return await response.json();
    }

    async getRecentSearches(params = {}, token) {
        const queryString = new URLSearchParams(params).toString();
        const response = await fetch(`${this.baseURL}/search-flashcards/recent?${queryString}`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.message || 'Failed to get recent searches');
        }

        return await response.json();
    }

    async getSearchStats(params = {}, token) {
        const queryString = new URLSearchParams(params).toString();
        const response = await fetch(`${this.baseURL}/search-flashcards/stats?${queryString}`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.message || 'Failed to get search statistics');
        }

        return await response.json();
    }
}

// Usage
const historyAPI = new SearchFlashcardsHistoryAPI();
const token = 'your-supabase-token';

// Get search history with pagination
const history = await historyAPI.getSearchHistory({
    per_page: 10,
    page: 1,
    status: 'completed'
}, token);

// Get recent searches for dashboard
const recent = await historyAPI.getRecentSearches({
    limit: 5,
    days: 7
}, token);

// Get user statistics
const stats = await historyAPI.getSearchStats({
    days: 30
}, token);
```

### React Component Example

```jsx
import React, { useState, useEffect } from 'react';

const SearchHistory = ({ token }) => {
    const [history, setHistory] = useState([]);
    const [loading, setLoading] = useState(true);
    const [pagination, setPagination] = useState({});

    useEffect(() => {
        loadHistory();
    }, []);

    const loadHistory = async (page = 1) => {
        try {
            setLoading(true);
            const response = await historyAPI.getSearchHistory({
                per_page: 10,
                page
            }, token);
            
            setHistory(response.data.data);
            setPagination({
                currentPage: response.data.current_page,
                lastPage: response.data.last_page,
                total: response.data.total
            });
        } catch (error) {
            console.error('Failed to load history:', error);
        } finally {
            setLoading(false);
        }
    };

    const handlePageChange = (page) => {
        loadHistory(page);
    };

    if (loading) {
        return <div>Loading search history...</div>;
    }

    return (
        <div className="search-history">
            <h2>Search History</h2>
            
            {history.map(search => (
                <div key={search.id} className="search-item">
                    <h3>{search.topic}</h3>
                    <p>{search.description}</p>
                    <div className="search-meta">
                        <span>Difficulty: {search.difficulty}</span>
                        <span>Flashcards: {search.flashcards_count}</span>
                        <span>Status: {search.status}</span>
                        <span>Created: {new Date(search.created_at).toLocaleDateString()}</span>
                    </div>
                    
                    {search.has_been_studied && (
                        <div className="study-stats">
                            <span>Study Sessions: {search.study_stats.total_sessions}</span>
                            <span>Average Score: {search.study_stats.average_score}%</span>
                        </div>
                    )}
                    
                    <button onClick={() => viewSearchDetails(search.id)}>
                        View Details
                    </button>
                </div>
            ))}
            
            {/* Pagination */}
            <div className="pagination">
                {pagination.currentPage > 1 && (
                    <button onClick={() => handlePageChange(pagination.currentPage - 1)}>
                        Previous
                    </button>
                )}
                
                <span>Page {pagination.currentPage} of {pagination.lastPage}</span>
                
                {pagination.currentPage < pagination.lastPage && (
                    <button onClick={() => handlePageChange(pagination.currentPage + 1)}>
                        Next
                    </button>
                )}
            </div>
        </div>
    );
};
```

## Error Handling

### Common Error Responses

- **401 Unauthorized**: Missing or invalid authentication token
- **404 Not Found**: Search ID doesn't exist or belongs to another user
- **422 Unprocessable Entity**: Invalid query parameters
- **500 Internal Server Error**: Server-side errors

### Error Response Format

```json
{
  "success": false,
  "message": "Error description",
  "error": "Detailed error message"
}
```

## Performance Considerations

- **Pagination**: Use pagination for large history lists
- **Filtering**: Apply filters to reduce data transfer
- **Caching**: Consider caching frequently accessed data
- **Lazy Loading**: Load detailed information only when needed

## Rate Limiting

All endpoints are protected by Laravel's built-in rate limiting middleware (`throttle:api`).

## Testing

### Test the History Endpoints

```bash
# Get search history
curl -X GET "http://localhost:8000/api/search-flashcards/history" \
  -H "Authorization: Bearer your-token"

# Get specific search details
curl -X GET "http://localhost:8000/api/search-flashcards/search/1" \
  -H "Authorization: Bearer your-token"

# Get recent searches
curl -X GET "http://localhost:8000/api/search-flashcards/recent?limit=3" \
  -H "Authorization: Bearer your-token"

# Get user statistics
curl -X GET "http://localhost:8000/api/search-flashcards/stats?days=7" \
  -H "Authorization: Bearer your-token"
```

## Next Steps

1. **Study Session Tracking**: Implement endpoints for recording study sessions
2. **Progress Analytics**: Add more detailed learning analytics
3. **Content Sharing**: Allow users to share generated flashcards
4. **Export Features**: Add support for exporting flashcards in various formats
5. **Collaborative Learning**: Enable group study sessions
