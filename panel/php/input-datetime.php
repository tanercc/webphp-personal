<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__ . '/../include/output.php';

include_once "include/db_names.php";

isset($inputFieldKey) ?: $inputFieldKey = 'datetime';
isset($inputFieldValue) ?: $inputFieldValue = isset($rowData[$inputFieldKey]) ? $rowData[$inputFieldKey] : NULL;

if (isset($inputFieldKey)) { panelFieldKey($inputFieldKey); }

?>

<div class="form-group">
  <label for="<?= $inputFieldKey ?>"><?= panelHtml(dbName($inputFieldKey)) ?></label>
  <div class="input-group date" id="<?= $inputFieldKey ?>_date" data-target-input="nearest">
    <input type="text" class="form-control datetimepicker-input" id="<?= $inputFieldKey ?>" name="<?= $inputFieldKey ?>" value="<?= panelDateValue($inputFieldValue) ?>" data-target="#<?= $inputFieldKey ?>_date" />
    <div class="input-group-append" data-target="#<?= $inputFieldKey ?>_date" data-toggle="datetimepicker">
      <div class="input-group-text"><i class="fa fa-calendar"></i></div>
    </div>
  </div>
</div>
<!-- /.form group -->

<script type="text/javascript">
  $(function() {
    $('#<?= $inputFieldKey ?>_date').datetimepicker({
      locale: 'tr',
      format: 'L',
      //format: 'LT', time only
    });
  });
</script>

<?php

$inputFieldKey = null;
$inputFieldValue = null;
