# New site — PHP CMS + SQLite/MySQL

This directory was generated from the clean starter. No sample portfolio, demo account, user files, or source Git history was copied. Requires PHP 8.4; Composer/npm is not required.

Read `AGENTS.md`, `docs/mimari.md`, and `docs/yeni-site.md` first. Build the site manually with PHP/HTML/CSS in `templates/`; keep site-specific design out of the panel core.

## Installation

In PowerShell, supply the password without putting it into command history:

```powershell
$secret = Read-Host 'Administrator password (12-72 bytes)' -AsSecureString
$env:PANEL_ADMIN_PASSWORD = [System.Net.NetworkCredential]::new('', $secret).Password
php tools/install.php --admin-email=admin@example.test
Remove-Item Env:\PANEL_ADMIN_PASSWORD
Remove-Variable secret
php tools/verify.php
php -S 127.0.0.1:8876 -t . router.php
```

Replace the email address. If a PHP command fails, check the error before continuing. The website is at `http://127.0.0.1:8876/`, and the panel at `/panel/`. Choose an available port if another demo is already using 8876.

`config/site.php` defines the site name, `database` (SQLite file or MySQL host/port/name/user/charset; env overrides `PANEL_DB_DRIVER`, `PANEL_DB_FILE`, `PANEL_DB_HOST/PORT/NAME/USER/PASSWORD`), and installation profile; `config/panel-fields.php` contains custom field labels. Put secrets in the Git-ignored `config/config.runtime.php`. The default database is `storage/site.sqlite`; MySQL (>= 8.0.19, MariaDB unsupported) needs an existing empty database and its password only via `PANEL_DB_PASSWORD` or `$databasePassword` in `config/config.runtime.php`. `database/schema.php` and `database/seed-base.php` are the reproducible setup sources; there are no migrations, so change the schema and reinstall a fresh database. `tools/verify.php` checks the structure equals `schema.php`. The seed has no fixed administrator account. Installation refuses to overwrite an existing database.

```powershell
git init
git add .
git commit -m "Initialize standalone CMS site"
```

This starter also accepts loopback access only. Production deployment requires a separate assessment of authentication, CAPTCHA, server configuration, backups, and email settings. The restriction is not removed automatically.
