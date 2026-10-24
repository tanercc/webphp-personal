<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__.'/login_security.php';
function checkLogin():bool {
 global $conn,$sitePermissions;
 if(!panelCsrfValid($_SERVER,$_POST)){http_response_code(403);return false;}
 if(!in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true)){return false;}
 $input=$_POST;$input['g-recaptcha-response']='local-only-demo';
 return panelAuthenticate($input,static fn($token)=>$token==='local-only-demo',static fn($email)=>panelFindLoginUser($conn,$email),
  array_values(array_diff(array_keys($sitePermissions),['guest'])),static fn($email)=>panelReserveLoginAttempt((getenv('PERSONAL_RATE_FILE')?:dirname(__DIR__,2).'/storage/login-rate.json'),$_SERVER['REMOTE_ADDR'],$email,time()));
}
