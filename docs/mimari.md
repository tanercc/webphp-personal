# Developer architecture map

## Read in this order

1. `AGENTS.md`: the repository's scope and contracts to preserve.
2. `README.md`, `config/site.php`: the example/installation profile, database, and startup instructions.
3. `database/schema.sql` and the seed entries relevant to the task.
4. The entry point below that matches the task. Do not read all vendor files indiscriminately.

| Task | Start with these files / symbols |
|---|---|
| Frontend routing and pages | `index.php`, `templates/`; Personal uses `templates/pages/` + `partials/` |
| Site data | `app/bootstrap.php`: `sitePage`, `siteRecords`, `setting`, `siteRows` |
| Installation and clean copies | `tools/install.php`, `tools/create-site.php`, `config/site.php`, `starter/` |
| Database connection / metadata | `panel/include/sqlite.php`: `PanelSqliteConnection`, `columns`, `execute_query` |
| Legacy panel helpers | `panel/include/db_functions.php`: `sqlQuery`, `dbGetTableData`, `dbGetTableFields` |
| API routing / CSRF | `panel/api/index.php` |
| API permissions and fields | `panel/api/security.php`: `apiAuthorize`, `apiWritableFields`, `apiValidateFields` |
| CRUD / pagination / translations | `panel/api/database.php`; `request.php`, `lang.php` |
| Form fields | `panel/content-*-id.php`, `panel/php/input-*.php`, `config/panel-fields.php` |
| Users / roles / sessions | `panel/include/login.php`, `login_security.php`, `permissions.php`, `session.php`, `csrf.php` |
| Record-scoped file selection | `panel/include/media_access.php`, `panel/plugins/filemanager/config/config.php` |
| Safe output | `panel/include/output.php`, `rich_html.php`; `e`, `panelHtml`, `panelRichHtml` |
| HTTP access boundaries | `.htaccess`, `panel/.htaccess`, `router.php`, `uploads/.htaccess` |
| Package integrity | `panel/plugins/DEPENDENCIES.json`, `tools/verify.php` |

## Request flows

- Visitor: router/rewrite → `index.php` → `app/bootstrap.php` → parameterized read → allowed PHP template.
- Panel: `panel/index.php` → configuration/database/session/role → list or record form.
- API: `panel/api/index.php` → session/role/method/CSRF → table/field/filter permissions → SQLite adapter → JSON.
- Media: Filemanager entry points → shared session/role/CSRF → `RF.subfolder` resource/record boundary → file operation.

Panel forms use schema column types and defaults. The SQLite adapter covers only the existing `SHOW COLUMNS` / legacy result interface currently used by the application; it is not a general MySQL compatibility layer. Check SQLite support and allowlists together when introducing a query.

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
- Panel/API/database/setup changes: run `python tests/http-workflows.py` in this reference repository. One suite checks both the demo and a generated clean site with separate SQLite databases, sessions, and servers.
- Package/setup changes: run `php tools/verify.php`; it refuses a missing database without creating one.
- Generated projects do not receive the Personal-specific HTTP suite. Add project-appropriate tests with a separate database; baseline package/SQLite verification is included.

Read missing details from the relevant files. There is no need to scan the entire source tree on every task or rely on memory of a previous conversation.

The verified reference run covered 101 Personal + 25 generated-starter HTTP requests, destination/database overwrite refusal, missing-password rejection, a new administrator, exclusion of demo content, and package hashes. `panel/include/config.php` is a non-secret compatibility loader tracked in Git; only private configuration files are ignored.
