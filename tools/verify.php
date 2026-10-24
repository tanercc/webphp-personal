<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASEPATH', '/');
$root = dirname(__DIR__); $count = 0;
$installation = require $root . '/config/site.php';
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
$path = getenv('PANEL_DB_FILE') ?: getenv('PERSONAL_DB_FILE') ?: $installation['database_file'];
if (!is_file($path)) { fwrite(STDERR, "Run tools/install.php first; no database created by verification.\n"); exit(1); }
$pdo = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ($pdo->query('PRAGMA integrity_check')->fetchColumn() !== 'ok' || $pdo->query('PRAGMA foreign_key_check')->fetch()) {
    throw new RuntimeException('SQLite integrity failed');
}
echo 'PASS: '.$count.' panel vendor hashes, '.count($theme).' official theme assets, SQLite integrity and foreign keys'.PHP_EOL;
