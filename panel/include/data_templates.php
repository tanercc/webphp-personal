<?php
defined('BASEPATH') or exit('No direct script access allowed');

function dataTemplateRegistry(): array
{
    $file = __DIR__ . '/../../config/data-templates.php';
    $registry = is_file($file) ? require $file : [];
    foreach ($registry as $key => $entry) {
        if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_-]*$/D', $key)
            || !is_array($entry) || !isset($entry['label'], $entry['file'])
            || !is_string($entry['label']) || !is_string($entry['file'])
            || !preg_match('/^content-data-[a-z0-9_-]+\.php$/D', $entry['file'])) {
            throw new RuntimeException('Invalid data template registry');
        }
    }
    return $registry;
}

function dataTemplateKeyValid($key): bool
{
    return is_string($key) && ($key === '' || isset(dataTemplateRegistry()[$key]));
}

function dataTemplateCode(array $row, array $categories, array $templates): string
{
    $categoryId = $row['cat_id'] ?? null;
    $category = is_scalar($categoryId) ? ($categories[$categoryId] ?? []) : [];
    foreach ([$row['template_id'] ?? null, $category['template_id'] ?? null] as $id) {
        if ((!is_int($id) && !is_string($id)) || !ctype_digit((string) $id)) { continue; }
        $template = $templates[$id] ?? [];
        $code = $template['code'] ?? '';
        if ((int) ($template['is_active'] ?? 1) === 1 && (int) ($template['deleted'] ?? 0) === 0
            && is_string($code) && $code !== '' && dataTemplateKeyValid($code)) { return $code; }
    }
    return '';
}

function dataTemplateFile(array $row, ?array $categories = null, ?array $templates = null): string
{
    global $tableCats, $tableTemplates;
    $key = dataTemplateCode($row, $categories ?? $tableCats ?? [], $templates ?? $tableTemplates ?? []);
    if ($key !== '') { return dataTemplateRegistry()[$key]['file']; }
    if (in_array((int) ($row['type_id'] ?? 0), [1, 3], true)) { return 'content-data-slider.php'; }
    if (!empty($row['cat_id'])) { return 'content-data-cat.php'; }
    return 'content-data-default.php';
}
