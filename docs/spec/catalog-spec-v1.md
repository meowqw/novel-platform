# Catalog v1.0 — Backend Technical Specification

**Status:** ready for agent planning. **Scope:** Laravel backend only. **Baseline:** Laravel 13 / PHP 8.4 / PostgreSQL 17 / Redis 7, modular monolith with Identity, Library, Editor and Publishing. **API prefix:** `/api/v1`.

## 1. Objective

Implement a public, unauthenticated catalog of **published works**, with pagination, title search, work-status filtering, deterministic sorting and safe, lightweight card metadata. Preserve existing author management and public reading APIs. No frontend implementation in this task.

The source of truth for visibility is `Publishing.work_publications.is_published`, **not** `Library.works.status`. `ongoing`, `completed` and `hiatus` describe progress only. Catalog entries can exist without published chapters, because Publishing already permits publishing an empty work. Do not infer visibility from chapter counts.

## 2. Integration and constraints

1. Read `AGENTS.md`, `docs/architecture.md`, `docs/conventions.md`, `docs/api/openapi.yaml`, existing Library/Publishing code and tests before making changes. The repository implementation and migrations take precedence over assumptions in this spec; report real mismatches in the plan.
2. Implement as `app/Modules/Catalog` (or match the repository's actual module layout), consuming existing Library/Publishing models via read-only queries. Do not duplicate work or publication records, introduce an event bus, CQRS framework, search service, Elasticsearch/Meilisearch, new Docker services, or repository abstractions.
3. Do not modify Identity, Editor draft/revisions, or Publishing state transitions. Do not change the meaning or contract of existing `/works`, `/public/works/{work}`, `/public/chapters/{chapter}` routes.
4. Reuse the project's public JSON envelope, validation errors, API Resources, route registration, UUIDv7 and PostgreSQL testing conventions.
5. Catalog endpoints require **no authentication**. A bearer token, if present, must not broaden visibility or change the result.

## 3. Public API contract

### `GET /api/v1/public/works`

A paginated collection of public catalog cards. This is distinct from authenticated `GET /api/v1/works`, which lists the author's own works.

| Query | Type | Default | Rules |
|---|---|---|---|
| `page` | integer | `1` | `>= 1` |
| `per_page` | integer | `20` | `1..50` |
| `q` | string | absent | Trim; Unicode-aware title search; max 100 characters; whitespace-only treated as absent; literal `%`, `_` and `\\` must not become SQL wildcards |
| `status` | enum | absent | `ongoing`, `completed`, `hiatus` |
| `sort` | enum | `recent` | `recent`, `published`, `title` |

Unknown query keys: ignore or reject according to the established API convention; document and test the choice. Invalid recognized values return **422** using the existing validation-error format, not a 500. Query params are combinable.

**Sort semantics:**
- `recent`: newest public activity first. Compute `last_activity_at` as `GREATEST(work_publications.published_at, MAX(chapter_publications.published_at for currently published chapters belonging to the work))`. If chapter publication timestamps are nullable or republish behavior differs in the implementation, normalize without exposing hidden content; exclude unpublished chapters from the activity calculation. Do **not** use `works.updated_at`: private title/description edits should not silently reorder public catalog items. Note that `published_at` represents the existing Publishing field's actual semantics; verify whether it changes on republish.
- `published`: work's `work_publications.published_at` descending.
- `title`: case-insensitive title ascending, using a documented PostgreSQL collation strategy.
- Every sort uses `works.id ASC` as a stable final tie-breaker. Document null handling for `last_activity_at` and timestamps; if a published row lacks `published_at`, treat it as an invariant violation or define a deterministic fallback rather than crashing.

**Search:** title-only case-insensitive substring matching for v1, Cyrillic and Latin supported. Use parameter binding and escape SQL LIKE metacharacters; avoid unsafe string interpolation. Do not claim full-text search, morphology, typo tolerance or relevance ranking. A search with `sort=recent` retains the requested sort rather than inventing a relevance algorithm.

**200 example** (illustrative values only):

```json
{
  "data": [
    {
      "id": "018f0b70-0000-7000-8000-000000000001",
      "title": "Пример истории",
      "description": "Краткое описание",
      "slug": "primer-istorii",
      "status": "ongoing",
      "author": {
        "id": "018f0b70-0000-7000-8000-000000000002",
        "username": "writer",
        "name": "Автор",
        "avatar_url": null
      },
      "cover_url": null,
      "published_at": "2026-10-01T10:00:00Z",
      "last_activity_at": "2026-10-03T10:00:00Z",
      "published_chapters_count": 2
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "per_page": 20, "total": 25, "last_page": 2 }
}
```

The exact public author shape must reuse or safely project the existing `PublicUser` contract. **Never return** email, private bio fields not already public, tokens, Editor drafts, revisions, `source_draft_version`, unpublished chapter titles, chapter content, or unpublished chapter counts. Avoid including entire `volumes`/`chapters` arrays in catalog cards. `published_chapters_count` counts only chapters with `chapter_publications.is_published = true` belonging to a currently published work.

Empty results: **200** with `data: []` and valid pagination metadata. No matches is not a 404.

### Optional `GET /api/v1/public/works?author=<username>`

**Deferred to v1.1** unless an existing frontend requirement already needs it. Do not silently implement a half-specified author filter.

## 4. Cover-image decision

The current Library schema and OpenAPI contract do **not** define work covers or an image upload pipeline. In Catalog v1, return **`cover_url: null`** consistently; reserve the nullable field in the catalog card schema so the frontend can show its neutral placeholder. Do not accept arbitrary remote image URLs or add upload endpoints, filesystem/public disk access, image processing, S3, or a `cover_path` migration as part of this spec.

**Follow-up task (separate spec):** author-only cover upload/replacement/removal, image MIME/content validation, byte and pixel limits, safe re-encoding, storage visibility, URL generation and lifecycle cleanup. Only after that task should catalog cards return real cover URLs. This separation prevents building an insecure ad hoc upload API.

## 5. Query implementation and performance

- Filter visibility in SQL, before pagination, using `work_publications.is_published = true`. Use a join or `whereHas` appropriate to the actual schema; avoid fetching all works into PHP and filtering afterward.
- Calculate chapter count and recent activity with scoped aggregate queries/subqueries; do not join raw chapters in a way that duplicates work rows or inflates pagination totals.
- Eager load only safe public author data and required aggregates. No N+1 queries; no Editor draft or published chapter JSON bodies loaded for list responses.
- Cap page size at 50; use normal Laravel length-aware pagination (`data`, `links`, `meta`) matching the existing `/works` style. Preserve recognized query parameters in pagination links.
- Verify indexes against real migrations: existing `work_publications` primary key on `work_id`, plus an index beginning with `is_published` and including `published_at` if the query planner benefits; existing `chapter_publications.chapter_id` and `chapters.work_id`. Add only justified, reversible indexes. Title substring search using `%term%` is acceptable for small v1 data sets; document the threshold for later `pg_trgm` rather than enabling it by default.
- No Redis/result caching in v1. Publication/unpublication must affect the next request immediately; do not accidentally cache a previously public result.
- Use bounded query complexity and reasonable existing rate limiting for anonymous endpoints. Return no SQL errors or internal details.

## 6. Visibility and concurrency invariants

- A work appears iff it is currently published, even if it has zero published chapters.
- A published chapter under an unpublished work never makes that work visible.
- Unpublishing a work removes it from **all** catalog searches, filters and counts on the next committed request.
- Publishing a chapter while its work is visible updates public count and recent activity; unpublishing a chapter removes it from count and activity aggregation. Private draft edits and revision restores do not change catalog activity.
- A single query should provide coherent filtering/count/ordering as far as PostgreSQL's default transaction isolation permits; do not promise stronger snapshot guarantees without implementing them.
- Slugs are only unique **per author**; use work UUIDs for card links and identifiers. Never resolve catalog cards by globally ambiguous slug.
- `status=hiatus` does not mean unpublished. Do not add moderation, hidden, deleted or age-rating flags that do not exist in the data model.

## 7. OpenAPI and docs

Update `docs/api/openapi.yaml` (OpenAPI 3.1) with:
- `GET /public/works`, parameters, 200/422/429 responses;
- `PublicCatalogWork`, `PaginatedPublicCatalogWorks` schemas and nullable `cover_url`;
- precise `recent`/`published`/`title` definitions and publication visibility;
- one realistic example including Cyrillic text and an empty-results example.

Do not replace or redefine existing `PublicWork`, `PublicChapter` or `PaginatedWorks` schemas. Add `docs/api/catalog.md` describing examples, sorting, publication rules, limitations, and how the frontend should link to `/read/{work_uuid}`. Update architecture/current-state docs as the repository expects.

## 8. Tests (Pest, real PostgreSQL)

Minimum coverage:

1. Guest can list; bearer token optional; own unpublished work is still absent even when authenticated.
2. Published work appears; unpublished/draft-only work absent; publishing an empty work appears with count zero.
3. Work unpublish removes it immediately from results and pagination total; republish restores visibility.
4. Published chapters counted correctly; unpublished chapters excluded; a chapter marked published under an unpublished work never leaks work metadata.
5. Search handles Cyrillic, Latin, case differences, spaces and literal `%`/`_` safely; SQL injection attempts do not change query semantics.
6. Status filters for all three values; invalid status, sort, page and per_page -> 422.
7. Each sort has deterministic tie-breaking; `recent` changes on chapter publish/republish as appropriate to actual timestamp behavior, not on private draft edit; `published` follows work publication timestamp.
8. Combined `q` + `status` + `sort` + pagination; stable total; pagination links preserve filters; empty result page.
9. More than 50 works, pages 1/2/3, no duplicates/missing rows for equal timestamps.
10. Response schema excludes private user data, unpublished chapter titles, drafts, published content JSON and revision metadata.
11. Query count stays bounded when listing 20–50 works; no N+1.
12. Existing Publishing public work/chapter endpoints, Library owner endpoints and Editor tests continue passing.

Use deterministic timestamps and factories; don't rely on sleep. Validate behavior against PostgreSQL rather than SQLite approximations.

## 9. Agent workflow — same implementation algorithm

**Phase 0 — inspection only.** Read repository instructions and existing migrations, API resources, publication timestamp behavior and tests. Present a concise implementation plan, proposed SQL/ORM query shape, expected migrations/indexes and any *actual* contract conflicts. **Do not edit files yet.** Ask for approval only for decisions that genuinely block implementation; otherwise proceed when authorized.

**Phase 1 — API and query.** Add module routes, controller, Form Request and public Resource, with bounded pagination, filtering, safe search, deterministic sorts and SQL aggregates. Avoid speculative abstractions.

**Phase 2 — tests.** Implement the cases in §8 and resolve actual failures; verify PostgreSQL-specific behavior and query plans where needed.

**Phase 3 — contract and docs.** Update OpenAPI and catalog docs without breaking existing schemas or routes.

**Phase 4 — verification.** Run targeted Catalog tests, relevant Publishing/Library regressions, `make quality` (or repository equivalent), and production build checks if defined. Report commands and outcomes honestly, including any unrun checks. Deliver a brief summary of files changed, migrations, API examples, and known limitations.

## 10. Definition of done

- Anonymous client can fetch a real paginated catalog at `GET /api/v1/public/works`.
- Only currently published works appear; chapter counts and activity use only currently published chapters.
- Search, filters, sorting and pagination are deterministic, validated and documented.
- Response is lightweight and privacy-safe; `cover_url` explicitly nullable pending a dedicated upload feature.
- Existing auth, author studio and reading APIs are unchanged; automated tests and OpenAPI reflect the implemented behavior.
