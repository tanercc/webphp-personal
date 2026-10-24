<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__ . '/../include/output.php';

include_once "include/db_names.php";

isset($inputFieldKey) ?: $inputFieldKey = 'varchar';
isset($inputFieldValue) ?: $inputFieldValue = isset($rowData[$inputFieldKey]) ? $rowData[$inputFieldKey] : '';

if (isset($inputFieldKey)) { panelFieldKey($inputFieldKey); }

?>

<div class="form-group">
  <label for="<?= $inputFieldKey ?>"><?= panelHtml(dbName($inputFieldKey)) ?></label>
  <input type="text" class="form-control" id="<?= $inputFieldKey ?>" name="<?= $inputFieldKey ?>" placeholder="<?= panelHtml(dbName($inputFieldKey)) ?>" maxlength="250" value="<?= panelHtml($inputFieldValue) ?>">
</div>
<!-- /.form-group -->

<?php

$inputFieldKey = null;
$inputFieldValue = null;
