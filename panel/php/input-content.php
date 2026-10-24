<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__ . '/../include/output.php';

include_once "include/db_names.php";

isset($inputFieldKey) ?: $inputFieldKey = 'content';
isset($inputFieldValue) ?: $inputFieldValue = isset($rowData[$inputFieldKey]) ? $rowData[$inputFieldKey] : '';

if (isset($inputFieldKey)) { panelFieldKey($inputFieldKey); }

?>

<div class="form-group">
  <label for="<?= $inputFieldKey ?>"><?= panelHtml(dbName($inputFieldKey)) ?></label>
  <textarea class="form-control" id="<?= $inputFieldKey ?>" rows="10"><?= panelHtml($inputFieldValue) ?></textarea>
  <input type="hidden" name="<?= $inputFieldKey ?>" value="">
</div>
<!-- /.form-group -->

<script>
  CKEDITOR.replace('content', {
    // Keep unused CDATA/full-page and remote version-notification paths disabled.
    fullPage: false,
    disallowedContent: 'script;style',
    versionCheck: false,
    placeholder: "İçerik ...",
    filebrowserBrowseUrl: '/panel/plugins/filemanager/dialog.php?type=2&editor=ckeditor&fldr=/',
    filebrowserImageBrowseUrl: '/panel/plugins/filemanager/dialog.php?type=1&editor=ckeditor&fldr=/'
  });
</script>

<?php

$inputFieldKey = null;
$inputFieldValue = null;
