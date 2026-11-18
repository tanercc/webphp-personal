<?php
defined('BASEPATH') or exit('No direct script access allowed');

function apiDbTable(string $table): string
{
    $extra = ['page_lang', 'slider_lang', 'menu_lang', 'data_lang', 'datatype_lang',
        'cat_lang', 'tag_lang', 'variable_lang', 'langs', 'usertypes', 'media', 'media_lang'];
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
    apiDbTable($table);
    foreach ($conn->columns($table) as $column) {
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
        $foreign = isset($options['catTable']) ? 'cat_id' : 'type_id';
        if (!in_array($foreign, $columns, true)) { throw new RuntimeException('Invalid relation', 422); }
        $select .= ',(SELECT r.name FROM ' . apiDbTable($relation) . " r WHERE r.id=t.`$foreign`) AS type";
    }
    $where = [];
    $values = [];
    if (in_array('deleted', $columns, true)) { $where[] = 't.deleted = ?'; $values[] = $options['deleted'] ?? 0; }
    foreach ($options as $key => $value) {
        if ($key === 'q') {
            if (!in_array('name', $columns, true)) { throw new RuntimeException('Invalid query field', 422); }
            $where[] = "t.`name` LIKE ? ESCAPE '!'";
            $values[] = dbLikePrefix((string) $value);
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
    $definition = panelSchema()['tables'][$table] ?? [];
    // Empty slugs are stored as NULL so the unique slug rules only apply to real values.
    if (array_key_exists('slug', $fields) && $fields['slug'] === '') { $fields['slug'] = null; }
    // Foreign keys: report a missing target as 422 instead of letting the engine fail the write.
    foreach ($definition['foreign'] ?? [] as $fk) {
        $column = $fk['column'];
        if ($column === 'image_id' || !array_key_exists($column, $fields) || $fields[$column] === null) { continue; }
        if (!apiDbQuery('SELECT id FROM ' . apiDbTable($fk['table']) . ' WHERE id=?', [(int) $fields[$column]])->fetch_assoc()) {
            throw new RuntimeException('Invalid value: ' . $column, 422);
        }
    }
    $current = null;
    $merged = static function () use (&$current, $fields, $update, $table, $id): array {
        $current ??= $update ? apiDbGetTableRow($table, $id) : [];
        return array_replace($current, $fields);
    };
    foreach ($definition['unique'] ?? [] as $columns) {
        if (!array_intersect($columns, array_keys($fields)) || array_diff($columns, apiDbColumns($table))) { continue; }
        $row = $merged();
        $values = array_map(static fn ($column) => $row[$column] ?? null, $columns);
        if (in_array(null, $values, true) || in_array('', $values, true)) { continue; }
        $where = implode(' AND ', array_map(static fn ($column) => "`$column`=?", $columns));
        if (apiDbQuery('SELECT id FROM ' . apiDbTable($table) . " WHERE $where AND id<>?", [...$values, $id])->fetch_assoc()) {
            throw new RuntimeException('Invalid value: ' . end($columns), 422);
        }
    }
    // A menu item points to at most one record (a free `link` may accompany it).
    $targets = ['page_id', 'data_id', 'datatype_id', 'cat_id', 'tag_id'];
    if ($table === 'menus' && array_intersect($targets, array_keys($fields))) {
        $row = $merged();
        if (count(array_filter($targets, static fn ($column) => !empty($row[$column]))) > 1) {
            throw new RuntimeException('Invalid value: menu target', 422);
        }
    }
    // A main image must be an active media row owned by this same record; a new record has none yet.
    if (array_key_exists('image_id', $fields) && $fields['image_id'] !== null && (!$update
        || !apiDbQuery('SELECT id FROM media WHERE id=? AND owner_table=? AND owner_id=? AND deleted=0 AND is_active=1', [(int) $fields['image_id'], $table, $id])->fetch_assoc())) {
        throw new RuntimeException('Invalid value: image_id', 422);
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
    if (in_array('created_at', apiDbColumns($table), true)) { $fields['created_at'] = date('Y-m-d H:i:s'); }
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

/** Parses `tag_ids` ("1,4,7" or empty) into existing, non-deleted tag ids. */
function apiDbTagIds($value): array
{
    if (!is_string($value) || strlen($value) > 2000) { throw new RuntimeException('Invalid value: tag_ids', 422); }
    $ids = $value === '' ? [] : array_values(array_unique(array_map(static function ($id) {
        if (!preg_match('/^[1-9][0-9]{0,9}$/D', $id)) { throw new RuntimeException('Invalid value: tag_ids', 422); }
        return (int) $id;
    }, explode(',', $value))));
    if (count($ids) > 100) { throw new RuntimeException('Invalid value: tag_ids', 422); }
    if ($ids) {
        $found = apiDbQuery('SELECT COUNT(*) AS n FROM tags WHERE deleted=0 AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids)->fetch_assoc();
        if ((int) $found['n'] !== count($ids)) { throw new RuntimeException('Invalid value: tag_ids', 422); }
    }
    return $ids;
}

/** Replaces the tag set of one data record. */
function apiDbSyncDataTags(int $dataId, array $tagIds): void
{
    global $conn;
    if (!apiDbQuery('SELECT id FROM datas WHERE id=? AND deleted=0', [$dataId])->fetch_assoc()) { throw new RuntimeException('Resource not found', 404); }
    $conn->begin_transaction();
    try {
        apiDbQuery('DELETE FROM data_tags WHERE data_id=?', [$dataId]);
        $now = date('Y-m-d H:i:s');
        foreach ($tagIds as $tagId) { apiDbQuery('INSERT INTO data_tags (data_id, tag_id, created_at) VALUES (?, ?, ?)', [$dataId, $tagId, $now]); }
        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }
}
