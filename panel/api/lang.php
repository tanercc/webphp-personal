<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');

switch ($_SERVER["REQUEST_METHOD"]) {
  case "POST":
    //insert
    $fields = [];
    $tableName = '';
    foreach ($_POST as $key => $value) {
      if ($key == 'lang_table' && is_string($value)) {
        $tableName = $value;
        unset($_POST[$key]);
      } else {
        $fields[$key] = (string) $value;
      }
    }

    $result = apiDbInsertTableRow($tableName, $fields);

    if ($result) {
      http_response_code(200);
      exit(json_encode(['id' => $result]));
    } else {
      http_response_code(404);
      exit('Error!');
    }
    break;

  case "PUT":
    //update
    $_PUT = $apiInput;

    $fields = [];
    $tableName = '';
    $updateId = '';
    foreach ($_PUT as $key => $value) {
      if ($key == 'lang_table' && is_string($value)) {
        $tableName = $value;
        unset($_PUT[$key]);
      } elseif ($key == 'id' && intval($value)) {
        $updateId = $value;
        unset($_PUT[$key]);
      } else {
        $fields[$key] = (string) $value;
      }
    }

    if (apiDbUpdateTableRow($tableName, $updateId, $fields)) {
      http_response_code(200);
      exit(json_encode(['id' => $updateId]));
    } else {
      http_response_code(404);
      exit('Error!');
    }
    break;

  default:
    http_response_code(400);
    exit('Bad request!');
}
