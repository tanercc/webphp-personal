<?php
defined('BASEPATH') or exit('No direct script access allowed');

function apiDbTable(string $table): string
{
    $extra = ['page_lang', 'slider_lang', 'menu_lang', 'data_lang', 'datatype_lang',
        'cat_lang', 'tag_lang', 'variable_lang', 'caption_lang', 'langs', 'usertypes'];
    if (!in_array($table, array_merge(apiResources(), $extra), true)) {
        throw new RuntimeException('Resource not found', 404);
    }
    return "`$table`";
}

function apiDbQuery(string $sql, array $values = [])
{
    global $conn;
    $statement = $conn->prepare($sql);
    try {
        $statement->execute($values);
        return $statement->field_count ? $statement->get_result() : true;
    } finally {
        $statement->close();
    }
}

function apiDbColumns(string $table): array
{
    global $conn;
    $columns = [];
    foreach ($conn->query('SHOW COLUMNS FROM ' . apiDbTable($table))->fetch_all(PDO::FETCH_ASSOC) as $column) {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $column['Field'])) { throw new RuntimeException('Unsupported schema field', 500); }
        $columns[] = $column['Field'];
    }
    return $columns;
}

function apiDbGetTableRow(string $table, $id): array
{
    // Never expose password hashes through a direct users read.
    $fields = $table === 'users'
        ? implode(',', array_map(static fn ($field) => "`$field`", array_diff(apiDbColumns($table), ['password']))) : '*';
    $row = apiDbQuery('SELECT ' . $fields . ' FROM ' . apiDbTable($table) . ' WHERE id = ?', [apiPositiveId($id)])->fetch_assoc();
    return $row ? apiDbLegacyRow($row) : [];
}

function apiDbLegacyRow(array $row): array
{
    // Old text-protocol mysqli responses used string IDs; Select2 relies on this.
    return array_map(static fn ($value) => is_int($value) || is_float($value) ? (string) $value : $value, $row);
}

function apiDbGetTableData(string $table, ?array $fields = null, array $options = []): array
{
    global $apiFoundRows;
    $columns = apiDbColumns($table);
    $options = apiValidateOptions($table, $options, $columns);
    $selected = $fields === null ? $columns : array_values(array_unique(array_merge(['id'], array_intersect($fields, $columns))));
    if ($table === 'users') { $selected = array_diff($selected, ['password']); }
    $select = implode(',', array_map(static fn ($field) => "t.`$field`", $selected));
    $relation = $options['typeTable'] ?? $options['catTable'] ?? null;
    if ($relation !== null) {
        $foreign = isset($options['catTable']) ? 'cat_id' : ($table === 'captions' ? 'data_id' : 'type_id');
        if (!in_array($foreign, $columns, true)) { throw new RuntimeException('Invalid relation', 422); }
        $select .= ',(SELECT r.name FROM ' . apiDbTable($relation) . " r WHERE r.id=t.`$foreign`) AS type";
    }
    $where = [];
    $values = [];
    if (in_array('deleted', $columns, true)) { $where[] = 't.deleted = ?'; $values[] = $options['deleted'] ?? 0; }
    foreach ($options as $key => $value) {
        if ($key === 'q') {
            $searchField = in_array('name', $columns, true) ? 'name' : 'filename';
            $where[] = "t.`$searchField` LIKE ?";
            $values[] = $value . '%';
        }
        elseif ($key !== 'deleted' && in_array($key, $columns, true)) { $where[] = "t.`$key` = ?"; $values[] = $value; }
    }
    $from = ' FROM ' . apiDbTable($table) . ' t' . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
    $apiFoundRows = (int) apiDbQuery('SELECT COUNT(*) AS total' . $from, $values)->fetch_assoc()['total'];
    $sort = $options['sortField'] ?? (in_array('sort_order', $columns, true) ? 'sort_order' : 'id');
    if ($sort === 'type' && $relation === null) { throw new RuntimeException('Invalid sort field', 422); }
    $sql = 'SELECT ' . $select . $from . ' ORDER BY ' . ($sort === 'type' ? '`type`' : "t.`$sort`")
        . ' ' . ($options['sortOrder'] ?? 'ASC') . ', t.id';
    if (isset($options['pageSize'])) {
        $size = (int) $options['pageSize'];
        $offset = ((int) ($options['pageIndex'] ?? 1) - 1) * $size;
        $sql .= ' LIMIT ? OFFSET ?';
        $values[] = $size;
        $values[] = $offset;
    }
    $rows = [];
    foreach (apiDbQuery($sql, $values)->fetch_all(PDO::FETCH_ASSOC) as $row) { $rows[$row['id']] = apiDbLegacyRow($row); }
    return $rows;
}

function apiDbGetFoundRows(): int
{
    global $apiFoundRows;
    return $apiFoundRows ?? 0;
}

function apiDbGetVariablesData(array $options = []): array
{
    $rows = apiDbGetTableData('variables', null, $options);
    if (!$rows) { return []; }
    $placeholders = implode(',', array_fill(0, count($rows), '?'));
    $translations = apiDbQuery('SELECT v.row_id, l.slug, v.name FROM variable_lang v
        INNER JOIN langs l ON l.id=v.lang_id AND l.is_active=1 AND l.deleted=0
        WHERE v.row_id IN (' . $placeholders . ')', array_keys($rows));
    $reserved = apiDbColumns('variables');
    foreach ($translations->fetch_all(PDO::FETCH_ASSOC) as $translation) {
        if (!in_array($translation['slug'], $reserved, true)) {
            $rows[$translation['row_id']][$translation['slug']] = $translation['name'];
        }
    }
    return $rows;
}

function apiDbNormalizeFields(string $table, array $fields, bool $update, int $id = 0): array
{
    $fields = apiValidateFields($table, $fields, apiDbColumns($table), $update, $id);
    foreach ($fields as $key => &$value) {
        if (str_ends_with($table, '_lang') && $value === '') { $value = null; }
        if (str_ends_with($key, '_id') && ($value === '' || $value === '0')) { $value = null; }
        if (str_ends_with($key, '_at') && $value === '') { $value = null; }
        if (str_ends_with($key, '_at') && $value !== null) {
            $parsed = date_parse($value);
            if ($parsed['warning_count'] || $parsed['error_count']) { throw new RuntimeException('Invalid date', 422); }
            $time = strtotime($value);
            if ($time === false) { throw new RuntimeException('Invalid date', 422); }
            $value = date('Y-m-d H:i:s', $time);
        }
    }
    unset($value);
    if (in_array($table, ['datas', 'cats'], true) && array_key_exists('template_id', $fields) && $fields['template_id'] !== null) {
        $template = apiDbQuery('SELECT code FROM templates WHERE id=? AND deleted=0 AND is_active=1', [$fields['template_id']])->fetch_assoc();
        if (!$template || !dataTemplateKeyValid($template['code']) || $template['code'] === '') { throw new RuntimeException('Invalid template', 422); }
    }
    if ($table === 'datas' && array_key_exists('cat_id', $fields) && $fields['cat_id'] !== null
        && !apiDbQuery('SELECT id FROM cats WHERE id=? AND deleted=0', [$fields['cat_id']])->fetch_assoc()) { throw new RuntimeException('Invalid category', 422); }
    if ($table === 'datas' && (array_key_exists('template_id', $fields) || array_key_exists('cat_id', $fields))) {
        $current = $update ? apiDbGetTableRow($table, $id) : [];
        $result = array_replace($current, $fields);
        if (!empty($result['template_id']) && empty($result['cat_id'])) {
            $template = apiDbQuery('SELECT code FROM templates WHERE id=?', [$result['template_id']])->fetch_assoc();
            if (($template['code'] ?? '') === 'category') { throw new RuntimeException('Select a category', 422); }
        }
    }
    if ($table === 'templates' && isset($fields['code'])) {
        $existing = apiDbQuery('SELECT id FROM templates WHERE code=? AND id<>?', [$fields['code'], $id])->fetch_assoc();
        if ($existing) { throw new RuntimeException('Template code already exists', 422); }
        // A template is a stable definition: changing its code would silently change every related page.
        if ($update) {
            $currentTemplate = apiDbGetTableRow('templates', $id);
            if (!$currentTemplate) { throw new RuntimeException('Template not found', 404); }
            if ($currentTemplate['code'] !== $fields['code']) { throw new RuntimeException('Template code cannot change', 422); }
        }
    }
    if (!$fields) { throw new RuntimeException('No writable fields', 422); }
    return $fields;
}

function apiDbWrite(string $table, string $sql, array $values, array $fields, int $id = 0): int
{
    global $conn;
    $conn->begin_transaction();
    try {
        apiDbQuery($sql, $values);
        $id = $id ?: (int) $conn->insert_id;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!filter_var($ip, FILTER_VALIDATE_IP)) { $ip = ''; }
        apiDbQuery('INSERT INTO logs (task, comment, user_id, ip_no) VALUES (?, ?, ?, ?)',
            [$table, 'record=' . $id . '; fields=' . implode(',', $fields), (int) ($_SESSION['userId'] ?? 0), $ip]);
        $conn->commit();
        return $id;
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }
}

function apiDbInsertTableRow(string $table, array $fields): int
{
    $fields = apiDbNormalizeFields($table, $fields, false);
    $columns = implode(',', array_map(static fn ($field) => "`$field`", array_keys($fields)));
    $placeholders = implode(',', array_fill(0, count($fields), '?'));
    return apiDbWrite($table, 'INSERT INTO ' . apiDbTable($table) . " ($columns) VALUES ($placeholders)", array_values($fields), array_keys($fields));
}

function apiDbUpdateTableRow(string $table, $id, array $fields): bool
{
    $id = apiPositiveId($id);
    $fields = apiDbNormalizeFields($table, $fields, true, $id);
    $assignments = implode(',', array_map(static fn ($field) => "`$field`=?", array_keys($fields)));
    apiDbWrite($table, 'UPDATE ' . apiDbTable($table) . " SET $assignments WHERE id=?", [...array_values($fields), $id], array_keys($fields), $id);
    return true;
}

function apiDbDeleteTableRow(string $table, $id): bool
{
    $id = apiPositiveId($id);
    apiDbWrite($table, 'UPDATE ' . apiDbTable($table) . ' SET deleted=1 WHERE id=?', [$id], ['deleted'], $id);
    return true;
}
