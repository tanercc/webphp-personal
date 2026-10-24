<?php
// Shared by the public templates and the standalone phpThumb endpoint.
function siteThumbnailRuntimeDirectory(): string
{
    $path = rtrim(sys_get_temp_dir(), '/\\') . '/cms-thumb-' . substr(hash('sha256', dirname(__DIR__, 2)), 0, 20);
    if (!is_dir($path) && !@mkdir($path, 0700, true) && !is_dir($path)) { throw new RuntimeException('Thumbnail storage unavailable'); }
    return $path;
}

function siteThumbnailKey(): string
{
    $configured = getenv('PANEL_THUMBNAIL_KEY');
    if (is_string($configured) && strlen($configured) >= 64) { return $configured; }
    $file = siteThumbnailRuntimeDirectory() . '/signing.key';
    $previous = umask(0077);
    try { $handle = fopen($file, 'c+b'); } finally { umask($previous); }
    if (!$handle) { throw new RuntimeException('Thumbnail signing unavailable'); }
    try {
        if (!flock($handle, LOCK_EX)) { throw new RuntimeException('Thumbnail signing unavailable'); }
        $key = stream_get_contents($handle);
        if ($key === '') {
            $key = bin2hex(random_bytes(48));
            if (fwrite($handle, $key) !== strlen($key) || !fflush($handle)) { throw new RuntimeException('Thumbnail signing unavailable'); }
        }
        if (strlen($key) !== 96) { throw new RuntimeException('Invalid thumbnail signing state'); }
        return $key;
    } finally { fclose($handle); }
}

function siteThumbnailSourceAllowed($source): bool
{
    if (!is_string($source) || preg_match('/[\x00-\x1f\x7f\\\\]/', $source)) { return false; }
    if (!preg_match('#^/(?:images|uploads)/#', $source)) { return false; }
    // Reject encoded separators/traversal too, without changing legitimate filenames.
    $decoded = rawurldecode($source);
    return !preg_match('#(?:^|/)\.\.(?:/|$)|[\x00-\x1f\x7f\\\\]#', $decoded);
}

function siteThumbnailUrl($source, array $options = []): string
{
    if (!siteThumbnailSourceAllowed($source)) { return ''; }
    $params = [];
    foreach ($options as $key => $value) {
        if (!in_array($key, ['w', 'h', 'aoe', 'far', 'f', 'q', 'zc'], true) || !is_scalar($value)) { throw new InvalidArgumentException('Invalid thumbnail option'); }
        $params[$key] = $value;
    }
    $params['src'] = $source;
    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    return '/phpThumb/phpThumb.php?' . $query . '&hash=' . hash_hmac('sha256', $query, siteThumbnailKey());
}
