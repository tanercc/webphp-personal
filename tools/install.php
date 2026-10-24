<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASEPATH', '/');
$root = dirname(__DIR__);
$installation = require $root . '/config/site.php';
$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help') {
        echo "php tools/install.php [--profile=demo|starter] [--admin-email=admin@example.test]\n";
        echo "Starter requires PANEL_ADMIN_PASSWORD in the environment (minimum 12 characters).\n";
        exit;
    }
    if (!preg_match('/^--(profile|admin-email)=(.+)$/D', $argument, $match) || isset($options[$match[1]])) {
        fwrite(STDERR, "Unknown or duplicate option. See --help.\n"); exit(1);
    }
    $options[$match[1]] = $match[2];
}
$profile = $options['profile'] ?? $installation['profile'];
if (!in_array($profile, $installation['allowed_profiles'], true)) {
    fwrite(STDERR, "Unsupported installation profile.\n"); exit(1);
}
$path = getenv('PANEL_DB_FILE') ?: getenv('PERSONAL_DB_FILE') ?: $installation['database_file'];
if (file_exists($path) || is_link($path)) {
    fwrite(STDERR, "Database exists; refusing to overwrite. Choose a new PANEL_DB_FILE.\n"); exit(1);
}
$email = $options['admin-email'] ?? '';
$password = getenv('PANEL_ADMIN_PASSWORD');
if ($profile === 'starter' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254
    || !is_string($password) || strlen($password) < 12 || strlen($password) > 72)) {
    fwrite(STDERR, "Starter needs --admin-email and a 12-72 byte PANEL_ADMIN_PASSWORD.\n"); exit(1);
}
if (!is_dir(dirname($path))) { fwrite(STDERR, "Database parent directory does not exist.\n"); exit(1); }
foreach ([$root.'/storage', $root.'/storage/sessions', $root.'/uploads/files', $root.'/uploads/thumbs'] as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0750, true)) { fwrite(STDERR, "Cannot prepare runtime directories.\n"); exit(1); }
}
$temp = $path . '.install-' . bin2hex(random_bytes(6));
try {
    $db = new PDO('sqlite:' . $temp, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec(file_get_contents($root . '/database/schema.sql'));
    $seed = $profile === 'demo' ? 'seed.sql' : 'seed-base.sql';
    $db->exec(file_get_contents($root . '/database/' . $seed));
    if ($profile === 'starter') {
        $statement = $db->prepare('INSERT INTO users (name,email,password,type_id,lang_id,image) VALUES (?,?,?,1,1,?)');
        $statement->execute(['Yönetici', $email, password_hash($password, PASSWORD_DEFAULT), '/panel/img/brand.svg']);
        $statement = $db->prepare('UPDATE variable_lang SET name=? WHERE row_id=1 AND lang_id=1');
        $statement->execute([$installation['name']]);
    }
    if ($db->query('PRAGMA integrity_check')->fetchColumn() !== 'ok' || $db->query('PRAGMA foreign_key_check')->fetch()) {
        throw new RuntimeException('Database validation failed');
    }
    $db = null;
    if ($profile === 'demo') { require __DIR__ . '/seed-media.php'; }
    // Exclusive publish: never overwrite a database created by a concurrent installer.
    $source = fopen($temp, 'rb');
    $destination = fopen($path, 'xb');
    if ($destination === false) { fclose($source); throw new RuntimeException('Database already exists'); }
    $copied = stream_copy_to_stream($source, $destination);
    fclose($source); fclose($destination);
    if ($copied !== filesize($temp)) { unlink($path); throw new RuntimeException('Incomplete database copy'); }
    unlink($temp);
    echo 'Installed ' . $profile . " SQLite database.\n";
} catch (Throwable $error) {
    $db = null;
    if (is_file($temp)) { unlink($temp); }
    fwrite(STDERR, "Installation failed. Existing databases preserved.\n"); exit(1);
}
