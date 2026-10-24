<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help') {
        echo "php tools/create-site.php --target=../new-site [--name=Site]\n";
        echo "Creates a new, uninstalled starter; refuses existing destinations. Requires Git.\n";
        exit;
    }
    if (!preg_match('/^--(target|name)=(.+)$/D', $argument, $match) || isset($options[$match[1]])) {
        fwrite(STDERR, "Unknown or duplicate option. See --help.\n"); exit(1);
    }
    $options[$match[1]] = $match[2];
}
function scaffoldGit(string $root, array $arguments): string {
    $pipes = [];
    $process = proc_open(array_merge(['git', '-C', $root], $arguments),
        [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
    if (!is_resource($process)) { throw new RuntimeException('Git is required'); }
    fclose($pipes[0]); $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0) { throw new RuntimeException('Cannot read source Git repository'); }
    return $output;
}
function scaffoldCopy(string $source, string $destination): void {
    if (!is_file($source) || is_link($source)) { throw new RuntimeException('Missing or linked source file'); }
    if (file_exists($destination) || is_link($destination)) { throw new RuntimeException('Refusing to replace a file'); }
    if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0750, true)) { throw new RuntimeException('Cannot create directory'); }
    if (!copy($source, $destination)) { throw new RuntimeException('Cannot copy source file'); }
}
try {
    $name = trim($options['name'] ?? 'Yeni Site');
    $requested = $options['target'] ?? '';
    if ($requested === '' || preg_match('/[\x00-\x1f\x7f]/', $requested)
        || $name === '' || mb_strlen($name) > 80 || preg_match('/[\x00-\x1f\x7f]/', $name)) {
        throw new RuntimeException('Provide a target directory and a valid site name');
    }
    $requested = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $requested);
    $parent = realpath(dirname($requested)); $leaf = basename($requested);
    if ($parent === false || in_array($leaf, ['', '.', '..'], true)) { throw new RuntimeException('Target parent must exist'); }
    $target = $parent . DIRECTORY_SEPARATOR . $leaf;
    $normalizedRoot = strtolower(str_replace('\\', '/', realpath($root)));
    $normalizedTarget = strtolower(str_replace('\\', '/', $target));
    if ($normalizedTarget === $normalizedRoot || str_starts_with($normalizedTarget, $normalizedRoot . '/')) {
        throw new RuntimeException('Target must be outside this repository');
    }
    if (file_exists($target) || is_link($target)) { throw new RuntimeException('Target already exists; nothing overwritten'); }
    if (realpath(trim(scaffoldGit($root, ['rev-parse', '--show-toplevel']))) !== realpath($root)) {
        throw new RuntimeException('Run from the standalone source repository');
    }
    $tracked = explode("\0", scaffoldGit($root, ['ls-files', '-z']));
    $files = ['.gitattributes','.gitignore','.htaccess','router.php','app/bootstrap.php',
        'config/config.php','config/data-templates.php','database/schema.sql','database/seed-base.sql',
        'panel/include/config.php','panel/plugins/DEPENDENCIES.json','tools/install.php','tools/create-site.php','tools/verify.php',
        'tools/serve.ps1','storage/.gitkeep','uploads/.htaccess',
        'docs/yeni-site.md','docs/mimari.md'];
    $skip = ['panel/content-dashboard.php','panel/content-messages.php'];
    $selected = [];
    foreach ($tracked as $relative) {
        if ($relative === '' || in_array($relative, $skip, true)) { continue; }
        if (preg_match('~(?:^|/)(?:\.git|\.env[^/]*|config\.(?:runtime|local|domain)\.php|cache|temp|thumbs)(?:/|$)~i', $relative)) { continue; }
        if (in_array($relative, $files, true) || str_starts_with($relative, 'panel/') || str_starts_with($relative, 'starter/')) {
            $selected[] = $relative;
        }
    }
    foreach ($files as $required) {
        if (!in_array($required, $selected, true)) { throw new RuntimeException('Required core file is not tracked: ' . $required); }
    }
    foreach (['index.php','templates/page.php','css/site.css','config/panel-fields.php','panel/content-dashboard.php','README.md'] as $overlay) {
        if (!in_array('starter/site/'.$overlay, $selected, true)) { throw new RuntimeException('Starter scaffold must be tracked in Git'); }
    }
    if (!mkdir($target, 0750)) { throw new RuntimeException('Cannot create target'); }
    foreach ($selected as $relative) { scaffoldCopy($root.'/'.$relative, $target.'/'.$relative); }
    foreach (['index.php','templates/page.php','css/site.css','config/panel-fields.php','panel/content-dashboard.php','README.md'] as $overlay) {
        scaffoldCopy($root.'/starter/site/'.$overlay, $target.'/'.$overlay);
    }
    scaffoldCopy($root.'/starter/AGENTS.md', $target.'/AGENTS.md');
    $site = "<?php\ndefined('BASEPATH') or exit('No direct script access allowed');\nreturn [\n"
        . "    'name' => " . var_export($name, true) . ",\n"
        . "    'profile' => 'starter',\n    'allowed_profiles' => ['starter'],\n"
        . "    'database_file' => dirname(__DIR__) . '/storage/site.sqlite',\n"
        . "    'session_name' => 'CMS' . substr(hash('sha256', __DIR__), 0, 20),\n];\n";
    if (file_put_contents($target.'/config/site.php', $site) === false) { throw new RuntimeException('Cannot write installation config'); }
    $permissions = file_get_contents($target.'/panel/include/permissions.php');
    $permissions = preg_replace('/^\s*\x27messages\x27,\R/m', '', $permissions);
    file_put_contents($target.'/panel/include/permissions.php', $permissions);
    echo 'Created clean starter: ' . $target . "\n";
    echo "No database, administrator, uploaded media, demo content or source Git history was copied.\n";
    echo "Set PANEL_ADMIN_PASSWORD, run php tools/install.php --admin-email=..., then git init.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Scaffold failed: ' . $error->getMessage() . "\n"); exit(1);
}
