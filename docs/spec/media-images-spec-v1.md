# Лист — Media / Images v1.0: Backend Technical Specification

**Status:** proposal for Cursor agent review, then implementation. **Stack:** Laravel 13, PHP 8.4, PostgreSQL 17, modular monolith; existing Identity, Library, Editor, Publishing, Catalog. **API prefix:** `/api/v1`.

## 1. Goal and scope

Support (a) one cover per work, (b) uploaded inline illustrations in Tiptap/ProseMirror chapter documents, and (c) durable rendering of published snapshots and historical revisions. Files are uploaded once and referenced by server-assigned IDs. Do not implement external-image hotlinking, image generation, galleries, CDN, or arbitrary file attachments in v1.

**Important:** Review actual migrations, existing document validator, resource shapes, route names, storage configuration, published snapshots and revision restore behavior before coding. If this spec conflicts with the repository, report it. Do not silently break current drafts, old snapshots, or API clients.

## 2. Architecture

- Add a small `app/Modules/Media` module (follow actual project layout) for image upload, authorization, metadata, storage, and delivery. Library owns `works.cover_media_id`; Editor owns allowed image nodes; Publishing owns snapshot visibility. Catalog only reads cover metadata.
- Store bytes using Laravel Filesystem, **not PostgreSQL**. Start with a private local disk and a backend image-delivery endpoint; allow changing to S3-compatible storage later without changing JSON references. Do not expose internal filesystem paths.
- Files are immutable after upload. New cover means a new asset and an updated reference, not overwriting an existing file. This preserves snapshots and cached images.
- Publicly published content must never depend on a temporary signed URL embedded in a draft or snapshot. Persist asset IDs in JSON and resolve delivery URLs at response/render time.

## 3. Data model

`media_assets` (exact naming adapt to conventions):
- `id` UUIDv7 PK
- `owner_id` FK users, required
- `kind` enum/string `cover|chapter_image`
- `disk`, `path` (opaque random key), `mime_type`, `byte_size`, `width`, `height`, `checksum_sha256`
- `created_at`, `updated_at` (optional `deleted_at` only if deletion semantics are fully defined)
- indexes: `(owner_id, created_at)`, optional `(kind, created_at)`; unique `(disk,path)`

`works.cover_media_id` nullable FK to `media_assets`, indexed. Never delete an asset as a side effect of replacing a cover or editing a chapter. If the repository uses soft deletes, explicitly define interactions with references and published snapshots.

Do **not** model each chapter image as a single FK: chapter JSON can contain many image nodes. For v1, calculate references when authorizing and before publishing; keep stored media immutable. Automated garbage collection is out of scope until reference tracking covers drafts, revisions, published snapshots, and cover history.

## 4. Uploads and validation

`POST /api/v1/media/images` — bearer PAT required, `multipart/form-data`, `file` required and `kind=cover|chapter_image`; response **201** `{ "data": { "id", "kind", "url", "width", "height", "mime_type", "byte_size" } }` (adjust envelope to existing convention). Validation errors 422, unauthenticated 401, throttle and max upload size enforced at application and nginx/PHP layers.

Limits for v1 (configurable): max **10 MiB** input; JPEG, PNG, WebP only; maximum **25 megapixels** and max 10000 px on either side. Validate decoded file content/MIME, not filename or browser-provided MIME. Reject SVG, GIF, HTML, PDF, HEIC and animated files unless deliberately supported in a later version. Reject corrupt files and decompression bombs. Strip EXIF/GPS metadata and normalize orientation; re-encode to safe JPEG/WebP/PNG using an available, tested image library. Do not use user-supplied filenames as storage paths. Make sure PHP upload limits and reverse-proxy limits agree with the API limit.

For `cover`: preserve portrait artwork, optionally create a small catalog thumbnail (e.g. 320x450 fit/contain without destructive cropping); original may remain for detail view. Do not force a fixed aspect ratio on uploaded artwork. For `chapter_image`: retain readable resolution while bounding width (e.g. 1600 px), preserve aspect ratio. If variants are implemented, store their metadata and keep a stable canonical asset ID.

Atomicity: validate/transform before inserting metadata; on DB failure remove newly created bytes; on filesystem failure do not insert a usable record. Never leave a successfully returned asset with missing bytes.

## 5. Cover API

- `PUT /api/v1/works/{work}/cover` with JSON `{ "media_id": "uuid" }`: owner only; verify `kind=cover`, owner matches work author, asset exists. Response existing work resource or `{data:{cover_url,cover_media_id}}`, whichever matches project convention; document exact shape.
- `DELETE /api/v1/works/{work}/cover`: owner only; set reference null; **do not delete bytes**. Return 204 or existing API convention.
- Add `cover_url` (nullable) to own-work detail, public work detail, and catalog card resources. Optional `cover_media_id` in authenticated owner resources only. Existing resources retain all current fields.
- **Publication policy:** cover is public only while the work is published. Since current work publication snapshots may not include cover metadata, choose and document whether cover changes take effect immediately on published works (recommended v1) or only upon republish. Tests must enforce the chosen policy. Do not leak an unpublished work cover through public routes.

## 6. Image node in Editor schema

Extend existing ProseMirror allow-list with a **block** image node:

```json
{
  "type": "image",
  "attrs": {
    "mediaId": "018f0b70-0000-7000-8000-000000000003",
    "alt": "Иллюстрация: заснеженная улица",
    "width": "wide"
  }
}
```

- `mediaId` is a UUID referring to an existing `chapter_image` owned by the work author; `alt` is optional bounded plain text (<= 300 characters); `width` enum `normal|wide|full` (default `normal`). Never persist arbitrary `src`, HTML, data URLs, CSS, event handlers, or external links in image attrs.
- Image is a block node between paragraphs, not embedded inside a text node. Keep all existing node/mark validation, nesting/size limits, CAS version semantics, revisions and restore. Account for image nodes in node-count limits; enforce a reasonable per-document image count (e.g. <= 100).
- Authorization: on draft save, revision restore and publish, validate that all referenced media exist, are `chapter_image`, and belong to the chapter's work author. Cross-user media IDs -> 422/403 according to existing API policy, with no information leak. **Do not** permit a chapter to reference a cover asset.
- Existing text-only documents remain valid. Do not mutate existing revision JSON. Preserve image nodes exactly through save -> revision -> restore -> publish -> public read.
- The public API should return the original JSON shape plus a safe way to resolve images, e.g. a `media` dictionary `{mediaId: {url,width,height}}` alongside chapter content, or a documented same-origin `GET /api/v1/public/media/{id}` URL derivation. Prefer not to rewrite immutable snapshot JSON just to add URLs.

## 7. Image delivery and privacy

- `GET /api/v1/media/{id}`: authenticated owner-only access for **unpublished** assets (used by author studio). Do not make every uploaded file publicly accessible.
- `GET /api/v1/public/media/{id}`: anonymous access **only if** the asset is currently referenced by a publicly visible published work cover or by an image node in a currently published chapter snapshot belonging to a published work. Verify against existing Publishing visibility rules. No authorization by knowing/guessing UUID alone. Return 404 for hidden/nonexistent assets; never expose storage paths or draft-only files.
- If checking published JSON references on every image request is expensive, introduce a minimal indexed `published_media_references` table maintained transactionally on publish/unpublish and cover changes, or another verified equivalent. It must reflect the current published snapshots; do not use stale in-memory checks. This is the one justified derived index in v1 if needed. Cover/publication visibility changes must invalidate access immediately.
- Serve accurate `Content-Type`, `X-Content-Type-Options: nosniff`, cache headers appropriate to access control. Private delivery must not be publicly cacheable. Public delivery may cache conservatively, with attention to unpublish revocation (avoid long-lived immutable public caches unless revocation trade-off is accepted).
- Protect against path traversal, SSRF (no remote fetching), malicious metadata and excessive CPU/memory use. No user-controlled redirects to file URLs.

## 8. Frontend integration contract (backend scope only)

Tiptap editor will use `@tiptap/extension-image` **with a custom schema/commands matching `mediaId` attrs** (stock Image extension uses `src`, so do not assume it is compatible without customization). UI: upload via toolbar, paste or drop; insert block at cursor only after upload succeeds; show upload progress/error; optional alt and width controls; do not embed base64 in JSON. Reader resolves media IDs through public API, preserves aspect ratio and mobile sizing. Work settings upload cover then assign media ID; catalog displays `cover_url` or neutral placeholder. Frontend implementation is **not** part of this backend spec.

## 9. Tests (Pest, real PostgreSQL)

- Owner upload works for supported formats, rejects unsupported/corrupt/oversize/huge-dimension/animated inputs; unauthenticated rejected; normalized output strips metadata; content and MIME match.
- Other user cannot assign a cover or embed an asset; wrong kind and nonexistent IDs rejected.
- Cover upload/replace/remove; public catalog/detail reflects cover when published; unpublished work cannot expose cover.
- Editor accepts valid image nodes, rejects `src`, external URL, HTML attrs, invalid width, wrong owner and excessive count; existing text docs still work.
- CAS conflicts preserve both old document and image references; revision save/restore preserves image nodes.
- Published snapshot renders same image after subsequent draft edits or cover replacement; chapter unpublish/work unpublish revokes public media access when no other public reference exists.
- Private image endpoint requires owner; public endpoint rejects unreferenced draft-only assets and handles missing bytes safely.
- Pagination/search/sort of Catalog unchanged except new nullable `cover_url`; no N+1 media queries.
- Concurrent publish/unpublish or cover changes do not leave stale public media references.

## 10. Documentation, quality and delivery

Update `docs/api/openapi.yaml` (multipart upload, image responses, 401/403/404/413/422, cover endpoints, `image` node schema and media resolution), `docs/architecture.md`, `docs/current-state.md`, `docs/api/media.md`, relevant Editor/Publishing docs. Run migrations, Pest, Pint, PHPStan, `make quality`, production build per repository workflow. Record actual results, no invented passes.

## 11. Suggested implementation sequence

1. **Review plan:** inspect actual models, draft validator, published snapshot representation and route conventions. Report conflicts, especially visibility and Tiptap schema.
2. **Media storage/upload:** migration, validation, normalization, owner-private delivery.
3. **Covers:** work ownership, endpoints, resources, Catalog integration.
4. **Inline images:** Editor validator, publish/revision preservation, public delivery/reference index.
5. **Integration tests + docs + quality:** include privacy and unpublish regression tests.

## 12. Explicit non-goals

No remote image URL insertion, public image browsing, arbitrary attachments, rich layout/floating text wrapping, crop editor, GIF/SVG support, storage garbage collection, or media quota/billing. Consider quotas and reference-aware GC in v1.1 after observing usage.
