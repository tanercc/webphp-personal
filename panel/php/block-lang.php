<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');

$siteLangId = array_search($siteRequestLang, array_column($siteLanguages, 'slug', 'id'));
$siteLangId ?: reset($siteLanguages)['id'];

if (!$siteRequestId && !$siteLangId && !isset($langTable)) return;

$typeFields = dbGetTableFields($langTable);

if ($rowData = dbGetTableLangRow($langTable, $siteRequestId, $siteLangId)) {

  $requestMethod = "PUT";
  $requestUrl = "/panel/api/lang";
} else {

  foreach ($typeFields as $field) {
    $rowData[$field['Field']] = '';
  }

  $requestMethod = "POST";
  $requestUrl = "/panel/api/lang";
}

?>

<!-- form start -->
<form role="form" id="edit-form">
  <div class="card-body">

    <input type="hidden" name="lang_table" value="<?= panelHtml($langTable) ?>">
    <?php if (isset($rowData['id']) && intval($rowData['id'])) : ?>
      <input type="hidden" name="id" value="<?= panelHtml($rowData['id']) ?>">
    <?php else : ?>
      <input type="hidden" name="row_id" value="<?= panelHtml($siteRequestId) ?>">
      <input type="hidden" name="lang_id" value="<?= panelHtml($siteLangId) ?>">
    <?php endif ?>

    <div class="save-button d-flex">
      <button type="submit" id="submit" class="btn btn-primary ml-auto">Kaydet</button>
    </div>

    <?php

    foreach ($typeFields as $field) {

      if (file_exists("php/input-$field[Field].php")) {

        $inputFieldKey = $field['Field'];
        include "input-$field[Field].php";
      } else {

        $pos = strpos($field['Type'], '(');
        $fieldType = ($pos !== false) ? substr($field['Type'], 0, $pos) : $field['Type'];

        if (file_exists("php/input-$fieldType.php")) {

          $inputFieldKey = $field['Field'];
          include "input-$fieldType.php";
        }
      }
    } ?>

  </div>
  <!-- /.card-body -->

  <div class="card-footer d-flex">
    <button type="submit" id="submit" class="btn btn-primary ml-auto">Kaydet</button>
  </div>
  <!-- /.card-footer -->

</form>

<script>
  $('#edit-form').submit(function(event) {
    event.preventDefault();
    var form = this;
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    if (CKEDITOR.instances.content) {
      $('input[name=content]').val(CKEDITOR.instances.content.getData());
    }

    $.ajax({
      type: <?= panelJson($requestMethod) ?>,
      url: <?= panelJson($requestUrl) ?>,
      data: $(form).serialize(),
      beforeSend: function() {
        waitingDialog.show("Kaydediliyor", {
          dialogSize: "sm",
          progressType: "warning"
        });
      },
      complete: function() {
        waitingDialog.hide();
      },
      success: function(data) {
        if (data.id > 0) {
          window.location = <?= panelJson(url("$siteRequest/$siteRequestId")) ?>;
        }
      },
      error: function(xhr, textStatus, errorThrown) {
        console.log('xhr: ', xhr);
        console.log('errorThrown: ', errorThrown);
      }
    });

  });
</script>
