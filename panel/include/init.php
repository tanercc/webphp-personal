<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');

//=======================================================
// session variables
//=======================================================
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
panelStartSession();

// session activity (sec)
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $maxlifetime)) {
  session_unset();     // unset $_SESSION variable
}
$_SESSION['LAST_ACTIVITY'] = time(); // update last activity time stamp

if (!isset($_SESSION['CREATED'])) {
  $_SESSION['CREATED'] = time();
} else if (time() - $_SESSION['CREATED'] > $maxlifetime) {
  // session started more than $maxlifetime sec ago
  session_regenerate_id(true);    // change session ID for the current session and invalidate old session ID
  $_SESSION['CREATED'] = time();  // update creation time
}

// set session user parameters
isset($_SESSION['userId']) ?: $_SESSION['userId'] = 0;
isset($_SESSION['userName']) ?: $_SESSION['userName'] = 'Guest';
isset($_SESSION['userType']) ?: $_SESSION['userType'] = 'guest';
isset($_SESSION['userAuthorized']) ?: $_SESSION['userAuthorized'] = false;

//=======================================================
// Custom exception handler
//=======================================================
require_once __DIR__ . '/exception.php';

//=======================================================
// Parse URL
//=======================================================

// http://site.com/panel/content/id
$request_params = getUrlParams();
$siteRequest = !empty($request_params[0]) ? $request_params[0] : '';
$siteRequestSlug = !empty($request_params[1]) ? $request_params[1] : '';
$siteRequestLang = !empty($request_params[2]) ? $request_params[2] : '';

if ($siteRequest == 'logout') {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !panelCsrfValid($_SERVER, $_POST)) {
    http_response_code(403);
    exit('Forbidden');
  }
  // reset user parameters
  session_unset();
  session_regenerate_id(true);
  $_SESSION['userId'] = 0;
  $_SESSION['userName'] = 'Guest';
  $_SESSION['userType'] = 'guest';
  $_SESSION['userImage'] = '/panel/img/brand.svg';
  $_SESSION['userAuthorized'] = false;
  $siteRequest = '';
  $siteRequestSlug = '';
  header('Location: /panel/', true, 303);
  exit;
}

panelCsrfToken();

$siteRequestId = is_numeric($siteRequestSlug) ? intval($siteRequestSlug) : 0;
if (empty($siteRequestId) && $siteRequestSlug != "new") $siteRequestSlug = '';

//=======================================================
// Global Variables
//=======================================================
$siteLanguages = dbGetLanguages();

// TEST TEST TEST
// $_SESSION['userId'] = 1;
// $_SESSION['userName'] = 'XXX';
// $_SESSION['userType'] = 'admin';
// $_SESSION['userAuthorized'] = true;

//=======================================================
// Check user permission
//=======================================================
if (!isUserAllowed($siteRequest)) {
  $siteRequest = '';
  $siteRequestSlug = '';
}
