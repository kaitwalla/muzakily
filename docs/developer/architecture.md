# Architecture Overview

Muzakily is a single-library, multi-user music streaming application built with Laravel and Vue.js.

## System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         Clients                                  │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐        │
│  │   Web    │  │  Mobile  │  │ Desktop  │  │   API    │        │
│  │  (Vue)   │  │  (PWA)   │  │  Client  │  │  Client  │        │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘  └────┬─────┘        │
└───────┼─────────────┼─────────────┼─────────────┼───────────────┘
        │             │             │             │
        └─────────────┴──────┬──────┴─────────────┘
                             │
                      ┌──────▼──────┐
                      │   Nginx     │
                      │  (Reverse   │
                      │   Proxy)    │
                      └──────┬──────┘
                             │
        ┌────────────────────┼────────────────────┐
        │                    │                    │
┌───────▼───────┐    ┌───────▼───────┐    ┌───────▼───────┐
│    Laravel    │    │    Laravel    │    │   Laravel     │
│     API       │    │    Queue      │    │   Scheduler   │
│   Servers     │    │   Workers     │    │               │
└───────┬───────┘    └───────┬───────┘    └───────────────┘
        │                    │
        └────────┬───────────┘
                 │
    ┌────────────┼────────────┬────────────┐
    │            │            │            │
┌───▼───┐  ┌─────▼─────┐  ┌───▼───┐  ┌─────▼─────┐
│  DB   │  │   Redis   │  │  R2   │  │Meilisearch│
│(Postgres)│  │  (Cache/  │  │(Files)│  │ (Search)  │
│       │  │  Queues)  │  │       │  │           │
└───────┘  └───────────┘  └───────┘  └───────────┘
```

## Technology Stack

### Backend

| Component | Technology | Purpose |
|-----------|------------|---------|
| Framework | Laravel 12 | API, business logic |
| PHP | 8.3+ | Runtime |
| Database | PostgreSQL | Primary data store |
| Cache | Redis | Caching, sessions |
| Queue | Redis | Background jobs |
| Storage | Cloudflare R2 | Audio file storage |
| Search | Meilisearch | Full-text search |
| Auth | Laravel Sanctum | API authentication |
| Real-time | Pusher | Remote player control |

### Frontend

| Component | Technology | Purpose |
|-----------|------------|---------|
| Framework | Vue 3 | UI framework |
| Language | TypeScript | Type-safe JavaScript |
| State | Pinia | State management |
| Router | Vue Router | SPA routing |
| Build | Vite | Build tooling |
| Styling | Tailwind CSS | Utility-first CSS |

## Directory Structure

```
muzakily/
├── app/
│   ├── Console/           # Artisan commands
│   ├── Events/            # Event classes
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/V1/    # API controllers
│   │   ├── Middleware/
│   │   ├── Requests/      # Form requests
│   │   └── Resources/     # API resources
│   ├── Jobs/              # Queue jobs
│   ├── Listeners/         # Event listeners
│   ├── Models/            # Eloquent models
│   ├── Policies/          # Authorization policies
│   ├── Providers/         # Service providers
│   └── Services/          # Business logic
├── config/                # Configuration files
├── database/
│   ├── factories/         # Model factories
│   ├── migrations/        # Database migrations
│   └── seeders/           # Database seeders
├── resources/
│   └── js/                # Vue frontend
│       ├── api/           # API client modules
│       ├── components/    # Vue components
│       ├── composables/   # Vue composables
│       ├── router/        # Vue Router config
│       ├── stores/        # Pinia stores
│       └── views/         # Page components
├── routes/
│   ├── api/
│   │   └── v1.php         # API routes
│   └── web.php            # Web routes
├── tests/
│   ├── Contracts/         # API contract tests
│   ├── Feature/           # Feature tests
│   ├── Unit/              # Unit tests
│   └── js/                # Frontend tests
└── docs/                  # Documentation
```

## Request Flow

### API Request

```
1. Request → Nginx
2. Nginx → Laravel (PHP-FPM)
3. Route matching
4. Middleware (auth, rate limiting)
5. Controller
6. Service layer (business logic)
7. Model (database operations)
8. Resource (response formatting)
9. JSON Response
```

### Audio Streaming

```
1. Client requests stream URL
2. Laravel generates presigned R2 URL
3. Client receives URL
4. Client fetches audio directly from R2
5. Playback begins
```

### Background Job

```
1. Controller dispatches job
2. Job added to Redis queue
3. Queue worker picks up job
4. Job executes (e.g., metadata extraction)
5. Database updated
6. Search index updated (if needed)
```

## Key Design Decisions

### Single Library

All users share the same music library. This simplifies:
- Storage management
- Library scanning
- Search indexing

### API-First

All functionality exposed via REST API:
- Enables mobile apps
- Separation of concerns
- Testable endpoints

### Stateless API

Using Sanctum tokens instead of sessions:
- Scalable
- Mobile-friendly
- Easy to test

### Background Processing

Heavy operations run in background:
- File upload processing
- Library scanning
- Metadata enrichment

### Smart Playlists

Dynamic playlist evaluation:
- Rules stored in database
- Songs evaluated on request
- No denormalization needed

## Security Model

### Authentication

- Sanctum bearer tokens
- Password hashing (bcrypt)
- Token revocation on logout

### Authorization

- Policy-based authorization
- Role-based access (admin/user)
- Resource ownership checks

### Data Protection

- Presigned URLs for file access
- Time-limited streaming URLs
- No direct storage access
