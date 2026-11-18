<?php
/** Database contract checks for the SQLite and MySQL dialects, run on disposable databases only.
 * Usage: php tests/db-contract.php --driver=sqlite|mysql|both
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASEPATH', '/');
$root = dirname(__DIR__);
require $root.'/panel/include/database.php';

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
function contractWithoutForeignKeys(PanelDbDialect $dialect, PDO $pdo, callable $work): void {
    $pdo->exec($dialect->name() === 'mysql' ? 'SET FOREIGN_KEY_CHECKS=0' : 'PRAGMA foreign_keys=OFF');
    try { $work(); } finally { $pdo->exec($dialect->name() === 'mysql' ? 'SET FOREIGN_KEY_CHECKS=1' : 'PRAGMA foreign_keys=ON'); }
}

/** Runs every check for one driver; returns the columns() output per table for the parity check. */
function contractRun(PanelDbDialect $dialect, array $config, array $schema): array {
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
    $file = tempnam(sys_get_temp_dir(), 'cms-contract-');
    try { return contractRun(panelDbDialect('sqlite'), ['driver' => 'sqlite', 'file' => $file], $schema); }
    finally { gc_collect_cycles(); @unlink($file); }
}
function contractMysql(array $schema, array $credentials): array {
    $dialect = panelDbDialect('mysql');
    $server = $dialect->connect($credentials);
    $name = 'cms_test_'.bin2hex(random_bytes(6));
    $server->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
    try { return contractRun($dialect, ['name' => $name] + $credentials, $schema); }
    finally { $server->exec('DROP DATABASE IF EXISTS `'.$name.'`'); }
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
