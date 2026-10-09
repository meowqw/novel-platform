# Publishing API (`/api/v1`)

Publication state and immutable public snapshots. Library owns structure; Editor owns drafts. `Work.status` is metadata, not publication.

## Author (Sanctum)

| Method | Path | Notes |
|--------|------|--------|
| GET | `/works/{work}/publication` | Owner status + published chapter count |
| PUT | `/works/{work}/publication` | `{ "published": bool }` idempotent; empty work allowed |
| GET | `/chapters/{chapter}/publication` | Owner status, `source_draft_version`, `has_snapshot` (no draft body) |
| POST | `/chapters/{chapter}/publish` | Snapshot current Editor draft; 422 if missing/empty draft |
| POST | `/chapters/{chapter}/unpublish` | Hide; retain snapshot |

Foreign resources → **404**. Work/chapter flags are independent: chapter may be published while work is not (publicly invisible until work is published).

## Public (no auth, throttle 120/min/IP)

| Method | Path | Notes |
|--------|------|--------|
| GET | `/public/works/{work}` | Only if work published; volumes with ≥1 published chapter; ungrouped published chapters |
| GET | `/public/chapters/{chapter}` | Only if work **and** chapter published |

Unpublished / unknown → **404**. `Cache-Control: no-store, private`. No draft/revision/source_draft_version in public payloads.

## Deletion

`work_publications` / `chapter_publications` use `ON DELETE RESTRICT`. Rows created on first publish (not on unpublish of never-published). Delete chapter/work with publication data → **409**.
