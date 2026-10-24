<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__ . '/../include/output.php';

include_once "include/db_names.php";

isset($inputFieldKey) ?: $inputFieldKey = 'filename';
isset($filterFieldName) ?: $filterFieldName = null;
isset($inputFieldValue) ?: $inputFieldValue = isset($rowData[$inputFieldKey]) ? $rowData[$inputFieldKey] : '';

if (!empty($inputFieldValue)) {
  $inputFieldData = $inputFieldValue;
} else {
  $inputFieldData = 'Seçiniz...';
}

if (isset($inputFieldKey)) { panelFieldKey($inputFieldKey); }

?>

<div class="form-group">
  <label for="<?= $inputFieldKey ?>"><?= panelHtml(dbName($inputFieldKey)) ?></label>

  <div class="d-block text-left">
    <img class="img-thumbnail w-25 mb-3" src="<?= panelHtml(panelFilePreviewUrl($rowData['data_id'] ?? 0, (string) $inputFieldValue)) ?>" alt style="display: <?= $inputFieldValue ? 'inline-block' : 'none' ?>">
  </div>

  <select class="form-control" style="width: 100%;" id="<?= $inputFieldKey ?>" name="<?= $inputFieldKey ?>">

    <?php if ($inputFieldValue != '') : ?>
      <option value="<?= panelHtml($inputFieldValue) ?>" selected><?= panelHtml($inputFieldData) ?></option>
    <?php endif ?>

  </select>
</div>
<!-- /.form-group -->

<script>
  $(function() {
    $("#<?= $inputFieldKey ?>").select2({
      theme: 'bootstrap4',
      placeholder: 'Seçiniz...',
      ajax: {
        type: "POST",
        url: "/panel/api/selectfile",
        dataType: 'json',
        data: function(params) {
          ajaxData = {
            q: params.term,
            page: params.page
          };
          var filterName = <?= panelJson($filterFieldName) ?>;
          if (filterName) {
            ajaxData[filterName] = $(document.getElementsByName(filterName)).val();
          }
          return ajaxData;
        },
        processResults: function(data, params) {
          params.page = params.page || 1;

          return {
            results: data,
            pagination: {
              more: (params.page * 10) < data.total
            }
          };
        }
      }
    });
  });
</script>

<?php

$inputFieldKey = null;
$inputFieldTypes = null;
$inputFieldValue = null;
