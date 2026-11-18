<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** Schema definition (database/schema.php), loaded once. No database dependency. */
function panelSchema(): array
{
    static $schema = null;
    return $schema ??= require __DIR__ . '/../../database/schema.php';
}

/** Validates write values against the schema so SQLite and MySQL accept and reject the same input. */
function panelValidateWrite(string $table, array $fields): array
{
    $columns = panelSchema()['tables'][$table]['columns'] ?? [];
    $result = [];
    foreach ($fields as $name => $value) {
        $name = (string) $name;
        if ($name === 'id') { $result[$name] = $value; continue; }
        $column = $columns[$name] ?? null;
        if ($column === null) { throw new RuntimeException('Invalid value: ' . $name, 422); }
        $type = $column['type'];
        $nullable = $column['nullable'] ?? true;
        $text = $value === null ? null : (is_scalar($value) ? (string) $value : false);
        if ($text === false) { throw new RuntimeException('Invalid value: ' . $name, 422); }
        $number = $type === 'int' || $type === 'tinyint';
        if ($text === null || ($text === '' && $number)) {
            if (!$nullable) { throw new RuntimeException('Invalid value: ' . $name, 422); }
            $result[$name] = $number ? null : $value;
            continue;
        }
        $valid = match ($type) {
            'int' => preg_match('/^-?\d+$/D', $text) === 1 && (int) $text >= -2147483648 && (int) $text <= 2147483647 && strlen(ltrim($text, '-')) < 12,
            'tinyint' => preg_match('/^-?\d+$/D', $text) === 1 && strlen(ltrim($text, '-')) < 6 && (int) $text >= -128 && (int) $text <= 127,
            'varchar' => mb_strlen($text, 'UTF-8') <= (int) $column['length'],
            'text' => strlen($text) <= 16777215,
            default => true,
        };
        if (!$valid) { throw new RuntimeException('Invalid value: ' . $name, 422); }
        $result[$name] = $value;
    }
    return $result;
}
