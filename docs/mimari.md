# Developer architecture map

## Read in this order

1. `AGENTS.md`: the repository's scope and contracts to preserve.
2. `README.md`, `config/site.php`: the example/installation profile, database, and startup instructions.
3. `database/schema.php` and the seed entries relevant to the task.
4. The entry point below that matches the task. Do not read all vendor files indiscriminately.

| Task | Start with these files / symbols |
|---|---|
| Frontend routing and pages | `index.php`, `templates/`; Personal uses `templates/pages/` + `partials/` |
| Site data | `app/bootstrap.php`: `sitePage`, `siteRecords`, `setting`, `siteRows` |
| Installation and clean copies | `tools/install.php`, `tools/create-site.php`, `config/site.php`, `starter/` |
| Database connection / metadata | `panel/include/database.php`: `PanelDbConnection`, `columns`, `execute_query`; `db_dialect.php` (engine differences), `db_schema.php` (schema, `panelValidateWrite`), `db_install.php` (create tables) |
| Legacy panel helpers | `panel/include/db_functions.php`: `sqlQuery`, `dbGetTableData`, `dbGetTableFields` |
| API routing / CSRF | `panel/api/index.php` |
| API permissions and fields | `panel/api/security.php`: `apiAuthorize`, `apiWritableFields`, `apiValidateFields` |
| CRUD / pagination / translations | `panel/api/database.php`; `request.php`, `lang.php` |
| Form fields | `panel/content-*-id.php`, `panel/php/input-*.php`, `config/panel-fields.php` |
| Users / roles / sessions | `panel/include/login.php`, `login_security.php`, `permissions.php`, `session.php`, `csrf.php` |
| Record media (upload, alt/caption, main image) | `panel/api/media.php`, `panel/php/input-image_id.php`, `media`/`media_lang` in `schema.php` |
| CKEditor/icon file selection | `panel/include/media_access.php`, `panel/plugins/filemanager/config/config.php` |
| Safe output | `panel/include/output.php`, `rich_html.php`; `e`, `panelHtml`, `panelRichHtml` |
| HTTP access boundaries | `.htaccess`, `panel/.htaccess`, `panel/api/.htaccess`, `router.php`, `uploads/.htaccess` |
| Package integrity | `panel/plugins/DEPENDENCIES.json`, `tools/verify.php` |

## Request flows

- Visitor: router/rewrite → `index.php` → `app/bootstrap.php` → parameterized read → allowed PHP template.
- Panel: `panel/index.php` → configuration/database/session/role → list or record form.
- API: `panel/api/index.php` → session/role/method/CSRF → table/field/filter permissions → database adapter → JSON.
- Record media: `panel/api/index.php` → session/role/CSRF → owner record edit right → type/size checks → `uploads/media/<table>/<id>/` + `media` row. Site reads join `media`/`media_lang` for `image`/`image_alt` in the same query.
- Editor files: Filemanager entry points → shared session/role/CSRF → `RF.subfolder` resource/record boundary → file operation.

Panel forms use column types and defaults from `schema.php`. Foreign keys, unique slugs, the single-target menu rule and `data_tags` (`tag_ids`) are checked in `apiDbNormalizeFields` before the write so both engines answer 422 instead of a driver error. The adapter covers only the legacy result interface currently used by the application. Writes from panel/API/contact are validated against `schema.php` (`panelValidateWrite`, 422 `Invalid value: <column>`) on both engines. Driver selection: `config/site.php` `database` plus `PANEL_DB_*` env overrides; the MySQL password only via env or `config/config.runtime.php`. MySQL >= 8.0.19 (utf8mb4, InnoDB, `utf8mb4_bin` on slug/code/email/limit_key); MariaDB unsupported.

Portable SQL in application code: backtick identifiers, single-quoted strings, `LIMIT n OFFSET m`, `COALESCE`, `LIKE ? ESCAPE '!'`. Do not use `IFNULL`, `||`, `GROUP_CONCAT`, `ON CONFLICT`, `ON DUPLICATE KEY`, `PRAGMA`, `information_schema`, `sqlite_master`, `SHOW`, `SQL_CALC_FOUND_ROWS`, `FOUND_ROWS`, `INSERT ... SET`, or SQL date functions. Engine differences live only in `panel/include/db_dialect.php`. `affected_rows` counts changed rows on MySQL but matched rows on SQLite, and `insert_id` is meaningful only right after an INSERT; rely on neither otherwise. Check both engines and allowlists together when introducing a query.

Schema workflow: `database/schema.php` is the only definition; `tools/install.php` builds a fresh database (SQLite temp file + exclusive publish; MySQL empty database, install lock, failed run drops only its own tables); `tools/verify.php` checks the structure equals `schema.php`. There are no migrations: edit `schema.php` and the seed, then reinstall; a site with real data needs a separate plan.

## Stable contracts

- List JSON uses `data` and `itemsCount`; identifiers remain strings on the client.
- `deleted` implements soft deletion, `is_active` controls publication, and `sort_order` controls ordering.
- General records and `*_lang` translations are linked by `row_id/lang_id`; non-empty translated fields override general values.
- Template paths never come from the database. Resolve codes through `config/data-templates.php`, preserving record → category → default precedence.
- Media roots are installation- and record-specific. Do not expose the shared upload root in the picker.
- Apply `panelRichHtml` when rendering rich HTML; never sanitize and rewrite form/database content in bulk.
- `assetAttributes` and `scriptAttributes` derive SRI and content versions from file bytes.
- Assemble the site manually with PHP partials. Do not put company-specific or theme behavior in the shared panel core.

## Verification by change type

- PHP changes: run `php -d error_reporting=32767 -l ...` on affected files.
- Panel/API/database/setup changes: run `python tests/http-workflows.py` in this reference repository. One suite checks both the demo and a generated clean site with separate disposable databases, sessions, and servers. For DB-touching changes also run `python tests/http-workflows.py --driver mysql` (`PANEL_TEST_MYSQL_HOST/PORT/USER/PASSWORD`; creates and drops `cms_test_<hex>`) and `php tests/db-contract.php --driver=both`; if MySQL is unavailable, say so in the report.
- Package/setup changes: run `php tools/verify.php`; it refuses a missing database without creating one.
- Generated projects do not receive the Personal-specific HTTP suite. Add project-appropriate tests with a separate database; baseline package/database verification is included.

Read missing details from the relevant files. There is no need to scan the entire source tree on every task or rely on memory of a previous conversation.

The verified reference run covered 101 Personal + 25 generated-starter HTTP requests, destination/database overwrite refusal, missing-password rejection, a new administrator, exclusion of demo content, and package hashes. `panel/include/config.php` is a non-secret compatibility loader tracked in Git; only private configuration files are ignored.
