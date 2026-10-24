<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');

switch ($_SERVER["REQUEST_METHOD"]) {
  case "HEAD":
    http_response_code(200);

  case "GET":

    $options = [];
    foreach ($_GET as $key => $value) {
      if ($value !== '') { $options[$key] = (string) $value; }
    }

    $result = apiDbGetVariablesData($options);
    $rowCount = apiDbGetFoundRows();

    foreach ($result as $i => $row) {
      if (isset($row['is_active'])) $result[$i]['is_active'] = (bool)$row['is_active'];
    }

    http_response_code(200);
    exit(json_encode(['data' => array_values($result), 'itemsCount' => $rowCount]));

    break;

  case "POST":
    //insert

    $fields = [];
    foreach ($_POST as $key => $value) {
      $fields[$key] = (string) $value;
    }

    $result = apiDbInsertTableRow($apiRequest, $fields);

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
    foreach ($_PUT as $key => $value) {
      $fields[$key] = (string) $value;
    }

    if (apiDbUpdateTableRow($apiRequest, $apiRequestId, $fields)) {
      http_response_code(200);
      exit(json_encode(['id' => $apiRequestId]));
    } else {
      http_response_code(404);
      exit('Error!');
    }
    break;

  case "DELETE":
    // DELETE
    parse_str(file_get_contents("php://input"), $_DELETE);

    if (apiDbDeleteTableRow($apiRequest, $apiRequestId)) {
      http_response_code(200);
      exit(json_encode(['id' => 0]));
    } else {
      http_response_code(404);
      exit('Error!');
    }
    break;
  default:
    http_response_code(400);
    exit('Bad request!');
}
