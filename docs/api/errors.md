# Error Handling

The Muzakily API uses conventional HTTP response codes and returns structured JSON error responses.

## HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Created - Resource successfully created |
| 202 | Accepted - Request accepted for async processing |
| 204 | No Content - Success with no response body |
| 400 | Bad Request - Business logic error |
| 401 | Unauthorized - Missing or invalid authentication |
| 403 | Forbidden - Insufficient permissions |
| 404 | Not Found - Resource does not exist |
| 415 | Unsupported Media Type - Invalid file format |
| 422 | Unprocessable Entity - Validation error |
| 500 | Internal Server Error - Server-side error |

## Error Response Formats

### Validation Errors (422)

Returned when request data fails validation rules.

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": [
      "The email field is required.",
      "The email must be a valid email address."
    ],
    "password": [
      "The password must be at least 8 characters."
    ]
  }
}
```

**Handling:**
- The `errors` object contains field names as keys
- Each field has an array of error messages
- Display these messages next to the corresponding form fields

### Business Logic Errors (400)

Returned when an operation violates business rules.

```json
{
  "error": {
    "code": "INVALID_OPERATION",
    "message": "Cannot add songs to a smart playlist"
  }
}
```

**Common Error Codes:**

| Code | Description |
|------|-------------|
| `INVALID_OPERATION` | Operation not allowed for this resource type |
| `UNSUPPORTED_FORMAT` | File format not supported |
| `CIRCULAR_HIERARCHY` | Would create circular parent-child relationship |
| `QUOTA_EXCEEDED` | Storage or rate limit exceeded |

### Authentication Errors (401)

Returned when authentication is missing or invalid.

```json
{
  "message": "Unauthenticated."
}
```

**Causes:**
- No `Authorization` header provided
- Invalid or expired token
- Token has been revoked

### Authorization Errors (403)

Returned when user lacks permission for the action.

```json
{
  "message": "This action is unauthorized."
}
```

**Causes:**
- Trying to access another user's playlist
- Non-admin trying to access admin endpoints
- User doesn't have required role/permission

### Not Found Errors (404)

Returned when the requested resource doesn't exist.

```json
{
  "error": {
    "code": "NOT_FOUND",
    "message": "Resource not found"
  }
}
```

Or with model binding:

```json
{
  "message": "No query results for model [App\\Models\\Song]."
}
```

### Unsupported Media Type (415)

Returned when uploading an unsupported file format.

```json
{
  "error": {
    "code": "UNSUPPORTED_FORMAT",
    "message": "The uploaded file format is not supported. Supported formats: mp3, m4a, flac"
  }
}
```

## Error Handling Examples

### JavaScript/TypeScript

```typescript
async function fetchSongs() {
  try {
    const response = await fetch('/api/v1/songs', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      },
    });

    if (!response.ok) {
      const error = await response.json();

      switch (response.status) {
        case 401:
          // Redirect to login
          window.location.href = '/login';
          break;
        case 403:
          throw new Error('You do not have permission to view songs');
        case 422:
          // Handle validation errors
          const messages = Object.values(error.errors).flat();
          throw new Error(messages.join(', '));
        default:
          throw new Error(error.message || error.error?.message || 'An error occurred');
      }
    }

    return response.json();
  } catch (error) {
    console.error('API Error:', error);
    throw error;
  }
}
```

### PHP/Laravel

```php
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

try {
    $response = Http::withToken($token)
        ->acceptJson()
        ->get('/api/v1/songs');

    $response->throw();

    return $response->json('data');
} catch (RequestException $e) {
    $status = $e->response->status();
    $body = $e->response->json();

    match ($status) {
        401 => throw new AuthenticationException(),
        403 => throw new AuthorizationException($body['message']),
        422 => throw new ValidationException($body['errors']),
        default => throw new ApiException($body['message'] ?? 'Unknown error'),
    };
}
```

## Rate Limiting

The API implements rate limiting to prevent abuse:

- **Default limit**: 60 requests per minute
- **Authenticated users**: Higher limits based on role

When rate limited, you'll receive a `429 Too Many Requests` response:

```json
{
  "message": "Too Many Attempts."
}
```

Check the response headers for rate limit info:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
Retry-After: 30
```

## Debugging Tips

1. **Always check the status code first** - It tells you the category of error
2. **Include `Accept: application/json`** - Ensures you get JSON error responses
3. **Check the `errors` object for 422** - Contains field-specific messages
4. **Check the `error.code` for 400** - Identifies the specific business rule violated
5. **Enable debug mode locally** - Set `APP_DEBUG=true` for detailed stack traces
