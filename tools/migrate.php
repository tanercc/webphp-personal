<?php
/** Applies pending schema migrations to the configured database, stamping a database created before migrations existed.
 * php tools/migrate.php [--status] [--backup-done] | --print-schema=sqlite|mysql
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASEPATH', '/');
date_default_timezone_set('Europe/Istanbul');
$root = dirname(__DIR__);
$installation = require $root.'/config/site.php';
require $root.'/panel/include/db_migrations.php';

function migrateFail(string $message): never { fwrite(STDERR, $message.PHP_EOL); exit(1); }

/** Copies the SQLite file to storage/backups/<name>-<YmdHis>.sqlite without overwriting an existing backup. */
function migrateBackup(string $file, string $directory): string {
    if (!is_dir($directory) && !mkdir($directory, 0750, true)) { migrateFail('Cannot create the backup directory; nothing was changed.'); }
    $target = $directory.'/'.pathinfo($file, PATHINFO_FILENAME).'-'.date('YmdHis').'.sqlite';
    $source = @fopen($file, 'rb');
    $destination = $source === false ? false : @fopen($target, 'xb');
    if ($source === false || $destination === false) {
        if ($source !== false) { fclose($source); }
        migrateFail('Backup failed; nothing was changed.');
    }
    $copied = stream_copy_to_stream($source, $destination);
    fclose($source);
    fclose($destination);
    clearstatcache();
    if ($copied === false || $copied !== filesize($file)) { @unlink($target); migrateFail('Backup failed; nothing was changed.'); }
    return $target;
}

$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help') {
        echo "php tools/migrate.php            apply pending migrations (SQLite: backup to storage/backups first)\n";
        echo "php tools/migrate.php --backup-done   required on MySQL after you have backed up the database\n";
        echo "php tools/migrate.php --status   list applied and pending migrations\n";
        echo "php tools/migrate.php --print-schema=sqlite|mysql   print DDL for database/schema.php (no connection)\n";
        exit;
    }
    if (!preg_match('/^--(?:(status|backup-done)|(print-schema)=(sqlite|mysql))$/D', $argument, $match, PREG_UNMATCHED_AS_NULL)) { migrateFail('Unknown option. See --help.'); }
    $name = $match[1] ?? $match[2];
    if (isset($options[$name])) { migrateFail('Duplicate option. See --help.'); }
    $options[$name] = $match[3] ?? true;
}

if (isset($options['print-schema'])) {
    if (count($options) > 1) { migrateFail('--print-schema cannot be combined with other options.'); }
    $dialect = panelDbDialect($options['print-schema']);
    $schema = require $root.'/database/schema.php';
    $mysql = $dialect->name() === 'mysql';
    // Foreign keys may reference tables defined later (datas -> templates); MySQL needs checks off to create them in this order.
    $output = $mysql ? "SET FOREIGN_KEY_CHECKS=0;\n" : '';
    foreach ($schema['tables'] as $table => $definition) {
        foreach ($dialect->createTableSql($table, $definition) as $sql) { $output .= $sql.";\n"; }
    }
    echo $output.($mysql ? "SET FOREIGN_KEY_CHECKS=1;\n" : '');
    exit(0);
}

$config = panelDatabaseConfig();
$apply = !isset($options['status']);
if ($apply && $config['driver'] === 'mysql' && !isset($options['backup-done'])) {
    migrateFail('Back up the MySQL database first, then run again with --backup-done.');
}
try {
    // A runtime SQLite connection never creates a missing file.
    $db = new PanelDbConnection(panelDbDialect($config['driver']), $config);
} catch (Throwable) {
    migrateFail('Database unavailable.');
}
$migrator = new PanelMigrator($db);
try {
    $legacy = !$db->tableExists(PANEL_MIGRATION_LEDGER) && $db->dialect()->describe($db->pdo())['tables'] !== [];
    $status = $migrator->status();
    if (!$apply) {
        if ($legacy) { echo "Migration history missing: the existing tables will be checked and stamped as ".PANEL_BASELINE_MIGRATION.".\n"; }
        echo 'Applied: '.($status['applied'] ? implode(', ', $status['applied']) : 'none').PHP_EOL;
        echo 'Pending: '.($status['pending'] ? implode(', ', $status['pending']) : 'none').PHP_EOL;
        exit(0);
    }
    if (!$legacy && !$status['pending']) { echo "Schema is up to date.\n"; exit(0); }
    if ($config['driver'] === 'sqlite') { echo 'Backup: '.migrateBackup($config['file'], $root.'/storage/backups').PHP_EOL; }
    if ($legacy) {
        $migrator->stampLegacy();
        echo 'Stamped existing schema as '.PANEL_BASELINE_MIGRATION.".\n";
    }
    foreach ($migrator->applyPending() as $id) { echo 'Applied: '.$id.PHP_EOL; }
    echo "Schema is up to date.\n";
} catch (PanelSchemaMismatch $mismatch) {
    fwrite(STDERR, "Existing tables do not match the baseline schema; nothing was changed.\n");
    foreach ($mismatch->differences as $line) { fwrite(STDERR, '  '.$line.PHP_EOL); }
    exit(1);
} catch (PanelMigrationFailure $failure) {
    migrateFail($failure->getMessage());
} catch (Throwable) {
    migrateFail('Migration failed; see --status.');
}
