# Library API (`/api/v1`)

All endpoints require Sanctum Bearer auth. Foreign works/volumes/chapters → **404**.

| Method | Path | Notes |
|--------|------|--------|
| GET | `/works` | Own works, newest first, `per_page` 1–100 (default 20) |
| POST | `/works` | Create; slug from title or explicit; title change does not change slug |
| GET | `/works/{work}` | Detail with volumes + chapters |
| PATCH | `/works/{work}` | title, description, status, slug |
| PUT | `/works/{work}/cover` | `{ "media_id" }` — `kind=cover` asset owned by author |
| DELETE | `/works/{work}/cover` | Clears reference; does not delete media bytes (**204**) |
| DELETE | `/works/{work}` | Cascades volumes/chapters; **409** if editor/publication data exists |
| POST | `/works/{work}/volumes` | Append |
| PATCH | `/works/{work}/volumes/{volume}` | Rename |
| DELETE | `/works/{work}/volumes/{volume}` | Chapters → ungrouped (append, keep order) |
| PUT | `/works/{work}/volumes/order` | `{ "order": [uuid…] }` complete list |
| POST | `/works/{work}/chapters` | `{ title, volume_id? }` |
| PATCH | `/works/{work}/chapters/{chapter}` | Rename |
| DELETE | `/works/{work}/chapters/{chapter}` | Compact positions; **409** if editor/publication data exists |
| PUT | `/works/{work}/chapters/order` | `{ volume_id: uuid\|null, order: [uuid…] }` |
| POST | `/works/{work}/chapters/{chapter}/move` | `{ volume_id: uuid\|null, position }` |

Positions are 1-based contiguous within each sibling list. Structural mutations lock the work row.

Work resources include `cover_media_id`, `cover_url`, `cover_thumb_url` (signed private URLs for studio; see `docs/api/media.md`).
