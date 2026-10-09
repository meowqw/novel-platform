# Catalog API (`/api/v1`)

Public, unauthenticated catalog of **currently published** works. Source of truth:
`work_publications.is_published`, not `works.status`.

## Endpoints

| Method | Path | Notes |
|--------|------|--------|
| GET | `/public/works` | Paginated catalog cards; throttle 120/min/IP |

Distinct from author `GET /works` and public reader `GET /public/works/{work}`.

## Query parameters

| Param | Default | Rules |
|-------|---------|--------|
| `page` | `1` | `>= 1` |
| `per_page` | `20` | `1..50` |
| `q` | absent | Trimmed title substring; max 100; whitespace-only ignored; `%`/`_`/`\` escaped |
| `status` | absent | `ongoing` \| `completed` \| `hiatus` |
| `sort` | `recent` | `recent` \| `published` \| `title` |

Unknown keys ignored. Invalid recognized values → **422**.

### Sort

- **recent** — `last_activity_at` DESC (`GREATEST` of work `published_at` and max published-chapter `published_at`); nulls last. Draft/`works.updated_at` changes do not move items.
- **published** — work `published_at` DESC (`NULL` as oldest).
- **title** — `lower(title)` ASC.
- Tie-breaker: `works.id` ASC.

`published_at` is overwritten on work republish (Publishing behavior).

## Card payload

Includes `PublicUser` author (full public shape), `cover_url` (public **thumb** URL when `cover_media_id` set; else null),
`published_at`, `last_activity_at`, `published_chapters_count` (published chapters only; may be `0`).

Never returns email, drafts, revisions, chapter bodies, or unpublished chapter titles.

`Cache-Control: no-store, private` — no result caching in v1.

## Frontend linking

Use work UUID: `/read/{work_uuid}` (or equivalent). Slugs are unique per author only.

## Limitations (v1)

- Title substring only (no FTS / `pg_trgm` until data volume warrants it)
- No author filter (`?author=` deferred to v1.1)
- Catalog `cover_url` is the cover thumbnail; work detail uses original (see `docs/api/media.md`)
