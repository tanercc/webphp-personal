<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');

if (($_SESSION['userAuthorized'] ?? false) !== true || empty($_SESSION['userId'])) {
  http_response_code(403);
  exit('Forbidden!');
}

switch ($_SERVER["REQUEST_METHOD"]) {

  case "PUT":
    //update
    $_PUT = $apiInput;

    $fields = [];
    $keys = ['name', 'email', 'password', 'image'];
    foreach ($_PUT as $key => $value) {
      if (in_array($key, $keys)) {
        $fields[$key] = ($key == 'password') ?
          password_hash(trim($value), PASSWORD_BCRYPT) :
          (string) $value;
      }
    }

    if (apiDbUpdateTableRow('users', $_SESSION["userId"], $fields)) {
      http_response_code(200);
      exit(json_encode(['id' => $apiRequestId]));
    } else {
      http_response_code(404);
      exit('Error!');
    }

    break;

  default:
    http_response_code(400);
    exit('Bad request!');
}
