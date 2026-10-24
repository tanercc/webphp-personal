<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__.'/../panel/include/exception.php';
error_reporting(E_ALL); ini_set('display_errors','0'); date_default_timezone_set('Europe/Istanbul');
$maxlifetime=86400; $multilanguage=true; $panel_name='Personal CMS '; $panel_logo='brand.svg';
$recaptcha_key=$recaptcha_secret=''; $upload_folder='../uploads/files';
$smtp_host=$smtp_username=$smtp_password=$mail_from_address='';$smtp_port=587;$smtp_auth=true;$smtp_encryption='tls';$mail_from_name='Personal Demo';
// This self-contained demo is intentionally accessible only on this computer.
if (PHP_SAPI!=='cli' && !in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true)) { http_response_code(403);exit('Local demo only'); }
if (session_status()!==PHP_SESSION_ACTIVE) {
 session_name('PERSONALCMS');
 ini_set('session.save_path',getenv('PERSONAL_SESSION_PATH')?:__DIR__.'/../storage/sessions');
}
