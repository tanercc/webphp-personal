# Record media (replaces captions) — short design

## Intent

`captions` / `caption_lang` store a loose filename per record with a title; the actual file lives wherever the
file manager put it, so the database and disk disagree, alt text does not exist, and `image` columns hold
free-text paths. Goal: record media lives in the database (single source of truth), uploaded through our own
endpoint with the existing session/role/CSRF rules. The file manager stays only for CKEditor images.

## Approaches

- **A — keep path strings, add a `media` table beside them.** Least work, but two sources of truth remain.
- **B — own upload endpoint, `media` table, `image` → `image_id`.** Chosen by the owner: DB is authoritative,
  alt/caption are translatable, the file manager is reduced to CKEditor.
- **C — extend the file manager to write DB rows.** Rejected: requires patching vendor code (AGENTS.md forbids).

## Design (B)

**Schema** (`database/schema.php`, no migrations — reinstall):

- `media`: `id`, `owner_table` varchar(32), `owner_id` int, `path` varchar(255) (site-relative URL),
  `original_name` varchar(255), `mime` varchar(100), `size` int, `width` int nullable, `height` int nullable,
  `is_active`, `deleted`, `sort_order`, `created_at`. Index `(owner_table, owner_id)`.
- `media_lang`: `id`, `row_id`, `lang_id`, `title` varchar(255), `alt` varchar(255), `caption` text,
  `created_at`; unique `(row_id, lang_id)`.
- `image` → `image_id` (int, nullable) on `pages`, `datas`, `sliders`, `menus`, `cats`, `tags`, `users`.
- Removed: `captions`, `caption_lang`; `image` on all `*_lang` tables (per-language alt/caption now lives in
  `media_lang`); `file` on `pages`, `datas`, `cats` (documents are media rows with mime `application/pdf`).
  `errors.file` and `icon` columns are unrelated and stay.
- Owner check is in application code (polymorphic owner; no SQL FK). `image_id` must point to an active,
  non-deleted media row owned by the same record — validated on write (422 `Invalid value: image_id`).

**Upload endpoint** — `panel/api/index.php` resource `media`, same session/role/CSRF/allowlist path as other
resources:

- `POST media` (multipart: `owner_table`, `owner_id`, `file`): caller must be allowed to edit the owner
  record (same role check as the owner resource; `users` own profile via `self`). Owner record must exist.
- Allowed: jpg, jpeg, png, webp, gif (images), pdf. Type checked by `finfo` (extension must match the
  detected MIME), images also by `getimagesize` (gives width/height). Max 10 MB. SVG rejected.
- Server generates the name (`bin2hex(random_bytes(8))` + ext) and writes to
  `uploads/media/<owner_table>/<owner_id>/`. Original name stored only as data.
- `PATCH media/<id>` / `PUT` via normal API: `sort_order`, `is_active`, translations (`media_lang`).
  `DELETE` is soft delete as elsewhere; file stays on disk (no hard delete in this round).
- List: `GET media?owner_table=&owner_id=` returns `data/itemsCount`, string IDs.

**Panel** — record edit pages that have `image_id` get a "Medya" card: upload, thumbnail list, title/alt per
language, sort, delete, "ana görsel yap" (sets `image_id`). `input-image.php` becomes this picker; the
file manager button is removed from record forms. Captions pages, sidebar item, permissions and API
entries are removed. `media_access.php` resource list drops `captions`; CKEditor's file manager scope is
unchanged.

**Site** — `app/bootstrap.php` resolves `image_id` into `image` (path) and `image_alt` (current language,
fallback record name) for page/record reads with one extra query per list (no N+1). Templates use
`image_alt` for `alt`. Gallery rendering is out of scope.

**Seed** — the 3 demo images move to `uploads/media/<table>/<id>/` and get `media` rows; `image_id` set.
`seed-base.php` has none.

**Tests** — `tests/http-workflows.py`: upload ok (png), wrong MIME/extension mismatch rejected, oversize
rejected, no CSRF → 403, role without owner permission → 403, foreign `image_id` → 422, list/soft delete.
Both drivers. AGENTS.md and `docs/mimari.md` updated.

## Non-goals

Gallery UI on the public site, image resizing/thumbnails, hard delete / orphan cleanup, the other schema
fixes (FKs, tags↔datas, slug uniqueness, menus CHECK) — those are the next small step.

## Left to the builder

Exact panel markup, JS for the media card (jQuery, existing patterns), query helper names.
