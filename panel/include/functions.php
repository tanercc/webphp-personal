<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/output.php';

//=======================================================
// error dump end die
//=======================================================
function dd($dump)
{
  echo '<pre style="background-color:orange">';
  print_r($dump);
  echo '</pre>';
  die;
}

//=======================================================
// error dump and die
//=======================================================
function du($dump)
{
  echo '<pre style="background-color:pink">';
  var_dump($dump);
  echo '</pre>';
  die;
}

//=======================================================
// Parse URL from REQUEST_URI
//=======================================================
function getUrlParams()
{
  $base = trim(BASEPATH, '/');
  $uri = $_SERVER['REQUEST_URI'];

  $request = explode('?', $uri)[0];
  $request = trim($request, '/');
  $request = preg_replace('/' . preg_quote($base, '/') . '/', '', $request, 1);
  return explode('/', trim($request, '/'));
}

//=======================================================
// generate URL
//=======================================================
function url($url)
{
  return rtrim(preg_replace('#/+#', '/', '/' . BASEPATH . '/' . $url), '/') ?: '/';
}

//=======================================================
// select content-*.php file
//=======================================================
function getSiteContent($dir)
{
  global $siteRequest;
  global $siteRequestSlug;

  $content = !empty($siteRequest) ? $siteRequest : 'dashboard';
  $content .= !empty($siteRequestSlug) ? '-id' : '';
  return file_exists($dir . "/content-$content.php") ? "content-$content.php" : "content-404.php";
}

//=======================================================
// get Client IP
//=======================================================
function getClientIp()
{
  if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    return $_SERVER['HTTP_CLIENT_IP'];
  } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    return $_SERVER['HTTP_X_FORWARDED_FOR'];
  } else {
    return $_SERVER['REMOTE_ADDR'];
  }
}
