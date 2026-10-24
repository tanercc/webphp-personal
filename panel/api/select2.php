<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');

switch ($_SERVER["REQUEST_METHOD"]) {
  case "POST":
    //Select2 List
    $fields = ['id', 'name'];
    $options = [];
    $tableName = '';
    foreach ($_POST as $key => $value) {
      if ($key == 'table' && is_string($value)) {
        $tableName = $value;
        unset($_POST[$key]);
      } else {
        $options[$key] = (string) $value;
      }
    }

    if (!$tableName) {
      http_response_code(406);
      exit('Parametre eksik!');
    }

    $result = apiDbGetTableData($tableName, $fields, $options);


    array_unshift($result, ['id' => 0, 'name' => 'Hiçbiri']);

    foreach ($result as $i => $r) {
      $result[$i]['text'] = $result[$i]['name'];
      unset($result[$i]['name']);
    }

    http_response_code(200);
    exit(json_encode($result));
    break;

  default:
    http_response_code(400);
    exit('Bad request!');
}
