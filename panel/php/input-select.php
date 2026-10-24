<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__ . '/../include/output.php';

include_once "include/db_names.php";

isset($inputFieldKey) ?: $inputFieldKey = 'select_id';
isset($inputFieldTypes) ?: $inputFieldTypes = [];
isset($inputFieldValue) ?: $inputFieldValue = isset($rowData[$inputFieldKey]) ? $rowData[$inputFieldKey] : '';

if (isset($inputFieldKey)) { panelFieldKey($inputFieldKey); }

?>

<div class="form-group">
  <label for="<?= $inputFieldKey ?>"><?= panelHtml(dbName($inputFieldKey)) ?></label>
  <select class="form-control custom-select" style="width: 100%;" name="<?= $inputFieldKey ?>" <?= $inputFieldKey=='type_id' ? 'required' : '' ?>>

    <?php if($inputFieldKey!='type_id'): ?>
      <option selected disabled>Seçiniz...</option>
      <option value="NULL">Hiçbiri</option>
    <?php endif ?>

    <?php foreach ($inputFieldTypes as $type) : ?>

      <option value="<?= panelHtml($type['id']) ?>" <?= $type['id'] == $inputFieldValue ? 'selected' : '' ?>>
        <?= panelHtml($type['name']) ?>
      </option>

    <?php endforeach ?>

  </select>
</div>
<!-- /.form-group -->

<?php

$inputFieldKey = null;
$inputFieldTypes = null;
$inputFieldValue = null;
