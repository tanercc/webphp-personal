<?php
defined('BASEPATH') or exit('No direct script access allowed');

function panelCsrfToken(): string
{
    if (!is_string($_SESSION['csrfToken'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $_SESSION['csrfToken'])) {
        panelCsrfRotate();
    }
    return $_SESSION['csrfToken'];
}

function panelCsrfRotate(): void
{
    $_SESSION['csrfToken'] = bin2hex(random_bytes(32));
}

function panelRedactAuthFields(array $values): array
{
    foreach ($values as $key => &$value) {
        if (is_string($key) && preg_match('/password|secret|token|recaptcha/i', $key)) {
            $value = '[redacted]';
        } elseif (is_array($value)) {
            $value = panelRedactAuthFields($value);
        }
    }
    unset($value);
    return $values;
}

function panelCsrfValid(array $server, array $input): bool
{
    $expected = $_SESSION['csrfToken'] ?? null;
    $received = array_key_exists('HTTP_X_CSRF_TOKEN', $server) ? $server['HTTP_X_CSRF_TOKEN'] : ($input['csrf_token'] ?? null);
    return is_string($expected) && strlen($expected) === 64 && is_string($received)
        && strlen($received) === 64 && hash_equals($expected, $received);
}

function panelRequireCsrf(string $method, array $server, array $input): void
{
    if (!in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true) && !panelCsrfValid($server, $input)) {
        throw new RuntimeException('Invalid CSRF token; reload the page', 403);
    }
}

function panelRequireMediaCsrf(string $entry, string $method, array $server, array $input, array $query): void
{
    if (!in_array($method, ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        throw new RuntimeException('Unsupported media method', 403);
    }
    if ($method !== 'GET' && $method !== 'HEAD') {
        panelRequireCsrf($method, $server, $input);
        return;
    }
    if (in_array($entry, ['upload.php', 'execute.php', 'force_download.php'], true)) {
        throw new RuntimeException('Media operation requires POST', 403);
    }
    if ($entry === 'ajax_calls.php') {
        $action = $query['action'] ?? null;
        $readOnly = in_array($action, ['media_preview', 'cad_preview', 'get_lang', 'new_file_form'], true)
            || ($action === 'get_file' && ($query['sub_action'] ?? null) === 'preview');
        if (!$readOnly && !panelCsrfValid($server, $input)) {
            throw new RuntimeException('Invalid CSRF token; reload the page', 403);
        }
    }
}
