<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__ . '/../include/output.php';

include_once "include/db_names.php";

isset($inputFieldKey) ?: $inputFieldKey = 'image';
isset($inputFieldValue) ?: $inputFieldValue = isset($rowData[$inputFieldKey]) ? $rowData[$inputFieldKey] : '';

if (isset($inputFieldKey)) { panelFieldKey($inputFieldKey); }

?>

<div class="form-group">
  <label for="<?= $inputFieldKey ?>"><?= panelHtml(dbName($inputFieldKey)) ?></label>
  <div class="d-block text-left">
    <img class="img-thumbnail w-25 mb-3" src="<?= panelHtml(panelMediaUrl($inputFieldValue)) ?>" alt style="display: <?= $inputFieldValue ? 'inline-block' : 'none' ?>">
  </div>
  <div class="input-group">
    <div class="input-group-prepend">
      <a data-filemanager href="javascript:;" data-src="/panel/plugins/filemanager/dialog.php?type=1&fldr=/&field_id=<?= $inputFieldKey ?>" data-type="iframe" class="btn btn-outline-secondary" type="button">
        Resim Seç
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
    $(formGroup).find("img").attr("src", '').hide();
    $(formGroup).find("input").val('');
  }

  if (typeof responsive_filemanager_callback != 'function') {
    window.responsive_filemanager_callback = function(field_id) {

      var fieldStr = field_id.replace(/[0-9]/g, '');
      var elem = document.getElementById(field_id);

      if (["image", "icon"].includes(fieldStr)) {
        $(elem).closest(".form-group").find("img").attr("src", elem.value).show();
      }
    };
  }
</script>

<?php

$inputFieldKey = null;
$inputFieldValue = null;
