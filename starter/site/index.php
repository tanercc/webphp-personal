<?php
require __DIR__ . '/app/bootstrap.php';
$path = trim(rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/'), '/');
$slug = $path === '' ? 'home' : $path;
$page = preg_match('/^[a-z0-9-]+$/D', $slug) ? sitePage($slug) : [];
if (!$page) { http_response_code(404); $page = ['title' => 'Sayfa bulunamadı', 'content' => '<p>Aradığınız sayfa bulunamadı.</p>']; }
require __DIR__ . '/templates/page.php';
