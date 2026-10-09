# Backend Foundation — Technical Specification v1.0

Status: Approved for implementation planning  
Scope: backend and infrastructure only  
Architecture: Laravel-native modular monolith

## 1. Goal and acceptance criteria

Create a reproducible backend foundation for a platform where authors publish serial literary works. No product features are implemented in this phase except a small demonstration module. No frontend, mobile app, SEO, AI integrations, monetization or user-facing social functionality.

Done when:
- A clean checkout starts locally with documented commands.
- The same application image can be deployed with production Compose configuration.
- PostgreSQL, Redis, Nginx, PHP-FPM and a queue worker are configured and health-checked appropriately.
- Database migrations run explicitly and reliably; deployment does not race migrations.
- An example module exposes a working versioned JSON API endpoint with validation, database persistence, authorization where relevant, and tests.
- CI runs formatting verification, static analysis and tests.
- Architecture and agent instructions are committed and match the actual code.
- Secrets are not committed or baked into images.

## 2. Baseline technology

- PHP 8.4; Laravel 12 as an initial baseline, verify maintained supported releases and package compatibility before installation.
- PostgreSQL 17; Redis 7; Nginx; PHP-FPM.
- Docker multi-stage builds; Docker Compose for both local and single-server production.
- Pest, PHPStan + Larastan, Laravel Pint.
- REST JSON API, versioned under `/api/v1`.
- Composer lock file committed. Container image tags/digests pinned for reproducibility; security updates planned.
- No separate Python/FastAPI service in v1.

## 3. Repository layout

```text
repository/
├── app/                         # standard Laravel tree
│   ├── Http/                    # global HTTP concerns
│   ├── Models/User.php          # leave default model in place initially
│   ├── Modules/
│   │   └── Example/
│   │       ├── Actions/
│   │       ├── Http/Controllers/
│   │       ├── Http/Requests/
│   │       ├── Http/Resources/
│   │       ├── Models/
│   │       ├── Database/Migrations/
│   │       ├── routes.php
│   │       └── ExampleServiceProvider.php
│   └── Providers/
├── bootstrap/
├── config/
├── database/
├── routes/
├── tests/
├── docker/
│   ├── nginx/
│   └── php/
├── docs/
│   ├── architecture.md
│   ├── conventions.md
│   ├── deployment.md
│   ├── current-state.md
│   └── decisions/
├── .cursor/rules/
├── .github/workflows/
├── AGENTS.md
├── compose.yaml
├── compose.dev.yaml
├── compose.prod.yaml
├── Dockerfile
├── .env.example
├── Makefile
└── README.md
```

Do not generate empty directories or unused abstractions. Preserve Laravel's default `bootstrap`, `config`, `database`, `routes`, `storage` and other framework conventions. Example module may be removed or adapted once real product modules exist.

## 4. Modular monolith rules

1. Organize business capabilities into modules under `app/Modules`.
2. Use thin controllers, Form Requests for HTTP validation, API Resources for output and Actions for nontrivial use cases.
3. Use Eloquent directly where appropriate. No mandatory repository, command bus, DTO or four-layer Clean Architecture scaffold.
4. Each module owns its business rules and writes to its own tables. Cross-module reads and relationships may be permitted when simple and explicit, but cross-module state changes must go through a documented public application service/action.
5. Avoid circular module dependencies. Modules may expose public services/contracts, events and read-only DTOs when needed.
6. Use Laravel policies and authorization checks for protected operations; never rely on route hiding as authorization.
7. Use Laravel events/jobs for side effects; queue work after DB commit where needed, and design retryable handlers to be idempotent.
8. Prefer framework-native DI and providers. No custom service locator or framework replacement.
9. Shared code must be technical and broadly reusable, not a dumping ground for business logic.
10. Changes to module boundaries or public contracts require an ADR and review.

## 5. Module registration

- Register each module's service provider through Laravel's supported provider bootstrap mechanism.
- Module provider loads module-specific routes and migrations using standard Laravel APIs.
- Keep route naming/prefixing consistent: `/api/v1/...`; avoid double API prefixing from framework routing.
- Providers must not perform migrations, network calls or destructive startup side effects.
- Add a test that confirms example module routes are registered and that migrations can run from a fresh database.

## 6. API conventions

- JSON requests and responses, UTF-8, consistent HTTP status codes.
- Versioned routes: `/api/v1`.
- Laravel-native validation error format unless a documented project-wide alternative is adopted.
- Use API Resources for public response representations; do not expose entire Eloquent models by default.
- Pagination uses a single documented convention; default Laravel pagination is acceptable.
- Use standard exceptions and centralized rendering for predictable production errors. Never leak stack traces or secrets.
- Authentication is not implemented in foundation except where needed for a demonstration. Future choice: Laravel Sanctum and Policies; document but do not build a full auth system now.
- API contract tooling (OpenAPI) can be introduced with first real endpoints; do not maintain fake specifications.

## 7. Database and persistence

- PostgreSQL as the only relational database.
- Migrations are versioned and committed; run via an explicit one-off deployment command.
- Use foreign keys, indexes and constraints based on real use cases.
- Prefer bigint identity IDs for framework-native simplicity in v1; defer UUIDv7 until actual entity and public-ID requirements are defined.
- UTC timestamps for persisted application times.
- Use DB transactions for multi-step consistency; avoid holding transactions across network requests.
- For module migrations, use `loadMigrationsFrom` or another documented supported mechanism and test `migrate:fresh` on an empty test DB.
- Do not create speculative production tables for works, chapters, revisions or subscriptions during foundation.

## 8. Docker and environments

Services: `nginx`, `app`, `worker`, `postgres`, `redis`.

- One production-capable PHP application image used by app and worker; distinct commands and process lifecycles.
- Multi-stage Dockerfile; Composer dependencies installed during build; no dev dependencies in production stage.
- `compose.yaml` holds shared settings; `compose.dev.yaml` and `compose.prod.yaml` hold environment differences.
- Local dev: bind-mount source, enable useful development tooling, publish HTTP port and optionally localhost-bound database ports.
- Production: immutable built image, no source bind mounts, no public PostgreSQL/Redis ports; only Nginx exposes HTTP to intended network.
- Store PostgreSQL in named persistent volume; Redis persistence optional depending on chosen queue durability guarantees, document data-loss implications.
- Set restart policies, healthchecks, resource considerations and graceful stop timeouts.
- Container process runs without root where feasible; explicitly handle `storage` and `bootstrap/cache` permissions.
- App encryption key and database credentials injected at runtime, never built into image or committed.
- Production `APP_DEBUG=false`, trusted proxies/headers configured for real reverse proxy setup.
- Health endpoint should verify basic app liveness without exposing sensitive configuration; readiness checks may include required dependencies, but avoid expensive checks.
- No automatic `migrate --force` from every application container on startup.
- Worker uses `queue:work` with defined retries, backoff, timeout, and `stop_grace_period`; deploy restarts workers safely.
- A scheduler service is added only when scheduled jobs exist.

## 9. Configuration and secrets

- Commit `.env.example` with placeholder values only; exclude actual `.env` files from Git.
- Distinguish development and production configuration clearly.
- Do not use production credentials locally.
- Document app key generation, first-run setup and secret rotation considerations.
- Use Redis for cache and queues; avoid making session storage assumptions until auth is designed.

## 10. Tests and code quality

- Pest: unit and feature tests. Test HTTP validation, successful write/read and database rollback/isolation for the example module.
- CI provisions PostgreSQL and Redis, runs migrations and tests with a dedicated test environment.
- Laravel Pint in check mode; PHPStan/Larastan at a practical baseline level (increase progressively; no mass ignores).
- Add a dependency boundary check later if module count and complexity justify it; do not configure Deptrac merely to claim compliance without meaningful rules.
- Prefer deterministic tests and factories. No tests relying on external services.
- CI checks composer validation and security-relevant configuration where feasible.

## 11. Example module

Implement a deliberately small `Example` module to validate the pattern end to end, e.g. `notes` or `samples` with `id`, `title`, `created_at`, `updated_at`.

Requirements:
- Route registration from module provider.
- Form Request validation for creation.
- Action encapsulating write operation.
- Eloquent model and module migration.
- API Resource for response.
- Feature tests: success, invalid input, fetch not found.
- Clearly mark module as architectural demonstration, not product functionality.

## 12. CI and deployment

CI on pull requests:
1. Install PHP dependencies from lock file.
2. Run Pint check.
3. Run PHPStan/Larastan.
4. Run tests with PostgreSQL and Redis.
5. Build Docker image to catch Dockerfile regressions.

Production single-server deployment (documented, not necessarily fully automated in first iteration):
1. Back up database before risky schema changes.
2. Build/pull a versioned image.
3. Supply production secrets through server configuration.
4. Run one-off migrations explicitly.
5. Start/update app, Nginx and worker services.
6. Verify health endpoint and logs; document rollback constraints for non-backward-compatible migrations.

Document backups and restoration testing; do not claim a named Docker volume is a backup.

## 13. Agent operating rules

`AGENTS.md` is a short index, not a copy of the entire spec. Agent reads relevant files before changing code.

- Before implementation: inspect current code, relevant module docs, conventions and current state.
- For substantial work: propose file-level plan and identify schema/API impacts.
- Make small coherent changes; avoid unrelated refactoring.
- Prefer native Laravel patterns; avoid speculative abstraction.
- Run appropriate tests and static analysis, report commands and actual results.
- Never claim tests passed unless executed.
- Update docs when contracts, architecture or deployment steps change.
- Do not modify deployment secrets or run destructive production commands.
- Do not introduce a new dependency without rationale and compatibility verification.
- Do not mark tasks complete while acceptance criteria are unverified.

Suggested `.cursor/rules`: `laravel.mdc`, `modules.mdc`, `testing.mdc`, scoped as appropriate. Avoid duplicating contradictory rules.

## 14. Delivery stages

### Stage A — Bootstrap
- Fresh Laravel app, composer lock, .env.example, README.
- Dockerfile, Compose base/dev/prod, Nginx and PHP runtime configuration.
- Local startup and health endpoint.

### Stage B — Modular infrastructure
- Module provider registration mechanism.
- One working Example module, migration, endpoints and tests.

### Stage C — Quality and agent context
- Pest, PHPStan/Larastan, Pint, GitHub Actions.
- AGENTS.md, Cursor rules, architecture/conventions/current-state/deployment docs.
- Verify clean clone -> setup -> run -> test; verify production image builds and starts.

## 15. Explicit non-goals

No frontend; no mobile app; no SEO; no AI provider integration; no real user/works/chapters/social implementation; no payments; no microservices; no Kubernetes; no RabbitMQ/Kafka; no distributed tracing; no elaborate RBAC; no custom framework, generic repository or command bus.

## 16. Open decisions to record (do not block bootstrap)

- Target server OS, CPU architecture, TLS termination and DNS for production.
- Whether production images are built on server or in CI and pushed to registry.
- Storage strategy for uploaded media (local only in dev; S3-compatible later).
- Authentication and public ID conventions when product entities are introduced.
- Backup destination, retention and recovery objectives before storing real user data.

## 17. Initial Cursor task

Read this specification in full. First propose a concise implementation plan, concrete file tree, Docker environment design, provider registration method and verification commands. Do not implement business modules beyond Example. Then implement in stages A/B/C, running and reporting checks after each stage. Favor the simplest Laravel-native implementation that satisfies the stated acceptance criteria. Stop and ask for approval before any major architecture change or new infrastructure service.
