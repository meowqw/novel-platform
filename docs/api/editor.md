# Editor API (`/api/v1`)

Owner-only Sanctum Bearer. Foreign chapter/revision → **404**. Invalid document → **422**. Stale CAS / idempotency misuse → **409**.

Document format: Tiptap/ProseMirror JSON, root `{ "type": "doc", "content": [...] }`.
Allow-list and limits: `config/editor.php` (nodes, marks, depth, size, plain-text length, `max_images`). Links: `http`/`https` only.

Block `image` node: attrs `mediaId` (UUID, `chapter_image` owned by author), optional `alt` (≤300), `width` (`normal`|`wide`|`full`). No `src`. Draft/revision responses include sibling `media` map for resolution (JSON content unchanged). Restore re-validates media ownership.

| Method | Path | Notes |
|--------|------|--------|
| GET | `/chapters/{chapter}/draft` | Lazy-create empty draft if missing |
| PUT | `/chapters/{chapter}/draft` | `{ content, version }` full replace; CAS on `version` |
| GET | `/chapters/{chapter}/revisions` | Paginated metadata only (`per_page` 1–100) |
| POST | `/chapters/{chapter}/revisions` | Snapshot current draft; optional `{ label }`; optional header `Idempotency-Key` |
| GET | `/chapters/{chapter}/revisions/{revision}` | Full content + metadata |
| POST | `/chapters/{chapter}/revisions/{revision}/restore` | `{ version }` → new draft version; revision unchanged |

## Concurrency

- Draft write: `UPDATE … WHERE version = expected`; conflict returns **409** with current `version` and `updated_at`.
- Concurrent first GET: `INSERT … ON CONFLICT DO NOTHING`, then read.
- Same `Idempotency-Key` + same effective payload → existing revision; different payload → **409**.

## Library deletion

`chapter_drafts` / `chapter_revisions` use `ON DELETE RESTRICT`. Deleting a chapter or work that still has editor **or publication** rows → **409**, no partial delete. Chapters without editor/publication rows delete as before. Volume delete only reassigns chapters.

ProseMirror `text` node strings keep leading/trailing spaces (not stripped by API middleware).
