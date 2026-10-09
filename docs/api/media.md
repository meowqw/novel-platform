# Media API (`/api/v1`)

Image upload, private/public delivery, work covers. Bytes live on private disk `media` (not PostgreSQL). Assets are immutable; replacing a cover or removing an image from a draft does **not** delete files.

## Upload (Sanctum)

| Method | Path | Notes |
|--------|------|--------|
| `POST` | `/media/images` | `multipart/form-data`: `file`, `kind=cover\|chapter_image` → **201** |

Limits (app): 10 MiB; JPEG/PNG/WebP; max 25 MP / 10000 px edge; animated rejected; EXIF stripped via re-encode (Intervention Image + GD). nginx `client_max_body_size` remains 32M.

Cover: normalized original + thumb ≤320×450 (contain). Chapter image: max width 1600 px.

Response `url` / `thumb_url` are **temporary signed** private URLs so a Next.js BFF/editor can render `<img>` without exposing Bearer tokens to the browser. BFF may also call `GET /media/{id}` with server-side Sanctum Bearer.

## Delivery

| Method | Path | Auth | Notes |
|--------|------|------|--------|
| `GET` | `/media/{id}` | Sanctum owner | `?variant=original\|thumb`; `Cache-Control: private, no-store` |
| `GET` | `/media/{id}/signed` | signed query | Same bytes; short TTL; for editor `<img>` |
| `GET` | `/public/media/{id}` | none | Only if currently publicly reachable |

**Public reachability**
- Cover: `works.cover_media_id = id` **and** work published (live).
- Chapter image: row in `published_media_references` **and** chapter published **and** work published.

Unpublish / cover replace / cover clear revokes access immediately when no other public reference remains. Public cache is short (`max-age=60, must-revalidate`), not immutable.

## Covers (Sanctum, owner)

| Method | Path | Notes |
|--------|------|--------|
| `PUT` | `/works/{work}/cover` | `{ "media_id" }` kind=cover, same owner |
| `DELETE` | `/works/{work}/cover` | null FK; **204**; bytes kept |

Owner `Work` resource: `cover_media_id`, `cover_url` (original signed), `cover_thumb_url`.  
Public work detail: `cover_url` → original public URL.  
Catalog card: `cover_url` → **thumb** public URL.

## Documents

ProseMirror block node:

```json
{ "type": "image", "attrs": { "mediaId": "<uuid>", "alt": "...", "width": "normal|wide|full" } }
```

No `src` / data URLs. Validated on draft save, revision restore, and publish. Responses for draft, revision detail, and public chapter include a sibling `media` map `{ mediaId: { url, width, height, mime_type, byte_size } }` without rewriting JSON content.

Image-only chapters still cannot be published (existing text requirement).
