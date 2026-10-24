# New-site developer and AI guide

Read this file, `docs/mimari.md`, and `docs/yeni-site.md` first, then inspect only the files relevant to the task. Do not scan every vendor source or the entire project for each change.

- This repository is an independent installation. Do not modify other sites or copy their configuration, users, content, or uploads.
- Run `git status --short` first. Site-specific design belongs in `index.php`, `templates/`, `css/`, `js/`, and `assets/`. The panel core remains independent of the design; do not add a theme engine.
- `config/site.php` defines the name, installation profile, database, and session name. Secrets belong in the Git-ignored `config/config.runtime.php`. Site-specific field labels belong in `config/panel-fields.php`.
- PHP 8.4 + PDO SQLite; Composer/npm is not required. `tools/install.php` must not overwrite an existing database. Update the schema and clean seed together. There is no default administrator account.
- `panel/include/sqlite.php` is a limited SQLite adapter. Parameterize SQL values and enforce server-side allowlists for identifiers, sorting, tables, and fields.
- Preserve API `data/itemsCount`, string IDs, role/resource/action/field checks, CSRF, soft deletion, and translation contracts.
- Use `panelHtml()` / `e()` for HTML text/attributes and only `panelRichHtml()` for rich HTML. Do not sanitize and rewrite database content in bulk.
- Preserve file-manager authentication, CSRF, and record-folder scope. A missing folder must not fall back to the shared root. Remote URL loading, quick upload, active-document uploads, and archive extraction remain disabled.
- Do not patch package sources manually or replace the editor/file manager. Preserve official package bytes, licenses, and the SHA-256 manifest.
- CKEditor 4.22.1 is the free edition, not LTS. Keep `fullPage:false`, `disallowedContent:'script;style'`, and `versionCheck:false`. Enabling these features or adding GeSHi/samples requires a new security assessment.
- Do not remove CSS/JS `integrity` values. `assetAttributes()` / `scriptAttributes()` derive SRI and content versions from actual file bytes.
- Do not silently remove the loopback restriction or weaken login security. The starter's local password/CSRF/rate-limit/session-rotation flow is not approval for production deployment.
- Lint changed PHP files and run `php tools/verify.php`. Write tests must use separate SQLite databases, uploads, and sessions. Never import seeds/tests into a user's database. Add tests appropriate to the new project; do not copy the Personal-specific demo tests.
- Never commit runtime databases, sessions, logs, user uploads, secrets, or another repository's `.git` directory.
