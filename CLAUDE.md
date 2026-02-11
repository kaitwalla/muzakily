# Claude Code Project Notes

Essential guidelines for working on this project.

## Commands

**All PHP/Composer/Artisan commands MUST run through Docker Compose:**

```bash
# PHPStan
docker compose exec app ./vendor/bin/phpstan analyse --level=6

# Tests
docker compose exec app php artisan test

# Artisan commands
docker compose exec app php artisan <command>

# Composer
docker compose exec app composer <command>
```

## Git Workflow

- **NEVER commit without asking the user first**
- Always stage specific files, not `git add .` or `git add -A`
- Never push unless explicitly asked

## Dangerous Commands - NEVER RUN

- **NEVER run `php artisan migrate:fresh`** - destroys all data
- **NEVER run `php artisan db:wipe`** - destroys all data
- **NEVER run `php artisan db:fresh`** - destroys all data

## Development Practices

- **Always create tests** - Follow TDD: write tests before implementation
- **PHPStan level 6** - All code must pass static analysis
- Use `ilike` for case-insensitive PostgreSQL queries (not `like`)
- Use null-safe operator (`?->`) when accessing properties that could be null
- Handle `tempnam()` failures (can return `false`)
- Use `firstOrCreate` to handle race conditions in concurrent scenarios
- NEVER re-run coderabbit without explicitly asking

## Project Stack

- Laravel 12 with Sanctum authentication
- PostgreSQL with full-text search (tsvector)
- Cloudflare R2 (S3-compatible storage)
- Vue 3 + TypeScript frontend
- Docker Compose for local development

## Key Directories

- `app/Http/Controllers/Api/V1/` - API controllers
- `app/Services/` - Business logic services
- `app/Models/` - Eloquent models
- `tests/` - PHPUnit tests (Unit, Feature, Contracts)
- `database/factories/` - Model factories for testing
