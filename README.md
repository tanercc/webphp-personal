# Personal CMS — SQLite demo

A standalone integration of Start Bootstrap Personal 1.0.1 with the existing PHP CMS. No source-site content, images, users, database records, or private configuration is carried over. The portfolio identity and sample projects are fictional; the profile photograph comes from the Personal theme's own distribution.

## Reuse in new projects

Personal remains the working example in this repository. Create a clean sibling project with:

```powershell
php tools/create-site.php --target=../new-site --name="New Company"
```

The command does not copy demo accounts/content/images, runtime databases, sessions, logs, uploads, or private configuration, and refuses to overwrite an existing destination. The new project starts with a plain PHP frontend and its own administrator setup. Begin with the [new-site guide](docs/yeni-site.md) and [architecture map](docs/mimari.md). The clean seed is `database/seed-base.sql`; the reusable scaffold is in `starter/site/`.

## Run the demo

Requires PHP 8.4 with `pdo_sqlite`, `mbstring`, `dom`, `fileinfo`, and `gd`. Composer/npm installation is not required.

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

## CREATE and seed SQL

- `database/schema.sql`: 28 tables, indexes, unique translation keys, and template foreign keys.
- `database/seed-base.sql`: a neutral starter without portfolio data or a fixed user account.
- `database/seed.sql`: fictional portfolio pages, navigation, settings, project/resume records, and a demo-only user.
- `tools/install.php`: applies the demo or starter SQL to a temporary SQLite file, checks integrity/foreign keys, and publishes the database. It never resets an existing database.
- `tools/seed-media.php`: copies the theme profile image and new sample project images into record folders without overwriting existing files.
- `storage/personal.sqlite`: the running database, excluded from Git and protected from HTTP access.

The SQL files use standard SQLite 3 syntax. For the demo, import the schema followed by its seed into an empty database with any SQLite client; run `php tools/seed-media.php` for media. To back up the installation, stop the server and copy the database file and `uploads/` together.

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
- `panel/include/sqlite.php`: PDO SQLite connection and the limited result/statement interface used by the panel. It is not a general MySQL emulator. Migration/dump/Adminer tools are not included.
- `app/`: public queries, safe output helpers, and contact processing.
- `templates/pages/`: home, resume, projects, project detail, contact, text, and 404 pages.
- `templates/partials/`: head, navigation, hero, project/resume cards, CTA, and footer.
- `config/`: independent installation settings and field labels.

No theme/module engine was added. Personal is assembled manually from PHP partials that read CMS records. Generic slider/category/template management remains in the panel; the Personal frontend uses only the content mapping above. The public site defaults to Turkish. English translation records/API remain available in the panel; a separate English visitor route is outside this demo's scope.

## Contact and media

The contact form uses server-side validation, CSRF, a honeypot, and an IP limit of 5 messages per 15 minutes. Messages are stored in SQLite; no email or paid SB Forms service is used. Filemanager retains record-level scope: only the edited record's files are shown. Quick upload, remote URL loading, active-document uploads, and archive extraction remain disabled.

## ServBay

Create a PHP site with this directory as its document root and select PHP 8.4; `webphp-personal.test` is one possible local hostname. Apache requires `mod_rewrite`, `mod_headers`, and `.htaccess` support. For Nginx, configure equivalent config/database/storage/tools/templates access restrictions and panel/API rewrites. Existing sites' ServBay settings are not modified. The ready-to-use preview runs on the PHP development server described above.

## Verification

```powershell
python tests/http-workflows.py
php tools/verify.php
```

The HTTP suite uses separate SQLite files, sessions, and servers; it does not change demo content. It checks overwrite refusal, real login/logout, lists/forms/CRUD/translations, custom fields appearing on the website, contact submissions, role/CSRF controls, rate limits, SRI, and private-file access restrictions. Browser checks covered the desktop theme, 375px mobile navigation/projects, the editor, and record-scoped image selection. The test server does not apply Apache `.htaccess` rules; `router.php` provides equivalent restrictions.

Theme: [Start Bootstrap Personal](https://github.com/startbootstrap/startbootstrap-personal), MIT; see `LICENSE-THEME`. Distributed theme CSS/JS/profile assets are unchanged; sources and hashes are recorded in `docs/theme-source.json` and `docs/theme-assets.json`. Panel packages retain their own licenses and `panel/plugins/DEPENDENCIES.json`; vendor sources were not patched. CSS/JS SRI values and content versions are derived from actual file bytes. Google Fonts and some Filemanager subdependencies still load externally; this is not a fully offline distribution. The free CKEditor 4.22.1 configuration-based security boundaries are recorded in `AGENTS.md`.
