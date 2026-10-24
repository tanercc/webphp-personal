<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__ . '/../include/output.php';

include_once "include/db_names.php";

isset($inputFieldKey) ?: $inputFieldKey = 'file';
isset($inputFieldValue) ?: $inputFieldValue = isset($rowData[$inputFieldKey]) ? $rowData[$inputFieldKey] : '';

if (isset($inputFieldKey)) { panelFieldKey($inputFieldKey); }

?>

<div class="form-group">
  <label for="<?= $inputFieldKey ?>"><?= panelHtml(dbName($inputFieldKey)) ?></label>
  <div class="input-group">
    <div class="input-group-prepend">
      <a data-filemanager href="javascript:;" data-src="/panel/plugins/filemanager/dialog.php?type=2&fldr=/&field_id=<?= $inputFieldKey ?>" data-type="iframe" class="btn btn-outline-secondary" type="button">
        Dosya Seç
      </a>
    </div>
    <input type="text" class="form-control" id="<?= $inputFieldKey ?>" name="<?= $inputFieldKey ?>" placeholder="<?= panelHtml(dbName($inputFieldKey)) ?>" maxlength="250" value="<?= panelHtml($inputFieldValue) ?>" readonly>
    <div class="input-group-append">
      <button class="btn btn-outline-danger" type="button" onclick="remove_<?= $inputFieldKey ?>()">
        Sil
      </button>
    </div>
  </div>
</div>
<!-- /.form-group -->

<script>
  function remove_<?= $inputFieldKey ?>() {
    var elem = document.getElementById('<?= $inputFieldKey ?>');
    var formGroup = $(elem).closest(".form-group");
    $(formGroup).find("input").val('');
  }
</script>

<?php

$inputFieldKey = null;
$inputFieldValue = null;
