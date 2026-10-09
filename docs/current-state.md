# Current state

Last updated: 2026-10-10

## Delivered

- Laravel 13 + PHP 8.4 foundation
- Docker Compose for local (`compose.dev.yaml`, port 8080) and production (`compose.prod.yaml`)
- Services: nginx, app (FPM), worker, PostgreSQL 17, Redis 7
- Health endpoint: `GET /api/v1/health`
- Example module `notes` under `app/Modules/Example`
- Identity module: register/login/logout, profile, email verification, password reset/change, Sanctum PAT management
- Library module: works / volumes / chapter metadata, ordering, owner-only API
- Editor module: chapter drafts (CAS), revisions, restore; document validation via `config/editor.php`
- Publishing module: work/chapter publish state, public snapshots, public read API
- Pest tests, Pint, Larastan (level 6), GitHub Actions CI
- Agent docs: `AGENTS.md`, `.cursor/rules`, architecture/conventions/deployment

## Not in scope yet

- SPA cookie-based Sanctum login UI
- OAuth / 2FA / passkeys
- Public catalog / search / subscriptions / comments / paid access
- Collaborative editing / AI
- Media storage / avatar upload
- Image registry publish from CI
