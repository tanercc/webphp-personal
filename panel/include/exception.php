<?php
defined('BASEPATH') or exit('No direct script access allowed');

function panelHandleFailure(string $type, int $code, string $file, int $line): never
{
    $event = bin2hex(random_bytes(8));
    $metadata = ['event' => $event, 'type' => $type, 'code' => $code, 'file' => basename($file), 'line' => $line];
    // Message/trace/request/session bodies may contain SQL, credentials or content.
    // Keep diagnostic identity only, including when the database itself is down.
    $record = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    try {
        if (!function_exists('dbWriteError')) { throw new RuntimeException('No database logger'); }
        dbWriteError($type, $code, 'Event ' . $event, basename($file), basename($_SERVER['SCRIPT_FILENAME'] ?? ''), $line, '', '', '', '');
    } catch (Throwable $ignored) {
        @error_log('CMS_ERROR ' . $record);
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store');
    }
    echo 'An error occurred. Reference: ' . $event;
    exit;
}

function siteException($exception)
{
    panelHandleFailure('exception', (int) $exception->getCode(), $exception->getFile(), $exception->getLine());
}
function siteError($errno, $errstr, $errfile, $errline)
{
    if (!(error_reporting() & $errno)) { return false; }
    // Let the active exception boundary handle failures inside the logger.
    throw new ErrorException('Application error', 0, $errno, $errfile, $errline);
}
ini_set('display_errors', '0');
set_exception_handler('siteException');
set_error_handler('siteError');
