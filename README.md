# Personal CMS — SQLite/MySQL demo

A standalone integration of Start Bootstrap Personal 1.0.1 with the existing PHP CMS. No source-site content, images, users, database records, or private configuration is carried over. The portfolio identity and sample projects are fictional; the profile photograph comes from the Personal theme's own distribution.

## Reuse in new projects

Personal remains the working example in this repository. Create a clean sibling project with:

```powershell
php tools/create-site.php --target=../new-site --name="New Company"
```

The command does not copy demo accounts/content/images, runtime databases, sessions, logs, uploads, or private configuration, and refuses to overwrite an existing destination. The new project starts with a plain PHP frontend and its own administrator setup. Begin with the [new-site guide](docs/yeni-site.md) and [architecture map](docs/mimari.md). The clean seed is `database/seed-base.php`; the reusable scaffold is in `starter/site/`.

## Run the demo

Requires PHP 8.4 with `pdo_sqlite` (default) or `pdo_mysql` (MySQL >= 8.0.19; MariaDB unsupported), `mbstring`, `dom`, `fileinfo`, and `gd`. Composer/npm installation is not required.

```powershell
cd webphp-personal
php tools/install.php
php -S 127.0.0.1:8876 -t . router.php
```

If the database already exists, `install.php` refuses to overwrite it. There is no need to reinstall the running demo. You can also start the server with `tools/serve.ps1`.

- Website: http://127.0.0.1:8876/
- Panel: http://127.0.0.1:8876/panel/
- Demo email: `admin@personal.test`
- Demo password: `PersonalDemo!2026`

This account is for the local demo only. Requests with a non-loopback `REMOTE_ADDR` are rejected. Login retains password hashing, CSRF, attempt limits, and session rotation; this local demo does not use Google CAPTCHA. Internet deployment requires a separate authentication/setup assessment. Bind the development server to `127.0.0.1`.

## Database and schema

- Driver: `config/site.php` `database` (`driver` `sqlite` with `file`, or `mysql` with `host`, `port`, `name`, `user`, `charset`). Env overrides: `PANEL_DB_DRIVER`, `PANEL_DB_FILE` (`PERSONAL_DB_FILE` fallback), `PANEL_DB_HOST/PORT/NAME/USER/PASSWORD`. The MySQL password comes only from env or the Git-ignored `config/config.runtime.php` (`$databasePassword`). The demo default stays SQLite.
- MySQL: >= 8.0.19, utf8mb4, InnoDB, `utf8mb4_bin` on slug/code/email/limit_key. MariaDB is unsupported.
- `database/schema.php`: the single schema definition (28 tables, indexes, unique translation keys, template foreign keys).
- `database/seed-base.php`: a neutral starter without portfolio data or a fixed user account. `database/seed.php`: fictional portfolio pages, navigation, settings, project/resume records, and a demo-only user. Never import a seed into a live database.
- `tools/install.php`: builds a database from scratch and never resets an existing one. SQLite: temporary file, integrity/foreign-key checks, exclusive publish. MySQL: an existing empty database is required, an install lock is taken, and a failed run drops only its own tables.
- `tools/verify.php`: checks vendor/theme hashes, integrity, and that the database structure equals `schema.php`.
- `tools/seed-media.php`: copies the theme profile image and new sample project images into record folders without overwriting existing files.
- `storage/personal.sqlite`: the running SQLite database, excluded from Git and protected from HTTP access.

There are no migrations: changing the data model means editing `schema.php` and the relevant seed, then installing a fresh database; a site with real data needs a separate plan first. Run `php tools/seed-media.php` for demo media. To back up a SQLite installation, stop the server and copy the database file and `uploads/` together; back up MySQL with your usual dump tooling together with `uploads/`.

## What can be edited in the panel?

Panel labels remain Turkish; the labels below identify the actual controls.

| Content | Panel location | Data used |
|---|---|---|
| Main heading and introduction | Pages (`Sayfalar`) → Home (`Ana sayfa`) → Turkish (`Türkçe`) | `page_lang.name/title`; badge and image are in General (`Genel`) |
| About, contact, privacy, and terms | `Sayfalar` → relevant page → `Türkçe` | `page_lang.content`, rendered through HTML Purifier |
| Projects | Data (`Veriler`) → Projects (`Projeler`) | General name/summary/image; detail content in the Turkish tab |
| Experience and education | `Veriler` → relevant type | Period, organization, location; description in the Turkish tab |
| Skills and languages | `Veriler` → relevant type | General name; a translated name overrides it when present |
| Brand, social links, CTA, and footer | Settings (`Ayarlar`) → Site variables | `variables` + Turkish `variable_lang` |
| Navigation | Menus (`Menüler`) → Menu list | `menus.link/name/sort_order` |
| Incoming messages | Panel dashboard → Messages (`Mesajlar`) | Local form submissions, never executed as HTML |

Non-empty Turkish translations override general values. Save a new record in the General tab first; its Turkish content and record-scoped file picker become available afterward. Installation-specific field labels live in `config/panel-fields.php`, rather than brand-specific code in the panel.

## Structure

- `panel/`: the existing AdminLTE/jsGrid/CKEditor/Filemanager panel with role, CSRF, and field checks.
- `panel/include/database.php`: PDO connection and the limited result/statement interface used by the panel; `db_dialect.php`: the only place for SQLite/MySQL differences; `db_schema.php`: schema and write validation; `db_install.php`: table creation. Migration/dump/Adminer tools are not included.
- `app/`: public queries, safe output helpers, and contact processing.
- `templates/pages/`: home, resume, projects, project detail, contact, text, and 404 pages.
- `templates/partials/`: head, navigation, hero, project/resume cards, CTA, and footer.
- `config/`: independent installation settings and field labels.

No theme/module engine was added. Personal is assembled manually from PHP partials that read CMS records. Generic slider/category/template management remains in the panel; the Personal frontend uses only the content mapping above. The public site defaults to Turkish. English translation records/API remain available in the panel; a separate English visitor route is outside this demo's scope.

## Contact and media

The contact form uses server-side validation, CSRF, a honeypot, and an IP limit of 5 messages per 15 minutes. Messages (name <= 50, message 10-255 characters) are stored in the database; no email or paid SB Forms service is used. Filemanager retains record-level scope: only the edited record's files are shown. Quick upload, remote URL loading, active-document uploads, and archive extraction remain disabled.

## ServBay

Create a PHP site with this directory as its document root and select PHP 8.4; `webphp-personal.test` is one possible local hostname. Apache requires `mod_rewrite`, `mod_headers`, and `.htaccess` support. For Nginx, configure equivalent config/database/storage/tools/templates access restrictions, panel/API rewrites, and the panel PHP entry-point allowlist from `router.php`. Existing sites' ServBay settings are not modified. The ready-to-use preview runs on the PHP development server described above.

## Verification

```powershell
python tests/http-workflows.py
python tests/http-workflows.py --driver mysql   # needs PANEL_TEST_MYSQL_HOST/PORT/USER/PASSWORD
php tests/db-contract.php --driver=both
php tools/verify.php
```

The HTTP suite uses separate disposable databases (SQLite files, or `cms_test_<hex>` MySQL databases created and dropped by the suite), sessions, and servers; it does not change demo content. It checks overwrite refusal, real login/logout, lists/forms/CRUD/translations, custom fields appearing on the website, contact submissions, role/CSRF controls, rate limits, SRI, and private-file access restrictions. Browser checks covered the desktop theme, 375px mobile navigation/projects, the editor, and record-scoped image selection. The test server does not apply Apache `.htaccess` rules; `router.php` provides equivalent restrictions.

Theme: [Start Bootstrap Personal](https://github.com/startbootstrap/startbootstrap-personal), MIT; see `LICENSE-THEME`. Distributed theme CSS/JS/profile assets are unchanged; sources and hashes are recorded in `docs/theme-source.json` and `docs/theme-assets.json`. Panel packages retain their own licenses and `panel/plugins/DEPENDENCIES.json`; vendor sources were not patched. CSS/JS SRI values and content versions are derived from actual file bytes. Google Fonts and some Filemanager subdependencies still load externally; this is not a fully offline distribution. The free CKEditor 4.22.1 configuration-based security boundaries are recorded in `AGENTS.md`.

## Planned AI content entry

Shared content operations, previewable JSON imports, and an optional local MCP adapter are documented in the [AI content entry implementation plan](docs/superpowers/plans/2026-10-25-ai-content-entry.md). These are future tasks, not available features. Implementation is postponed; resume from this repository when requested.
