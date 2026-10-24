<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__ . '/../include/output.php';

isset($inputFieldValue) ?: $inputFieldValue = isset($rowData['is_active']) ? $rowData['is_active'] : '1';

if (isset($inputFieldKey)) { panelFieldKey($inputFieldKey); }

?>

<div class="custom-control custom-checkbox">
  <input type="checkbox" class="custom-control-input" id="is_active" <?= $inputFieldValue ? 'checked' : '' ?> onclick="click_isActive(this)">
  <label class="custom-control-label" for="is_active">Aktif</label>
  <input type="hidden" name="is_active" value="<?= panelHtml($inputFieldValue) ?>">
</div>
<!-- /.card-body -->

<script>
  function click_isActive(elem) {
    var formGroup = $(elem).closest(".custom-control");
    $(formGroup).find("input[name=is_active]").val(elem.checked ? 1 : 0);
  }

  $("input[name=is_active]").change(function() {
    $("#is_active")[0].checked = this.value == "1" ? true : false;
  });
</script>

<?php

$inputFieldValue = null;
