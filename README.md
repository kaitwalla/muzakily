<p align="center">
  <img src="muzakily-logo.png" alt="Muzakily" width="200">
</p>

<h1 align="center">Muzakily</h1>

<p align="center">
  A self-hosted music streaming application with smart playlists, multi-device sync, and a modern Vue.js interface.
</p>

## Features

- **Music Library Management** - Automatic scanning and organization from cloud storage
- **Smart Playlists** - Dynamic playlists based on rules (genre, year, play count, etc.)
- **Smart Folders** - Automatic organization based on file structure
- **Tagging System** - Hierarchical tags with auto-assignment patterns
- **Full-Text Search** - Fast, typo-tolerant search powered by Meilisearch
- **Remote Player Control** - Control playback across devices via Pusher
- **Multi-Format Support** - MP3, AAC, FLAC with on-demand transcoding
- **Modern SPA** - Vue 3 + TypeScript frontend with responsive design

## Tech Stack

### Backend

- Laravel 12
- PostgreSQL
- Redis (cache, queues, sessions)
- Cloudflare R2 (S3-compatible storage)
- Meilisearch (full-text search)
- Laravel Sanctum (API authentication)
- Pusher (real-time events)

### Frontend

- Vue 3 + TypeScript
- Pinia (state management)
- Vue Router
- Tailwind CSS
- Vite

## Requirements

- Docker & Docker Compose
- Node.js 20+

## Quick Start

### 1. Clone and Configure

```bash
git clone https://github.com/your-org/muzakily.git
cd muzakily
cp .env.example .env
```

Edit `.env` with your configuration (see [Configuration](docs/developer/configuration.md)).

### 2. Start Services

```bash
docker compose up -d
```

### 3. Install Dependencies

```bash
docker compose exec app composer install
npm install
```

### 4. Initialize Database

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

### 5. Start Development

```bash
npm run dev
```

Access the application at http://localhost

### Default Credentials

| Email | Password | Role |
|-------|----------|------|
| admin@example.com | password | Admin |
| user@example.com | password | User |

## Development Commands

```bash
# Run PHP tests
docker compose exec app php artisan test

# Run PHPStan (level 6)
docker compose exec app ./vendor/bin/phpstan analyse --level=6

# Run frontend tests
npm run test

# Type check
npm run type-check

# Build for production
npm run build
```

## Documentation

### User Guide

- [Getting Started](docs/user-guide/getting-started.md)
- [Browsing Library](docs/user-guide/browsing-library.md)
- [Playlists](docs/user-guide/playlists.md)
- [Smart Playlists](docs/user-guide/smart-playlists.md)
- [Smart Folders](docs/user-guide/smart-folders.md)
- [Tags](docs/user-guide/tags.md)
- [Search](docs/user-guide/search.md)
- [Remote Control](docs/user-guide/remote-control.md)
- [Mobile Access](docs/user-guide/mobile-apps.md)

### API Documentation

- [OpenAPI Specification](docs/api/openapi.yaml)
- [Authentication](docs/api/authentication.md)
- [Error Handling](docs/api/errors.md)
- [Endpoints](docs/api/endpoints/)

### Developer Guide

- [Architecture](docs/developer/architecture.md)
- [Setup](docs/developer/setup.md)
- [Configuration](docs/developer/configuration.md)
- [Testing](docs/developer/testing.md)
- [Contributing](docs/developer/contributing.md)

## Project Structure

```
muzakily/
├── app/
│   ├── Http/Controllers/Api/V1/  # API controllers
│   ├── Models/                    # Eloquent models
│   └── Services/                  # Business logic
├── database/
│   ├── factories/                 # Model factories
│   └── migrations/                # Database migrations
├── docs/                          # Documentation
├── resources/js/                  # Vue frontend
│   ├── api/                       # API client
│   ├── components/                # Vue components
│   ├── stores/                    # Pinia stores
│   └── views/                     # Page components
├── routes/api/                    # API routes
└── tests/                         # PHPUnit tests
```

## API Overview

All API endpoints are prefixed with `/api/v1/` and require authentication via Bearer token.

| Endpoint | Description |
|----------|-------------|
| `POST /auth/login` | Authenticate and get token |
| `GET /songs` | List/search songs |
| `GET /songs/{id}/stream` | Get streaming URL |
| `GET /albums` | List albums |
| `GET /artists` | List artists |
| `GET /playlists` | List user playlists |
| `GET /search` | Full-text search |
| `POST /player/control` | Remote player control |

See [API Documentation](docs/api/openapi.yaml) for complete reference.

## Environment Variables

Key configuration options:

| Variable | Description |
|----------|-------------|
| `DB_*` | PostgreSQL connection |
| `REDIS_*` | Redis connection |
| `R2_*` | Cloudflare R2 storage |
| `MEILISEARCH_*` | Search engine |
| `PUSHER_*` | Real-time events |

See [Configuration Guide](docs/developer/configuration.md) for all options.

## License

MIT License - see [LICENSE](LICENSE) for details.
