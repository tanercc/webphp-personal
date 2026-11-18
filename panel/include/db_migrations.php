<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__.'/database.php';
/** Versioned schema migrations on SQLite and MySQL.
 * A migration file returns `['id' => '0001_name', 'up' => static function (PanelMigrator $m): void {...}]`; the baseline also
 * returns its embedded definition under `schema` for stampLegacy(). Applied ids are recorded in `schema_migrations`.
 */

const PANEL_MIGRATION_LEDGER = 'schema_migrations';
const PANEL_BASELINE_MIGRATION = '0001_baseline';

/** A migration failed. The message names only the migration id and step index, never SQL or bound values. */
final class PanelMigrationFailure extends RuntimeException {}

/** describe() does not match the baseline; `differences` holds one "table.column: expected X, actual Y" line per difference. */
final class PanelSchemaMismatch extends RuntimeException {
    public function __construct(public readonly array $differences) { parent::__construct('Schema does not match the baseline'); }
}

/** Migration files as id => path, sorted by id. */
function panelMigrationFiles(?string $directory = null): array {
    $directory ??= dirname(__DIR__, 2).'/database/migrations';
    $files = [];
    foreach (glob($directory.'/*.php') ?: [] as $path) {
        $id = basename($path, '.php');
        if (!preg_match('/^\d{4}_[a-z0-9_]{1,95}$/D', $id)) { throw new RuntimeException('Invalid migration file name'); }
        $files[$id] = $path;
    }
    ksort($files, SORT_STRING);
    return $files;
}

function panelSchemaIsCurrent(PanelDbConnection $db): bool {
    $files = panelMigrationFiles();
    if (!$files || !$db->tableExists(PANEL_MIGRATION_LEDGER)) { return false; }
    $row = $db->query('SELECT MAX(`id`) AS `id` FROM `schema_migrations`')->fetch_assoc();
    return ($row['id'] ?? null) === array_key_last($files);
}

function panelLoadMigration(string $id, string $path): array {
    $migration = (static fn (string $file): mixed => require $file)($path);
    if (!is_array($migration) || ($migration['id'] ?? null) !== $id || !(($migration['up'] ?? null) instanceof Closure)) {
        throw new RuntimeException('Invalid migration file');
    }
    return $migration;
}

/** Human-readable structural differences between two normalized schemas (table, column, expected, actual). */
function panelSchemaDifferences(array $expected, array $actual): array {
    $show = static fn (mixed $value): string => $value === null ? 'missing' : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $names = array_unique(array_merge(array_keys($expected['tables']), array_keys($actual['tables'])));
    sort($names);
    $lines = [];
    foreach ($names as $table) {
        $want = $expected['tables'][$table] ?? null;
        $have = $actual['tables'][$table] ?? null;
        if ($want === $have) { continue; }
        if ($want === null || $have === null) {
            $lines[] = $table.': expected '.($want === null ? 'no table' : 'table').', actual '.($have === null ? 'missing' : 'unexpected table');
            continue;
        }
        $columns = array_unique(array_merge(array_keys($want['columns']), array_keys($have['columns'])));
        foreach ($columns as $column) {
            if (($want['columns'][$column] ?? null) !== ($have['columns'][$column] ?? null)) {
                $lines[] = $table.'.'.$column.': expected '.$show($want['columns'][$column] ?? null).', actual '.$show($have['columns'][$column] ?? null);
            }
        }
        if (array_keys($want['columns']) !== array_keys($have['columns'])) {
            $lines[] = $table.' column order: expected '.implode(',', array_keys($want['columns'])).', actual '.implode(',', array_keys($have['columns']));
        }
        foreach (['unique', 'indexes', 'foreign'] as $part) {
            if ($want[$part] !== $have[$part]) { $lines[] = $table.' '.$part.': expected '.$show($want[$part]).', actual '.$show($have[$part]); }
        }
    }
    return $lines;
}

final class PanelMigrator {
    private const LEDGER_DEFINITION = ['columns' => [
        'id' => ['type' => 'varchar', 'length' => 100, 'nullable' => false, 'primary' => true],
        'applied_at' => ['type' => 'datetime', 'nullable' => false],
    ]];
    /** contact_limits as created by the SQLite schema.sql used before migrations, and its column map onto the baseline. */
    private const LEGACY_CONTACT_LIMITS = ['columns' => [
        'key' => ['type' => 'text', 'primary' => true],
        'window' => ['type' => 'integer', 'nullable' => false],
        'hits' => ['type' => 'integer', 'nullable' => false],
    ]];
    private const LEGACY_CONTACT_LIMITS_MAP = ['key' => 'limit_key', 'window' => 'window_start', 'hits' => 'hits'];

    private int $step = 0;
    private bool $inTransaction = false;

    public function __construct(private PanelDbConnection $db, private ?string $directory = null) {}

    public function createTable(string $table, array $definition): void {
        $this->step++;
        $statements = $this->db->dialect()->createTableSql($table, $definition);
        if ($this->sqlite()) { array_map($this->exec(...), $statements); return; }
        // MySQL checks foreign-key targets at CREATE time and baseline tables reference `templates`, which comes later.
        // The new table is empty, so creating it with checks off cannot admit orphan rows.
        $this->exec('SET FOREIGN_KEY_CHECKS=0');
        try { array_map($this->exec(...), $statements); }
        finally { $this->exec('SET FOREIGN_KEY_CHECKS=1'); }
    }

    public function dropTable(string $table): void {
        $this->step++;
        $this->exec('DROP TABLE '.$this->q($table));
    }

    public function addColumn(string $table, string $column, array $definition): void {
        $this->step++;
        $keyed = ($definition['type'] ?? '') === 'id' || !empty($definition['primary']);
        $required = ($definition['nullable'] ?? true) === false && !isset($definition['default']);
        if ($this->sqlite() && ($keyed || $required)) {
            // SQLite's ADD COLUMN cannot add key columns or NOT NULL columns without a default.
            $current = $this->currentDefinition($table);
            $map = array_combine(array_keys($current['columns']), array_keys($current['columns']));
            $current['columns'][$column] = $definition;
            $this->rebuild($table, $current, $map);
            return;
        }
        $this->exec('ALTER TABLE '.$this->q($table).' ADD COLUMN '.$this->db->dialect()->columnSql($column, $definition));
    }

    public function renameColumn(string $table, string $from, string $to): void {
        $this->step++;
        $this->exec('ALTER TABLE '.$this->q($table).' RENAME COLUMN '.$this->q($from).' TO '.$this->q($to));
    }

    public function addIndex(string $table, string $name, array $columns): void {
        $this->step++;
        $this->exec('CREATE INDEX '.$this->q($name).' ON '.$this->q($table).' ('.$this->columnList($columns).')');
    }

    public function addUnique(string $table, string $name, array $columns): void {
        $this->step++;
        $this->exec('CREATE UNIQUE INDEX '.$this->q($name).' ON '.$this->q($table).' ('.$this->columnList($columns).')');
    }

    public function addForeignKey(string $table, array $foreign): void {
        $this->step++;
        $fk = ['column' => (string)($foreign['column'] ?? ''), 'table' => (string)($foreign['table'] ?? ''), 'references' => (string)($foreign['references'] ?? 'id')];
        if (isset($foreign['on_delete'])) { $fk['on_delete'] = (string)$foreign['on_delete']; }
        $current = $this->currentDefinition($table);
        if (!isset($current['columns'][$fk['column']])) { throw new InvalidArgumentException('Unknown foreign-key column'); }
        if ($this->sqlite()) {
            $current['foreign'][] = $fk;
            $this->rebuild($table, $current, array_combine(array_keys($current['columns']), array_keys($current['columns'])));
            return;
        }
        $this->exec($this->mysqlForeignKeySql($table, $fk));
    }

    /** Recreates $table with $newDefinition, copying each old column of $columnMap (old => new). */
    public function rebuildTable(string $table, array $newDefinition, array $columnMap): void {
        $this->step++;
        $this->rebuild($table, $newDefinition, $columnMap);
    }

    public function execute(string $sql, array $params = []): void {
        $this->step++;
        $this->db->execute_query($sql, $params);
    }

    /** Applies every pending migration in id order; returns the applied ids. */
    public function applyPending(): array {
        if (!$this->db->tableExists(PANEL_MIGRATION_LEDGER) && $this->db->dialect()->describe($this->db->pdo())['tables'] !== []) {
            throw new RuntimeException('Database has tables but no migration history');
        }
        $done = $this->appliedIds();
        $applied = [];
        foreach (panelMigrationFiles($this->directory) as $id => $path) {
            if (in_array($id, $done, true)) { continue; }
            $this->run($id, panelLoadMigration($id, $path)['up']);
            $applied[] = $id;
        }
        return $applied;
    }

    /** Records the baseline for a database created before migrations existed, after checking that its structure matches.
     * Only the legacy SQLite contact_limits shape is converted; any other difference throws PanelSchemaMismatch and changes nothing. */
    public function stampLegacy(): void {
        if ($this->db->tableExists(PANEL_MIGRATION_LEDGER)) { throw new RuntimeException('Migration history already exists'); }
        $path = panelMigrationFiles($this->directory)[PANEL_BASELINE_MIGRATION] ?? throw new RuntimeException('Baseline migration missing');
        $baseline = panelLoadMigration(PANEL_BASELINE_MIGRATION, $path)['schema'] ?? null;
        if (!is_array($baseline) || !isset($baseline['tables']['contact_limits'])) { throw new RuntimeException('Baseline migration has no schema'); }
        $binary = !$this->sqlite();
        $actual = panelDbNormalizeSchema($this->db->dialect()->describe($this->db->pdo()), $binary);
        $expected = panelDbNormalizeSchema($baseline, $binary);
        $legacy = $expected;
        $legacy['tables']['contact_limits'] = panelDbNormalizeSchema(['tables' => ['contact_limits' => self::LEGACY_CONTACT_LIMITS]], $binary)['tables']['contact_limits'];
        if ($actual !== $expected && $actual !== $legacy) {
            $reference = ($actual['tables']['contact_limits'] ?? null) === $legacy['tables']['contact_limits'] ? $legacy : $expected;
            throw new PanelSchemaMismatch(panelSchemaDifferences($reference, $actual));
        }
        $convert = $actual !== $expected;
        $this->run(PANEL_BASELINE_MIGRATION, function (PanelMigrator $m) use ($convert, $baseline): void {
            if ($convert) { $m->rebuildTable('contact_limits', $baseline['tables']['contact_limits'], self::LEGACY_CONTACT_LIMITS_MAP); }
        });
    }

    public function status(): array {
        $applied = $this->appliedIds();
        return ['applied' => $applied, 'pending' => array_values(array_diff(array_keys(panelMigrationFiles($this->directory)), $applied))];
    }

    /** One migration: on SQLite a single transaction with foreign keys off and checked before commit;
     * on MySQL (implicit DDL commits) the ledger row is written only after `up` returns. */
    private function run(string $id, Closure $up): void {
        $this->step = 0;
        try {
            $work = function () use ($id, $up): void {
                $this->ensureLedger();
                $up($this);
                $this->db->execute_query('INSERT INTO `schema_migrations` (`id`, `applied_at`) VALUES (?, ?)', [$id, date('Y-m-d H:i:s')]);
            };
            $this->sqlite() ? $this->sqliteAtomic($work) : $work();
        } catch (Throwable $e) {
            $where = $this->step > 0 ? ' at step '.$this->step : ' before its first step';
            $after = $this->sqlite() ? '; all of its changes were rolled back.' : '; restore the backup before retrying.';
            throw new PanelMigrationFailure('Migration '.$id.' failed'.$where.$after, 0, $e);
        }
    }

    private function rebuild(string $table, array $definition, array $columnMap): void {
        $temp = panelDbIdentifier($table.'__new');
        $current = array_column($this->db->columns($table), 'Field');
        if (!$current || !$columnMap || count(array_unique($columnMap)) !== count($columnMap)) { throw new InvalidArgumentException('Invalid rebuild'); }
        foreach ($columnMap as $from => $to) {
            if (!in_array((string)$from, $current, true) || !isset($definition['columns'][$to])) { throw new InvalidArgumentException('Invalid column map'); }
        }
        $copy = 'INSERT INTO '.$this->q($temp).' ('.$this->columnList(array_values($columnMap)).') SELECT '
            .$this->columnList(array_map('strval', array_keys($columnMap))).' FROM '.$this->q($table);
        $dialect = $this->db->dialect();
        $indexes = array_slice($dialect->createTableSql($table, ['foreign' => []] + $definition), 1);
        if ($this->sqlite()) {
            $this->sqliteAtomic(function () use ($dialect, $temp, $table, $definition, $copy, $indexes): void {
                $this->exec($dialect->createTableSql($temp, ['indexes' => []] + $definition)[0]);
                $this->exec($copy);
                $this->exec('DROP TABLE '.$this->q($table));
                $this->exec('ALTER TABLE '.$this->q($temp).' RENAME TO '.$this->q($table));
                array_map($this->exec(...), $indexes);
            });
            return;
        }
        // MySQL: build without foreign keys so constraint names never carry the temporary table name, then add them back.
        $this->exec($dialect->createTableSql($temp, ['indexes' => [], 'foreign' => []] + $definition)[0]);
        try { $this->exec($copy); $this->exec('DROP TABLE '.$this->q($table)); }
        catch (Throwable $e) { $this->exec('DROP TABLE IF EXISTS '.$this->q($temp)); throw $e; }
        $this->exec('RENAME TABLE '.$this->q($temp).' TO '.$this->q($table));
        array_map($this->exec(...), $indexes);
        foreach ($definition['foreign'] ?? [] as $fk) { $this->exec($this->mysqlForeignKeySql($table, $fk)); }
    }

    /** Runs $work in one transaction with foreign keys off; `foreign_key_check` must be clean before commit, and
     * foreign keys are switched back on in every path. Nested calls join the transaction already open. */
    private function sqliteAtomic(callable $work): void {
        if ($this->inTransaction) { $work(); return; }
        $pdo = $this->db->pdo();
        $pdo->exec('PRAGMA foreign_keys=OFF');
        try {
            // The pragma is ignored inside a transaction; dropping a parent table with foreign keys on would cascade.
            if ((int)$pdo->query('PRAGMA foreign_keys')->fetchColumn() !== 0) { throw new RuntimeException('Foreign keys could not be disabled'); }
            $pdo->beginTransaction();
            $this->inTransaction = true;
            $work();
            if ($pdo->query('PRAGMA foreign_key_check')->fetch() !== false) { throw new RuntimeException('Foreign key check failed'); }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $e;
        } finally {
            $this->inTransaction = false;
            $pdo->exec('PRAGMA foreign_keys=ON');
        }
    }

    /** describe() entry for $table, with integer defaults restored so a rebuild keeps numeric DEFAULT literals. */
    private function currentDefinition(string $table): array {
        $definition = $this->db->dialect()->describe($this->db->pdo())['tables'][$table] ?? throw new InvalidArgumentException('Unknown table');
        foreach ($definition['columns'] as $column => $spec) {
            if (in_array($spec['type'], ['int', 'tinyint'], true) && isset($spec['default']) && preg_match('/^-?\d+$/D', (string)$spec['default'])) {
                $definition['columns'][$column]['default'] = (int)$spec['default'];
            }
        }
        return $definition;
    }

    private function mysqlForeignKeySql(string $table, array $fk): string {
        $dialect = $this->db->dialect();
        if (!$dialect instanceof PanelMysqlDialect) { throw new LogicException('MySQL only'); }
        return 'ALTER TABLE '.$this->q($table).' ADD CONSTRAINT '.$this->q($dialect->foreignKeyName($table, $fk['column']))
            .' FOREIGN KEY ('.$this->q($fk['column']).') REFERENCES '.$this->q($fk['table']).' ('.$this->q($fk['references'] ?? 'id').')'.panelDbOnDelete($fk);
    }

    private function ensureLedger(): void {
        if ($this->db->tableExists(PANEL_MIGRATION_LEDGER)) { return; }
        array_map($this->exec(...), $this->db->dialect()->createTableSql(PANEL_MIGRATION_LEDGER, self::LEDGER_DEFINITION));
    }

    private function appliedIds(): array {
        if (!$this->db->tableExists(PANEL_MIGRATION_LEDGER)) { return []; }
        return array_column($this->db->query('SELECT `id` FROM `schema_migrations` ORDER BY `id`')->fetch_all(), 'id');
    }

    private function sqlite(): bool { return $this->db->dialect()->name() === 'sqlite'; }
    private function exec(string $sql): void { $this->db->pdo()->exec($sql); }
    private function q(string $name): string { return '`'.panelDbIdentifier($name).'`'; }
    private function columnList(array $columns): string {
        if (!$columns) { throw new InvalidArgumentException('Empty column list'); }
        return implode(', ', array_map($this->q(...), $columns));
    }
}
