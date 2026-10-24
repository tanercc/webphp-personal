<?php
defined('BASEPATH') or exit('No direct script access allowed');

function panelHtml($value): string
{
    if ($value !== null && !is_scalar($value)) { return ''; }
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function panelJson($value): string
{
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
}

function panelFieldKey(string $value): string
{
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $value)) {
        throw new InvalidArgumentException('Invalid form field identifier');
    }
    return $value;
}

function panelMediaUrl($value): string
{
    if ($value !== null && !is_scalar($value)) { return ''; }
    $url = trim((string) ($value ?? ''));
    if (preg_match('/[\x00-\x1f\x7f\\\\]/', $url) || str_starts_with($url, '//')) { return ''; }
    if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $url, $match) && !in_array(strtolower($match[1]), ['http', 'https'], true)) { return ''; }
    return $url;
}

function panelFilePreviewUrl($id, string $filename): string
{
    if (!is_int($id) && !is_string($id)) { return ''; }
    if (!ctype_digit((string) $id) || (int) $id < 1 || strpbrk($filename, "/\\\0") !== false) { return ''; }
    return '/uploads/files/datas/' . (int) $id . '/' . rawurlencode($filename);
}

function panelDateValue($value): string
{
    if (!is_string($value) || trim($value) === '' || $value === '0') { return ''; }
    $parsed = date_parse($value);
    if ($parsed['error_count'] || $parsed['warning_count']) { return ''; }
    $timestamp = strtotime($value);
    return $timestamp === false ? '' : date('d.m.Y', $timestamp);
}
