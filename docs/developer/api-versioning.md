# API Versioning

Muzakily uses URL-based API versioning to ensure backward compatibility.

## Versioning Strategy

### URL Prefix

All API endpoints are prefixed with the version:

```
/api/v1/songs
/api/v1/playlists
/api/v2/songs  (future)
```

### Current Version

The current stable API version is **v1**.

## Route Organization

### Route Files

```
routes/
├── api.php           # Loads versioned route files
└── api/
    └── v1.php        # Version 1 routes
```

### api.php

```php
Route::prefix('v1')
    ->middleware('api')
    ->group(base_path('routes/api/v1.php'));

// Future versions
// Route::prefix('v2')
//     ->middleware('api')
//     ->group(base_path('routes/api/v2.php'));
```

### v1.php

```php
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::delete('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('songs', SongController::class)->only(['index', 'show', 'update']);
    Route::get('songs/{song}/stream', [SongController::class, 'stream']);
    // ...
});
```

## Controller Organization

### Namespacing

```
app/Http/Controllers/
└── Api/
    └── V1/
        ├── AuthController.php
        ├── SongController.php
        ├── AlbumController.php
        └── ...
```

### Controller Example

```php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;

class SongController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        // V1 implementation
    }
}
```

## Resource Versioning

### Versioned Resources

```
app/Http/Resources/
└── Api/
    └── V1/
        ├── SongResource.php
        ├── AlbumResource.php
        └── ...
```

### Resource Example

```php
namespace App\Http\Resources\Api\V1;

class SongResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            // V1 response structure
        ];
    }
}
```

## Request Versioning

### Versioned Requests

```
app/Http/Requests/
└── Api/
    └── V1/
        ├── CreatePlaylistRequest.php
        └── ...
```

## Adding a New Version

### 1. Create Route File

```php
// routes/api/v2.php
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('songs', V2\SongController::class);
});
```

### 2. Register Routes

```php
// routes/api.php
Route::prefix('v2')
    ->middleware('api')
    ->group(base_path('routes/api/v2.php'));
```

### 3. Create Controllers

```php
namespace App\Http\Controllers\Api\V2;

class SongController extends Controller
{
    // V2 implementation with breaking changes
}
```

### 4. Create Resources

```php
namespace App\Http\Resources\Api\V2;

class SongResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // New response structure
        ];
    }
}
```

## Breaking Changes

### What Constitutes Breaking

- Removing endpoints
- Removing fields from responses
- Changing field types
- Changing authentication
- Changing error formats

### Non-Breaking Changes

- Adding endpoints
- Adding optional fields
- Adding query parameters
- Performance improvements

## Deprecation Policy

### Timeline

1. **Announce** - Deprecation notice in changelog
2. **Warn** - Add `Deprecation` header to responses
3. **Sunset** - Remove after 6 months

### Deprecation Header

```php
class DeprecatedEndpointMiddleware
{
    public function handle(Request $request, Closure $next, string $sunset): Response
    {
        $response = $next($request);

        $response->headers->set('Deprecation', 'true');
        $response->headers->set('Sunset', $sunset);
        $response->headers->set('Link', '</api/v2/songs>; rel="successor-version"');

        return $response;
    }
}
```

### Route Usage

```php
Route::get('old-endpoint', [Controller::class, 'method'])
    ->middleware('deprecated:2024-06-01');
```

## Client Considerations

### Version Detection

```typescript
const API_VERSION = 'v1';
const BASE_URL = `/api/${API_VERSION}`;

async function fetchSongs() {
  return fetch(`${BASE_URL}/songs`);
}
```

### Handling Deprecation

```typescript
async function fetchWithDeprecationCheck(url: string) {
  const response = await fetch(url);

  if (response.headers.get('Deprecation')) {
    const sunset = response.headers.get('Sunset');
    const successor = response.headers.get('Link');
    console.warn(`Endpoint deprecated, sunset: ${sunset}, use: ${successor}`);
  }

  return response;
}
```

## Documentation

### Per-Version Docs

Maintain separate documentation for each API version:

```
docs/api/
├── v1/
│   ├── openapi.yaml
│   └── endpoints/
└── v2/
    ├── openapi.yaml
    └── endpoints/
```

### Version in OpenAPI

```yaml
openapi: 3.0.3
info:
  title: Muzakily API
  version: 1.0.0

servers:
  - url: /api/v1
    description: API Version 1
```
