<?php
defined('BASEPATH') or exit('No direct script access allowed');
/** Engine dialects: only the operations that genuinely differ between SQLite and MySQL.
 * Identifiers are validated against a strict pattern; values are always bound by callers.
 */
interface PanelDbDialect {
    public function name(): string;
    public function connect(array $config): PDO;
    public function columns(PDO $pdo, string $table): array;
    public function tableExists(PDO $pdo, string $table): bool;
    public function upsertSql(string $table, array $columns, array $keyColumns): string;
    public function createTableSql(string $table, array $definition): array;
    public function describe(PDO $pdo): array;
    public function integrityErrors(PDO $pdo, array $schema): array;
}

const PANEL_DB_LOGICAL_TYPES = ['id', 'int', 'tinyint', 'varchar', 'text', 'datetime', 'timestamp'];
const PANEL_DB_IGNORED_TABLES = ['schema_migrations', 'sqlite_sequence'];

function panelDbDialect(string $driver): PanelDbDialect {
    return match ($driver) {
        'sqlite' => new PanelSqliteDialect(),
        'mysql' => new PanelMysqlDialect(),
        default => throw new InvalidArgumentException('Unsupported database driver'),
    };
}

function panelDbIdentifier(string $name): string {
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/D', $name)) { throw new InvalidArgumentException('Invalid identifier'); }
    return $name;
}

/** Canonical form of a schema definition, so definitions and describe() output compare with ===.
 * Column key order is fixed; table, unique, index and foreign-key order is not significant. */
function panelDbNormalizeSchema(array $schema, bool $withBinary = true): array {
    $tables = [];
    foreach ($schema['tables'] ?? [] as $table => $definition) {
        $columns = [];
        foreach ($definition['columns'] ?? [] as $column => $spec) {
            $type = $spec['type'] ?? '';
            $out = ['type' => $type];
            if ($type === 'varchar') { $out['length'] = (int)($spec['length'] ?? 0); }
            $out['nullable'] = $type === 'id' ? false : (bool)($spec['nullable'] ?? true);
            if (array_key_exists('default', $spec) && $spec['default'] !== null) { $out['default'] = (string)$spec['default']; }
            if ($withBinary) { $out['binary'] = (bool)($spec['binary'] ?? false); }
            $out['primary'] = $type === 'id' || !empty($spec['primary']);
            $columns[$column] = $out;
        }
        $unique = array_map(static fn (array $cols): array => array_values($cols), $definition['unique'] ?? []);
        usort($unique, static fn (array $a, array $b): int => strcmp(implode(',', $a), implode(',', $b)));
        $indexes = array_map(static fn (array $cols): array => array_values($cols), $definition['indexes'] ?? []);
        ksort($indexes);
        $foreign = array_map(static fn (array $fk): array => [
            'column' => $fk['column'], 'table' => $fk['table'], 'references' => $fk['references'] ?? 'id',
            'on_delete' => strtoupper($fk['on_delete'] ?? 'NO ACTION'),
        ], $definition['foreign'] ?? []);
        usort($foreign, static fn (array $a, array $b): int => strcmp($a['column'], $b['column']));
        $tables[$table] = ['columns' => $columns, 'unique' => $unique, 'indexes' => $indexes, 'foreign' => $foreign];
    }
    ksort($tables);
    return ['tables' => $tables];
}

/** Parses a declared type such as `varchar(50)` into a logical column spec (without nullability). */
function panelDbParseType(string $declared): array {
    $declared = strtolower(trim($declared));
    if (preg_match('/^varchar\((\d+)\)$/D', $declared, $m)) { return ['type' => 'varchar', 'length' => (int)$m[1]]; }
    return ['type' => $declared];
}

function panelDbTypeString(array $spec): string {
    return $spec['type'] === 'varchar' ? 'varchar('.(int)$spec['length'].')' : $spec['type'];
}

function panelDbDefaultLiteral(mixed $value): string {
    if (is_bool($value)) { return $value ? '1' : '0'; }
    if (is_int($value) || is_float($value)) { return (string)$value; }
    if (is_string($value)) { return "'".str_replace("'", "''", $value)."'"; }
    throw new InvalidArgumentException('Invalid default');
}

function panelDbValidateColumn(array $spec): void {
    if (!in_array($spec['type'] ?? null, PANEL_DB_LOGICAL_TYPES, true)) { throw new InvalidArgumentException('Invalid column type'); }
    if ($spec['type'] === 'varchar' && (int)($spec['length'] ?? 0) < 1) { throw new InvalidArgumentException('Invalid varchar length'); }
}

final class PanelSqliteDialect implements PanelDbDialect {
    public function name(): string { return 'sqlite'; }

    public function connect(array $config): PDO {
        try {
            $file = (string)($config['file'] ?? '');
            if ($file === '' || is_dir($file)) { throw new RuntimeException('Invalid file'); }
            $pdo = new PDO('sqlite:'.$file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
            $pdo->query('SELECT COUNT(*) FROM sqlite_master')->fetchColumn();
            return $pdo;
        } catch (Throwable) {
            throw new RuntimeException('Database unavailable', 503);
        }
    }

    public function columns(PDO $pdo, string $table): array {
        $out = [];
        foreach ($this->tableInfo($pdo, $table) as $c) {
            $isId = $this->isIdColumn($c);
            $default = $c['dflt_value'];
            if (is_string($default) && strlen($default) > 1 && $default[0] === "'") { $default = str_replace("''", "'", substr($default, 1, -1)); }
            $out[] = ['Field' => $c['name'], 'Type' => strtolower($c['type']), 'Null' => ($c['notnull'] || $c['pk']) ? 'NO' : 'YES',
                'Key' => $c['pk'] ? 'PRI' : '', 'Default' => $default, 'Extra' => $isId ? 'auto_increment' : ''];
        }
        return $out;
    }

    public function tableExists(PDO $pdo, string $table): bool {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");
        $statement->execute([panelDbIdentifier($table)]);
        return $statement->fetchColumn() !== false;
    }

    public function upsertSql(string $table, array $columns, array $keyColumns): string {
        [$insert, $set] = panelDbUpsertParts($table, $columns, $keyColumns, static fn (string $c): string => '`'.$c.'`=excluded.`'.$c.'`');
        $keys = implode(',', array_map(static fn (string $c): string => '`'.$c.'`', $keyColumns));
        return $insert.' ON CONFLICT ('.$keys.') '.($set ? 'DO UPDATE SET '.implode(',', $set) : 'DO NOTHING');
    }

    public function createTableSql(string $table, array $definition): array {
        $q = static fn (string $name): string => '"'.panelDbIdentifier($name).'"';
        $foreign = array_column($definition['foreign'] ?? [], null, 'column');
        $parts = [];
        foreach ($definition['columns'] as $column => $spec) {
            panelDbValidateColumn($spec);
            if ($spec['type'] === 'id') { $parts[] = $q($column).' INTEGER PRIMARY KEY AUTOINCREMENT'; continue; }
            $sql = $q($column).' '.panelDbTypeString($spec);
            if (!empty($spec['primary'])) { $sql .= ' NOT NULL PRIMARY KEY'; }
            elseif (($spec['nullable'] ?? true) === false) { $sql .= ' NOT NULL'; }
            if (array_key_exists('default', $spec) && $spec['default'] !== null) { $sql .= ' DEFAULT '.panelDbDefaultLiteral($spec['default']); }
            if (isset($foreign[$column])) {
                $fk = $foreign[$column];
                $sql .= ' REFERENCES '.$q($fk['table']).'('.$q($fk['references'] ?? 'id').')'.panelDbOnDelete($fk);
            }
            $parts[] = $sql;
        }
        foreach ($definition['unique'] ?? [] as $columns) { $parts[] = 'UNIQUE('.implode(',', array_map($q, $columns)).')'; }
        $statements = ['CREATE TABLE '.$q($table).' ('.implode(', ', $parts).')'];
        foreach ($definition['indexes'] ?? [] as $index => $columns) {
            $statements[] = 'CREATE INDEX '.$q($index).' ON '.$q($table).'('.implode(',', array_map($q, $columns)).')';
        }
        return $statements;
    }

    public function describe(PDO $pdo): array {
        $tables = [];
        $names = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($names as $table) {
            if (in_array($table, PANEL_DB_IGNORED_TABLES, true) || str_starts_with($table, 'sqlite_')) { continue; }
            $columns = [];
            foreach ($pdo->query('PRAGMA table_info("'.str_replace('"', '""', $table).'")')->fetchAll(PDO::FETCH_ASSOC) as $c) {
                if ($this->isIdColumn($c)) { $columns[$c['name']] = ['type' => 'id']; continue; }
                $spec = panelDbParseType($c['type']);
                if ($c['notnull']) { $spec['nullable'] = false; }
                if ($c['dflt_value'] !== null) { $spec['default'] = $this->defaultValue($c['dflt_value']); }
                if ($c['pk']) { $spec['primary'] = true; }
                $columns[$c['name']] = $spec;
            }
            $unique = [];
            $indexes = [];
            $quoted = '"'.str_replace('"', '""', $table).'"';
            foreach ($pdo->query('PRAGMA index_list('.$quoted.')')->fetchAll(PDO::FETCH_ASSOC) as $index) {
                if (($index['origin'] ?? '') === 'pk') { continue; }
                $info = $pdo->query('PRAGMA index_info("'.str_replace('"', '""', $index['name']).'")')->fetchAll(PDO::FETCH_ASSOC);
                usort($info, static fn (array $a, array $b): int => $a['seqno'] <=> $b['seqno']);
                $cols = array_column($info, 'name');
                if ($index['unique']) { $unique[] = $cols; }
                elseif (!str_starts_with($index['name'], 'sqlite_autoindex_')) { $indexes[$index['name']] = $cols; }
            }
            $foreign = [];
            foreach ($pdo->query('PRAGMA foreign_key_list('.$quoted.')')->fetchAll(PDO::FETCH_ASSOC) as $fk) {
                $foreign[] = ['column' => $fk['from'], 'table' => $fk['table'], 'references' => $fk['to'] ?? 'id', 'on_delete' => $fk['on_delete']];
            }
            $tables[$table] = ['columns' => $columns, 'unique' => $unique, 'indexes' => $indexes, 'foreign' => $foreign];
        }
        return ['tables' => $tables];
    }

    public function integrityErrors(PDO $pdo, array $schema): array {
        $errors = [];
        foreach ($pdo->query('PRAGMA integrity_check')->fetchAll(PDO::FETCH_COLUMN) as $message) {
            if ($message !== 'ok') { $errors[] = 'Integrity: '.$message; }
        }
        foreach ($pdo->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $errors[] = 'Foreign key: '.$row['table'].' row '.$row['rowid'].' references a missing '.$row['parent'].' row';
        }
        return $errors;
    }

    private function tableInfo(PDO $pdo, string $table): array {
        return $pdo->query('PRAGMA table_info("'.panelDbIdentifier($table).'")')->fetchAll(PDO::FETCH_ASSOC);
    }

    private function isIdColumn(array $c): bool { return $c['pk'] && strtolower($c['type']) === 'integer'; }

    private function defaultValue(string $raw): string {
        if (strlen($raw) > 1 && $raw[0] === "'") { return str_replace("''", "'", substr($raw, 1, -1)); }
        return $raw;
    }
}

final class PanelMysqlDialect implements PanelDbDialect {
    private const SQL_MODE = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
    private const MIN_VERSION = '8.0.19';
    /** Logical types stored under a different MySQL type, tagged with a `cms:` column comment. */
    private const COMMENTED_TYPES = ['text' => 'MEDIUMTEXT', 'timestamp' => 'DATETIME'];

    public function name(): string { return 'mysql'; }

    public function connect(array $config): PDO {
        try {
            $charset = (string)($config['charset'] ?? 'utf8mb4');
            if ($charset !== 'utf8mb4') { throw new RuntimeException('Unsupported charset'); }
            $dsn = 'mysql:host='.$config['host'].';port='.(int)$config['port'].';charset=utf8mb4';
            if (($config['name'] ?? '') !== '') { $dsn .= ';dbname='.panelDbIdentifier((string)$config['name']); }
            $pdo = new PDO($dsn, (string)$config['user'], (string)$config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $version = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
            if (stripos($version, 'mariadb') !== false || !preg_match('/^(\d+\.\d+\.\d+)/', $version, $m) || version_compare($m[1], self::MIN_VERSION, '<')) {
                throw new RuntimeException('Unsupported server');
            }
            $offset = (new DateTimeImmutable())->format('P');
            if (!preg_match('/^[+-]\d{2}:\d{2}$/D', $offset)) { throw new RuntimeException('Invalid offset'); }
            $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci');
            $pdo->exec("SET SESSION sql_mode='".self::SQL_MODE."', SESSION time_zone='".$offset."'");
            return $pdo;
        } catch (Throwable) {
            throw new RuntimeException('Database unavailable', 503);
        }
    }

    public function columns(PDO $pdo, string $table): array {
        $out = [];
        foreach ($this->columnRows($pdo, $table) as $c) {
            $spec = $this->logicalSpec($c);
            $out[] = ['Field' => $c['COLUMN_NAME'], 'Type' => $spec['type'] === 'id' ? 'int' : panelDbTypeString($spec),
                'Null' => $c['IS_NULLABLE'] === 'YES' ? 'YES' : 'NO', 'Key' => $c['COLUMN_KEY'] === 'PRI' ? 'PRI' : '',
                'Default' => $c['COLUMN_DEFAULT'], 'Extra' => str_contains(strtolower($c['EXTRA']), 'auto_increment') ? 'auto_increment' : ''];
        }
        return $out;
    }

    public function tableExists(PDO $pdo, string $table): bool {
        $statement = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $statement->execute([panelDbIdentifier($table)]);
        return $statement->fetchColumn() !== false;
    }

    public function upsertSql(string $table, array $columns, array $keyColumns): string {
        [$insert, $set] = panelDbUpsertParts($table, $columns, $keyColumns, static fn (string $c): string => '`'.$c.'`=new.`'.$c.'`');
        if (!$set) { $set = ['`'.$keyColumns[0].'`=new.`'.$keyColumns[0].'`']; }
        return $insert.' AS new ON DUPLICATE KEY UPDATE '.implode(',', $set);
    }

    public function createTableSql(string $table, array $definition): array {
        $q = static fn (string $name): string => '`'.panelDbIdentifier($name).'`';
        $parts = [];
        foreach ($definition['columns'] as $column => $spec) {
            panelDbValidateColumn($spec);
            if ($spec['type'] === 'id') { $parts[] = $q($column).' INT NOT NULL AUTO_INCREMENT PRIMARY KEY'; continue; }
            $sql = $q($column).' '.(self::COMMENTED_TYPES[$spec['type']] ?? panelDbTypeString($spec));
            if (!empty($spec['binary'])) { $sql .= ' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin'; }
            $sql .= (!empty($spec['primary']) || ($spec['nullable'] ?? true) === false) ? ' NOT NULL' : ' NULL';
            if (array_key_exists('default', $spec) && $spec['default'] !== null) { $sql .= ' DEFAULT '.panelDbDefaultLiteral($spec['default']); }
            if (!empty($spec['primary'])) { $sql .= ' PRIMARY KEY'; }
            if (isset(self::COMMENTED_TYPES[$spec['type']])) { $sql .= " COMMENT 'cms:".$spec['type']."'"; }
            $parts[] = $sql;
        }
        foreach ($definition['unique'] ?? [] as $columns) { $parts[] = 'UNIQUE KEY ('.implode(',', array_map($q, $columns)).')'; }
        foreach ($definition['foreign'] ?? [] as $fk) {
            $parts[] = 'CONSTRAINT '.$q($this->foreignKeyName($table, $fk['column'])).' FOREIGN KEY ('.$q($fk['column']).') REFERENCES '
                .$q($fk['table']).' ('.$q($fk['references'] ?? 'id').')'.panelDbOnDelete($fk);
        }
        $statements = ['CREATE TABLE '.$q($table).' ('.implode(', ', $parts).') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci'];
        foreach ($definition['indexes'] ?? [] as $index => $columns) {
            $statements[] = 'CREATE INDEX '.$q($index).' ON '.$q($table).' ('.implode(',', array_map($q, $columns)).')';
        }
        return $statements;
    }

    public function describe(PDO $pdo): array {
        $tables = [];
        $names = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($names as $table) {
            if (in_array($table, PANEL_DB_IGNORED_TABLES, true)) { continue; }
            $columns = [];
            foreach ($this->columnRows($pdo, $table) as $c) {
                $spec = $this->logicalSpec($c);
                if ($spec['type'] !== 'id') {
                    if ($c['IS_NULLABLE'] !== 'YES') { $spec['nullable'] = false; }
                    if ($c['COLUMN_DEFAULT'] !== null) { $spec['default'] = $c['COLUMN_DEFAULT']; }
                    if ($c['COLLATION_NAME'] === 'utf8mb4_bin') { $spec['binary'] = true; }
                    if ($c['COLUMN_KEY'] === 'PRI') { $spec['primary'] = true; }
                }
                $columns[$c['COLUMN_NAME']] = $spec;
            }
            $foreign = [];
            $statement = $pdo->prepare('SELECT k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE
                FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
                WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.COLUMN_NAME');
            $statement->execute([$table]);
            $foreignNames = [];
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $fk) {
                $foreignNames[] = $fk['CONSTRAINT_NAME'];
                $foreign[] = ['column' => $fk['COLUMN_NAME'], 'table' => $fk['REFERENCED_TABLE_NAME'], 'references' => $fk['REFERENCED_COLUMN_NAME'], 'on_delete' => $fk['DELETE_RULE']];
            }
            $statement = $pdo->prepare("SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME<>'PRIMARY' ORDER BY INDEX_NAME, SEQ_IN_INDEX");
            $statement->execute([$table]);
            $grouped = [];
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $grouped[$row['INDEX_NAME']]['unique'] = (int)$row['NON_UNIQUE'] === 0;
                $grouped[$row['INDEX_NAME']]['columns'][] = $row['COLUMN_NAME'];
            }
            $unique = [];
            $indexes = [];
            foreach ($grouped as $index => $info) {
                if ($info['unique']) { $unique[] = $info['columns']; }
                elseif (!in_array($index, $foreignNames, true)) { $indexes[$index] = $info['columns']; } // FK support index MySQL creates itself
            }
            $tables[$table] = ['columns' => $columns, 'unique' => $unique, 'indexes' => $indexes, 'foreign' => $foreign];
        }
        return ['tables' => $tables];
    }

    public function integrityErrors(PDO $pdo, array $schema): array {
        $errors = [];
        foreach ($schema['tables'] as $table => $definition) {
            foreach ($definition['foreign'] ?? [] as $fk) {
                $child = panelDbIdentifier($table);
                $column = panelDbIdentifier($fk['column']);
                $parent = panelDbIdentifier($fk['table']);
                $reference = panelDbIdentifier($fk['references'] ?? 'id');
                $count = (int)$pdo->query('SELECT COUNT(*) FROM `'.$child.'` c LEFT JOIN `'.$parent.'` p ON p.`'.$reference.'`=c.`'.$column.'`'
                    .' WHERE c.`'.$column.'` IS NOT NULL AND p.`'.$reference.'` IS NULL')->fetchColumn();
                if ($count > 0) { $errors[] = 'Foreign key: '.$child.'.'.$column.' has '.$count.' row(s) referencing a missing '.$parent.'.'.$reference; }
            }
        }
        return $errors;
    }

    private function columnRows(PDO $pdo, string $table): array {
        $statement = $pdo->prepare('SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT, EXTRA, COLLATION_NAME, COLUMN_COMMENT
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
        $statement->execute([panelDbIdentifier($table)]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Logical type from the `cms:` comment or the column type; an auto-increment primary key is `id`. */
    private function logicalSpec(array $c): array {
        if ($c['COLUMN_KEY'] === 'PRI' && str_contains(strtolower($c['EXTRA']), 'auto_increment')) { return ['type' => 'id']; }
        if (str_starts_with((string)$c['COLUMN_COMMENT'], 'cms:')) { return ['type' => substr($c['COLUMN_COMMENT'], 4)]; }
        return panelDbParseType($c['COLUMN_TYPE']);
    }

    private function foreignKeyName(string $table, string $column): string { return panelDbIdentifier('fk_'.$table.'_'.$column); }
}

/** Shared INSERT prefix and update assignments for both upsert dialects. */
function panelDbUpsertParts(string $table, array $columns, array $keyColumns, callable $assign): array {
    $columns = array_map(static fn ($c): string => panelDbIdentifier((string)$c), array_values($columns));
    $keyColumns = array_map(static fn ($c): string => panelDbIdentifier((string)$c), array_values($keyColumns));
    if (!$columns || !$keyColumns || array_diff($keyColumns, $columns)) { throw new InvalidArgumentException('Invalid upsert'); }
    $insert = 'INSERT INTO `'.panelDbIdentifier($table).'` ('.implode(',', array_map(static fn (string $c): string => '`'.$c.'`', $columns)).') VALUES ('
        .implode(',', array_fill(0, count($columns), '?')).')';
    $set = [];
    foreach ($columns as $column) { if (!in_array($column, $keyColumns, true)) { $set[] = $assign($column); } }
    return [$insert, $set];
}

function panelDbOnDelete(array $fk): string {
    if (!isset($fk['on_delete'])) { return ''; }
    $action = strtoupper((string)$fk['on_delete']);
    if (!in_array($action, ['SET NULL', 'CASCADE', 'RESTRICT', 'NO ACTION'], true)) { throw new InvalidArgumentException('Invalid ON DELETE action'); }
    return ' ON DELETE '.$action;
}
