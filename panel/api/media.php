<?php
/** Record media: upload, list, update (order, visibility, per-language title/alt/caption) and soft delete.
 * Files go to uploads/media/<owner_table>/<owner_id>/ under a server-generated name; the media row is authoritative. */
defined('BASEPATH') or exit('No direct script access allowed');

const API_MEDIA_MAX_BYTES = 10 * 1024 * 1024;
const API_MEDIA_TYPES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
    'webp' => 'image/webp', 'gif' => 'image/gif', 'pdf' => 'application/pdf'];

/** Validates the owner reference and the caller's right to edit that record. */
function apiMediaOwner($table, $id, array $session, array $permissions): array
{
    if (!is_string($table) || !in_array($table, apiMediaOwnerTables(), true)) { throw new RuntimeException('Invalid value: owner_table', 422); }
    $id = apiPositiveId($id);
    if (!apiMediaOwnerAllowed($table, $id, $session, $permissions)) { throw new RuntimeException('Forbidden', 403); }
    if (!apiDbQuery('SELECT id FROM ' . apiDbTable($table) . ' WHERE id=? AND deleted=0', [$id])->fetch_assoc()) {
        throw new RuntimeException('Owner not found', 404);
    }
    return [$table, $id];
}

/** Loads a non-deleted media row and checks the caller may edit its owner. */
function apiMediaRow(int $id, array $session, array $permissions): array
{
    $row = apiDbQuery('SELECT * FROM media WHERE id=? AND deleted=0', [apiPositiveId($id)])->fetch_assoc();
    if (!$row) { throw new RuntimeException('Media not found', 404); }
    apiMediaOwner($row['owner_table'], (string) $row['owner_id'], $session, $permissions);
    return $row;
}

function apiMediaList(string $table, int $id): array
{
    $rows = apiDbQuery('SELECT id, path, original_name, mime, size, width, height, is_active, sort_order FROM media'
        . ' WHERE owner_table=? AND owner_id=? AND deleted=0 ORDER BY sort_order, id', [$table, $id])->fetch_all();
    $result = [];
    foreach ($rows as $row) {
        $row = apiDbLegacyRow($row);
        $row['is_active'] = (bool) $row['is_active'];
        $row['lang'] = (object) [];
        $result[$row['id']] = $row;
    }
    if ($result) {
        $marks = implode(',', array_fill(0, count($result), '?'));
        // One query for every translation of the listed rows (no per-row lookups).
        $langs = apiDbQuery("SELECT row_id, lang_id, title, alt, caption FROM media_lang WHERE row_id IN ($marks)", array_map('intval', array_keys($result)))->fetch_all();
        foreach ($langs as $lang) {
            $result[(string) $lang['row_id']]['lang']->{(string) $lang['lang_id']} = [
                'title' => $lang['title'] ?? '', 'alt' => $lang['alt'] ?? '', 'caption' => $lang['caption'] ?? ''];
        }
    }
    return array_values($result);
}

function apiMediaUpload(string $table, int $id, $file): int
{
    if (!is_array($file) || !is_int($file['error'] ?? null) || !is_string($file['tmp_name'] ?? null) || !is_string($file['name'] ?? null)) {
        throw new RuntimeException('Invalid value: file', 422);
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) { throw new RuntimeException('File too large', 422); }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) { throw new RuntimeException('Invalid value: file', 422); }
    $size = filesize($file['tmp_name']);
    if ($size === false || $size < 1) { throw new RuntimeException('Invalid value: file', 422); }
    if ($size > API_MEDIA_MAX_BYTES) { throw new RuntimeException('File too large', 422); }
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mime = API_MEDIA_TYPES[$extension] ?? null;
    $detected = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if ($mime === null || $detected !== $mime) { throw new RuntimeException('Unsupported file type', 422); }
    $width = $height = null;
    if ($mime !== 'application/pdf') {
        $image = @getimagesize($file['tmp_name']);
        if (!$image || ($image['mime'] ?? '') !== $mime || $image[0] < 1 || $image[1] < 1) { throw new RuntimeException('Unsupported file type', 422); }
        [$width, $height] = $image;
    }

    $directory = dirname(__DIR__, 2) . "/uploads/media/$table/$id";
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) { throw new RuntimeException('Cannot store media'); }
    $name = bin2hex(random_bytes(8)) . '.' . ($extension === 'jpeg' ? 'jpg' : $extension);
    if (!move_uploaded_file($file['tmp_name'], "$directory/$name")) { throw new RuntimeException('Cannot store media'); }

    $original = mb_substr(preg_replace('/[\x00-\x1f\x7f]/u', '', basename(str_replace('\\', '/', $file['name']))) ?? '', 0, 255, 'UTF-8');
    $order = (int) apiDbQuery('SELECT COALESCE(MAX(sort_order), 0) AS m FROM media WHERE owner_table=? AND owner_id=?', [$table, $id])->fetch_assoc()['m'] + 10;
    $fields = ['owner_table' => $table, 'owner_id' => $id, 'path' => "/uploads/media/$table/$id/$name",
        'original_name' => $original, 'mime' => $mime, 'size' => $size, 'width' => $width, 'height' => $height, 'sort_order' => $order, 'created_at' => date('Y-m-d H:i:s')];
    try {
        $fields = panelValidateWrite('media', $fields);
        $columns = implode(',', array_map(static fn ($field) => "`$field`", array_keys($fields)));
        return apiDbWrite('media', "INSERT INTO media ($columns) VALUES (" . implode(',', array_fill(0, count($fields), '?')) . ')',
            array_values($fields), array_keys($fields));
    } catch (Throwable $error) {
        @unlink("$directory/$name");
        throw $error;
    }
}

function apiMediaUpdate(array $row, array $input): void
{
    global $conn;
    $media = [];
    foreach (['sort_order', 'is_active'] as $key) {
        if (array_key_exists($key, $input)) { $media[$key] = is_scalar($input[$key]) ? (string) $input[$key] : null; }
    }
    if (isset($media['is_active']) && !in_array($media['is_active'], ['0', '1'], true)) { throw new RuntimeException('Invalid value: is_active', 422); }
    if (array_key_exists('sort_order', $media) && ($media['sort_order'] === null || filter_var($media['sort_order'], FILTER_VALIDATE_INT) === false)) {
        throw new RuntimeException('Invalid value: sort_order', 422);
    }
    $text = array_intersect_key($input, array_flip(['title', 'alt', 'caption']));
    $unknown = array_diff(array_keys($input), ['id', 'sort_order', 'is_active', 'lang_id', 'title', 'alt', 'caption']);
    if ($unknown) { throw new RuntimeException('Invalid field', 422); }
    if (!$media && !$text) { throw new RuntimeException('No writable fields', 422); }
    if (isset($input['id']) && (string) $input['id'] !== (string) $row['id']) { throw new RuntimeException('Mismatched identifier', 422); }

    $lang = null;
    if ($text) {
        $langId = apiPositiveId($input['lang_id'] ?? null);
        if (!apiDbQuery('SELECT id FROM langs WHERE id=? AND deleted=0', [$langId])->fetch_assoc()) { throw new RuntimeException('Invalid value: lang_id', 422); }
        foreach ($text as $key => $value) {
            if (!is_scalar($value) && $value !== null) { throw new RuntimeException('Invalid value: ' . $key, 422); }
            $text[$key] = trim((string) $value);
        }
        $lang = panelValidateWrite('media_lang', ['row_id' => (int) $row['id'], 'lang_id' => $langId] + $text);
    }
    $media = panelValidateWrite('media', $media);

    $conn->begin_transaction();
    try {
        if ($media) {
            apiDbQuery('UPDATE media SET ' . implode(',', array_map(static fn ($field) => "`$field`=?", array_keys($media))) . ' WHERE id=?',
                [...array_values($media), (int) $row['id']]);
        }
        // An inactive row cannot stay the record's main image (record saves would fail validation).
        if (($media['is_active'] ?? null) === '0') {
            apiDbQuery('UPDATE ' . apiDbTable($row['owner_table']) . ' SET image_id=NULL WHERE id=? AND image_id=?', [(int) $row['owner_id'], (int) $row['id']]);
        }
        if ($lang) { $conn->upsert('media_lang', $lang, ['row_id', 'lang_id']); }
        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    if ($apiRequestId) { throw new RuntimeException('Method not allowed', 405); }
    [$ownerTable, $ownerId] = apiMediaOwner($_GET['owner_table'] ?? null, $_GET['owner_id'] ?? null, $_SESSION, $sitePermissions);
    $data = apiMediaList($ownerTable, $ownerId);
    exit(json_encode(['data' => $data, 'itemsCount' => count($data)]));
}
if ($method === 'POST') {
    if ($apiRequestId) { throw new RuntimeException('Method not allowed', 405); }
    [$ownerTable, $ownerId] = apiMediaOwner($apiInput['owner_table'] ?? null, $apiInput['owner_id'] ?? null, $_SESSION, $sitePermissions);
    exit(json_encode(['id' => (string) apiMediaUpload($ownerTable, $ownerId, $_FILES['file'] ?? null)]));
}
$row = apiMediaRow(apiPositiveId((string) $apiRequestId), $_SESSION, $sitePermissions);
if ($method === 'PUT') {
    apiMediaUpdate($row, $apiInput);
    exit(json_encode(['id' => (string) $row['id']]));
}
// DELETE: soft delete; a record whose main image this was loses it. The file stays on disk.
$conn->begin_transaction();
try {
    apiDbQuery('UPDATE media SET deleted=1 WHERE id=?', [(int) $row['id']]);
    apiDbQuery('UPDATE ' . apiDbTable($row['owner_table']) . ' SET image_id=NULL WHERE id=? AND image_id=?', [(int) $row['owner_id'], (int) $row['id']]);
    $conn->commit();
} catch (Throwable $error) {
    $conn->rollback();
    throw $error;
}
exit(json_encode(['id' => '0']));
