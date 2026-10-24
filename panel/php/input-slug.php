<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__ . '/../include/output.php';

isset($inputFieldValue) ?: $inputFieldValue = isset($rowData['slug']) ? $rowData['slug'] : '';

if (isset($inputFieldKey)) { panelFieldKey($inputFieldKey); }

?>

<div class="form-group">
  <label for="slug">Url</label>
  <input type="text" class="form-control" id="slug" name="slug" placeholder="Url" minlength="3" maxlength="250" value="<?= panelHtml($inputFieldValue) ?>" required readonly>
</div>
<!-- /.form-group -->

<script>
  $("#name").change(function() {
    var stripped = this.value.replace(/<[^>]*>?/gm, '');
    var value = URLify(stripped);
    $('#slug').val(value);
  });
</script>

<?php

$inputFieldValue = null;
