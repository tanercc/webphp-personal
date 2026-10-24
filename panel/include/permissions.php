<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');

//=======================================================
// permission list using usertypes
//=======================================================

$sitePermissions = [
  'admin' => [
    'media',
    //site and api necessary routes
    'dashboard',
    'messages',
    'logout',
    'lang',
    'select2',
    'selectfile',

    //page
    'pages',
    'pagetypes',
    //slider
    'sliders',
    'slidertypes',
    //menu
    'menus',
    'menutypes',
    'menusort',
    //data
    'datas',
    'datatypes',
    'templates',
    'cats',
    'tags',
    //setting
    'users',
    'variables',
    'captions',
    'self',
  ],
  'user' => [
    'media',
    //site and api necessary routes
    'dashboard',
    'messages',
    'logout',
    'lang',
    'select2',

    //page
    'pages',
    //slider
    'sliders',
    //menu
    'menus',
    'menusort',
    //data
    'datas',
    'cats',
    'tags',
    //setting
    'self',
  ],
  'guest' => [
    //site and api necessary routes
    'login',
  ],
];

//=======================================================
// single or array, return contents are in permission
//=======================================================
function isUserAllowed($contents)
{
  global $sitePermissions;

  is_array($contents) ?: $contents = [$contents];
  $permissions = is_array($sitePermissions[$_SESSION['userType']]) ? $sitePermissions[$_SESSION['userType']] : [];

  $allowed = array_intersect($contents, $permissions);

  return boolval(count($allowed));
}
