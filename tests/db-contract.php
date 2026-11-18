<?php
/** Database contract checks for the SQLite and MySQL dialects, run on disposable databases only.
 * Usage: php tests/db-contract.php --driver=sqlite|mysql|both
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASEPATH', '/');
$root = dirname(__DIR__);
require $root.'/panel/include/db_dialect.php';

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
    };
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
