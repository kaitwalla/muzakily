# Claude Code Project Notes

Essential guidelines for working on this project.

## Commands

**All PHP/Composer/Artisan commands MUST run through Docker Compose. NPM commands run locally:**

```bash
# PHPStan
docker compose exec app ./vendor/bin/phpstan analyse --level=6

# Tests (PHP)
docker compose exec app php artisan test

# TypeScript type check
npm run type-check

# Full test suite (TypeScript + PHP)
npm run type-check && docker compose exec app php artisan test

# Artisan commands
docker compose exec app php artisan <command>

# Composer
docker compose exec app composer <command>
```

**Always run both TypeScript and PHP checks before committing frontend changes.**

## Git Workflow

- **NEVER commit without asking the user first**
- Always stage specific files, not `git add .` or `git add -A`
- Never push unless explicitly asked

## Dangerous Commands - NEVER RUN

- **NEVER run `php artisan migrate:fresh`** - destroys all data
- **NEVER run `php artisan db:wipe`** - destroys all data
- **NEVER run `php artisan db:fresh`** - destroys all data

## Development Practices

- Use the Action pattern in PHP where it makes sense, instead of leaving logic in controllers
- **Always create tests** - Follow TDD: write tests before implementation
- **PHPStan level 6** - All PHP code must pass static analysis
- **TypeScript strict mode** - Run `npm run type-check` for frontend changes
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

## API Contracts

- **All paginated endpoints must use Laravel's standard pagination** (`page`, `per_page` params; `current_page`, `last_page`, `per_page`, `total` in response meta)
- **Contract tests in `tests/Contracts/`** verify response structures that clients depend on
- When adding or modifying API endpoints, add/update contract tests to prevent breaking changes
- Both web (Vue) and mobile (Swift) clients depend on these contracts

## Documentation

**Read the docs before starting work on any issue.** Comprehensive documentation is in `/docs`:

- `docs/developer/` - Architecture, caching, smart playlists, testing strategies
- `docs/api/` - API endpoints, OpenAPI spec, error codes
- `docs/user-guide/` - Feature documentation from user perspective

Key docs to review:
- `docs/developer/smart-playlists.md` - Smart playlist rules, materialization, caching
- `docs/api/endpoints/playlists.md` - Playlist API including tag operators
