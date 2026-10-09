# Architecture

Modular monolith on Laravel 13. Business capabilities live under `app/Modules/*`. The HTTP surface is a versioned JSON API under `/api/v1`.

## Runtime

- **nginx** — HTTP entrypoint
- **app** — PHP 8.4-FPM application
- **worker** — `queue:work` on Redis (same image as app); used for queued mail/notifications
- **postgres** — PostgreSQL 17 (persistent volume)
- **redis** — cache and queue (ephemeral; see ADR 0002)

## Modules

Each module owns:

- `*ServiceProvider` registered in `bootstrap/providers.php`
- routes via route group in `boot()`
- migrations via `loadMigrationsFrom` when module-owned (framework/auth tables may live in `database/migrations`)
- HTTP layer: Controllers, Form Requests, API Resources
- nontrivial writes: Actions
- Eloquent models for its tables (exception below)

Rules:

- Thin controllers; no mandatory repositories/DTO/command bus
- Cross-module writes go through a documented public Action/service
- Prefer framework-native DI and providers

## Identity

- Owns registration, authentication, profile, email verification, password reset, and API token management
- Canonical model remains `App\Models\User` (UUIDv7; ADR 0003)
- Auth: Laravel Sanctum personal access tokens (Bearer); SPA cookie mode not enabled yet but not blocked
- Mail: queued notifications via Redis worker; local `MAIL_MAILER=log` or Mailpit
- Other modules may read `User` / `user_id` but must not change credentials directly

## Library

- Owns works, optional volumes, chapter metadata and ordering (no chapter body / publishing)
- `author_id` → `users.id`; owner-only API under Sanctum
- See `docs/api/library.md`

## Editor

- Owns private chapter drafts, CAS versioning, immutable revisions and restore
- Reads Library `Chapter` / `work.author_id` for ownership; no Library writes
- FK `ON DELETE RESTRICT` on drafts/revisions; Library delete returns **409** when editor data exists
- See `docs/api/editor.md` and `config/editor.php`

## Publishing

- Owns work/chapter publication flags and chapter public snapshots (copied from Editor draft)
- Reads Library structure and Editor drafts; does not write Library/Editor tables
- FK `ON DELETE RESTRICT`; delete with publication rows → **409**
- Lock order on chapter publish: `chapters` → `chapter_drafts` → `chapter_publications`
- See `docs/api/publishing.md`

## Catalog

- Owns public catalog listing (`GET /public/works`); read-only joins on Library + Publishing tables
- Visibility: `work_publications.is_published`; empty published works allowed (`published_chapters_count = 0`)
- Cover cards use public media thumb URL (`works.cover_media_id`); no result caching in v1
- See `docs/api/catalog.md`

## Media

- Owns `media_assets`, private disk storage, upload/normalization (Intervention Image + GD), delivery endpoints
- Library owns `works.cover_media_id` (live public cover while work published)
- Editor validates `image` nodes (`mediaId`); Publishing maintains `published_media_references` on chapter publish/unpublish
- Public bytes only when cover is live-published or chapter refs + work/chapter published
- See `docs/api/media.md`

## Example module

`app/Modules/Example` demonstrates the pattern with a `notes` resource. It is an architectural demo, not product functionality.
