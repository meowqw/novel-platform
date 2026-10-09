# Publishing v1.0 — Backend Technical Specification

Status: implemented (v1.0 backend). Scope: backend only.

## 1. Goal
Enable authors to explicitly publish and unpublish works and chapters, and readers to access only published work metadata and immutable published chapter snapshots. Draft changes must never change public content until an explicit republication. No frontend, catalog search, subscriptions, comments, paid access, scheduled publishing, moderation, or AI.

## 2. Existing architecture
- Laravel 13 / PHP 8.4, PostgreSQL 17, Redis 7, Sanctum; modular monolith.
- Identity owns users; Library owns works/volumes/chapters, ordering and metadata; Editor owns private chapter drafts and revisions.
- Editor uses optimistic locking (`version`), immutable revisions, validated ProseMirror JSON, derived plain text. Library deletion of chapters with Editor data currently returns 409 due to restrictive FKs.
- Follow `AGENTS.md`, `.cursor/rules`, existing API contracts and conventions. Prefer Eloquent, Form Requests, Policies, Resources and focused Actions for transactional publication. No mandatory repositories, buses or generic abstractions.

## 3. Ownership and publication semantics
- Publishing owns **publication state** and **published content snapshots**. It must not write Editor draft/revision data or duplicate Library structural ownership.
- A work must be explicitly published before its chapters can be publicly read. Publishing a work does not automatically publish its chapters.
- A chapter must be explicitly published, and its parent work must currently be published, to be publicly accessible. An unpublished work hides all its chapters, regardless of chapter publication flags.
- Publishing a chapter copies the current validated Editor draft content and derived plain text into a published snapshot. Subsequent autosaves, restores and revisions do not affect that snapshot.
- Republishing a chapter replaces the **current public snapshot** atomically with a new snapshot. Public historical versions are **not** exposed or retained in v1; Editor revisions remain private. Avoid storing duplicate snapshots per publication unless a real requirement appears.
- Unpublishing a chapter hides its snapshot without deleting it; republishing **must explicitly choose** whether to publish the current draft or restore the last published snapshot. For v1, use the simpler rule: the publish endpoint always snapshots the current draft. No automatic republication.
- Unpublishing a work hides all its chapters but does not reset their chapter-level publication state. Re-publishing the work restores public visibility of chapters still marked published. Document this explicitly.
- Volume publication is out of scope: public volume metadata is visible only if it contains at least one publicly readable chapter; ungrouped published chapters remain supported.

## 4. Proposed schema (validate against existing migrations)
### work_publications
- `work_id` UUID primary key, FK `works.id` ON DELETE RESTRICT / NO ACTION.
- `is_published` boolean NOT NULL DEFAULT false.
- `published_at` timestamptz nullable, `unpublished_at` timestamptz nullable; `created_at`, `updated_at`.
- Create lazily or during work creation, consistently; no cross-module write hook unless needed.

### chapter_publications
- `chapter_id` UUID primary key, FK `chapters.id` ON DELETE RESTRICT / NO ACTION.
- `is_published` boolean NOT NULL DEFAULT false.
- `content_json` JSONB nullable until first publication; `plain_text` TEXT nullable until first publication.
- `source_draft_version` BIGINT nullable; `published_at` timestamptz nullable; `unpublished_at` timestamptz nullable; `created_at`, `updated_at`.
- Check constraint: when `is_published = true`, content_json, plain_text, source_draft_version and published_at are not null.
- Consider whether a monotonic `publication_version` is useful for cache invalidation; include only if needed. Do not expose draft version or sensitive editing metadata in public responses.
- Keep one current snapshot per chapter. If a separate immutable snapshot table materially simplifies transactional correctness, propose it with rationale during planning instead of blindly adding tables.

### Important deletion contract
- Existing Library chapter/work delete operations must not silently erase published content. Restrictive FKs are intentional; deleting a chapter/work with publication data should return a handled 409, not a database 500.
- If a publication row exists but was never published, determine whether deletion should still be blocked; prefer avoiding row creation until first publish to keep empty chapters deletable.
- Do not implement cross-module destructive cleanup in v1. Document future trash/soft-delete migration.

## 5. Author API (under `/api/v1`, Sanctum)
- `PUT /works/{work}/publication` body `{ "published": true|false }`: set work publication state. Idempotent; same desired state is a no-op. Only owner; foreign -> 404. Decide and document whether publishing an empty work is allowed (recommend yes, public work may have no readable chapters).
- `GET /works/{work}/publication`: owner-only publication status and published chapter counts.
- `POST /chapters/{chapter}/publish`: explicitly snapshot current Editor draft and mark chapter published. Require draft exists, is valid, and has non-whitespace readable text; otherwise 422/409 with documented code. Owner-only. May be called while work unpublished; chapter remains invisible until work is published (document this behavior).
- `POST /chapters/{chapter}/unpublish`: idempotently hide chapter; retain snapshot for audit/recovery. Owner-only.
- `GET /chapters/{chapter}/publication`: owner-only status, timestamps and snapshot source version; never return private draft via this route.
- Prefer existing route style and error envelope. If proposed paths conflict with Library routes, propose minimal consistent adjustments before implementation.

## 6. Public read API (no Sanctum required)
- `GET /public/works/{work}`: only if work published. Return safe metadata, author public identity, and structure containing **only published chapters**; omit empty volumes unless documented otherwise. Never expose private metadata, Editor draft, revision IDs, private stats or tokens.
- `GET /public/chapters/{chapter}`: only if both chapter and work are published. Return public chapter metadata, published `content_json`, and optional public stats. No draft source/version or revision metadata.
- Public resources may use UUID identifiers in v1. Do not assume work slugs are globally unique: Library slugs are unique **per author**. If supporting slug URLs, use author username + work slug and define ambiguity/rename behavior; otherwise UUID is sufficient for v1.
- Nonexistent, unpublished and inaccessible public resources -> 404. No public list/search/catalog in v1.
- Public work structure must preserve Library ordering, including ungrouped chapters and volumes. Never leak unpublished chapter titles or counts through structure, pagination or errors.

## 7. Transactional correctness and concurrency
- Publishing a chapter must read a consistent Editor draft snapshot and copy content, plain_text and source version together in one transaction. Use row locking / consistent transactional read; concurrent draft save must not yield mismatched content and version.
- Concurrent publish requests for the same chapter must serialize and leave one internally consistent snapshot. Do not hold locks while doing expensive validation; validate as appropriate without trusting stale content.
- Work and chapter publication state transitions should be atomic. If there are cross-table locks, define a consistent lock order and add targeted concurrency tests.
- Public read must never return partially written snapshots; use transactional DB update and one coherent query/result.
- Do not automatically publish after Editor save, revision creation or restore.
- Author operations should not reveal another author's work/chapter; use consistent 404 scoping.

## 8. Validation, safety, and privacy
- Reuse Editor's document validator/extractor rather than inventing a divergent schema. Publishing must not accept arbitrary client-supplied content.
- Require meaningful visible text, not just JSON nodes/whitespace; define Unicode-aware whitespace policy and test Cyrillic.
- Never expose raw HTML; clients must safely render ProseMirror JSON, sanitize links, and avoid injecting untrusted HTML.
- Protect write endpoints with authentication and rate limits. Keep public reads reasonably rate-limited if existing project policy requires it.
- Do not log full manuscript contents, token values or private document bodies.
- Public cache must be disabled in v1 unless explicit invalidation is implemented; avoid stale public snapshots after unpublish. If HTTP cache headers exist, ensure unpublish does not leave content publicly accessible via shared caches.

## 9. Tests (Pest + PostgreSQL)
- Owner can publish/unpublish work and chapter; other owner -> 404; unauthenticated author endpoints -> 401.
- Public endpoints: published work/chapter visible; draft/unpublished work/chapter -> 404.
- Work published with no chapters; chapter published while work hidden; work unpublish/re-publish preserves chapter flags.
- Editor draft changes and restore after publication do not alter public snapshot; explicit republish updates snapshot and source version.
- Publish empty/whitespace-only/missing draft rejected; valid Unicode content accepted.
- Public work structure contains only published chapters, correct order, grouped and ungrouped; no leaked unpublished titles.
- Concurrent draft save/publish and two concurrent publishes cannot mix content/version or produce partial snapshots (at least deterministic integration tests; process stress optional).
- Public responses exclude Editor internals, unpublished metadata, and secrets.
- Deleting a published chapter/work returns handled 409; existing Library/Editor delete behavior remains correct.
- Repeated publish/unpublish behavior is documented and tested. No public cache leakage after unpublish.
- Full `make quality`, production image build, regression tests for Identity/Library/Editor.

## 10. Implementation phases
I. Inspect existing Library/Editor schema, deletion semantics, routes and resources. Present a concise plan and only genuine architectural choices for approval. Do not modify code yet.
II. Publication migrations/models, FK/constraint tests, policies/ownership integration.
III. Author publication endpoints and atomic chapter snapshot, focused tests.
IV. Public work/chapter read endpoints and public-safe Resources, filtering/order tests.
V. Integration, security, concurrency and deletion tests; `docs/api/publishing.md`, update architecture/current-state, `make quality`, production build.

## 11. Acceptance criteria
- Author can publish a work and chapters, and unauthenticated readers can read only explicitly published content.
- Editing/restoring private draft never mutates public chapter content without explicit republish.
- Unpublishing reliably removes public access; no public leakage of private chapter metadata.
- All transitions are authorized, atomic, predictable, documented and tested.
- Existing 47+ tests remain green; Pint/PHPStan/production build pass.

## 12. Cursor context and token efficiency
- Read `AGENTS.md`, relevant Library/Editor contracts, migrations and touched code; avoid full repository dumps.
- Keep plans and reports concise. Report only decisions, checks, blockers and deviations.
- Use focused tests while implementing and full quality suite at the end. Never trade data safety or test coverage for token savings.
- Stop for approval only on real architectural conflicts or destructive data changes.
