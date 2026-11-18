<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__ . '/../include/session.php';
require_once __DIR__ . '/../include/data_templates.php';
require_once __DIR__ . '/../include/db_schema.php';

function apiResources(): array
{
    return ['pages', 'pagetypes', 'sliders', 'slidertypes', 'menus', 'menutypes',
        'datas', 'datatypes', 'cats', 'tags', 'users', 'variables', 'templates'];
}

function apiColumnNames(string $table): array
{
    return apiDbColumns($table);
}

function apiPositiveId($value): int
{
    if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]*$/D', (string) $value)
        || filter_var($value, FILTER_VALIDATE_INT) === false) {
        throw new RuntimeException('Invalid identifier', 422);
    }
    return (int) $value;
}

function apiAuthorize(string $route, string $method, int $id, array $session, array $permissions): void
{
    $last = $session['LAST_ACTIVITY'] ?? null;
    if (($session['userAuthorized'] ?? false) !== true || !is_int($last) || $last > time()
        || time() - $last > panelSessionLifetime() || !is_scalar($session['userId'] ?? null)
        || !filter_var($session['userId'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
        throw new RuntimeException('Authentication required', 401);
    }
    $special = ['lang' => ['POST', 'PUT'], 'select2' => ['POST'], 'self' => ['PUT'], 'media' => ['GET', 'POST', 'PUT', 'DELETE']];
    if (!in_array($route, apiResources(), true) && !isset($special[$route])) {
        throw new RuntimeException('Resource not found', 404);
    }
    $role = $session['userType'] ?? null;
    if (!is_string($role) || !in_array($route, $permissions[$role] ?? [], true)) {
        throw new RuntimeException('Forbidden', 403);
    }
    if (!in_array($method, $special[$route] ?? ['HEAD', 'GET', 'POST', 'PUT', 'DELETE'], true)) {
        throw new RuntimeException('Method not allowed', 405);
    }
    if ($route === 'self' && $id !== 0 && $id !== (int) $session['userId']) {
        throw new RuntimeException('Forbidden', 403);
    }
}

function apiTranslationTable($table, array $session, array $permissions): string
{
    $map = ['page_lang' => 'pages', 'slider_lang' => 'sliders', 'menu_lang' => 'menus',
        'data_lang' => 'datas', 'datatype_lang' => 'datatypes', 'cat_lang' => 'cats',
        'tag_lang' => 'tags', 'variable_lang' => 'variables'];
    $role = $session['userType'] ?? '';
    if (!is_string($table) || !isset($map[$table]) || !in_array($map[$table], $permissions[$role] ?? [], true)) {
        throw new RuntimeException('Forbidden translation resource', 403);
    }
    return $table;
}

function apiLookupTable($table, array $session, array $permissions): string
{
    $relations = ['pages' => ['pagetypes'], 'sliders' => ['slidertypes'],
        'menus' => ['menutypes', 'pages', 'datas', 'datatypes', 'cats', 'tags'],
        'datas' => ['datatypes', 'datas', 'templates'], 'cats' => ['datatypes', 'cats', 'templates'],
        'tags' => ['cats'], 'users' => ['usertypes']];
    $allowed = ['langs'];
    foreach ($permissions[$session['userType'] ?? ''] ?? [] as $resource) {
        if (in_array($resource, apiResources(), true)) {
            $allowed[] = $resource;
            $allowed = array_merge($allowed, $relations[$resource] ?? []);
        }
    }
    if (!is_string($table) || !in_array($table, $allowed, true)) {
        throw new RuntimeException('Forbidden lookup resource', 403);
    }
    return $table;
}

function apiWritableFields(string $table): array
{
    $common = ['name', 'is_active', 'sort_order'];
    $fields = [
        'pages' => ['slug', 'type_id', 'info', 'icon', 'image_id', 'video', 'link', 'include'],
        'pagetypes' => ['template'], 'sliders' => ['type_id', 'info', 'image_id'], 'slidertypes' => [],
        'menus' => ['type_id', 'image_id', 'link', 'parent_id', 'page_id', 'data_id', 'datatype_id', 'cat_id', 'tag_id'],
        'menutypes' => [],
        'datas' => ['slug', 'type_id', 'info', 'icon', 'image_id', 'video', 'link', 'writer', 'write_at', 'coordinate', 'include', 'cat_id', 'parent_id',
            'column_1', 'column_2', 'column_3', 'column_4', 'column_5', 'column_6', 'column_7', 'column_8', 'column_9', 'template_id'],
        'templates' => ['code'], 'datatypes' => ['slug'], 'cats' => ['slug', 'type_id', 'image_id', 'link', 'parent_id', 'template_id'],
        'tags' => ['slug', 'type_id', 'icon', 'image_id', 'cat_id'],
        'variables' => [], 'users' => ['email', 'password', 'image_id', 'type_id', 'lang_id'],
    ];
    if ($table === 'self') { return ['name', 'email', 'password', 'image_id']; }
    if (in_array($table, ['page_lang', 'data_lang', 'cat_lang'], true)) {
        return ['name', 'slug', 'title', 'content', 'row_id', 'lang_id'];
    }
    $translations = ['slider_lang' => ['name', 'title', 'content'],
        'menu_lang' => ['name', 'link', 'title', 'is_active'],
        'datatype_lang' => ['name', 'slug'], 'tag_lang' => ['name', 'slug'],
        'variable_lang' => ['name']];
    if (isset($translations[$table])) { return array_merge($translations[$table], ['row_id', 'lang_id']); }
    if (!isset($fields[$table])) { throw new RuntimeException('Resource not found', 404); }
    return array_merge($common, $fields[$table]);
}

function apiValidateFields(string $table, array $input, array $columns, bool $update, int $id): array
{
    $allowed = array_intersect(apiWritableFields($table), $columns);
    $fields = [];
    foreach ($input as $key => $value) {
        if ($key === 'id') {
            if ($value !== '' && $value !== '0' && $value !== 0 && apiPositiveId($value) !== $id) {
                throw new RuntimeException('Mismatched identifier', 422);
            }
            continue;
        }
        if (!in_array($key, $allowed, true) || ($update && in_array($key, ['row_id', 'lang_id'], true) && str_ends_with($table, '_lang'))
            || (!is_scalar($value) && $value !== null)) {
            throw new RuntimeException('Invalid field', 422);
        }
        // Existing handlers expect strings. Normalize here to avoid array/null errors.
        $value = $value === null ? '' : (string) $value;
        if ($table === 'templates' && $key === 'code' && ($value === '' || !dataTemplateKeyValid($value))) { throw new RuntimeException('Invalid template', 422); }
        if (str_ends_with($key, '_id') && $value === 'NULL') { $value = ''; }
        if (str_ends_with($key, '_id') && $value !== '' && $value !== '0') { apiPositiveId($value); }
        if ($key === 'is_active' && !in_array($value, ['0', '1'], true)) {
            throw new RuntimeException('Invalid boolean', 422);
        }
        if ($key === 'sort_order' && filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new RuntimeException('Invalid sort order', 422);
        }
        $fields[$key] = $value;
    }
    return panelValidateWrite($table === 'self' ? 'users' : $table, $fields);
}

function apiValidateOptions(string $table, array $options, array $columns): array
{
    $relations = ['datas' => ['typeTable' => 'datatypes'], 'menus' => ['typeTable' => 'menutypes'],
        'sliders' => ['typeTable' => 'slidertypes'], 'users' => ['typeTable' => 'usertypes'],
        'tags' => ['catTable' => 'cats']];
    $result = [];
    $filterable = array_diff($columns, ['password', 'created_at', 'updated_at']);
    foreach ($options as $key => $value) {
        if (!is_scalar($value) && $value !== null) { throw new RuntimeException('Invalid query value', 422); }
        if ($value === '' || $value === null) { continue; }
        $value = (string) $value;
        if ($key === 'sortField') {
            if (!in_array($value, $filterable, true) && !($value === 'type' && isset($relations[$table]))) {
                throw new RuntimeException('Invalid sort field', 422);
            }
        } elseif ($key === 'sortOrder') {
            $value = strtoupper($value);
            if (!in_array($value, ['ASC', 'DESC'], true)) { throw new RuntimeException('Invalid sort direction', 422); }
        } elseif (in_array($key, ['pageIndex', 'pageSize', 'page'], true)) {
            $number = apiPositiveId($value);
            if (($key === 'pageSize' && $number > 100) || $number > 1000000) { throw new RuntimeException('Invalid pagination', 422); }
        } elseif (in_array($key, ['typeTable', 'catTable'], true)) {
            if (($relations[$table][$key] ?? null) !== $value) { throw new RuntimeException('Invalid relation', 422); }
        } elseif ($key === 'q') {
            if (strlen($value) > 1000) { throw new RuntimeException('Query too long', 422); }
        } elseif (!in_array($key, $filterable, true)) {
            throw new RuntimeException('Invalid query field', 422);
        }
        $result[$key] = $value;
    }
    return $result;
}

/** Record tables that can own media rows (`media.owner_table`). */
function apiMediaOwnerTables(): array
{
    return ['pages', 'sliders', 'menus', 'datas', 'cats', 'tags', 'users'];
}

/** The caller may manage media of a record they may edit: its resource role, or their own profile via `self`. */
function apiMediaOwnerAllowed(string $table, int $id, array $session, array $permissions): bool
{
    $allowed = $permissions[$session['userType'] ?? ''] ?? [];
    if (!in_array($table, apiMediaOwnerTables(), true)) { return false; }
    return in_array($table, $allowed, true)
        || ($table === 'users' && $id === (int) ($session['userId'] ?? 0) && in_array('self', $allowed, true));
}
