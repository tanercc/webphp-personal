<?php
/** Database contract checks for the SQLite and MySQL dialects, run on disposable databases only.
 * Usage: php tests/db-contract.php --driver=sqlite|mysql|both
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASEPATH', '/');
$root = dirname(__DIR__);
require $root.'/panel/include/database.php';
require $root.'/panel/include/db_migrations.php';

$options = getopt('', ['driver:']);
$driver = $options['driver'] ?? 'sqlite';
if (!in_array($driver, ['sqlite', 'mysql', 'both'], true)) { fwrite(STDERR, "Usage: php tests/db-contract.php --driver=sqlite|mysql|both\n"); exit(1); }
$schema = require $root.'/database/schema.php';

function contractMysqlCredentials(): ?array {
    $values = [];
    foreach (['HOST', 'PORT', 'USER', 'PASSWORD'] as $key) {
        $value = getenv('PANEL_TEST_MYSQL_'.$key);
        if ($value === false || ($value === '' && $key !== 'PASSWORD')) { return null; }
        $values[strtolower($key)] = $value;
    }
    return ['driver' => 'mysql', 'host' => $values['host'], 'port' => (int)$values['port'], 'name' => '', 'user' => $values['user'], 'password' => $values['password'], 'charset' => 'utf8mb4'];
}
function contractAssert(bool $condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } }
function contractCreateSchema(PanelDbDialect $dialect, PDO $pdo, array $schema): void {
    // MySQL rejects a foreign key to a table that does not exist yet unless checks are off during creation.
    if ($dialect->name() === 'mysql') { $pdo->exec('SET FOREIGN_KEY_CHECKS=0'); }
    try {
        foreach ($schema['tables'] as $table => $definition) {
            foreach ($dialect->createTableSql($table, $definition) as $sql) { $pdo->exec($sql); }
        }
    } finally {
        if ($dialect->name() === 'mysql') { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); }
    }
}
/** Runs $work with the config of a new, empty disposable database that is removed afterwards. */
function contractScratch(string $driver, ?array $credentials, callable $work): mixed {
    if ($driver === 'sqlite') {
        $file = tempnam(sys_get_temp_dir(), 'cms-contract-');
        try { return $work(['driver' => 'sqlite', 'file' => $file]); }
        finally { gc_collect_cycles(); @unlink($file); }
    }
    $server = panelDbDialect('mysql')->connect($credentials);
    $name = 'cms_test_'.bin2hex(random_bytes(6));
    $server->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
    try { return $work(['name' => $name] + $credentials); }
    finally { gc_collect_cycles(); $server->exec('DROP DATABASE IF EXISTS `'.$name.'`'); }
}
/** Runs tools/migrate.php with only the given PANEL_* variables; returns [exit code, stdout, stderr]. */
function contractMigrate(array $arguments, array $env): array {
    $base = array_filter(getenv(), static fn (string $key): bool => !str_starts_with($key, 'PANEL_') && !str_starts_with($key, 'PERSONAL_'), ARRAY_FILTER_USE_KEY);
    $out = tempnam(sys_get_temp_dir(), 'cms-out-');
    $err = tempnam(sys_get_temp_dir(), 'cms-err-');
    try {
        $process = proc_open([PHP_BINARY, dirname(__DIR__).'/tools/migrate.php', ...$arguments], [0 => ['pipe', 'r'], 1 => ['file', $out, 'w'], 2 => ['file', $err, 'w']],
            $pipes, dirname(__DIR__), $env + $base);
        fclose($pipes[0]);
        $code = proc_close($process);
        return [$code, (string)file_get_contents($out), (string)file_get_contents($err)];
    } finally { @unlink($out); @unlink($err); }
}
/** Environment that points tools/migrate.php at a MySQL scratch database. */
function contractMysqlEnv(array $config): array {
    return ['PANEL_DB_DRIVER' => 'mysql', 'PANEL_DB_HOST' => $config['host'], 'PANEL_DB_PORT' => (string)$config['port'], 'PANEL_DB_NAME' => $config['name'],
        'PANEL_DB_USER' => $config['user'], 'PANEL_DB_PASSWORD' => $config['password']];
}
/** Builds a SQLite database from the legacy schema fixture plus $statements, runs $work($file), then removes it and its backups. */
function contractLegacyDatabase(array $statements, callable $work): void {
    $root = dirname(__DIR__);
    $file = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cms-legacy-'.bin2hex(random_bytes(6)).'.sqlite';
    try {
        $pdo = new PDO('sqlite:'.$file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec((string)file_get_contents($root.'/tests/fixtures/legacy-schema.sql'));
        foreach ($statements as $sql) { $pdo->exec($sql); }
        $pdo = null;
        $work($file);
    } finally {
        gc_collect_cycles();
        @unlink($file);
        foreach (glob($root.'/storage/backups/'.basename($file, '.sqlite').'-*.sqlite') ?: [] as $backup) { @unlink($backup); }
    }
}
function contractSqliteRows(string $file, string $sql): array {
    $pdo = new PDO('sqlite:'.$file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}
/** Normalized describe() entry of one table, or null when it is missing. */
function contractTable(PanelDbConnection $db, string $table): ?array {
    $tables = $db->dialect()->describe($db->pdo())['tables'];
    if (!isset($tables[$table])) { return null; }
    return panelDbNormalizeSchema(['tables' => [$table => $tables[$table]]], $db->dialect()->name() === 'mysql')['tables'][$table];
}
function contractRenameKey(array $columns, string $from, string $to): array {
    $out = [];
    foreach ($columns as $name => $spec) { $out[$name === $from ? $to : $name] = $spec; }
    return $out;
}
function contractWithoutForeignKeys(PanelDbDialect $dialect, PDO $pdo, callable $work): void {
    $pdo->exec($dialect->name() === 'mysql' ? 'SET FOREIGN_KEY_CHECKS=0' : 'PRAGMA foreign_keys=OFF');
    try { $work(); } finally { $pdo->exec($dialect->name() === 'mysql' ? 'SET FOREIGN_KEY_CHECKS=1' : 'PRAGMA foreign_keys=ON'); }
}

/** Runs every check for one driver; returns the columns() output per table for the parity check. */
function contractRun(PanelDbDialect $dialect, array $config, array $schema, ?array $credentials): array {
    $checks = [];
    $checks['schema_roundtrip'] = function (PDO $pdo) use ($dialect, $schema): void {
        $binary = $dialect->name() === 'mysql';
        $expected = panelDbNormalizeSchema($schema, $binary);
        $actual = panelDbNormalizeSchema($dialect->describe($pdo), $binary);
        foreach (array_unique(array_merge(array_keys($expected['tables']), array_keys($actual['tables']))) as $table) {
            contractAssert(($expected['tables'][$table] ?? null) === ($actual['tables'][$table] ?? null),
                'describe() differs for table '.$table.': '.json_encode($actual['tables'][$table] ?? null));
        }
        contractAssert($expected === $actual, 'describe() differs from the definition');
    };
    $checks['columns_shape'] = function (PDO $pdo) use ($dialect, $schema): void {
        $columns = $dialect->columns($pdo, 'pages');
        contractAssert(array_column($columns, 'Field') === array_keys($schema['tables']['pages']['columns']), 'pages field order');
        $byName = array_column($columns, null, 'Field');
        contractAssert(array_keys($columns[0]) === ['Field', 'Type', 'Null', 'Key', 'Default', 'Extra'], 'columns() keys');
        contractAssert($byName['name']['Type'] === 'varchar(50)', 'pages.name type');
        contractAssert($byName['created_at']['Type'] === 'timestamp', 'pages.created_at type');
        contractAssert($byName['is_active']['Default'] === '1', 'pages.is_active default');
        contractAssert($byName['id']['Key'] === 'PRI' && $byName['id']['Extra'] === 'auto_increment', 'pages.id key');
        contractAssert($byName['id']['Type'] === ($dialect->name() === 'sqlite' ? 'integer' : 'int'), 'pages.id type');
        $content = array_column($dialect->columns($pdo, 'page_lang'), null, 'Field')['content'];
        contractAssert($content['Type'] === 'text', 'page_lang.content type');
        contractAssert($dialect->tableExists($pdo, 'pages') && !$dialect->tableExists($pdo, 'missing_table'), 'tableExists');
    };
    $checks['upsert'] = function (PDO $pdo) use ($dialect): void {
        $sql = $dialect->upsertSql('contact_limits', ['limit_key', 'window_start', 'hits'], ['limit_key']);
        $key = str_repeat('a', 64);
        $pdo->prepare($sql)->execute([$key, 100, 1]);
        $pdo->prepare($sql)->execute([$key, 100, 2]);
        $rows = $pdo->query('SELECT `limit_key`, `hits` FROM `contact_limits`')->fetchAll(PDO::FETCH_ASSOC);
        contractAssert(count($rows) === 1 && (int)$rows[0]['hits'] === 2, 'upsert did not update the existing row');
        // Key-only upsert with a re-indexed key list must still produce valid SQL and leave the row alone.
        $pdo->exec("INSERT INTO `pages` (`name`) VALUES ('upsert')");
        $id = (int)$pdo->lastInsertId();
        $pdo->prepare($dialect->upsertSql('pages', ['id'], [5 => 'id']))->execute([$id]);
        $rows = $pdo->query('SELECT `name` FROM `pages`')->fetchAll(PDO::FETCH_ASSOC);
        contractAssert(count($rows) === 1 && $rows[0]['name'] === 'upsert', 'key-only upsert changed the row');
        $pdo->exec('DELETE FROM `pages`');
    };
    $checks['connection_contract'] = function () use ($dialect, $config): void {
        $db = new PanelDbConnection($dialect, $config);
        contractAssert($db->dialect() === $dialect && $db->pdo() instanceof PDO, 'connection accessors');
        $insert = 'INSERT INTO `pages` (`name`, `slug`) VALUES (?, ?)';
        contractAssert($db->execute_query($insert, ['Contract', 'connection-contract']) === true, 'insert result');
        $id = $db->insert_id;
        contractAssert($id > 0, 'insert_id after insert');
        $db->execute_query('UPDATE `pages` SET `name`=? WHERE `id`=?', ['Changed', $id]);
        contractAssert($db->affected_rows === 1, 'affected_rows after update');
        $statement = $db->prepare('SELECT `id`, `name` FROM `pages` WHERE `id`=?');
        $statement->bind_param('i', $id);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        contractAssert(is_array($row) && (int)$row['id'] === $id && $row['name'] === 'Changed', 'prepared select');
        $db->begin_transaction();
        $db->execute_query($insert, ['Rolled back', 'connection-rollback']);
        $db->rollback();
        $count = $db->execute_query('SELECT COUNT(*) FROM `pages` WHERE `slug`=?', ['connection-rollback'])->fetch_row()[0];
        contractAssert((int)$count === 0, 'rollback kept the insert');
        $db->upsert('contact_limits', ['limit_key' => 'connection', 'window_start' => 1, 'hits' => 1], ['limit_key']);
        $db->upsert('contact_limits', ['limit_key' => 'connection', 'window_start' => 1, 'hits' => 3], ['limit_key']);
        $hits = $db->query("SELECT `hits` FROM `contact_limits` WHERE `limit_key`='connection'")->fetch_all();
        contractAssert(count($hits) === 1 && (int)$hits[0]['hits'] === 3, 'connection upsert');
        contractAssert($db->tableExists('pages') && $db->columns('pages') === $dialect->columns($db->pdo(), 'pages'), 'connection metadata');
    };
    if ($dialect->name() === 'sqlite') {
        $checks['missing_sqlite_file'] = function (): void {
            $missing = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cms-missing-'.bin2hex(random_bytes(6)).'.sqlite';
            $expect503 = static function (callable $connect, string $label): void {
                try { $connect(); } catch (RuntimeException $e) {
                    contractAssert($e->getCode() === 503 && $e->getMessage() === 'Database unavailable', $label.' failure leaks details');
                    return;
                }
                throw new RuntimeException($label.' accepted a missing database file');
            };
            $saved = ['PANEL_DB_DRIVER' => getenv('PANEL_DB_DRIVER'), 'PANEL_DB_FILE' => getenv('PANEL_DB_FILE')];
            putenv('PANEL_DB_DRIVER=sqlite');
            putenv('PANEL_DB_FILE='.$missing);
            try {
                $expect503(static fn () => panelDbDialect('sqlite')->connect(['file' => $missing]), 'dialect connect');
                $expect503(static fn () => panelDatabaseConnection(), 'panelDatabaseConnection()');
            } finally {
                foreach ($saved as $name => $value) { putenv($value === false ? $name : $name.'='.$value); }
            }
            contractAssert(!file_exists($missing), 'runtime connection created a database file');
        };
    }
    if ($dialect->name() === 'mysql') {
        $checks['binary_collation'] = function (PDO $pdo): void {
            $insert = $pdo->prepare('INSERT INTO `users` (`email`) VALUES (?)');
            $insert->execute(['a@x.test']);
            $insert->execute(['A@x.test']);
            $pdo->prepare('INSERT INTO `pages` (`slug`) VALUES (?)')->execute(['about']);
            $select = $pdo->prepare('SELECT `id` FROM `pages` WHERE `slug`=?');
            $select->execute(['About']);
            contractAssert($select->fetch() === false, 'slug comparison is not binary');
        };
    }
    $checks['migrator_ops'] = function () use ($dialect, $config): void {
        $db = new PanelDbConnection($dialect, $config);
        $m = new PanelMigrator($db);
        $binary = $dialect->name() === 'mysql';
        $expect = static function (array $definition, string $step) use ($db, $binary): void {
            $expected = panelDbNormalizeSchema(['tables' => ['mig_probe' => $definition]], $binary)['tables']['mig_probe'];
            $actual = contractTable($db, 'mig_probe');
            contractAssert($actual === $expected, $step.': mig_probe differs: '.json_encode($actual));
        };
        $foreignKeysOn = static function (string $step) use ($db, $dialect): void {
            if ($dialect->name() === 'sqlite') { contractAssert((int)$db->pdo()->query('PRAGMA foreign_keys')->fetchColumn() === 1, $step.': foreign_keys not restored'); }
        };
        $definition = ['columns' => [
            'id' => ['type' => 'id'], 'name' => ['type' => 'varchar', 'length' => 50], 'code' => ['type' => 'varchar', 'length' => 32, 'binary' => true],
            'template_id' => ['type' => 'int'],
        ], 'unique' => [], 'indexes' => [], 'foreign' => []];
        $m->createTable('mig_probe', $definition);
        $expect($definition, 'createTable');
        $m->addColumn('mig_probe', 'note', ['type' => 'text']);
        $definition['columns']['note'] = ['type' => 'text'];
        $expect($definition, 'addColumn');
        $m->renameColumn('mig_probe', 'name', 'title');
        $definition['columns'] = contractRenameKey($definition['columns'], 'name', 'title');
        $expect($definition, 'renameColumn');
        $m->addIndex('mig_probe', 'mig_probe_title', ['title']);
        $definition['indexes'] = ['mig_probe_title' => ['title']];
        $expect($definition, 'addIndex');
        $m->addUnique('mig_probe', 'mig_probe_code', ['code']);
        $definition['unique'] = [['code']];
        $expect($definition, 'addUnique');
        // NOT NULL without a default: SQLite cannot ADD COLUMN this in place and rebuilds the table.
        $m->addColumn('mig_probe', 'weight', ['type' => 'int', 'nullable' => false]);
        $definition['columns']['weight'] = ['type' => 'int', 'nullable' => false];
        $expect($definition, 'addColumn rebuild');
        $foreignKeysOn('addColumn rebuild');
        $db->execute_query("INSERT INTO `templates` (`name`, `code`) VALUES ('Probe', 'mig-probe')");
        $templateId = $db->insert_id;
        $db->execute_query("INSERT INTO `mig_probe` (`title`, `code`, `template_id`, `note`, `weight`) VALUES ('Probe', 'p1', ?, 'n', 7)", [$templateId]);
        $m->addForeignKey('mig_probe', ['column' => 'template_id', 'table' => 'templates', 'on_delete' => 'SET NULL']);
        $definition['foreign'] = [['column' => 'template_id', 'table' => 'templates', 'references' => 'id', 'on_delete' => 'SET NULL']];
        $expect($definition, 'addForeignKey');
        $foreignKeysOn('addForeignKey');
        unset($definition['columns']['note']);
        $m->rebuildTable('mig_probe', $definition, ['id' => 'id', 'title' => 'title', 'code' => 'code', 'template_id' => 'template_id', 'weight' => 'weight']);
        $expect($definition, 'rebuildTable');
        $foreignKeysOn('rebuildTable');
        $rows = $db->query('SELECT `title`, `code`, `template_id` FROM `mig_probe`')->fetch_all();
        contractAssert(count($rows) === 1 && $rows[0]['title'] === 'Probe' && $rows[0]['code'] === 'p1' && (int)$rows[0]['template_id'] === $templateId, 'rebuildTable lost data');
        try { $m->rebuildTable('mig_probe', $definition, ['missing' => 'title']); $rejected = false; } catch (Throwable) { $rejected = true; }
        contractAssert($rejected, 'rebuildTable accepted an unknown source column');
        $expect($definition, 'rejected rebuildTable');
        $foreignKeysOn('rejected rebuildTable');
        $db->execute_query('DELETE FROM `templates` WHERE `id`=?', [$templateId]);
        $rows = $db->query('SELECT `template_id` FROM `mig_probe`')->fetch_all();
        contractAssert($rows[0]['template_id'] === null, 'ON DELETE SET NULL not enforced after rebuild');
        $m->dropTable('mig_probe');
        contractAssert(!$db->tableExists('mig_probe'), 'dropTable');
    };
    $checks['migration_equivalence'] = function () use ($dialect, $schema, $credentials): void {
        contractScratch($dialect->name(), $credentials, function (array $config) use ($dialect, $schema): void {
            $db = new PanelDbConnection($dialect, $config);
            contractAssert(!panelSchemaIsCurrent($db), 'empty database reported current');
            $m = new PanelMigrator($db);
            contractAssert($m->status() === ['applied' => [], 'pending' => ['0001_baseline']], 'status before');
            contractAssert($m->applyPending() === ['0001_baseline'], 'applyPending ids');
            $binary = $dialect->name() === 'mysql';
            contractAssert(panelDbNormalizeSchema($dialect->describe($db->pdo()), $binary) === panelDbNormalizeSchema($schema, $binary), 'baseline differs from database/schema.php');
            contractAssert($m->status() === ['applied' => ['0001_baseline'], 'pending' => []], 'status after');
            contractAssert(panelSchemaIsCurrent($db), 'migrated database not current');
            contractAssert($m->applyPending() === [], 'second applyPending applied again');
            if (!$binary) { contractAssert((int)$db->pdo()->query('PRAGMA foreign_keys')->fetchColumn() === 1, 'foreign_keys not restored'); }
        });
    };
    $checks['migration_failure'] = function () use ($dialect, $credentials): void {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cms-migrations-'.bin2hex(random_bytes(6));
        mkdir($directory);
        try {
            copy(dirname(__DIR__).'/database/migrations/0001_baseline.php', $directory.'/0001_baseline.php');
            file_put_contents($directory.'/0002_broken.php', "<?php\nreturn ['id' => '0002_broken', 'up' => static function (PanelMigrator \$m): void {\n"
                ."    \$m->addColumn('pages', 'broken_probe', ['type' => 'int']);\n    \$m->execute('SELECT * FROM `missing_table`');\n}];\n");
            contractScratch($dialect->name(), $credentials, function (array $config) use ($dialect, $directory): void {
                $db = new PanelDbConnection($dialect, $config);
                $m = new PanelMigrator($db, $directory);
                try { $m->applyPending(); $message = null; } catch (PanelMigrationFailure $e) { $message = $e->getMessage(); }
                contractAssert($message !== null && str_contains($message, '0002_broken') && str_contains($message, 'step 2'), 'failure does not name migration and step: '.$message);
                contractAssert(!str_contains($message, 'missing_table'), 'failure message leaks SQL');
                contractAssert($m->status() === ['applied' => ['0001_baseline'], 'pending' => ['0002_broken']], 'failed migration was recorded');
                $hasColumn = in_array('broken_probe', array_column($db->columns('pages'), 'Field'), true);
                // SQLite rolls the whole migration back; MySQL DDL commits implicitly, so recovery is the backup.
                contractAssert($hasColumn === ($dialect->name() === 'mysql'), 'unexpected state after the failed migration');
                if ($dialect->name() === 'sqlite') { contractAssert((int)$db->pdo()->query('PRAGMA foreign_keys')->fetchColumn() === 1, 'foreign_keys not restored'); }
            });
        } finally {
            array_map('unlink', glob($directory.'/*.php') ?: []);
            @rmdir($directory);
        }
    };
    $checks['print_schema'] = function (): void {
        $missing = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cms-missing-'.bin2hex(random_bytes(6)).'.sqlite';
        $env = ['PANEL_DB_DRIVER' => 'sqlite', 'PANEL_DB_FILE' => $missing];
        [$code, $mysql] = contractMigrate(['--print-schema=mysql'], $env);
        contractAssert($code === 0 && str_contains($mysql, 'MEDIUMTEXT') && str_contains($mysql, 'utf8mb4_bin') && str_contains($mysql, 'AUTO_INCREMENT'), 'mysql DDL');
        [$code, $sqlite] = contractMigrate(['--print-schema=sqlite'], $env);
        contractAssert($code === 0 && str_contains($sqlite, 'AUTOINCREMENT') && str_contains($sqlite, 'CREATE TABLE "contact_limits"'), 'sqlite DDL');
        contractAssert(!file_exists($missing), 'print-schema opened a database');
    };
    if ($dialect->name() === 'sqlite') {
        $checks['stamp_legacy'] = function () use ($schema): void {
            $missing = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cms-missing-'.bin2hex(random_bytes(6)).'.sqlite';
            [$code, , $stderr] = contractMigrate([], ['PANEL_DB_DRIVER' => 'sqlite', 'PANEL_DB_FILE' => $missing]);
            contractAssert($code === 1 && !file_exists($missing) && trim($stderr) === 'Database unavailable.', 'migrate created or misreported a missing database');
            contractLegacyDatabase([
                "INSERT INTO contact_limits (key, window, hits) VALUES ('legacy-key', 1234, 3)",
                "INSERT INTO pages (name, slug) VALUES ('Legacy page', 'legacy')",
            ], function (string $file) use ($schema): void {
                $env = ['PANEL_DB_DRIVER' => 'sqlite', 'PANEL_DB_FILE' => $file];
                [$code, $stdout, $stderr] = contractMigrate([], $env);
                contractAssert($code === 0, 'stamp failed: '.$stderr);
                contractAssert(array_column(contractSqliteRows($file, 'SELECT id FROM schema_migrations'), 'id') === ['0001_baseline'], 'baseline not recorded');
                $limits = contractSqliteRows($file, 'SELECT limit_key, window_start, hits FROM contact_limits');
                contractAssert($limits === [['limit_key' => 'legacy-key', 'window_start' => 1234, 'hits' => 3]], 'contact_limits row not copied');
                contractAssert(contractSqliteRows($file, 'SELECT name, slug FROM pages') === [['name' => 'Legacy page', 'slug' => 'legacy']], 'pages row changed');
                $backups = glob(dirname(__DIR__).'/storage/backups/'.basename($file, '.sqlite').'-*.sqlite') ?: [];
                contractAssert(count($backups) === 1 && str_contains($stdout, basename($backups[0])), 'backup missing');
                contractAssert(array_column(contractSqliteRows($backups[0], 'PRAGMA table_info(contact_limits)'), 'name') === ['key', 'window', 'hits'], 'backup is not the original');
                $db = new PanelDbConnection(panelDbDialect('sqlite'), ['file' => $file]);
                contractAssert(panelDbNormalizeSchema($db->dialect()->describe($db->pdo()), false) === panelDbNormalizeSchema($schema, false), 'stamped schema differs');
                $db = null;
                [$code, $stdout] = contractMigrate([], $env);
                contractAssert($code === 0 && str_contains($stdout, 'up to date'), 'second run not idempotent');
                [$code, $stdout] = contractMigrate(['--status'], $env);
                contractAssert($code === 0 && str_contains($stdout, '0001_baseline'), 'status output');
            });
        };
        $checks['stamp_mismatch'] = function (): void {
            contractLegacyDatabase(['ALTER TABLE pages ADD COLUMN extra int'], function (string $file): void {
                $before = hash_file('sha256', $file);
                [$code, , $stderr] = contractMigrate([], ['PANEL_DB_DRIVER' => 'sqlite', 'PANEL_DB_FILE' => $file]);
                contractAssert($code === 1 && str_contains($stderr, 'pages') && str_contains($stderr, 'extra'), 'mismatch not reported: '.$stderr);
                contractAssert(hash_file('sha256', $file) === $before, 'mismatched database was modified');
            });
        };
    }
    if ($dialect->name() === 'mysql') {
        $checks['mysql_backup_flag'] = function () use ($dialect, $schema, $credentials): void {
            contractScratch('mysql', $credentials, function (array $config) use ($dialect, $schema): void {
                [$code, $stdout, $stderr] = contractMigrate([], contractMysqlEnv($config));
                contractAssert($code === 1 && str_contains($stderr, '--backup-done'), 'migrate ran on MySQL without --backup-done');
                $db = new PanelDbConnection($dialect, $config);
                contractAssert($dialect->describe($db->pdo())['tables'] === [] && !$db->tableExists('schema_migrations'), 'tables created without --backup-done');
                [$code, $stdout, $stderr] = contractMigrate(['--backup-done'], contractMysqlEnv($config));
                contractAssert($code === 0, 'migrate --backup-done failed: '.$stderr);
                contractAssert(panelDbNormalizeSchema($dialect->describe($db->pdo())) === panelDbNormalizeSchema($schema) && panelSchemaIsCurrent($db), 'MySQL migrate result');
                if ($config['password'] !== '') { contractAssert(!str_contains($stdout.$stderr, $config['password']), 'output contains the password'); }
            });
        };
    }
    $checks['integrity'] = function (PDO $pdo) use ($dialect, $schema): void {
        contractAssert($dialect->integrityErrors($pdo, $schema) === [], 'healthy database reported problems');
        contractWithoutForeignKeys($dialect, $pdo, fn () => $pdo->exec('INSERT INTO `datas` (`name`, `template_id`) VALUES (\'orphan\', 999)'));
        $errors = $dialect->integrityErrors($pdo, $schema);
        contractAssert(count($errors) === 1 && is_string($errors[0]), 'orphan foreign key not reported exactly once');
    };
    $checks['connect_failure'] = function () use ($dialect, $config): void {
        $bad = $dialect->name() === 'mysql' ? ['password' => $config['password'].'-wrong'] + $config : ['file' => sys_get_temp_dir()] + $config;
        try { $dialect->connect($bad); } catch (RuntimeException $e) {
            contractAssert($e->getCode() === 503 && $e->getMessage() === 'Database unavailable' && $e->getPrevious() === null, 'connect failure leaks details');
            return;
        }
        throw new RuntimeException('connect() accepted a bad configuration');
    };

    $pdo = $dialect->connect($config);
    contractCreateSchema($dialect, $pdo, $schema);
    $columns = [];
    foreach (array_keys($schema['tables']) as $table) { $columns[$table] = $dialect->columns($pdo, $table); }
    foreach ($checks as $name => $check) {
        try { $check($pdo); } catch (Throwable $e) { throw new RuntimeException($name.': '.$e->getMessage()); }
    }
    echo 'PASS: '.count($checks).' database contract checks ('.$dialect->name().')'.PHP_EOL;
    return $columns;
}

function contractSqlite(array $schema): array {
    return contractScratch('sqlite', null, fn (array $config): array => contractRun(panelDbDialect('sqlite'), $config, $schema, null));
}
function contractMysql(array $schema, array $credentials): array {
    return contractScratch('mysql', $credentials, fn (array $config): array => contractRun(panelDbDialect('mysql'), $config, $schema, $credentials));
}

$credentials = null;
if ($driver !== 'sqlite') {
    $credentials = contractMysqlCredentials();
    if ($credentials === null) { fwrite(STDERR, "MySQL test credentials missing (PANEL_TEST_MYSQL_HOST/PORT/USER/PASSWORD)\n"); exit(2); }
}
try {
    $sqliteColumns = $driver !== 'mysql' ? contractSqlite($schema) : null;
    $mysqlColumns = $driver !== 'sqlite' ? contractMysql($schema, $credentials) : null;
    if ($driver === 'both') {
        foreach ($sqliteColumns as $table => $columns) {
            $strip = static fn (array $rows): array => array_map(static fn (array $row): array => $row['Field'] === 'id' ? ['Type' => ''] + $row : $row, $rows);
            if ($strip($columns) !== $strip($mysqlColumns[$table] ?? [])) {
                throw new RuntimeException('columns_parity: columns() differs for table '.$table);
            }
        }
        echo 'PASS: 1 database contract checks (parity)'.PHP_EOL;
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: '.$e->getMessage().PHP_EOL);
    exit(1);
}
