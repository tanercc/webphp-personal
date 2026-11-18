# Dual database support (SQLite + MySQL) — design

> **Amendment (2026-11-18, owner decision during implementation):** There are no existing sites with data to carry forward, so the migration system (§4, `schema_migrations`, `tools/migrate.php`, legacy stamping, backups, and the maintenance gate) is **removed**. Every database is built from scratch from `database/schema.php` by `tools/install.php`; seeders (`database/seed.php`, `database/seed-base.php`) provide demo and test data. Schema changes edit `database/schema.php` and reinstall. Sections below that describe migrations are superseded. The live demo database is reinstalled from scratch once, with the owner's approval.

**Status:** Approved, amended. Phase 1 of 2. Phase 2 (schema fixes and the `media` module) gets its own design and becomes the first real migration built on this phase.

## Intent

The CMS must run unchanged on either SQLite or MySQL, chosen per installation by configuration. The owner wants the application itself to be database-portable; there is no single deployment target driving it. Portability must come from one source of truth, not from two hand-maintained copies kept in sync by hope.

Phase 1 delivers the infrastructure only: a driver/dialect layer, a single schema definition, a migration runner, portable seeds, and tests that run on both databases. The resulting schema is **structurally identical to today's `database/schema.sql`** (same tables, columns, declared types, defaults, constraints, indexes). The single exception is the `contact_limits` column rename required for MySQL (see Constraints).

## Goals

- One connection class family exposing the existing mysqli-like contract (`execute_query`, `query`, `prepare`/`bind_param`/`execute`/`get_result`, `fetch_assoc`/`fetch_row`/`fetch_array`/`fetch_all`, `insert_id`, `affected_rows`, `begin_transaction`/`commit`/`rollback`, `columns`) with a SQLite driver and a MySQL driver.
- Panel, API, public site, and tools emit one portable SQL subset. Dialect differences live in a small dialect class, not in regex rewriting of SQL text.
- Schema defined once in PHP; DDL for each database generated from it.
- Versioned migrations recorded in a `schema_migrations` table; `tools/migrate.php` applies pending ones. Migration 0001 is the baseline (today's schema).
- Seeds are database-neutral data, inserted with prepared statements.
- `tools/install.php`, `tools/verify.php`, `tools/create-site.php`, and `tests/http-workflows.py` work on both drivers.
- The live demo keeps working on SQLite with no manual steps beyond running `php tools/migrate.php` once (which only stamps the baseline).

## Non-goals

- Schema changes (FKs, tags relation, slug uniqueness, menus CHECK, media, captions removal). That is phase 2.
- Copying data between SQLite and MySQL installations.
- PostgreSQL, SQL Server, or other engines.
- Production deployment. The loopback-only restriction, local login, and the rest of the security posture stay as they are.
- An ORM or query builder for application queries. Application code keeps writing SQL strings.

## Anti-goals (failure even if it technically works)

- Requiring Composer, npm, or any third-party PHP library.
- Changing the HTTP API contracts: `data/itemsCount`, string IDs, soft deletion, translations, role/action/field/CSRF checks.
- Changing what the panel forms render. Forms read column types and defaults from `columns()`; both drivers must return the same `Field/Type/Null/Key/Default/Extra` values for the same table.
- Destroying or overwriting an existing database, on either driver, through install or migrate.
- Leaking SQL, bound values, DSNs, or credentials into errors, logs, or HTTP responses (current `sqlQuery` behavior is preserved).
- Keeping regex-based SQL translation "for compatibility".

## Constraints

- PHP 8.4, PDO only (`pdo_sqlite`, `pdo_mysql`). Both extensions are present on the development machine.
- MySQL 8.0+ (local development: ServBay MySQL 8.4.9). Character set `utf8mb4`, collation `utf8mb4_0900_ai_ci`, engine InnoDB. MariaDB is not supported in this phase (no `0900` collations; differing JSON/CHECK behavior).
- MySQL connection sets `sql_mode` to at least `STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`; native prepares (`PDO::ATTR_EMULATE_PREPARES=false`).
- Because strict MySQL rejects values SQLite silently stores (over-long `varchar`, non-integer `int`), all panel/API writes validate field values against the schema definition (length, integer, nullability) **on both drivers** and return 422 with the field name. Same request, same result on either engine. This makes SQLite slightly stricter than today; that is intended.
- `id` maps to signed `INT` on MySQL so it matches every existing `int` reference column (`template_id` FKs, `type_id`, `row_id`, ...).
- Identifiers keep the table/field allowlists in `panel/api/security.php` and `dbSqlIdentifier`. Values stay parameterized.
- `contact_limits` uses the MySQL reserved words `key` and `window`, and a `TEXT PRIMARY KEY` that MySQL cannot index. Baseline shape: `contact_limits(limit_key varchar(64) PRIMARY KEY, window_start int NOT NULL, hits int NOT NULL)` (the key is a SHA-256 hex digest, always 64 characters). `app/contact.php` updated accordingly. For the existing live SQLite database, the baseline stamp step rebuilds this one table (rows are short-lived rate-limit counters; copying them is enough) before recording 0001. This is the only tolerated difference in the stamp comparison.
- Identifier and comparison columns behave identically on both engines: `slug` columns, `templates.code`, `users.email`, `contact_limits.limit_key` use `utf8mb4_bin` on MySQL (matching SQLite's binary `=` and UNIQUE semantics). All other text uses the default `utf8mb4_0900_ai_ci`. The schema definition carries a per-column `binary` flag for this.
- Times: PHP keeps the configured `Europe/Istanbul` zone; application code continues to produce date/time strings in PHP. The MySQL session `time_zone` is set to the PHP default zone's current offset so any database-side time function agrees with PHP. No data conversion.
- Installation-specific settings: `config/site.php` gains a `database` array: `['driver' => 'sqlite', 'file' => .../storage/personal.sqlite]` or `['driver' => 'mysql', 'host', 'port', 'name', 'user', 'charset' => 'utf8mb4']`. MySQL password only from the environment (`PANEL_DB_PASSWORD`) or the git-ignored `config/config.runtime.php`. Existing `PANEL_DB_FILE`/`PERSONAL_DB_FILE` overrides keep working for SQLite; `PANEL_DB_DSN`-style overrides added for MySQL tests. The reference demo's database path and cookie name are unchanged.

## Approaches considered

- **A. Two drivers, two hand-written schema files.** Fastest; keeps the regex translation and doubles every schema change and migration. Rejected: phase 2 and the deferred AI content work would each pay the duplication cost.
- **B. Portable SQL + single schema definition + dialect class. (Chosen by the owner.)** Rewrites a small number of panel queries once, removes translation hacks, and gives migrations a single definition to work from.
- **C. Doctrine DBAL or similar.** Rejected: requires Composer.

## Design

### 1. Connection layer (`panel/include/database.php`, replacing `panel/include/sqlite.php`)

- `PanelDatabaseConnection` (abstract or single class with injected dialect) implements the existing contract. `PanelSqliteResult`/`PanelSqliteStatement` become driver-neutral `PanelDbResult`/`PanelDbStatement`; old class names may remain as aliases only if something outside the panel references them (verify with search; otherwise rename everywhere).
- `panelDatabaseConnection()` replaces `panelSqliteConnection()`; it reads `config/site.php` + env overrides and caches one connection per request.
- `execute_query` passes SQL through unchanged. No `SHOW COLUMNS`, `FOUND_ROWS`, `INSERT ... SET`, or `SQL_CALC_FOUND_ROWS` interception remains.
- Fetched values: both drivers return PHP scalars as PDO gives them; the API's existing string-ID normalization is relied upon and covered by tests on both drivers. If MySQL returns different PHP types than SQLite for the same column where it affects output, normalize in the result class, not in callers.

### 2. Dialect (`panel/include/db_dialect.php`)

One class per engine with only the operations that genuinely differ:

| Operation | SQLite | MySQL |
|---|---|---|
| `connect()` session setup | `PRAGMA foreign_keys=ON; busy_timeout` | `sql_mode`, `time_zone`, `NAMES utf8mb4` |
| `columns(table)` → `Field/Type/Null/Key/Default/Extra` | `PRAGMA table_info` (current mapping) | `information_schema.COLUMNS` mapped to the same shape and the same `Type` strings the schema declares |
| `tableExists(table)` | `sqlite_master` | `information_schema.TABLES` for the current schema |
| `upsert(table, keyCols, row, updateCols)` | `ON CONFLICT(...) DO UPDATE SET col=excluded.col` | `INSERT ... AS new ON DUPLICATE KEY UPDATE col=new.col` |
| DDL rendering from the schema definition | see §3 | see §3 |
| `integrityCheck()` | `PRAGMA integrity_check` + `foreign_key_check` | FK orphan queries generated from the schema definition (+ `CHECK TABLE` optional) |

### 3. Schema definition (`database/schema.php`)

- Returns a PHP array: tables → columns (name, logical type, length, nullable, default, auto-increment PK), unique keys, indexes, foreign keys (with `ON DELETE`), and checks. Logical types used today: `id`, `int`, `tinyint`, `varchar(n)`, `text`, `datetime`, `timestamp`.
- Type mapping:
  - `id` → SQLite `INTEGER PRIMARY KEY AUTOINCREMENT`; MySQL `INT NOT NULL AUTO_INCREMENT PRIMARY KEY`.
  - `int`/`tinyint`/`varchar(n)` → same names on both.
  - `text` → SQLite `text`; MySQL `MEDIUMTEXT` (TEXT's 64 KB is too small for page content). `columns()` must still report `text` on MySQL so panel forms do not change.
  - `timestamp` → SQLite `timestamp`; MySQL `DATETIME` (avoids TIMESTAMP auto-update and 2038 range). `columns()` reports `timestamp`.
  - `datetime` → `datetime` on both.
- `database/schema.sql` is removed as a source. Generated per-engine DDL can be printed with `php tools/migrate.php --print-schema=sqlite|mysql` for review; it is not committed.
- `tools/create-site.php` exports `database/schema.php`, migrations, and the dialect files instead of `schema.sql`.

### 4. Migrations (`database/migrations/NNNN_name.php`, `tools/migrate.php`)

- Each migration is a PHP file returning `['id' => '0001_baseline', 'up' => fn(PanelMigrator $m) => ...]`. Down migrations are not supported; recovery is via backup.
- `PanelMigrator` offers schema operations rendered per dialect: `createTable`, `dropTable`, `addColumn`, `renameColumn`, `addIndex`, `addUnique`, `addForeignKey`, `addCheck`, plus `execute(sql, params)` for data moves. Operations SQLite cannot do in place (adding FK/CHECK, changing a column) use the standard SQLite table-rebuild procedure (`foreign_keys=OFF`, create new, copy, drop, rename, `foreign_key_check`, `foreign_keys=ON`) inside the migrator, never in individual migrations.
- `schema_migrations(id varchar(100) PRIMARY KEY, applied_at datetime)`.
- `0001_baseline` creates today's schema via `createTable` calls from `database/schema.php` (with `contact_limits` renamed columns).
- Existing database without `schema_migrations`: `migrate.php` compares its live structure (via `columns()` and index/constraint introspection) with the baseline. If it matches (allowing the old `contact_limits` column names), it performs the rename and stamps `0001`; otherwise it stops with a structural diff and changes nothing.
- Safety: `migrate.php` refuses to run without `--backup-done` on MySQL; on SQLite it copies the database file to `storage/backups/<name>-<timestamp>.sqlite` first. SQLite migrations run in one transaction per migration; MySQL DDL is non-transactional, so each migration records itself only after all steps succeed, and the runner reports exactly which step failed.
- Panel and site never run migrations automatically. Every request (panel, API, public site) compares the latest applied migration id with the latest migration file id (one indexed query, cached per request). If the database lags, the public site returns a 503 maintenance page, the panel shows a maintenance message, and the API returns 503 JSON. Nothing writes to the database in that state.
- `schema.php` must always equal "baseline + all migrations". A test applies all migrations to an empty database and compares the result with a database created directly from `schema.php`, on both drivers.

### 5. Portable SQL in application code

Known changes (search for completeness during planning):

- `db_functions.php`: `SQL_CALC_FOUND_ROWS` + `SELECT FOUND_ROWS()` → separate `COUNT(*)` query (as `panel/api/database.php` already does); `LIMIT a, b` → `LIMIT b OFFSET a`; `INSERT ... SET` → standard `INSERT (cols) VALUES (...)`; `SHOW COLUMNS` → `$conn->columns()`; `sqlite_master` → `$conn->tableExists()`.
- `panel/api/database.php`: `SHOW COLUMNS` → `$conn->columns()`.
- `app/contact.php`: `ON CONFLICT` → dialect `upsert`; new column names.
- `login_security.php`, `app/bootstrap.php`: already portable; verify.
- Portable subset rules (document in AGENTS.md): backtick-quoted identifiers; single-quoted strings; `LIMIT n OFFSET m`; `COALESCE`; no `IFNULL`, `||` concatenation, `GROUP_CONCAT`, `ON CONFLICT`, `ON DUPLICATE KEY`, PRAGMA, `information_schema`, or date functions in application SQL. Date/time values are produced in PHP and bound as values.
- `LIKE` on non-binary text differs (SQLite: ASCII case-insensitive; MySQL `ai_ci`: case- and accent-insensitive). Accepted for panel search only; MySQL's behaviour is the more useful one there. `=` and UNIQUE on identifier columns stay identical via `utf8mb4_bin` (see Constraints).

### 6. Seeds (`database/seed.php`, `database/seed-base.php`)

- Replace `seed.sql`/`seed-base.sql` with PHP files returning ordered `table => list of rows` with explicit IDs. `install.php` inserts them with prepared statements in FK-safe order.
- MySQL: after explicit-ID inserts, AUTO_INCREMENT continues correctly; SQLite: `sqlite_sequence` is updated by AUTOINCREMENT inserts automatically. Test that the next panel-created row gets `max(id)+1` on both.
- Content is unchanged; a test compares row counts and a hash of exported rows against the current SQL seeds once, during migration of the seed format.

### 7. Installer, verify, create-site

- `install.php`:
  - SQLite: unchanged strategy (temp file, validate, exclusive publish).
  - MySQL: target database must exist and contain **no tables**; otherwise refuse. Run baseline migration + seed; on failure, drop only the tables this run created (safe because the database was empty) and report.
  - Starter admin creation unchanged (password hash only, from environment).
- `verify.php`: vendor/theme hashes as today; database check uses dialect `integrityCheck()` and verifies `schema_migrations` is current.
- `create-site.php`: exports the new files; the clean starter still installs on either driver.

### 8. Tests

- `tests/http-workflows.py` gains `--driver sqlite|mysql` (default sqlite). Direct database assertions currently done with Python `sqlite3` move to a tiny PHP CLI helper (`tests/db-query.php`, CLI-only, blocked over HTTP like `tests/`) that uses the same connection layer, so Python needs no MySQL client.
- MySQL runs create a disposable database `cms_test_<random>` using credentials from `PANEL_TEST_MYSQL_*` environment variables and drop it afterwards, even on failure. The live demo database is never touched.
- New checks: migration equivalence (§4), `columns()` parity between drivers for every table, baseline stamping of a copy of a pre-migration SQLite database, upsert behaviour, next-ID after seed.
- AGENTS.md checklist: for DB-touching changes, run the suite on both drivers; if MySQL is unavailable, say so explicitly in the report.

## Decided by the owner

- Approach B.
- MySQL support is for portability in general, not a specific host.

## Calls made by the designer (open to correction)

- MySQL 8.0+, utf8mb4 / `utf8mb4_0900_ai_ci`, InnoDB; MariaDB out of scope.
- `contact_limits` rebuilt in the baseline (`limit_key varchar(64)`, `window_start`, `hits`).
- Signed `INT` ids on MySQL.
- `utf8mb4_bin` for slug/code/email/limit_key so lookups and UNIQUE behave the same as SQLite.
- Schema-derived write validation (422) on both drivers instead of MySQL-only errors.
- Maintenance 503 for site/panel/API while migrations are pending.
- PHP keeps `Europe/Istanbul`; no time data conversion.
- PHP-array schema definition, PHP seeds, no committed generated DDL.
- No down migrations; SQLite auto-backup, MySQL requires explicit `--backup-done`.
- `text` → `MEDIUMTEXT`, `timestamp` → `DATETIME` on MySQL, with `columns()` reporting the logical type.
- Default demo driver stays SQLite.

## Left to the builder

- Exact class/file names beyond those above, internal structure of the migrator, and how DDL rendering is factored.
- Exact maintenance-message wording and placement.
- Whether `PanelDbResult` normalizes integer types (decide from test evidence).
- Order of implementation tasks, provided each task leaves both test runs green (MySQL run may start as "baseline only" until the connection layer lands).

## Documentation to update

`AGENTS.md`, `starter/AGENTS.md`, `README.md`, `docs/mimari.md`, `docs/yeni-site.md`, `starter/site/README.md`: driver selection, portable SQL rules, migrations workflow, seed format, test commands for both drivers.
