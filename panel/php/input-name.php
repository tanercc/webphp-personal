<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__ . '/../include/output.php';

$required = isset($requestUrl) && strpos($requestUrl, 'lang') === false ? true : false;
isset($inputFieldValue) ?: $inputFieldValue = isset($rowData['name']) ? $rowData['name'] : '';

if (isset($inputFieldKey)) { panelFieldKey($inputFieldKey); }

?>

<div class="form-group">
  <label for="name">Ad</label>
  <input type="text" class="form-control" id="name" name="name" placeholder="Ad" minlength="1" maxlength="512" value="<?= panelHtml($inputFieldValue) ?>" <?= $required ? 'required' : '' ?>>
</div>
<!-- /.form-group -->

<?php

$inputFieldValue = null;
