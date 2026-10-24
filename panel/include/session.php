<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__.'/../../config/config.php';

function panelSessionLifetime(): int
{
    global $maxlifetime;
    return is_int($maxlifetime) && $maxlifetime > 0 ? $maxlifetime : 86400;
}

function panelSessionCookieOptions(array $server): array
{
    $https = strtolower((string) ($server['HTTPS'] ?? ''));
    return ['lifetime' => 0, 'path' => '/', 'domain' => '',
        'secure' => ($https !== '' && $https !== 'off') || (int) ($server['SERVER_PORT'] ?? 0) === 443,
        'httponly' => true, 'samesite' => 'Lax'];
}

function panelStartSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) { return; }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string) panelSessionLifetime());
    if (filter_var(ini_get('session.use_cookies'), FILTER_VALIDATE_BOOLEAN)) {
        session_set_cookie_params(panelSessionCookieOptions($_SERVER));
    }
    if (!session_start()) { throw new RuntimeException('Session unavailable', 500); }
}
