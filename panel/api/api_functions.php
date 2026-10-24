<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');

function getUrlParams()
{
  $base = trim(BASEPATH, '/');
  $uri = $_SERVER['REQUEST_URI'];

  $request = explode('?', $uri)[0];
  $request = trim($request, '/');
  $request = preg_replace('/' . preg_quote($base, '/') . '/', '', $request, 1);
  return explode("/", trim($request, '/'));
}

function paramPostGet($var, $default = '')
{
  global $conn;
  if (isset($_POST[$var])) {
    return strval($_POST[$var]);
  } else {
    if (isset($_GET[$var])) {
      return strval($_GET[$var]);
    } else {
      return $default;
    }
  }
}
function paramGetPost($var, $default = '')
{
  global $conn;
  if (isset($_GET[$var])) {
    return strval($_GET[$var]);
  } else {
    if (isset($_POST[$var])) {
      return strval($_POST[$var]);
    } else {
      return $default;
    }
  }
}
function paramGet($var, $default = '')
{
  global $conn;
  if (isset($_GET[$var])) {
    return strval($_GET[$var]);
  } else {
    return $default;
  }
}
function paramPost($var, $default = '')
{
  global $conn;
  if (isset($_POST[$var])) {
    return strval($_POST[$var]);
  } else {
    return $default;
  }
}

//=======================================================
// get Client IP
//=======================================================
function getClientIp()
{
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}

function getImages($folder, $id, $selectedImage = '')
{
  $path = "../../uploads/files/$folder/$id/";
  $ignore = ['.', '..'];
  $result = [];

  if (is_dir($path)) {
    $dh = opendir($path);
    while (false !== ($file = readdir($dh))) {
      if (!in_array($file, $ignore)) {
        if (
          !is_dir($path . $file)
          && ($selectedImage) != urldecode('/' . $path . $file)
        ) {
          $result[] = $file;
        }
      }
    }
  }

  sort($result);

  return $result;
}
