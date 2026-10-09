# Library v1.0 — Technical Specification

Status: ready for implementation planning. Scope: backend only. Baseline: Laravel 13, PHP 8.4, PostgreSQL 17, Redis 7, modular monolith, Identity v1.0.

## 1. Goal and boundaries

Provide authenticated authors with CRUD for works, optional volumes, chapter metadata, ordering, and owner-only management. Do not implement chapter body, autosave, revisions, publishing, reader access, feeds, media upload, AI, or subscription logic. Do not expose a public catalog in this iteration.

## 2. Existing project integration

- Follow `AGENTS.md`, `docs/architecture.md`, `docs/conventions.md`, `docs/api/identity.md`, and existing Example module patterns where relevant.
- Implement in `app/Modules/Library`. Reuse existing module registration, routing, response/error conventions, authentication, UUIDv7 conventions and CI. Do not introduce a second User model.
- No new infrastructure services, packages, repository abstraction, CQRS bus, or custom framework.
- Only create classes that are actually used. Thin controllers, Form Requests, API Resources, focused Actions for meaningful state transitions, Eloquent models, Policies.

## 3. Model and invariants

### works
- `id`: UUIDv7 primary key.
- `author_id`: UUIDv7 foreign key to `users.id`, indexed, restrict deletion by default pending product decision on account deletion.
- `title`: string, 1–200 chars after trimming.
- `description`: nullable text, max 10,000 chars.
- `slug`: string, 1–220 chars, generated from title or supplied explicitly; unique **per author**, not globally. Use a deterministic fallback for titles without Latin transliteration; resolve collisions explicitly (e.g., suffix), not via silent overwrite. Slug is mutable in v1 because no public links are exposed; document future permalink implications.
- `status`: enum-like string: `ongoing`, `completed`, `hiatus` (metadata only; NOT publication state). Default `ongoing`.
- `created_at`, `updated_at`.

### volumes
- `id`: UUIDv7 primary key.
- `work_id`: UUIDv7 FK to works, indexed.
- `title`: string, 1–200 chars.
- `position`: positive integer; unique within work.
- timestamps.

### chapters
- `id`: UUIDv7 primary key.
- `work_id`: UUIDv7 FK to works, indexed.
- `volume_id`: nullable UUIDv7 FK to volumes.
- `title`: string, 1–200 chars.
- `position`: positive integer, unique within `(work_id, volume_id)` conceptually, **including NULL volumes**. PostgreSQL default UNIQUE treats NULL values as distinct; use PostgreSQL 17 `UNIQUE NULLS NOT DISTINCT (work_id, volume_id, position)` or equivalent partial unique indexes. Do not rely on a plain unique index.
- timestamps.

### Domain rules
- A work can have zero volumes and zero chapters.
- A chapter may be ungrouped (`volume_id = null`) even when volumes exist; volumes are optional.
- If a chapter has a volume, that volume MUST belong to the same work. Enforce in application validation and preferably database constraint (e.g., composite FK with suitable unique index), or document why a DB constraint is deferred.
- Chapter title and metadata only; NO `content`, `body`, `published_at`, or `visibility` fields in v1.
- Ownership derives from `works.author_id`; users may only manage their own works, volumes, chapters. Never trust author_id from client.
- Delete work cascades to volumes and chapters. Deleting a volume must NOT delete chapters: choose and implement explicit behavior of moving its chapters to ungrouped, with ordering, in one transaction. Do not rely on `ON DELETE SET NULL` alone to maintain positions.
- Preserve referential integrity and stable ordering under concurrent writes.

## 4. Ordering contract

- `position` starts at 1 and is contiguous within each sibling list. Lists: volumes within a work; chapters within each `(work_id, volume_id)` group, including ungrouped.
- Creation appends to the relevant list. Deletion compacts positions.
- Moving chapters between volumes or ungrouped lists and reordering volumes/chapters are atomic, authenticated, owner-only operations.
- Use transactions and row-level locking at work level (or equivalent serialization) to avoid concurrent reorder collisions. Beware unique-position constraints during swaps: use a safe two-phase renumber strategy or deferrable constraints, with tests.
- Reorder endpoints accept **complete ordered arrays of UUIDs** for the target list; reject duplicates, omissions, foreign IDs and cross-work IDs. A chapter move endpoint takes target `volume_id` (nullable) and target position; it atomically compacts source and inserts in destination.
- For volume deletion, append its chapters to the ungrouped list in their prior order. Define deterministic ordering if other ungrouped chapters exist.

## 5. API v1

All routes under `/api/v1`, authenticated with existing Sanctum middleware. Use existing JSON envelope/error conventions; do not invent a new envelope.

| Method | Path | Purpose |
|---|---|---|
| GET | `/works` | Paginated list of caller's works; newest first |
| POST | `/works` | Create work |
| GET | `/works/{work}` | Owner-only work details with volumes and chapters |
| PATCH | `/works/{work}` | Update title, description, status, slug |
| DELETE | `/works/{work}` | Delete work and nested metadata |
| POST | `/works/{work}/volumes` | Append volume |
| PATCH | `/works/{work}/volumes/{volume}` | Rename volume |
| DELETE | `/works/{work}/volumes/{volume}` | Delete volume and move its chapters to ungrouped |
| PUT | `/works/{work}/volumes/order` | Replace complete volume order |
| POST | `/works/{work}/chapters` | Append chapter; optional volume_id |
| PATCH | `/works/{work}/chapters/{chapter}` | Rename chapter |
| DELETE | `/works/{work}/chapters/{chapter}` | Delete chapter |
| PUT | `/works/{work}/chapters/order` | Replace complete order for one volume or ungrouped list |
| POST | `/works/{work}/chapters/{chapter}/move` | Move chapter to target volume and position |

Use nested scoped bindings or equivalent explicit checks so a volume/chapter from another work cannot be accessed via a different work ID. Unauthorized foreign resources should not leak details; follow existing 403/404 policy consistently. Validate payloads strictly, including array sizes and IDs.

Response includes IDs, title, description/status where relevant, position, volume_id and timestamps. Never include future draft content or private user fields. List endpoint must paginate (default 20, bounded max 100). Use eager loading to prevent N+1 queries. No public read endpoints in v1.

## 6. Code organization

Suggested minimum, adapted to existing project conventions:

```
app/Modules/Library/
  Models/{Work,Volume,Chapter}.php
  Http/Controllers/{Work,Volume,Chapter}Controller.php
  Http/Requests/...
  Http/Resources/...
  Actions/{ReorderVolumes,ReorderChapters,MoveChapter,DeleteVolume}.php
  Policies/WorkPolicy.php
  Database/Migrations/...
  routes.php
  LibraryServiceProvider.php
```

Additional Actions for CRUD only if they simplify nontrivial logic. Avoid a mandatory repository layer. Shared ownership checks should be centralized, not duplicated across controllers. Prefer existing Laravel conventions and dependency injection.

## 7. Database / migration requirements

- All PK/FK types must match Identity UUIDv7 users.
- Index `works(author_id, created_at)`, `volumes(work_id, position)`, `chapters(work_id, volume_id, position)`; enforce appropriate uniqueness and FK integrity.
- Migrations reversible. Do not run `migrate:fresh` against non-disposable data.
- Add factories for tests where useful; use PostgreSQL for tests of NULL uniqueness, FK constraints and ordering behavior (SQLite does not reproduce these semantics).
- Check migration rollback and repeated deployment safety.

## 8. Security and validation

- All endpoints require authentication. Owner-only access for reads and writes.
- Authorization must occur before mutation; nested IDs must belong to their parent work.
- Reject invalid title lengths, status values, malformed UUIDs, duplicate reorder IDs, invalid target volumes, negative/out-of-range positions.
- Use transaction boundaries for multi-row operations; protect against concurrent reorders and stale updates where applicable.
- Rate limiting may reuse existing API defaults; avoid adding bespoke limits without evidence.

## 9. Tests / acceptance

- Unauthenticated requests rejected.
- Author can create, list, view, edit, delete own work.
- Other users cannot view or modify foreign works or nested resources.
- Work without volumes can contain chapters; mixed grouped/ungrouped chapters work.
- Volume and chapter create/delete/reorder/move preserve contiguous positions.
- Deleting a volume moves chapters to ungrouped without data loss.
- Reject cross-work volume_id and nested resource mismatches.
- PostgreSQL NULL-volume uniqueness enforced; duplicate positions impossible.
- Concurrent reorder/move safety covered by meaningful integration tests or explicitly documented test limitation with DB guarantees.
- Pagination and input validation tested; no N+1 for details/list.
- `make quality` passes (Pest, Pint, PHPStan level 6); existing Identity and Example tests still pass.
- `docs/api/library.md`, `docs/architecture.md`, `docs/current-state.md` updated only as needed.

## 10. Implementation stages

I. Inspect existing Foundation/Identity conventions, produce short plan and migration/API design; resolve contradictions before coding.
II. Migrations, models, relations, factories, ownership policy and database tests.
III. Work CRUD API + validation + tests.
IV. Volume/chapter CRUD + optional volumes + tests.
V. Ordering, move, volume deletion behavior + transactional/concurrency tests.
VI. API docs, targeted tests, full `make quality`, production image build and concise report.

Once plan is approved, proceed stages II–VI without extra approvals unless changing public contracts or architecture.

## 11. Cursor token-efficiency policy

- Start with `AGENTS.md`, this spec, module registration conventions and the few relevant source files. Search filenames/symbols first; open only necessary ranges.
- Do not dump the entire repository or reread Identity implementation when a public contract suffices.
- Keep one short implementation plan; no repeated recaps or verbose narration.
- During implementation run targeted tests; run full quality suite once before final report (and again only after necessary fixes).
- Final report: changed areas, test counts/results, deviations/risks, decisions needed; no per-file commentary.
- Never trade correctness, authorization, migrations or test coverage for token savings.

## 12. Explicitly deferred

Editor document content, autosave, revisions, published snapshots, reader-facing catalog, visibility, subscriptions, media, tags/genres, search, ratings, analytics, monetization and AI. Design integration later without adding placeholder abstractions now.

## 13. Decisions requiring attention during implementation

- `slug` strategy for non-Latin titles and collision handling must be deterministic and tested.
- The ordering algorithm must be compatible with actual PostgreSQL uniqueness constraints; verify against PostgreSQL, not just mocks.
- Confirm whether database-level cross-work volume FK is implemented; if not, document application-level guarantee and follow-up.
