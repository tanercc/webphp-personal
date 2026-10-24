<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__ . '/../include/output.php';

include_once "include/db_names.php";

$inputFieldKey = 'last_login';
isset($inputFieldValue) ?: $inputFieldValue = isset($rowData[$inputFieldKey]) ? $rowData[$inputFieldKey] : '';

if (isset($inputFieldKey)) { panelFieldKey($inputFieldKey); }

?>

<div class="form-group">
  <label for="<?= $inputFieldKey ?>"><?= panelHtml(dbName($inputFieldKey)) ?></label>
  <input type="text" class="form-control" placeholder="<?= panelHtml(dbName($inputFieldKey)) ?>" value="<?= panelHtml($inputFieldValue) ?>" disabled>
</div>
<!-- /.form-group -->

<?php

$inputFieldKey = null;
$inputFieldValue = null;
