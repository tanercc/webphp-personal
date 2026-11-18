<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASEPATH', '/');
$root = dirname(__DIR__); $count = 0;
$installation = require $root . '/config/site.php';
require $root . '/panel/include/database.php';
$manifest = json_decode(file_get_contents($root.'/panel/plugins/DEPENDENCIES.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($manifest['packages'] as $package) {
    foreach ($package['files'] as $relative => $hash) {
        if (hash_file('sha256', $root.'/panel/plugins/'.$relative) !== $hash) { throw new RuntimeException('Vendor hash mismatch: '.$relative); }
        $count++;
    }
}
$theme = [];
if ($installation['profile'] === 'demo' || is_file($root.'/docs/theme-assets.json')) {
    $theme = json_decode(file_get_contents($root.'/docs/theme-assets.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($theme as $asset) {
        if (hash_file('sha256', $root.'/'.$asset['file']) !== $asset['sha256']) { throw new RuntimeException('Theme hash mismatch: '.$asset['file']); }
    }
}
$config = panelDatabaseConfig();
if ($config['driver'] === 'sqlite' && !is_file($config['file'])) { fwrite(STDERR, "Run tools/install.php first; no database created by verification.\n"); exit(1); }
try {
    // A runtime connection never creates a database; SQLite checks below are read-only.
    $db = new PanelDbConnection(panelDbDialect($config['driver']), $config);
} catch (Throwable) {
    fwrite(STDERR, "Database unavailable.\n"); exit(1);
}
$schema = require $root.'/database/schema.php';
$binary = $db->dialect()->name() === 'mysql';
try {
    $problems = $db->dialect()->integrityErrors($db->pdo(), $schema);
    $expected = panelDbNormalizeSchema($schema, $binary)['tables'];
    $actual = panelDbNormalizeSchema($db->dialect()->describe($db->pdo()), $binary)['tables'];
    foreach (array_unique(array_merge(array_keys($expected), array_keys($actual))) as $table) {
        if (($expected[$table] ?? null) !== ($actual[$table] ?? null)) {
            $problems[] = 'Table '.$table.' '.(!isset($actual[$table]) ? 'is missing' : (!isset($expected[$table]) ? 'is not in database/schema.php' : 'differs from database/schema.php')).'.';
        }
    }
} catch (Throwable) {
    $problems = ['Database checks could not run.'];
}
if ($problems) {
    foreach ($problems as $line) { fwrite(STDERR, 'FAIL: '.$line.PHP_EOL); }
    exit(1);
}
echo 'PASS: '.$count.' panel vendor hashes, '.count($theme).' official theme assets, '.($binary ? 'MySQL' : 'SQLite').' integrity and schema'.PHP_EOL;
