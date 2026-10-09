# Conventions

## API

- JSON request/response, UTF-8
- Version prefix: `/api/v1/...`
- Validation errors: default Laravel JSON format
- Public responses: API Resources (do not dump Eloquent models)
- Pagination: default Laravel pagination when needed

## Modules

1. Create `app/Modules/{Name}/{Name}ServiceProvider.php`
2. Register the provider in `bootstrap/providers.php`
3. In `boot()`: `loadMigrationsFrom` + route group with `middleware('api')->prefix('api')`
4. Module routes use `prefix('v1')` so the final path is `/api/v1/...`
5. Keep `register()` free of migrations, network calls, and destructive work

## Code style

- Laravel Pint (default rules)
- PHPStan / Larastan level 6
- Pest for Feature and Unit tests
- Prefer small, coherent changes; avoid speculative abstractions

## Database

- PostgreSQL only
- Bigint identity IDs by default in v1
- Exception: `users.id` and Library entities use UUIDv7 (ADR 0003); Sanctum tokens use `uuidMorphs`
- UTC timestamps
- Migrations run explicitly (`make migrate`), never automatically on every container start
- Destructive `migrate:fresh` is for local/empty databases only — never on production data
