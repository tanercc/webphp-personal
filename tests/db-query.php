<?php
/** Test helper: run one query against a disposable database and print its rows as JSON,
 * or create/drop a scratch MySQL database (cms_test_<12 hex>).
 * php tests/db-query.php --sql="..." [--params='[json]']   (PANEL_DB_DRIVER, PANEL_DB_FILE / PANEL_DB_HOST, _PORT, _NAME, _USER, _PASSWORD)
 * php tests/db-query.php --create-database=NAME | --drop-database=NAME   (PANEL_TEST_MYSQL_HOST, _PORT, _USER, _PASSWORD)
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASEPATH', '/');
require dirname(__DIR__).'/panel/include/db_dialect.php';

function dbQueryFail(string $message, int $code): never { fwrite(STDERR, $message.PHP_EOL); exit($code); }
function dbQueryEnv(string $name): string { $value = getenv($name); return $value === false ? '' : $value; }

$options = getopt('', ['sql:', 'params:', 'create-database:', 'drop-database:']);
try {
    if (isset($options['create-database']) || isset($options['drop-database'])) {
        $create = isset($options['create-database']);
        $name = (string)($create ? $options['create-database'] : $options['drop-database']);
        if (!preg_match('/^cms_test_[0-9a-f]{12}$/D', $name)) { dbQueryFail('Database name must match cms_test_<12 hex digits>', 1); }
        foreach (['HOST', 'PORT', 'USER', 'PASSWORD'] as $key) {
            $value = getenv('PANEL_TEST_MYSQL_'.$key);
            if ($value === false || ($value === '' && $key !== 'PASSWORD')) { dbQueryFail('MySQL test credentials missing (PANEL_TEST_MYSQL_HOST/PORT/USER/PASSWORD)', 2); }
        }
        $pdo = panelDbDialect('mysql')->connect(['driver' => 'mysql', 'host' => dbQueryEnv('PANEL_TEST_MYSQL_HOST'), 'port' => (int)dbQueryEnv('PANEL_TEST_MYSQL_PORT'),
            'name' => '', 'user' => dbQueryEnv('PANEL_TEST_MYSQL_USER'), 'password' => dbQueryEnv('PANEL_TEST_MYSQL_PASSWORD'), 'charset' => 'utf8mb4']);
        $pdo->exec($create ? 'CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci' : 'DROP DATABASE IF EXISTS `'.$name.'`');
        echo '[]'.PHP_EOL;
        exit(0);
    }
    if (!isset($options['sql']) || (string)$options['sql'] === '') { dbQueryFail('Usage: php tests/db-query.php --sql="..." [--params=\'[json]\']', 1); }
    $params = json_decode((string)($options['params'] ?? '[]'), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($params) || !array_is_list($params)) { dbQueryFail('--params must be a JSON list', 1); }
    // Task 3 replaces this with panelDatabaseConnection(); until then the target comes from the environment only.
    $driver = dbQueryEnv('PANEL_DB_DRIVER') ?: 'sqlite';
    if ($driver === 'sqlite') {
        if (dbQueryEnv('PANEL_DB_FILE') === '') { dbQueryFail('PANEL_DB_FILE is required', 1); }
        $config = ['driver' => 'sqlite', 'file' => dbQueryEnv('PANEL_DB_FILE')];
    } else {
        $config = ['driver' => 'mysql', 'host' => dbQueryEnv('PANEL_DB_HOST'), 'port' => (int)(dbQueryEnv('PANEL_DB_PORT') ?: 3306), 'name' => dbQueryEnv('PANEL_DB_NAME'),
            'user' => dbQueryEnv('PANEL_DB_USER'), 'password' => dbQueryEnv('PANEL_DB_PASSWORD'), 'charset' => 'utf8mb4'];
    }
    $pdo = panelDbDialect($driver)->connect($config);
    $statement = $pdo->prepare((string)$options['sql']);
    $statement->execute($params);
    $rows = $statement->columnCount() ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $e) {
    // Never echo SQL, values, DSNs or credentials.
    dbQueryFail($e instanceof RuntimeException && $e->getCode() === 503 ? 'Database unavailable' : 'Query failed', 1);
}
