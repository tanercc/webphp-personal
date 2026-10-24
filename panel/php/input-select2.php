<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__ . '/../include/output.php';

include_once "include/db_names.php";

isset($inputFieldKey) ?: $inputFieldKey = 'select_id';
isset($inputFieldTable) ?: $inputFieldTable = null;
isset($inputFieldValue) ?: $inputFieldValue = isset($rowData[$inputFieldKey]) ? $rowData[$inputFieldKey] : '';
isset($filterFieldName) ?: $filterFieldName = null;

if ($inputFieldTable && $row = dbGetTableRow($inputFieldTable, $inputFieldValue)) {
  $inputFieldData = $row['name'];
} else {
  $inputFieldData = 'Seçiniz...';
}

if (isset($inputFieldKey)) { panelFieldKey($inputFieldKey); }

?>

<div class="form-group">
  <label for="<?= $inputFieldKey ?>"><?= panelHtml(dbName($inputFieldKey)) ?></label>
  <select class="form-control" style="width: 100%;" id="<?= $inputFieldKey ?>" name="<?= $inputFieldKey ?>">

    <?php if ($inputFieldValue > 0) : ?>
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
        url: "/panel/api/select2",
        dataType: 'json',
        data: function(params) {
          ajaxData = {
            table: <?= panelJson($inputFieldTable) ?>,
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

          if (<?= panelJson($siteRequest) ?> == <?= panelJson($inputFieldTable) ?>) {
            // console.log(data);
            var removeIndex = data.map(function(item) {
              return item.id;
            }).indexOf(<?= panelJson((string) $siteRequestId) ?>);
            if (removeIndex >= 0) { data.splice(removeIndex, 1); }
          }
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
