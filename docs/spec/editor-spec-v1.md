# Editor v1.0 — Backend Technical Specification

Status: implemented (v1.0 backend). Scope: backend only. Deletion policy: RESTRICT + API 409 (not CASCADE).

## 1. Goal
Implement reliable private chapter drafts, optimistic concurrency control, explicit immutable revisions, and restoration. No frontend, publishing, AI, collaboration, WebSockets, or offline sync.

## 2. Existing foundation
- Laravel 13, PHP 8.4, PostgreSQL 17, Redis 7; Docker local/production.
- Existing Identity (App\Models\User, Sanctum) and Library (works, volumes, chapters).
- Preserve project conventions, `AGENTS.md`, `.cursor/rules/*`, and Library API contract.
- Prefer Laravel-native module structure at `app/Modules/Editor`; no new dependencies unless justified.

## 3. Module boundaries
- Library owns chapters and their structure. Editor owns only document content and revisions.
- Editor must check chapter existence and ownership via a narrow, documented Library-facing contract (prefer existing public Action/Service; introduce minimal query contract only if required).
- No direct writes to Library tables. Avoid circular module dependencies.
- Chapter deletion must not leave orphaned Editor data. Before implementation, inspect existing Library delete behavior and choose one explicit, tested integration strategy (database FK cascade where suitable, or Library-owned deletion orchestration). Do not introduce a reverse dependency from Library to Editor without documenting it. If deletion semantics conflict, request approval.

## 4. Data model
### chapter_drafts
- `chapter_id` UUID PK and FK to chapters.id (ON DELETE CASCADE if compatible with existing Library behavior)
- `content_json` JSONB NOT NULL, valid initial empty ProseMirror doc
- `plain_text` TEXT NOT NULL DEFAULT '' (derived server-side)
- `version` BIGINT NOT NULL DEFAULT 1, CHECK version > 0
- `created_at`, `updated_at`

### chapter_revisions
- `id` UUIDv7 PK
- `chapter_id` UUID FK to chapters.id (ON DELETE CASCADE if consistent with deletion policy)
- `content_json` JSONB NOT NULL
- `plain_text` TEXT NOT NULL
- `source_version` BIGINT NOT NULL
- `created_by` UUID FK to users.id (choose appropriate deletion policy)
- `created_at` timestamp
- Optional short `label` VARCHAR(120) nullable
- Index (`chapter_id`, `created_at`, `id`) for pagination
- Revisions immutable after creation (enforce in application, test)

Do not store HTML as source of truth. No duplicate document in Library.

## 5. Document format and validation
- Canonical Tiptap/ProseMirror JSON: root `{ "type": "doc", "content": [...] }`.
- v1 allow-list of nodes: doc, paragraph, text, heading (levels 1–3), bulletList, orderedList, listItem, blockquote, hardBreak, horizontalRule. Marks: bold, italic, strike, code, link (only http/https; reject unsafe protocols). Align exact schema with intended Tiptap extensions and document it.
- Reject unknown nodes/marks/attributes rather than silently dropping text. Bound nesting depth, node count, document bytes (initial recommendation: <= 1 MiB JSON), plain text length (initial recommendation: <= 200,000 Unicode characters); make limits configurable.
- Validate structure recursively and prevent pathological input. Do not trust client-supplied plain text, counts or HTML.
- Derive `plain_text` deterministically on server, including appropriate newlines between blocks; count characters with Unicode-aware logic. Document word-count convention (Unicode segmentation; consistent for Cyrillic). Avoid expensive external packages until needed.
- Empty valid document is allowed.

## 6. API (under `/api/v1`, Sanctum protected)
- `GET /chapters/{chapter}/draft`: lazy-create an empty draft if absent, or return an explicit empty draft representation; choose one consistent behavior and document it. Response: chapter_id, content, version, updated_at, stats.
- `PUT /chapters/{chapter}/draft`: request `{ content: object, version: integer }`. Full replacement, not patch. On success return new version, updated_at, stats. Requires authenticated owner.
- `GET /chapters/{chapter}/revisions`: paginated newest first, metadata only (id, source_version, label, created_at), no full document bodies.
- `POST /chapters/{chapter}/revisions`: create an immutable snapshot of current draft, optional label. Must be idempotent against concurrent retries when client supplies an `Idempotency-Key`, or explicitly document simpler semantics for v1; do not claim retry safety without implementation.
- `GET /chapters/{chapter}/revisions/{revision}`: return revision content and metadata; ensure revision belongs to chapter.
- `POST /chapters/{chapter}/revisions/{revision}/restore`: require `{ version: integer }`, restore content into draft as a NEW version (never modify revision); return updated draft. Do not auto-publish.
- For another user's chapter/revision, return 404 consistently with Library. Unauthenticated 401. Invalid document 422. Stale version 409 with safe current version and guidance, without overwriting content.
- Avoid returning entire documents in list endpoints.

## 7. Concurrency and atomicity
- All draft writes are atomic, including JSON, plain text, version and updated_at.
- Enforce compare-and-swap at database level (`UPDATE ... WHERE chapter_id=? AND version=?`) or row-lock transaction with exact version check; affected rows must equal one. Handle lazy creation races with DB uniqueness and retry/read.
- On 409 never change draft; two simultaneous saves with same version must yield one success and one conflict.
- Restore obeys same concurrency rule.
- Revision creation snapshots a consistent draft version in a transaction; no partial revision.
- Do not implement merge or last-write-wins.

## 8. Security and privacy
- All routes owner-only, including nested revision lookup; foreign resources return 404.
- Enforce rate limiting on write endpoints, payload size limits in app and Nginx (consistent), and request validation.
- Treat document JSON as untrusted. Never render arbitrary HTML or permit javascript: links.
- Do not log full manuscript content, revision bodies, auth tokens or sensitive request payloads.
- Do not include draft content in publicly exposed Library resources.

## 9. Tests
- Draft creation/first read and empty document.
- Owner-only GET/PUT/list/create/get/restore; foreign chapter and foreign revision -> 404.
- Valid Unicode/Cyrillic text and stats; nested allowed formatting.
- Invalid root, unknown nodes/marks, malformed nesting, unsafe links, oversized/deep docs -> 422.
- Successful update increments version once; stale update -> 409 and unchanged content.
- Two concurrent updates with same version: exactly one succeeds (at least deterministic integration test; process-level stress test optional).
- Snapshot immutable, paginated list excludes content; restoration increments version and leaves revision unchanged.
- Library chapter deletion cleans up Editor data; no orphan records.
- PostgreSQL integration tests, Pest, Pint, PHPStan; full `make quality` and production image build at end.

## 10. Implementation stages
I. Review Library chapter ownership/deletion contracts; finalize minimal integration and document schema/limits.
II. Migrations, models, JSON validator/plain-text extractor, focused unit/DB tests.
III. Draft read/save with CAS, owner authorization, validation and concurrency tests.
IV. Revisions and restore, nested scoping, pagination and tests.
V. API documentation `docs/api/editor.md`, update architecture/current-state, run full checks and production build.

## 11. Acceptance criteria
- Existing Foundation/Identity/Library/Example tests remain green.
- No user can access another user's drafts or revisions.
- A stale or concurrent save cannot silently overwrite another save.
- Revision history is immutable and restore does not publish.
- Content JSON is validated and plain text is server-derived.
- Chapter deletion lifecycle is explicit and tested.
- Production image builds, `make quality` passes, API documented.

## 12. Cursor token efficiency
- Read `AGENTS.md`, relevant Library contracts and only touched source files; avoid broad repo dumps.
- Short implementation plan and concise per-stage report (result/checks/issues).
- Reuse standard Laravel patterns; do not generate unused abstractions or docs.
- Run focused tests during development and full quality suite before completion.
- Stop for approval only on architectural contradictions, destructive changes or ambiguous deletion semantics.
