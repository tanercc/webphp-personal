# Personal CMS SQLite demo

- [x] Copy the CMS and unchanged vendor distributions into an independent repository; no source-site content/config/uploads.
- [x] SQLite PDO adapter preserves existing panel list/form/API contracts, prepared values, role and CSRF gates.
- [x] Save SQLite CREATE and seed SQL; install transactionally without overwriting an existing database.
- [x] Split the official Personal layout into PHP page/section partials; load pages, projects, resume and settings from CMS tables.
- [x] Local contact form persists messages with CSRF and rate limits, without external mail/form services.
- [x] Verify local login, CRUD, translations, template relations, media scope, public output and secret-file denial in disposable SQLite databases.
- [x] Document setup, content mapping, licenses and demo-only limitations; commit.

Visual intent: preserve the supplied Personal theme, gradient typography, Bootstrap spacing, profile silhouette and responsive navigation. No replacement theme engine or visual redesign. Site-specific templates remain outside the CMS.
