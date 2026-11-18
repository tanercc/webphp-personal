# Dual Database (SQLite + MySQL) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Run the CMS unchanged on SQLite or MySQL, selected per installation, with one schema definition, versioned migrations, portable seeds, and tests on both engines.

**Architecture:** Application code emits one portable SQL subset through a single connection class; a small dialect object per engine handles connection setup, introspection, upsert, DDL rendering, and integrity checks. `database/schema.php` is the current structure; migrations carry their own definitions and a test proves "baseline + migrations == schema.php" on both engines.

**Tech Stack:** PHP 8.4, PDO (`pdo_sqlite`, `pdo_mysql`), MySQL 8.4 (ServBay) for local MySQL runs, Python 3 test runner (stdlib only).

**Spec:** `docs/superpowers/specs/2026-11-18-dual-database-design.md`

## Global Constraints

- No Composer, npm, or third-party PHP libraries. PDO only.
- MySQL ≥ 8.0.19 (refines the spec's "8.0+": required for `INSERT ... AS new ON DUPLICATE KEY UPDATE`). Connection fails with a generic error below that version. MariaDB unsupported.
- MySQL: `utf8mb4`, default collation `utf8mb4_0900_ai_ci`, InnoDB; `utf8mb4_bin` on every `slug` column, `templates.code`, `users.email`, `contact_limits.limit_key`.
- MySQL session: `sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'`, `time_zone` = current offset of PHP's default zone (`(new DateTimeImmutable())->format('P')`), `PDO::ATTR_EMULATE_PREPARES=false`.
- SQLite session: `PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;`.
- Type mapping: `id` → SQLite `INTEGER PRIMARY KEY AUTOINCREMENT` / MySQL `INT NOT NULL AUTO_INCREMENT PRIMARY KEY`; `int`, `tinyint`, `varchar(n)`, `datetime` same name on both; `text` → MySQL `MEDIUMTEXT`; `timestamp` → MySQL `DATETIME`. `columns()` reports the logical type on both engines.
- Schema stays structurally identical to the current `database/schema.sql`, except `contact_limits(limit_key varchar(64) PRIMARY KEY, window_start int NOT NULL, hits int NOT NULL)`.
- HTTP API contracts unchanged: `data/itemsCount`, string IDs, soft deletion, translations, role/action/field/CSRF checks.
- Errors, logs, and HTTP responses never contain SQL, bound values, DSNs, or credentials.
- No regex rewriting of SQL text remains after Task 3.
- Install and migrate never delete or overwrite an existing database. Never import a seed into a live database. Tests use disposable databases only.
- Demo default driver stays SQLite at `storage/personal.sqlite`; session cookie name unchanged.
- Portable SQL rules for application code: backtick identifiers, single-quoted strings, `LIMIT n OFFSET m`, `COALESCE`, `LIKE ? ESCAPE '!'`; no `IFNULL`, `||`, `GROUP_CONCAT`, `ON CONFLICT`, `ON DUPLICATE KEY`, `PRAGMA`, `information_schema`, `sqlite_master`, `SHOW`, `SQL_CALC_FOUND_ROWS`, `FOUND_ROWS`, `INSERT ... SET`, or SQL date functions. Dates are produced in PHP.
- PHP keeps `Europe/Istanbul`; no time data conversion.
- After each task: `php -l` on changed PHP files, `python tests/http-workflows.py` (SQLite) and, from Task 6 on, `python tests/http-workflows.py --driver mysql`, and `php tools/verify.php`.
- MySQL test credentials come from `PANEL_TEST_MYSQL_HOST`, `PANEL_TEST_MYSQL_PORT`, `PANEL_TEST_MYSQL_USER`, `PANEL_TEST_MYSQL_PASSWORD`. Ask the user for them once; never write them to a tracked file.

## Review Focus

1. Contact form name of 51–100 characters: today accepted; `datas.name` is `varchar(50)`, so MySQL strict mode would fail with 503. Expected: same 422 message on both engines (Task 7).
2. 4-byte UTF-8 (emoji) and Turkish characters in page content and names: expected byte-identical round trip on both engines (Task 6).
3. Panel search input containing `%`, `_`, or `!`: expected to match literally on both engines (MySQL's default LIKE escape is backslash, SQLite has none) (Task 1).
4. MySQL connection failure (wrong password, server down) in site, panel, API, install, verify, migrate: expected generic error, exit code 1 / HTTP 503, and no host/user/password in output or logs (Task 3, Task 5).
5. IDs and integers on MySQL in API JSON: expected strings exactly as on SQLite (Select2 relies on it) (Task 6).

---

## File Structure

| Path | Responsibility |
|---|---|
| `panel/include/db_dialect.php` | `PanelDbDialect` interface, `PanelSqliteDialect`, `PanelMysqlDialect` |
| `panel/include/database.php` | `PanelDbResult`, `PanelDbStatement`, `PanelDbConnection`, `panelDatabaseConfig()`, `panelDatabaseConnection()` (replaces `panel/include/sqlite.php`) |
| `panel/include/db_schema.php` | `panelSchema()`, `panelValidateWrite()` |
| `panel/include/db_migrations.php` | `PanelMigrator`, `panelMigrationFiles()`, `panelSchemaIsCurrent()`, `panelRequireCurrentSchema()` |
| `database/schema.php` | Current schema definition |
| `database/migrations/0001_baseline.php` | Baseline migration with its own embedded definition |
| `database/seed.php`, `database/seed-base.php` | Demo / clean seed rows (replace `seed.sql`, `seed-base.sql`) |
| `tools/migrate.php` | CLI: status, apply, stamp legacy, print schema |
| `tools/install.php`, `tools/verify.php`, `tools/create-site.php` | Both drivers |
| `tests/db-query.php` | CLI helper: run a query as JSON; create/drop scratch MySQL databases |
| `tests/db-contract.php` | CLI contract checks for dialects, migrator, equivalence, stamping |
| `tests/fixtures/legacy-schema.sql` | Verbatim copy of today's `database/schema.sql` for stamp tests |
| `tests/http-workflows.py` | `--driver sqlite|mysql` |

Schema definition shape (used by `database/schema.php`, migrations, and `describe()`):

```php
['tables' => [
  'pages' => [
    'columns' => [
      'id' => ['type' => 'id'],
      'name' => ['type' => 'varchar', 'length' => 50],
      'slug' => ['type' => 'varchar', 'length' => 255, 'binary' => true],
      'is_active' => ['type' => 'tinyint', 'default' => 1],
      'created_at' => ['type' => 'timestamp'],
    ],
    'unique' => [],                 // list of column lists, e.g. [['row_id','lang_id']]
    'indexes' => [],                // name => column list
    'foreign' => [],                // list of ['column'=>..,'table'=>..,'references'=>'id','on_delete'=>'SET NULL']
  ],
]]
```

Column keys: `type` (`id|int|tinyint|varchar|text|datetime|timestamp`), `length` (varchar only), `nullable` (default `true`; `id` is never nullable), `default` (scalar or absent), `binary` (default `false`). Column order is significant. `describe()` returns the same shape; on SQLite `binary` is not observable and is omitted from comparisons.

---

### Task 1: Portable application SQL on the current adapter

**Files:**
- Modify: `panel/include/db_functions.php` (lines 30-36 `dbSqlLimit`, 126-127 and 251-252 LIKE, 225-280 found rows, 316/331/347 `SHOW COLUMNS`, 376 `INSERT ... SET`, 416 `sqlite_master`)
- Modify: `panel/api/database.php` (line 30 `SHOW COLUMNS`, line 72 LIKE)
- Modify: `app/contact.php` (line 11, 15)
- Modify: `panel/include/sqlite.php` (add `columns()` already exists; add `tableExists()`, `upsert()`)
- Test: `tests/http-workflows.py`

**Interfaces:**
- Produces (on current `PanelSqliteConnection`, kept with the same signatures by `PanelDbConnection` in Task 3):
  - `columns(string $table): array` — list of `['Field','Type','Null','Key','Default','Extra']`
  - `tableExists(string $table): bool`
  - `upsert(string $table, array $row, array $keyColumns): void` — inserts `$row`; on key conflict updates every non-key column of `$row`
- Produces: `dbLikePrefix(string $term): string` in `db_functions.php` — escapes `!`, `%`, `_` with `!` and appends `%`.
- `contact_limits` keeps its old column names in this task (renamed in Task 4).

- [ ] **Step 1: Add failing tests to `tests/http-workflows.py`** (inside the demo block, after login)
  - Static guard: for every file in `app/`, `panel/include/` (except `sqlite.php`, `database.php`, `db_dialect.php`, `db_migrations.php`), `panel/api/`, `templates/`, `index.php`, assert none of these case-insensitive tokens appear inside PHP string literals: `SQL_CALC_FOUND_ROWS`, `FOUND_ROWS`, `SHOW COLUMNS`, `sqlite_master`, `PRAGMA`, `ON CONFLICT`, `ON DUPLICATE`, `INSERT INTO \`[a-z_]+\` SET`, `LIMIT ' . ` followed by a comma pattern. Use a regex over file text; keep the list in one Python constant `NON_PORTABLE_SQL`.
  - LIKE literal match: create page `{'name':'100%_off!','slug':'like-test','type_id':'1'}`; `GET /panel/api/pages?q=100%25_` must return exactly that row (`itemsCount == 1`); `GET /panel/api/pages?q=1000` must return `itemsCount == 0`; `GET /panel/api/pages?q=100!` returns 0.
- [ ] **Step 2: Run** `python tests/http-workflows.py` — Expected: FAIL on the static guard.
- [ ] **Step 3: Implement**
  - `dbSqlLimit` emits ` LIMIT {size} OFFSET {offset}`.
  - `dbGetTableData` drops `SQL_CALC_FOUND_ROWS`; it stores the count from a `SELECT COUNT(*) FROM (<same query without LIMIT>) counted` executed with the same params in a static/global read by `dbGetFoundRows()` (same pattern as `apiDbGetTableData`). `dbGetFoundRows()` keeps its signature and return type.
  - `dbInsertTableRow` uses `INSERT INTO \`t\` (\`a\`,\`b\`) VALUES (?,?)`.
  - `SHOW COLUMNS` call sites use `$conn->columns($table)`; `dbIsTableExist` uses `$conn->tableExists($table)`.
  - All three LIKE sites use `LIKE ? ESCAPE '!'` with `dbLikePrefix(...)` (in `panel/api/database.php` call `dbLikePrefix` too; `db_functions.php` is already loaded there).
  - `app/contact.php` uses `$conn->upsert('contact_limits', ['key'=>..,'window'=>..,'hits'=>..], ['key'])`.
  - In `sqlite.php` delete the `SHOW COLUMNS`, `FOUND_ROWS`, `INSERT ... SET`, and `SQL_CALC_FOUND_ROWS` interception branches; add `tableExists` and `upsert` (SQLite `ON CONFLICT(...) DO UPDATE SET c=excluded.c` built from allowlisted identifiers via the same identifier regex).
- [ ] **Step 4: Run** `python tests/http-workflows.py` and `php tools/verify.php` — Expected: all PASS lines.
- [ ] **Step 5: Commit** `git commit -m "Use portable SQL in panel, API and contact form"`

---

### Task 2: Schema definition and engine dialects

**Files:**
- Create: `database/schema.php`, `panel/include/db_dialect.php`, `tests/db-query.php`, `tests/db-contract.php`
- Test: `tests/db-contract.php`

**Interfaces:**
- Produces `interface PanelDbDialect`:
  - `name(): string` — `'sqlite'` or `'mysql'`
  - `connect(array $config): PDO` — throws `RuntimeException('Database unavailable', 503)` on any failure, with no driver message attached (no `previous`)
  - `columns(PDO $pdo, string $table): array` — `Field/Type/Null/Key/Default/Extra`; `Type` is the logical declared type string (`int`, `tinyint`, `varchar(255)`, `text`, `timestamp`, `datetime`, `integer` for SQLite `id`); `Extra` is `auto_increment` for `id`
  - `tableExists(PDO $pdo, string $table): bool`
  - `upsertSql(string $table, array $columns, array $keyColumns): string` — `?` placeholders in `$columns` order
  - `createTableSql(string $table, array $definition): array` — list of DDL statements (table, then indexes)
  - `describe(PDO $pdo): array` — schema-definition shape for all tables except `schema_migrations` and `sqlite_sequence`
  - `integrityErrors(PDO $pdo, array $schema): array` — list of human-readable problems, empty when healthy
- `PanelMysqlDialect` writes `COMMENT 'cms:text'` / `COMMENT 'cms:timestamp'` on columns whose MySQL type differs from the logical type; `columns()` and `describe()` read the comment back. `connect()` checks `SELECT VERSION()` ≥ 8.0.19 and sets session values from Global Constraints.
- `PanelMysqlDialect::integrityErrors` runs, per foreign key in `$schema`, an orphan count query; `PanelSqliteDialect::integrityErrors` runs `PRAGMA integrity_check` and `PRAGMA foreign_key_check`.
- Config array consumed by `connect()`: SQLite `['driver'=>'sqlite','file'=>string]`; MySQL `['driver'=>'mysql','host'=>string,'port'=>int,'name'=>string,'user'=>string,'password'=>string,'charset'=>'utf8mb4']`.
- `tests/db-query.php` (CLI only, `PHP_SAPI==='cli'` else 404):
  - `php tests/db-query.php --sql="..." [--params='[json]']` → prints JSON list of rows; uses `panelDatabaseConnection()` once Task 3 lands, and in this task a dialect connect from env (`PANEL_DB_DRIVER`, `PANEL_DB_FILE`, `PANEL_DB_HOST`, `PANEL_DB_PORT`, `PANEL_DB_NAME`, `PANEL_DB_USER`, `PANEL_DB_PASSWORD`).
  - `--create-database=NAME` / `--drop-database=NAME` (MySQL only, connects without a schema using `PANEL_TEST_MYSQL_*`); NAME must match `^cms_test_[0-9a-f]{12}$`, else exit 1.
- `tests/db-contract.php` (CLI only): `php tests/db-contract.php --driver=sqlite|mysql`; SQLite uses a temp file, MySQL creates and always drops a scratch database; prints `PASS: <n> database contract checks (<driver>)`.

- [ ] **Step 1: Write `database/schema.php`** by transcribing `database/schema.sql` exactly into the definition shape (same table order, column order, defaults, `UNIQUE`s, both indexes, both `template_id` foreign keys with `on_delete => 'SET NULL'`), except `contact_limits` per Global Constraints; mark `binary => true` on the columns listed in Global Constraints. `contact_limits.limit_key` has `nullable => false`; `window_start`, `hits` `nullable => false`. `users.email` keeps `UNIQUE(email)`; `templates.code` keeps `UNIQUE(code)`.
- [ ] **Step 2: Write failing contract checks in `tests/db-contract.php`**
  - `schema_roundtrip`: create every table from `database/schema.php` via `createTableSql`; assert `describe()` equals the normalized definition.
  - `columns_shape`: for `pages`, assert `columns()` returns rows whose `Field` order matches the definition, `Type` of `name` is `varchar(50)`, of `created_at` is `timestamp`, `content` in `page_lang` is `text`, `is_active` `Default` is `'1'`, `id` `Key` is `PRI` and `Extra` `auto_increment`.
  - `columns_parity` (runs only when both `PANEL_TEST_MYSQL_*` and SQLite are available, invoked as `--driver=both`): `columns()` output identical between engines for every table, ignoring the `Type` of `id` (`integer` vs `int`).
  - `upsert`: insert then upsert the same `limit_key` into `contact_limits`; assert one row with updated `hits`.
  - `binary_collation` (MySQL): inserting `users` rows with emails `a@x.test` and `A@x.test` both succeed; `SELECT id FROM pages WHERE slug=?` with `'About'` does not match `'about'`.
  - `integrity`: healthy database returns `[]`; after inserting `datas.template_id=999` with FK checks disabled for the insert, MySQL/SQLite report one problem.
  - `connect_failure`: config with wrong password (MySQL) or a directory path as file (SQLite) throws `RuntimeException` with code 503 whose message is exactly `Database unavailable` and whose `getPrevious()` is null.
- [ ] **Step 3: Run** `php tests/db-contract.php --driver=sqlite` — Expected: FAIL (dialect classes missing).
- [ ] **Step 4: Implement `panel/include/db_dialect.php`** per the Interfaces block. SQLite `describe()` uses `PRAGMA table_info`, `index_list`/`index_info` (unique vs non-unique; skip `sqlite_autoindex_*` names but report their columns as `unique`), `foreign_key_list`.
- [ ] **Step 5: Run** `php tests/db-contract.php --driver=sqlite`, `--driver=mysql`, `--driver=both` — Expected: PASS lines for each.
- [ ] **Step 6: Commit** `git commit -m "Add schema definition and SQLite/MySQL dialects"`

---

### Task 3: Driver-neutral connection layer

**Files:**
- Create: `panel/include/database.php`
- Delete: `panel/include/sqlite.php`
- Modify: `panel/include/db_functions.php:10-11`, `config/site.php`, `config/config.php:6`, `tools/create-site.php:82` (site.php template), `panel/include/login_security.php:58` (type hint)
- Test: `tests/http-workflows.py`, `tests/db-contract.php`

**Interfaces:**
- Consumes: `PanelDbDialect` (Task 2).
- Produces:
  - `final class PanelDbConnection` with public `int $insert_id`, `int $affected_rows`; `__construct(PanelDbDialect $dialect, array $config)`; `dialect(): PanelDbDialect`; `pdo(): PDO`; `execute_query(string $sql, array $values = []): PanelDbResult|bool`; `query(string $sql): PanelDbResult|bool`; `prepare(string $sql): PanelDbStatement`; `columns(string $table): array`; `tableExists(string $table): bool`; `upsert(string $table, array $row, array $keyColumns): void`; `begin_transaction(): void`; `commit(): void`; `rollback(): void`.
  - `PanelDbResult` and `PanelDbStatement`: same public methods/properties as today's `PanelSqliteResult`/`PanelSqliteStatement`.
  - `panelDatabaseConfig(): array` — from `$installation['database']`, overridden by env (`PANEL_DB_DRIVER`, `PANEL_DB_FILE`/`PERSONAL_DB_FILE`, `PANEL_DB_HOST`, `PANEL_DB_PORT`, `PANEL_DB_NAME`, `PANEL_DB_USER`, `PANEL_DB_PASSWORD`), MySQL password falling back to `$databasePassword` from `config/config.runtime.php`.
  - `panelDatabaseConnection(): PanelDbConnection` — one cached instance per request.
- `config/site.php`: replace `'database_file' => ...` with `'database' => ['driver' => 'sqlite', 'file' => dirname(__DIR__) . '/storage/personal.sqlite']`. `create-site.php` writes the same shape with `storage/site.sqlite`.
- No SQL text is inspected or rewritten anywhere in `database.php`.

- [ ] **Step 1: Add failing checks**
  - `tests/db-contract.php` `connection_contract`: through `PanelDbConnection`, insert a `pages` row, assert `insert_id > 0`, `affected_rows == 1` after an update, `prepare()->bind_param()->execute()->get_result()->fetch_assoc()` returns the row, rollback discards an insert.
  - `tests/http-workflows.py`: assert `/panel/include/database.php` and `/panel/include/db_dialect.php` return 403; replace `/panel/include/sqlite.php` in the forbidden list.
- [ ] **Step 2: Run** both — Expected: FAIL (class missing).
- [ ] **Step 3: Implement `database.php`**, switch `db_functions.php` to `$conn = panelDatabaseConnection();`, delete `sqlite.php`, update config files and `PanelSqliteConnection` type hints (`grep -rn PanelSqlite` must return nothing outside `graft/`). `$databaseFile` global is removed; `install.php`/`verify.php` temporarily read `panelDatabaseConfig()['file']`.
- [ ] **Step 4: Run** `php tests/db-contract.php --driver=sqlite`, `--driver=mysql`, `python tests/http-workflows.py`, `php tools/verify.php` — Expected: PASS.
- [ ] **Step 5: Commit** `git commit -m "Replace SQLite adapter with driver-neutral connection layer"`

---

### Task 4: Migrator, baseline, and `tools/migrate.php`

**Files:**
- Create: `panel/include/db_migrations.php`, `database/migrations/0001_baseline.php`, `tools/migrate.php`, `tests/fixtures/legacy-schema.sql` (byte copy of current `database/schema.sql`)
- Modify: `app/contact.php` (new column names), `router.php` and `.htaccess` (no change needed if `tests/` and `database/` already blocked — verify)
- Test: `tests/db-contract.php`

**Interfaces:**
- Consumes: `PanelDbConnection`, `PanelDbDialect::createTableSql/describe` (Tasks 2–3).
- Produces:
  - `final class PanelMigrator`: `__construct(PanelDbConnection $db)`; `createTable(string $table, array $definition): void`; `dropTable(string $table): void`; `addColumn(string $table, string $column, array $definition): void`; `renameColumn(string $table, string $from, string $to): void`; `addIndex(string $table, string $name, array $columns): void`; `addUnique(string $table, string $name, array $columns): void`; `addForeignKey(string $table, array $foreign): void`; `rebuildTable(string $table, array $newDefinition, array $columnMap): void`; `execute(string $sql, array $params = []): void`; `applyPending(): array` (returns applied ids); `stampLegacy(): void`; `status(): array` (`['applied'=>[], 'pending'=>[]]`).
  - On SQLite, `addForeignKey` and any change SQLite cannot do in place go through `rebuildTable` (`foreign_keys=OFF`, create `<table>__new`, copy mapped columns, drop, rename, recreate indexes, `foreign_key_check` must be empty, `foreign_keys=ON`).
  - Migration file contract: `return ['id' => '0001_baseline', 'up' => static function (PanelMigrator $m): void {...}];` — `0001_baseline.php` embeds its own full definition array (a copy of `database/schema.php`'s contents at this point), never `require`s `database/schema.php`.
  - `schema_migrations(id varchar(100) PRIMARY KEY, applied_at datetime NOT NULL)` created by the migrator itself.
  - `panelMigrationFiles(): array` — id ⇒ path, sorted by id, from `database/migrations/*.php`.
  - `panelSchemaIsCurrent(PanelDbConnection $db): bool` — `schema_migrations` exists and its `MAX(id)` equals the last id of `panelMigrationFiles()`.
- `tools/migrate.php` (CLI only): `--status`, `--print-schema=sqlite|mysql` (DDL for `database/schema.php` to stdout, no connection), default action applies pending migrations. SQLite: copy the database file to `storage/backups/<basename>-<YmdHis>.sqlite` before any change (create `storage/backups` with 0750). MySQL: refuse with exit 1 unless `--backup-done`. Database without `schema_migrations` and with tables: call `stampLegacy()`.
- `stampLegacy()`: `describe()` must equal the baseline definition, except `contact_limits` may have the legacy shape `(key text PRIMARY KEY, window integer NOT NULL, hits integer NOT NULL)`; if legacy, `rebuildTable` maps `key→limit_key`, `window→window_start`, `hits→hits`; then records `0001_baseline`. Any other difference: print a structural diff (table, column, expected, actual) to stderr, exit 1, change nothing.
- Each migration on SQLite runs in one transaction; on MySQL, the `schema_migrations` row is inserted only after the `up` callable returns; failure output names the migration id and step index.

- [ ] **Step 1: Add failing contract checks**
  - `migration_equivalence`: empty database → `applyPending()` → `describe()` equals normalized `database/schema.php` (both drivers).
  - `stamp_legacy` (SQLite): create a database from `tests/fixtures/legacy-schema.sql`, insert one `contact_limits` row and one `pages` row, run `php tools/migrate.php` with `PANEL_DB_FILE` pointing at it; assert exit 0, `schema_migrations` has `0001_baseline`, `contact_limits` has `limit_key` with the copied row, the `pages` row is intact, and a backup file exists.
  - `stamp_mismatch` (SQLite): legacy fixture plus `ALTER TABLE pages ADD COLUMN extra int`; assert exit 1, stderr contains `pages` and `extra`, file hash unchanged.
  - `mysql_backup_flag`: `php tools/migrate.php` on MySQL without `--backup-done` exits 1 and creates no tables.
  - `migrator_ops` (both drivers, scratch table `mig_probe`): `createTable`, `addColumn`, `renameColumn`, `addIndex`, `addUnique`, `addForeignKey` (to `templates`), `rebuildTable`; after each, `describe()['tables']['mig_probe']` matches the expected definition; `dropTable` removes it.
  - `print_schema`: `--print-schema=mysql` output contains `MEDIUMTEXT`, `utf8mb4_bin`, `AUTO_INCREMENT`; `--print-schema=sqlite` contains `AUTOINCREMENT`.
- [ ] **Step 2: Run** `php tests/db-contract.php --driver=sqlite` and `--driver=mysql` — Expected: FAIL.
- [ ] **Step 3: Implement** `db_migrations.php`, `0001_baseline.php`, `tools/migrate.php`; update `app/contact.php` to `limit_key`/`window_start`.
- [ ] **Step 4: Run** both contract drivers, `python tests/http-workflows.py`, `php tools/verify.php` — Expected: PASS. (`install.php` still builds from `schema.sql` until Task 5; update that file's `contact_limits` line to the new shape in this task so the contact form keeps working.)
- [ ] **Step 5: Commit** `git commit -m "Add migrations with baseline and legacy stamping"`

---

### Task 5: Portable seeds, installer, verify, create-site

**Files:**
- Create: `database/seed.php`, `database/seed-base.php`
- Delete: `database/schema.sql`, `database/seed.sql`, `database/seed-base.sql`
- Modify: `tools/install.php`, `tools/verify.php`, `tools/create-site.php:54` (export list: `database/schema.php`, `database/migrations/0001_baseline.php`, `database/seed-base.php`, `tools/migrate.php`, and the new `panel/include/*.php` are already under `panel/`)
- Test: `tests/http-workflows.py`, `tests/db-contract.php`

**Interfaces:**
- Consumes: `PanelMigrator::applyPending()`, `PanelDbConnection`, `panelDatabaseConfig()`.
- Seed file contract: `return ['langs' => [[...row...], ...], 'usertypes' => [...], ...];` — tables in FK-safe insertion order, every row with explicit `id`, values exactly as in the current SQL seeds.
- `install.php` keeps its CLI options and messages; final line becomes `Installed <profile> <driver> database.`
  - SQLite: unchanged temp-file + validate + exclusive publish flow, now `applyPending()` then seed inserts then starter admin, then `integrityErrors()` must be empty.
  - MySQL: connect to the configured database; if it has any table, exit 1 `Database is not empty; refusing to install.`; else `applyPending()`, seed, admin; on any failure drop exactly the tables listed by `describe()` after the failure (database was empty) plus `schema_migrations`, then exit 1 `Installation failed. Existing databases preserved.`
- `verify.php`: vendor/theme hash checks unchanged; database checks become `integrityErrors() === []`, `panelSchemaIsCurrent()`, and `describe()` equals normalized `database/schema.php`. Final line: `PASS: <n> panel vendor hashes, <m> official theme assets, <driver> integrity, schema and migrations`.

- [ ] **Step 1: Generate seed PHP files** with a throwaway script in the scratchpad (not committed): load current `schema.sql` + `seed.sql` into an in-memory SQLite, export each table's rows in insertion order with `var_export`. Same for `seed-base.sql`.
- [ ] **Step 2: One-time equivalence check (not committed)**: install the old way into temp A and the new way into temp B (SQLite); for every table, `SELECT * ORDER BY id` must be identical. Record the result in the commit message.
- [ ] **Step 3: Add failing checks**
  - `tests/db-contract.php` `next_id_after_seed`: after seed, inserting a `pages` row yields `max(id)+1` (both drivers).
  - `tests/db-contract.php` `install_mysql_refuses_nonempty`: scratch database with one table → `install.php` exits 1 and the table still exists.
  - `tests/db-contract.php` `install_secret_free`: MySQL install/verify/migrate with a wrong `PANEL_DB_PASSWORD` exit 1; combined stdout+stderr contains neither the password, the user, nor the host.
  - `tests/http-workflows.py` starter section: replace `database/seed-base.sql` reads with `database/seed-base.php`; forbidden-copy list uses `database/seed.php`; the `sqlite_master` assertion becomes a `tableExists('contact_limits')` check via `tests/db-query.php` (Task 6 wires the helper; in this task call it directly with `PANEL_DB_FILE`).
- [ ] **Step 4: Run** — Expected: FAIL.
- [ ] **Step 5: Implement** installer, verify, create-site changes; delete the three SQL files.
- [ ] **Step 6: Run** `python tests/http-workflows.py`, `php tools/verify.php`, `php tests/db-contract.php --driver=sqlite`, `--driver=mysql` — Expected: PASS.
- [ ] **Step 7: Commit** `git commit -m "Install from migrations and portable PHP seeds on SQLite or MySQL"`

---

### Task 6: HTTP suite on both drivers

**Files:**
- Modify: `tests/http-workflows.py`
- Test: itself

**Interfaces:**
- Consumes: `tests/db-query.php` (Task 2), `tests/db-contract.php`.
- CLI: `python tests/http-workflows.py [--driver sqlite|mysql]` (default `sqlite`). `mysql` without all `PANEL_TEST_MYSQL_*` set: print `MySQL test credentials missing (PANEL_TEST_MYSQL_HOST/PORT/USER/PASSWORD)` and exit 2.
- Python helper `db(sql: str, params: tuple = ()) -> list[dict]` calls `php tests/db-query.php` with the run's env; replaces every `sqlite3` use (lines 74–95, 143–149). `import sqlite3` removed.
- MySQL runs: create `cms_test_<12 hex>` for the demo and another for the starter via `--create-database`; always `--drop-database` in `finally`; server env gets `PANEL_DB_DRIVER=mysql`, `PANEL_DB_HOST`, `PANEL_DB_PORT`, `PANEL_DB_NAME`, `PANEL_DB_USER`, `PANEL_DB_PASSWORD`.
- Each run also executes `php tests/db-contract.php --driver=<driver>`; the MySQL run additionally `--driver=both`.
- "Demo content unchanged" checks keep hashing `storage/personal.sqlite` in both modes.

- [ ] **Step 1: Add failing assertions**
  - Unicode round trip: `POST /panel/api/pages` `{'name':'Çağrı 😀 ışık','slug':'unicode-test','type_id':'1'}`; `GET` returns the identical string; `db("SELECT name FROM pages WHERE slug=?",('unicode-test',))[0]['name']` identical.
  - String IDs: every value of `id` in `GET /panel/api/pages` `data` and in `GET /panel/api/pages/1` is a JSON string.
- [ ] **Step 2: Run** `python tests/http-workflows.py --driver mysql` — Expected: FAIL (flag unknown).
- [ ] **Step 3: Implement** the driver flag, helper, scratch-database lifecycle.
- [ ] **Step 4: Run** `python tests/http-workflows.py` and `python tests/http-workflows.py --driver mysql` — Expected: all PASS lines in both; `SHOW DATABASES LIKE 'cms_test_%'` afterwards returns nothing.
- [ ] **Step 5: Commit** `git commit -m "Run HTTP workflows against SQLite and MySQL"`

---

### Task 7: Schema-derived write validation

**Files:**
- Create: `panel/include/db_schema.php`
- Modify: `panel/api/security.php:108-137` (`apiValidateFields` end), `panel/include/db_functions.php` (`dbLegacyWriteValues`), `app/contact.php:7`
- Test: `tests/http-workflows.py`

**Interfaces:**
- Produces:
  - `panelSchema(): array` — cached `require` of `database/schema.php`.
  - `panelValidateWrite(string $table, array $fields): array` — returns fields with `''`/`null` for nullable `int`/`tinyint` as `null`; throws `RuntimeException('Invalid value: <column>', 422)` when: column not in schema; `int` not matching `^-?\d+$` or outside −2147483648..2147483647; `tinyint` outside −128..127; non-nullable column given `null`/`''` (except `varchar`/`text`, where `''` is valid); `varchar` with `mb_strlen > length`; `text` with `strlen > 16777215`. `id` is never validated as writable.
- `apiValidateFields` calls `panelValidateWrite($table, $fields)` on its result and returns that. `dbLegacyWriteValues` calls it on its result.
- `app/contact.php`: name limit becomes 50 (`mb_strlen($values['name'])>50`), same error message; insert values pass through `panelValidateWrite('datas', ...)`, whose 422 maps to the existing 422 contact error.

- [ ] **Step 1: Add failing tests** (both drivers via Task 6 harness)
  - `PUT /panel/api/pages/1` `{'name': 'ş'*51}` → 422 with `{"error":"Invalid value: name"}`; `{'name': 'ş'*50}` → 200 and round-trips.
  - `PUT /panel/api/pages/1` `{'type_id':'abc'}` → 422; `{'type_id':''}` → 200 and `db()` shows `NULL`.
  - `PUT /panel/api/lang` on `page_lang` with `title` of 256 chars → 422.
  - Contact: name of 51 chars → 422 with `Ad, geçerli e-posta ve en az 10 karakterlik bir mesaj girin.`; name of 50 chars → success.
- [ ] **Step 2: Run** both drivers — Expected: FAIL (SQLite returns 200 for 51 chars; MySQL returns 500/503).
- [ ] **Step 3: Implement** per Interfaces.
- [ ] **Step 4: Run** both drivers and `php tools/verify.php` — Expected: PASS.
- [ ] **Step 5: Commit** `git commit -m "Validate writes against the schema on both engines"`

---

### Task 8: Maintenance gate for pending migrations

**Files:**
- Modify: `panel/include/db_migrations.php` (add gate), `app/bootstrap.php` (after includes), `panel/index.php` (after `db_functions.php` include), `panel/api/index.php:29-30`
- Test: `tests/http-workflows.py`

**Interfaces:**
- Consumes: `panelSchemaIsCurrent()` (Task 4).
- Produces: `panelRequireCurrentSchema(string $context): void` with `$context` ∈ `site|panel|api`. When not current: HTTP 503, `Retry-After: 300`, `Cache-Control: no-store`; `site` and `panel` emit a minimal Turkish HTML page titled `Bakım çalışması` with the sentence `Site kısa bir bakım çalışmasındadır. Lütfen biraz sonra tekrar deneyin.`; `api` emits `{"error":"Maintenance"}`; then `exit`. No database write happens before the gate in any entry point.
- A database connection failure in any entry point also yields 503 with the same pages (catch `RuntimeException` code 503 from connect).

- [ ] **Step 1: Add failing tests** (both drivers): copy-run setup with an extra migration file is not allowed in the repo, so simulate lag by `db("DELETE FROM schema_migrations")` on the disposable database; then `GET /` → 503 containing `Bakım çalışması`; `GET /panel/` → 503; `GET /panel/api/pages` (logged in) → 503 JSON `{"error":"Maintenance"}`; `POST /contact` → 503 and `datas` count unchanged. Restore with `db("INSERT INTO schema_migrations(id,applied_at) VALUES('0001_baseline','2026-11-18 00:00:00')")` and confirm `GET /` → 200.
- [ ] **Step 2: Run** — Expected: FAIL.
- [ ] **Step 3: Implement** per Interfaces.
- [ ] **Step 4: Run** both drivers and `php tools/verify.php` — Expected: PASS.
- [ ] **Step 5: Commit** `git commit -m "Serve maintenance responses while migrations are pending"`

---

### Task 9: Documentation and the live demo database

**Files:**
- Modify: `AGENTS.md`, `starter/AGENTS.md`, `README.md`, `docs/mimari.md`, `docs/yeni-site.md`, `starter/site/README.md`

- [ ] **Step 1: Update docs**: driver selection (`config/site.php` `database`, env overrides, password only via env or `config/config.runtime.php`); portable SQL rules (copy the Global Constraints bullet); migrations workflow (`php tools/migrate.php`, backups, `--backup-done`, never edit an applied migration, `schema.php` must equal baseline + migrations); seed format (`database/seed*.php`); test commands for both drivers and the rule "if MySQL is unavailable, say so in the report"; replace every mention of `schema.sql`, `seed.sql`, `seed-base.sql`, `panel/include/sqlite.php`, `PRAGMA table_info` with the new equivalents (`grep -rn "schema.sql\|seed.sql\|seed-base.sql\|sqlite.php\|PRAGMA table_info" AGENTS.md starter docs README.md` returns only historical plan files).
- [ ] **Step 2: Ask the user** before touching `storage/personal.sqlite`. With approval, run `php tools/migrate.php --status`, then `php tools/migrate.php`; expected output names the backup path and `0001_baseline` stamped. Then `php tools/verify.php` → PASS and open `/`, `/contact`, `/panel/` once on the dev server.
- [ ] **Step 3: Run** `python tests/http-workflows.py`, `python tests/http-workflows.py --driver mysql`, `php tools/verify.php` — Expected: PASS.
- [ ] **Step 4: Commit** `git commit -m "Document dual database support and migration workflow"`
