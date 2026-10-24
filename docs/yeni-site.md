# Create a new website with this CMS

This repository contains both a working **Personal example** and a **starter** for independent projects using the same CMS. The example theme is optional. You do not need to reread the entire source tree or every vendor package for each new site; start with the entry points below.

## Quick start

From the reference repository root, choose a sibling directory that does not exist:

```powershell
php tools/create-site.php --target=../new-site --name="New Company"
cd ../new-site
```

The command copies tracked core files from the working tree. Git is required to generate the project, but not to run the new PHP site. Track newly added core files before generating a copy. An existing destination is rejected even when empty. Destinations inside the source repository are also rejected. The new project does not inherit the source `.git` directory or commit history; initialize its repository as shown below.

Included: the panel/packages, SQLite adapter, schema/clean seed, access restrictions and routing, plain PHP frontend, setup/verification tools, and developer guides. Excluded: the Personal design/images/portfolio, demo seed/account, runtime database, sessions/logs/uploads, private configuration, and source Git history. The source `config/config.runtime.php` is excluded even if tracked.

Install without writing the password into command history:

```powershell
$secret = Read-Host 'Administrator password (12-72 bytes)' -AsSecureString
$env:PANEL_ADMIN_PASSWORD = [System.Net.NetworkCredential]::new('', $secret).Password
php tools/install.php --admin-email=admin@example.test
Remove-Item Env:\PANEL_ADMIN_PASSWORD
Remove-Variable secret
php tools/verify.php
php -S 127.0.0.1:8877 -t . router.php
```

Replace the email address; 8877 is an example available port. Resolve failed commands before continuing. The administrator password is not recorded in SQL, documentation, or command arguments; only its hash is stored in the database. No default/demo user is created. The website is served at the root, and the panel at `/panel/`.

```powershell
git init
git add .
git commit -m "Initialize standalone CMS site"
```

## Where to build the new design

- `index.php`: URL-to-allowed-page mapping. Never turn database values into unrestricted include paths.
- `templates/`: manually assembled PHP page and section files. Separate shared head/navigation/footer partials here.
- `css/`, `js/`, `assets/`: files belonging to the new site. Use the existing helpers to generate CSS/JS SRI and content versions.
- `config/site.php`: site name, installation profile, database path, and independent session name.
- `config/panel-fields.php`: custom column labels for data types, when needed.
- `database/schema.sql`: tables/indexes; `database/seed-base.sql`: neutral setup records. Keep any site-specific content seed separate and free of secrets.
- `panel/`: the shared role/CRUD/form/media core. Do not change it merely to implement a new design.

Generated projects contain none of Personal's `templates/pages/`, CSS, images, or paid form integration. Replace the plain `templates/page.php` to build the new design. No theme engine is introduced.

## Map content to existing tables

| Requirement | Existing structure | Contract |
|---|---|---|
| Corporate page | `pages` + `page_lang` | Separate general settings from translated content; publish only active, non-deleted records. |
| Repeated services/projects/team cards | `datatypes` + `datas` + `data_lang` | Define types in the new site's seed; do not copy Personal's numeric type IDs. |
| Hierarchy/categories | `cats`, `datas.cat_id` | Category default: `cats.template_id`; record override: `datas.template_id`. |
| Navigation | `menutypes` + `menus` + `menu_lang` | Allowlisted URL schemes; preserve ordering, active status, and soft deletion. |
| Site name, contact details, footer | `variables` + `variable_lang` | Stable keys, panel-managed values, context-appropriate encoding. |
| Media | `uploads/files/{resource}/{id}` | Save a new record first; the picker opens only that record's folder. |

The helpers `sitePage($slug)`, `siteRecords($typeSlug)`, `setting($key)`, and `siteRows($sql,$params)` are in `app/bootstrap.php`. Their default visitor language is Turkish (`lang_id=1`); additional language routes must be implemented separately. Empty translation fields fall back to general values. Use `e()` for HTML text/attributes and `panelRichHtml()` for rich HTML.

## Working with an AI agent

The following brief is a useful starting point in the new repository:

> Read AGENTS.md, docs/mimari.md, and docs/yeni-site.md first. Build a [website type] using [design reference] in this repository. Preserve the existing CMS, SQLite, and media security contracts. Keep site-specific HTML/PHP/CSS/JS in the frontend. Load content from pages/datas/variables and their translations. Save the required CREATE and seed SQL. Inspect the files relevant to the task; do not modify vendor sources or write to other repositories.

The guides do not replace implementation review. The agent must inspect callers and tests for the behavior it changes, and read relevant core files when touching database, role, or media behavior. Record new project decisions in that project's AGENTS/README files.

## The Personal reference example

The existing `index.php`, `templates/pages/`, `templates/partials/`, `css/`, `assets/`, and `database/seed.sql` remain the working example in this repository; they were not moved or removed. Running `php tools/install.php` here still installs the demo and refuses to overwrite an existing database. To try a clean database in a different file within the reference repository, set `PANEL_DB_FILE` and use `--profile=starter --admin-email=...`; the Personal frontend does not automatically switch themes. The project-generation command is the recommended route for a new site.

## Security and production boundary

Generated projects retain loopback restrictions and password/CSRF/role/field/media protections. Generating a project is not production deployment. Assess CAPTCHA, external access, SMTP, server routing, persistent backups, and TLS separately. Secrets belong in `config/config.runtime.php`; runtime databases, sessions, and logs belong in `storage/`, outside Git and HTTP access. Never copy another site's configuration or upload directory.
