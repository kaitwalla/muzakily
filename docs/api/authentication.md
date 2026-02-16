# Authentication

Muzakily uses Laravel Sanctum for API authentication via bearer tokens.

## Obtaining a Token

Send a POST request to `/api/v1/auth/login` with your credentials:

```bash
curl -X POST https://your-domain.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email": "user@example.com", "password": "your-password"}'
```

**Response:**

```json
{
  "data": {
    "token": "1|abc123def456...",
    "user": {
      "id": 1,
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "John Doe",
      "email": "user@example.com",
      "role": "user",
      "created_at": "2024-01-01T00:00:00.000000Z"
    }
  }
}
```

## Using the Token

Include the token in the `Authorization` header for all authenticated requests:

```bash
curl https://your-domain.com/api/v1/songs \
  -H "Authorization: Bearer 1|abc123def456..." \
  -H "Accept: application/json"
```

## Token Lifetime

By default, Sanctum tokens do not expire automatically. However, you can configure token expiration in your application:

```php
// config/sanctum.php
'expiration' => 60 * 24, // Token expires after 24 hours (in minutes)
```

Tokens can also be revoked:

- **Logout**: The current token is revoked when you call `/api/v1/auth/logout`
- **Admin revocation**: Administrators can revoke user tokens
- **Expiration**: If configured, tokens expire automatically after the specified time

## Logout

Revoke your current token:

```bash
curl -X DELETE https://your-domain.com/api/v1/auth/logout \
  -H "Authorization: Bearer 1|abc123def456..." \
  -H "Accept: application/json"
```

**Response:** `204 No Content`

## Get Current User

Retrieve the authenticated user's profile:

```bash
curl https://your-domain.com/api/v1/auth/me \
  -H "Authorization: Bearer 1|abc123def456..." \
  -H "Accept: application/json"
```

**Response:**

```json
{
  "data": {
    "id": 1,
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "name": "John Doe",
    "email": "user@example.com",
    "role": "user",
    "created_at": "2024-01-01T00:00:00.000000Z"
  }
}
```

## Update Profile

Update the authenticated user's profile:

```bash
curl -X PATCH https://your-domain.com/api/v1/auth/me \
  -H "Authorization: Bearer 1|abc123def456..." \
  -H "Content-Type: multipart/form-data" \
  -F "name=Jane Doe" \
  -F "preferences[audio_quality]=high" \
  -F "preferences[crossfade]=5"
```

### Update Profile Fields

| Field | Type | Description |
|-------|------|-------------|
| `name` | string | Display name (max 255 chars) |
| `preferences.audio_quality` | string | Audio quality preference (see below) |
| `preferences.crossfade` | integer | Crossfade duration: 0, 3, 5, or 10 seconds |

### Audio Quality Settings

| Value | Bitrate | Use Case |
|-------|---------|----------|
| `raw` | Original | No transcoding, streams file as-is (FLAC, high-bitrate MP3, etc.) |
| `auto` | Adaptive | Adjusts based on network conditions |
| `high` | 320 kbps | Best lossy quality, higher bandwidth |
| `normal` | 256 kbps | Good quality, moderate bandwidth (default) |
| `low` | 128 kbps | Data saver, for slow/metered connections |

When transcoding is requested, files are converted to the target bitrate. With `raw`, files stream at their original quality without any conversion.
| `avatar` | file | Profile image (max 2MB) |
| `current_password` | string | Required when changing password |
| `password` | string | New password (min 8 chars) |
| `password_confirmation` | string | Must match password |

### Change Password

```bash
curl -X PATCH https://your-domain.com/api/v1/auth/me \
  -H "Authorization: Bearer 1|abc123def456..." \
  -H "Content-Type: application/json" \
  -d '{
    "current_password": "old-password",
    "password": "new-password",
    "password_confirmation": "new-password"
  }'
```

**Response:**

```json
{
  "data": {
    "id": 1,
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "name": "Jane Doe",
    "email": "user@example.com",
    "role": "user",
    "created_at": "2024-01-01T00:00:00.000000Z"
  }
}
```

## Error Responses

### Invalid Credentials (401)

```json
{
  "message": "The provided credentials are incorrect."
}
```

### Missing/Invalid Token (401)

```json
{
  "message": "Unauthenticated."
}
```

### Insufficient Permissions (403)

```json
{
  "message": "This action is unauthorized."
}
```

## Security Best Practices

1. **Store tokens securely** - Never expose tokens in URLs or client-side code
2. **Use HTTPS** - Always use encrypted connections in production
3. **Logout when done** - Revoke tokens when users log out
4. **One token per device** - Generate separate tokens for each device/session
