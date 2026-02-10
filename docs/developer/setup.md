# Development Setup

Get Muzakily running locally for development.

## Prerequisites

- Docker and Docker Compose
- Node.js 20+
- Git

## Quick Start

### 1. Clone Repository

```bash
git clone https://github.com/your-org/muzakily.git
cd muzakily
```

### 2. Environment Setup

```bash
cp .env.example .env
```

Edit `.env` with your configuration:

```env
APP_NAME=Muzakily
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=muzakily
DB_USERNAME=muzakily
DB_PASSWORD=secret

REDIS_HOST=redis

# R2 Storage (optional for development)
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_BUCKET=muzakily
R2_ENDPOINT=

# Meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=masterKey

# Pusher (optional)
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=
```

### 3. Start Services

```bash
docker compose up -d
```

This starts:
- PHP-FPM (Laravel)
- PostgreSQL
- Redis
- Meilisearch
- Nginx

### 4. Install Dependencies

```bash
# PHP dependencies
docker compose exec app composer install

# Node dependencies
npm install
```

### 5. Initialize Database

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

### 6. Generate App Key

```bash
docker compose exec app php artisan key:generate
```

### 7. Start Frontend Dev Server

```bash
npm run dev
```

### 8. Access Application

- **Web App**: http://localhost
- **API**: http://localhost/api/v1
- **Meilisearch**: http://localhost:7700

## Default Credentials

After seeding:

| Email | Password | Role |
|-------|----------|------|
| admin@example.com | password | Admin |
| user@example.com | password | User |

## Development Workflow

### Running Commands

All PHP commands run through Docker:

```bash
# Artisan
docker compose exec app php artisan <command>

# Composer
docker compose exec app composer <command>

# PHPStan
docker compose exec app ./vendor/bin/phpstan analyse --level=6

# Tests
docker compose exec app php artisan test
```

### Frontend Development

```bash
# Dev server with HMR
npm run dev

# Type checking
npm run type-check

# Build for production
npm run build

# Run tests
npm run test
```

### Code Quality

```bash
# PHPStan (static analysis)
docker compose exec app ./vendor/bin/phpstan analyse --level=6

# PHP tests
docker compose exec app php artisan test

# Frontend tests
npm run test

# Type checking
npm run type-check
```

## Local Storage

For development without R2, configure local filesystem:

```env
FILESYSTEM_DISK=local
```

Place test audio files in `storage/app/music/`.

## Queue Worker

For background job processing:

```bash
docker compose exec app php artisan queue:work
```

Or use the queue container in docker-compose.

## Meilisearch

### Reindex

```bash
docker compose exec app php artisan scout:import "App\Models\Song"
docker compose exec app php artisan scout:import "App\Models\Album"
docker compose exec app php artisan scout:import "App\Models\Artist"
```

### Clear Index

```bash
docker compose exec app php artisan scout:flush "App\Models\Song"
```

## Database

### Fresh Migration

```bash
docker compose exec app php artisan migrate:fresh --seed
```

### Create Migration

```bash
docker compose exec app php artisan make:migration create_table_name
```

## Troubleshooting

### Container Issues

```bash
# Rebuild containers
docker compose build --no-cache

# View logs
docker compose logs -f app

# Shell into container
docker compose exec app bash
```

### Permission Issues

```bash
# Fix storage permissions
docker compose exec app chmod -R 775 storage bootstrap/cache
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
```

### Database Connection

```bash
# Test connection
docker compose exec app php artisan db:show
```

### Clear Caches

```bash
docker compose exec app php artisan config:clear
docker compose exec app php artisan cache:clear
docker compose exec app php artisan route:clear
docker compose exec app php artisan view:clear
```

## IDE Setup

### VS Code

Recommended extensions:
- PHP Intelephense
- Vue Language Features (Volar)
- Tailwind CSS IntelliSense
- ESLint
- Prettier

### PHPStorm

1. Configure Docker interpreter
2. Set up PHPStan for static analysis
3. Enable Vue.js plugin
