<?php
define('BASEPATH', '/panel/api');
require __DIR__ . '/api_functions.php';
require __DIR__ . '/../include/config.php';
require __DIR__ . '/../include/permissions.php';
require __DIR__ . '/security.php';
require_once __DIR__ . '/../include/session.php';
require_once __DIR__ . '/../include/csrf.php';
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

try {
    panelStartSession();
    $request_params = getUrlParams();
    $apiRequest = $request_params[0] ?? '';
    $rawId = $request_params[1] ?? '';
    if (count($request_params) > 2) { throw new RuntimeException('Resource not found', 404); }
    $apiRequestId = $rawId === '' ? 0 : apiPositiveId($rawId);
    $method = $_SERVER['REQUEST_METHOD'];
    apiAuthorize($apiRequest, $method, $apiRequestId, $_SESSION, $sitePermissions);
    $_SESSION['LAST_ACTIVITY'] = time();
    if ($method === 'HEAD') { http_response_code(200); exit; }

    $apiInput = $_POST;
    if ($method === 'PUT') { parse_str(file_get_contents('php://input'), $apiInput); }
    panelRequireCsrf($method, $_SERVER, $apiInput);
    unset($apiInput['csrf_token']);
    require __DIR__ . '/../include/db_functions.php';
    require __DIR__ . '/database.php';

    if ($apiRequest === 'lang') {
        $table = apiTranslationTable($apiInput['lang_table'] ?? null, $_SESSION, $sitePermissions);
        $id = $method === 'PUT' ? apiPositiveId($apiInput['id'] ?? null) : 0;
        unset($apiInput['lang_table']);
        $apiInput = apiValidateFields($table, $apiInput, apiColumnNames($table), $method === 'PUT', $id);
        if ($method === 'POST') {
            apiPositiveId($apiInput['row_id'] ?? null);
            apiPositiveId($apiInput['lang_id'] ?? null);
        }
        $apiInput['lang_table'] = $table;
        if ($id) { $apiInput['id'] = (string) $id; }
    } elseif ($apiRequest === 'select2') {
        $table = apiLookupTable($apiInput['table'] ?? null, $_SESSION, $sitePermissions);
        unset($apiInput['table']);
        $apiInput = apiValidateOptions($table, $apiInput, apiColumnNames($table));
        $apiInput['table'] = $table;
    } elseif ($apiRequest === 'selectfile') {
        $apiInput = ['data_id' => (string) apiPositiveId($apiInput['data_id'] ?? null)];
    } else {
        $table = $apiRequest === 'self' ? 'users' : $apiRequest;
        $columns = apiColumnNames($table);
        $_GET = apiValidateOptions($table, $_GET, $columns);
        if (in_array($method, ['PUT', 'POST'], true)) {
            if ($method === 'PUT' && $apiRequest !== 'self') { apiPositiveId($apiRequestId); }
            $id = $apiRequest === 'self' ? (int) $_SESSION['userId'] : $apiRequestId;
            $apiInput = apiValidateFields($apiRequest, $apiInput, $columns, $method === 'PUT', $id);
            if ($apiInput === []) { throw new RuntimeException('No writable fields', 422); }
        }
        if ($method === 'DELETE') { apiPositiveId($apiRequestId); }
    }
    $_POST = $apiInput;
    $handlers = ['lang', 'select2', 'selectfile', 'self', 'users', 'variables'];
    require __DIR__ . '/' . (in_array($apiRequest, $handlers, true) ? $apiRequest : 'request') . '.php';
} catch (Throwable $error) {
    $status = $error instanceof RuntimeException && in_array($error->getCode(), [401, 403, 404, 405, 422], true)
        ? $error->getCode() : 500;
    http_response_code($status);
    echo json_encode(['error' => $status === 500 ? 'Request failed' : $error->getMessage()]);
}
