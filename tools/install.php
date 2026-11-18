<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASEPATH', '/');
$root = dirname(__DIR__);
$installation = require $root . '/config/site.php';
require $root . '/panel/include/db_install.php';
$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help') {
        echo "php tools/install.php [--profile=demo|starter] [--admin-email=admin@example.test]\n";
        echo "Starter requires PANEL_ADMIN_PASSWORD in the environment (minimum 12 characters).\n";
        echo "SQLite creates a new PANEL_DB_FILE; MySQL needs an existing, empty database (PANEL_DB_DRIVER=mysql).\n";
        exit;
    }
    if (!preg_match('/^--(profile|admin-email)=(.+)$/D', $argument, $match) || isset($options[$match[1]])) {
        fwrite(STDERR, "Unknown or duplicate option. See --help.\n"); exit(1);
    }
    $options[$match[1]] = $match[2];
}
function installFail(string $message): never { fwrite(STDERR, $message . "\n"); exit(1); }

/** Creates the tables of database/schema.php, inserts the profile's seed rows (tables and columns allowlisted by the schema),
 * creates the starter administrator, then checks integrity and that the structure equals the definition. */
function installBuild(PanelDbConnection $db, string $root, string $profile, array $installation, string $email, #[\SensitiveParameter] string $password, ?array &$created = null): void {
    $schema = require $root . '/database/schema.php';
    panelCreateSchema($db, $schema, $created);
    $seed = require $root . '/database/' . ($profile === 'demo' ? 'seed.php' : 'seed-base.php');
    $installedAt = date('Y-m-d H:i:s');
    $db->begin_transaction();
    try {
        foreach ($seed as $table => $rows) {
            $columns = $schema['tables'][$table]['columns'] ?? throw new RuntimeException('Seed table is not in the schema');
            foreach ($rows as $row) {
                if (!isset($row['id']) || array_diff_key($row, $columns)) { throw new RuntimeException('Seed row has an unknown column or no id'); }
                if (isset($columns['created_at']) && !array_key_exists('created_at', $row)) { $row['created_at'] = $installedAt; }
                $names = array_map(static fn (string $column): string => '`' . panelDbIdentifier($column) . '`', array_keys($row));
                $db->execute_query('INSERT INTO `' . panelDbIdentifier($table) . '` (' . implode(',', $names) . ') VALUES ('
                    . implode(',', array_fill(0, count($row), '?')) . ')', array_values($row));
            }
        }
        if ($profile === 'starter') {
            $db->execute_query('INSERT INTO `users` (`name`,`email`,`password`,`type_id`,`lang_id`) VALUES (?,?,?,1,1)',
                ['Yönetici', $email, password_hash($password, PASSWORD_DEFAULT)]);
            $db->execute_query('UPDATE `variable_lang` SET `name`=? WHERE `row_id`=1 AND `lang_id`=1', [$installation['name']]);
        }
        $db->commit();
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }
    $binary = $db->dialect()->name() === 'mysql';
    if ($db->dialect()->integrityErrors($db->pdo(), $schema) !== []
        || panelDbNormalizeSchema($db->dialect()->describe($db->pdo()), $binary) !== panelDbNormalizeSchema($schema, $binary)) {
        throw new RuntimeException('Database validation failed');
    }
}

$profile = $options['profile'] ?? $installation['profile'];
if (!in_array($profile, $installation['allowed_profiles'], true)) { installFail('Unsupported installation profile.'); }
$config = panelDatabaseConfig();
if (!in_array($config['driver'], ['sqlite', 'mysql'], true)) { installFail('Unsupported database driver.'); }
$path = $config['file'] ?? '';
if ($config['driver'] === 'sqlite' && (file_exists($path) || is_link($path))) {
    installFail('Database exists; refusing to overwrite. Choose a new PANEL_DB_FILE.');
}
$email = $options['admin-email'] ?? '';
$password = getenv('PANEL_ADMIN_PASSWORD');
if ($profile === 'starter' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254
    || !is_string($password) || strlen($password) < 12 || strlen($password) > 72)) {
    installFail('Starter needs --admin-email and a 12-72 byte PANEL_ADMIN_PASSWORD.');
}
$password = is_string($password) ? $password : '';
if ($config['driver'] === 'sqlite' && !is_dir(dirname($path))) { installFail('Database parent directory does not exist.'); }
foreach ([$root.'/storage', $root.'/storage/sessions', $root.'/uploads/files', $root.'/uploads/media', $root.'/uploads/thumbs'] as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0750, true)) { installFail('Cannot prepare runtime directories.'); }
}

if ($config['driver'] === 'mysql') {
    if ($config['name'] === '') { installFail('Set PANEL_DB_NAME to an existing, empty MySQL database.'); }
    try {
        $db = new PanelDbConnection(panelDbDialect('mysql'), $config);
    } catch (Throwable) {
        installFail('Database unavailable; nothing was changed.');
    }
    $dialect = $db->dialect();
    try {
        // One installer per database: the lock is held from the emptiness check until this process exits.
        $lock = $db->execute_query('SELECT GET_LOCK(?, 0) AS `locked`', ['cms_install_' . md5($config['name'])])->fetch_assoc()['locked'];
        $empty = $dialect->describe($db->pdo())['tables'] === [];
    } catch (Throwable) {
        installFail('Database unavailable; nothing was changed.');
    }
    if ((int)$lock !== 1) { installFail('Another installation is running for this database; nothing was changed.'); }
    if (!$empty) { installFail('Database is not empty; refusing to install.'); }
    $created = [];
    try {
        installBuild($db, $root, $profile, $installation, $email, $password, $created);
        if ($profile === 'demo') { require __DIR__ . '/seed-media.php'; }
    } catch (Throwable) {
        // Drop exactly the tables this run created; anything else in the database is left alone.
        try {
            $db->rollback();
            $db->pdo()->exec('SET FOREIGN_KEY_CHECKS=0');
            try {
                foreach (array_reverse($created) as $table) { $db->pdo()->exec('DROP TABLE `' . panelDbIdentifier($table) . '`'); }
            } finally {
                $db->pdo()->exec('SET FOREIGN_KEY_CHECKS=1');
            }
        } catch (Throwable) {
            fwrite(STDERR, "Removing the partially created tables failed; drop them before retrying.\n");
        }
        installFail('Installation failed. Existing databases preserved.');
    }
    echo 'Installed ' . $profile . " MySQL database.\n";
    exit(0);
}

$temp = $path . '.install-' . bin2hex(random_bytes(6));
try {
    $db = new PanelDbConnection(panelDbDialect('sqlite'), ['file' => $temp, 'create' => true]);
    installBuild($db, $root, $profile, $installation, $email, $password);
    $db = null;
    gc_collect_cycles(); // Release every handle on the temporary file before copying and removing it (Windows).
    if ($profile === 'demo') { require __DIR__ . '/seed-media.php'; }
    // Exclusive publish: never overwrite a database created by a concurrent installer.
    $source = fopen($temp, 'rb');
    $destination = @fopen($path, 'xb');
    if ($destination === false) { fclose($source); throw new RuntimeException('Database already exists'); }
    $copied = stream_copy_to_stream($source, $destination);
    fclose($source); fclose($destination);
    if ($copied !== filesize($temp)) { unlink($path); throw new RuntimeException('Incomplete database copy'); }
    unlink($temp);
    echo 'Installed ' . $profile . " SQLite database.\n";
} catch (Throwable) {
    // No exception variable: its trace would hold installBuild()'s connection argument and keep the temporary file open.
    $db = null;
    gc_collect_cycles();
    if (is_file($temp)) { @unlink($temp); }
    installFail('Installation failed. Existing databases preserved.');
}
