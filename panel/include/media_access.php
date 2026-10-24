<?php
require_once __DIR__ . '/session.php';

/** Shared media gate; called before the legacy file manager initializes RF. */
function requireMediaAccess(array $permissions, ?int $maxLifetime = null): void
{
    $maxLifetime ??= panelSessionLifetime();
    $role = $_SESSION['userType'] ?? null;
    $userId = $_SESSION['userId'] ?? null;
    $lastActivity = $_SESSION['LAST_ACTIVITY'] ?? null;
    $now = time();
    $validUser = (is_int($userId) || is_string($userId))
        && filter_var($userId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
    $validActivity = is_int($lastActivity)
        && $lastActivity <= $now
        && ($now - $lastActivity) <= $maxLifetime;
    $allowedRole = is_string($role)
        && isset($permissions[$role])
        && is_array($permissions[$role])
        && in_array('media', $permissions[$role], true);

    if (($_SESSION['userAuthorized'] ?? false) !== true || !$validUser || !$validActivity || !$allowedRole) {
        unset($_SESSION['RF']);
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store');
        exit('Forbidden');
    }

    $_SESSION['LAST_ACTIVITY'] = $now;
}

/** A picker is bound to an existing panel record, never the shared upload root. */
function panelMediaRecordScope(array $permissions): string
{
    $scope = $_SESSION['RF']['subfolder'] ?? null;
    if (!is_string($scope) || !preg_match('~\A([a-z]+)/([1-9][0-9]*)/?\z~', $scope, $match)) {
        throw new RuntimeException('Invalid media scope', 403);
    }
    $resource = $match[1];
    $resources = ['pages','sliders','menus','datas','datatypes','cats','tags','users','variables','captions'];
    $allowed = $permissions[$_SESSION['userType'] ?? ''] ?? [];
    $ownProfile = $resource === 'users' && $match[2] === (string) ($_SESSION['userId'] ?? '') && in_array('self', $allowed, true);
    if (!in_array($resource, $resources, true) || (!in_array($resource, $allowed, true) && !$ownProfile)) {
        throw new RuntimeException('Forbidden media scope', 403);
    }
    return $resource . '/' . $match[2];
}

function panelMediaCleanPath($path): string
{
    if (!is_string($path) || preg_match('~[\x00-\x1f\x7f\\\\:]~', $path)) { throw new RuntimeException('Invalid media path', 403); }
    if (str_starts_with($path, '/')) { throw new RuntimeException('Invalid media path', 403); }
    foreach (explode('/', $path) as $part) {
        if ($part === '.' || $part === '..' || (DIRECTORY_SEPARATOR === '\\' && preg_match('/[. ]$/D', $part))) {
            throw new RuntimeException('Invalid media path', 403);
        }
    }
    return rtrim($path, '/');
}

/** Reject aliases/junctions/symlinks even when their targets are also public uploads. */
function panelMediaLocalPath(string $base, string $path): void
{
    $root = realpath($base);
    if (is_link(rtrim($base, '/\\'))) { throw new RuntimeException('Invalid media root', 403); }
    if ($root === false) {
        $base = rtrim($base, '/\\');
        $parent = realpath(dirname($base));
        if ($parent === false) { throw new RuntimeException('Invalid media root', 403); }
        $root = $parent . DIRECTORY_SEPARATOR . basename($base);
    }
    $candidate = $root;
    foreach (explode('/', $path) as $part) {
        if ($part === '') { continue; }
        $candidate .= DIRECTORY_SEPARATOR . $part;
        $resolved = realpath($candidate);
        if (is_link($candidate) || ($resolved !== false && (DIRECTORY_SEPARATOR === '\\' ? strcasecmp($resolved, $candidate) !== 0 : $resolved !== $candidate))) {
            throw new RuntimeException('Invalid media alias', 403);
        }
    }
}

function panelMediaScopedPath(array $config, string $scope, $path, bool $relative = false): void
{
    if ($relative && $path === '/') { $path = ''; }
    for ($decode = 0; $decode < 5; $decode++) {
        $clean = panelMediaCleanPath($path);
        $full = $relative ? $scope . ($clean !== '' ? '/' . $clean : '') : $clean;
        if ($full !== $scope && !str_starts_with($full, $scope . '/')) { throw new RuntimeException('Outside media scope', 403); }
        panelMediaLocalPath($config['current_path'], $full);
        panelMediaLocalPath($config['thumbs_base_path'], $full);
        $decoded = rawurldecode($path);
        if ($decoded === $path) { return; }
        $path = $decoded;
    }
    throw new RuntimeException('Invalid media encoding', 403);
}

function panelMediaBasename($name): void
{
    for ($decode = 0; $decode < 5; $decode++) {
        $clean = panelMediaCleanPath($name);
        if ($clean === '' || str_contains($name, '/')) { throw new RuntimeException('Invalid media name', 403); }
        $decoded = rawurldecode($name);
        if ($decoded === $name) { return; }
        $name = $decoded;
    }
    throw new RuntimeException('Invalid media encoding', 403);
}

function requireMediaRecordAccess(array $config, array $permissions): void
{
    $scope = panelMediaRecordScope($permissions);
    $entry = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
    if (!in_array($entry, ['dialog.php','upload.php','execute.php','ajax_calls.php','force_download.php'], true)) { return; }
    foreach (['field_id','view','filter','sort_by','type','callback','editor','extensions','lang','akey','popup','crossdomain','CKEditor','CKEditorFuncNum','multiple','relative_url','descending'] as $field) {
        if (isset($_GET[$field]) && !is_string($_GET[$field])) { throw new RuntimeException('Invalid media option', 403); }
    }
    if (isset($_GET['field_id']) && $_GET['field_id'] !== '' && !preg_match('/\A[a-zA-Z_][a-zA-Z0-9_.\[\]-]{0,127}\z/', $_GET['field_id'])) {
        throw new RuntimeException('Invalid media field', 403);
    }
    if (isset($_GET['callback']) && in_array($_GET['callback'], ['eval','Function','setTimeout','setInterval','execScript','constructor','__proto__','prototype'], true)) {
        throw new RuntimeException('Invalid media callback', 403);
    }
    if (!empty($config['ftp_host'])) { throw new RuntimeException('Local media required', 403); }
    if (($_SESSION['RF']['cms_scope'] ?? null) !== $scope) {
        unset($_SESSION['RF']['clipboard'], $_SESSION['RF']['clipboard_action'], $_SESSION['RF']['fldr']);
    }
    $_SESSION['RF']['cms_scope'] = $scope;
    $_SESSION['RF']['subfolder'] = $scope . '/';
    // Legacy quick-upload bypasses the normal extension/MIME checks and record folder.
    if ($entry === 'dialog.php' && !empty($_FILES)) { throw new RuntimeException('Use media upload', 403); }
    if ($entry === 'upload.php' && isset($_POST['url'])) { throw new RuntimeException('Remote upload disabled', 403); }
    if ($entry === 'ajax_calls.php' && ($_GET['action'] ?? '') === 'extract') { throw new RuntimeException('Archive extraction disabled', 403); }
    if ($entry === 'dialog.php') {
        $folder = $_GET['fldr'] ?? '/';
        panelMediaScopedPath($config, $scope, $folder, true);
        $_GET['fldr'] = $folder;
        return;
    }
    foreach ([$_GET, $_POST] as $input) {
        foreach (['path','file','fldr'] as $field) {
            if (array_key_exists($field, $input)) { panelMediaScopedPath($config, $scope, $input[$field]); }
        }
    }
    if (isset($_POST['paths'])) {
        if (!is_array($_POST['paths'])) { throw new RuntimeException('Invalid media paths', 403); }
        foreach ($_POST['paths'] as $path) { panelMediaScopedPath($config, $scope, $path); }
    }
    if (isset($_POST['name'])) { panelMediaBasename($_POST['name']); }
    if (isset($_POST['names']) && $_POST['names'] !== '') {
        if (!is_array($_POST['names'])) { throw new RuntimeException('Invalid media names', 403); }
        foreach ($_POST['names'] as $name) { panelMediaBasename($name); }
    }
    if ($entry === 'force_download.php' && isset($_POST['path'], $_POST['name'])) {
        panelMediaScopedPath($config, $scope, rtrim($_POST['path'], '/') . '/' . $_POST['name']);
    }
    if ($entry === 'execute.php' && ($_GET['action'] ?? '') === 'paste_clipboard' && isset($_SESSION['RF']['clipboard']['path'])) {
        panelMediaScopedPath($config, $scope, $_SESSION['RF']['clipboard']['path']);
    }
    if ($entry === 'execute.php' && in_array($_GET['action'] ?? '', ['delete_folder','rename_folder'], true)
        && isset($_POST['path']) && rtrim($_POST['path'], '/') === $scope) { throw new RuntimeException('Cannot modify media root', 403); }
}
