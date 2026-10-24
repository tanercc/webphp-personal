<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');

if ($_SESSION["userType"] != 'admin' && $_SESSION["userId"] != $siteRequestId) {
  include "content-403.php";
  return;
}

$_SESSION['RF']["subfolder"] = "./";

$typeFields = dbGetTableFields('users');

if ($siteRequestId > 0 && $rowData = dbGetTableRow($siteRequest, $siteRequestId)) {

  $imageFolder = "$siteRequest/$siteRequestId";
  $_SESSION['RF']["subfolder"] = $imageFolder;
  if (!file_exists("$upload_folder/$imageFolder")) {
    mkdir("$upload_folder/$imageFolder", 0777, true);
  }

  $requestMethod = "PUT";
  $requestUrl = "/panel/api/$siteRequest/$siteRequestId";
} else {

  foreach ($typeFields as $field) {
    $rowData[$field['Field']] = $field['Default'] ?? '';
  }

  $requestMethod = "POST";
  $requestUrl = "/panel/api/$siteRequest";
}

?>

<!-- Content Header (Page header) -->
<section class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1>Kullanıcı</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="<?= panelHtml(url('/')) ?>">Anasayfa</a></li>
          <li class="breadcrumb-item active"><a href="<?= panelHtml(url('users')) ?>">Kullanıcılar</a></li>
        </ol>
      </div>
    </div>
  </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">
  <div class="container-fluid">
    <div class="row">
      <!-- left column -->
      <div class="col-lg-8 mx-lg-auto">
        <!-- general form elements -->
        <div class="card card-primary">
          <div class="card-header">
            <h3 class="card-title">Kullanıcı <?= ($siteRequestId > 0) ? 'Düzenle' : 'Ekle' ?></h3>
          </div>
          <!-- /.card-header -->


          <!-- form start -->
          <form role="form" id="edit-form">
            <div class="card-body">

              <div class="save-button d-flex">
                <button type="submit" id="submit" class="btn btn-primary ml-auto">Kaydet</button>
              </div>

              <?php if (isset($rowData['id'])) : ?>
                <input type="hidden" name="id" value="<?= panelHtml($rowData['id']) ?>">
              <?php endif ?>

              <?php

              foreach ($typeFields as $field) {

                if ($siteRequestId <= 0 && in_array($field['Field'], ['icon'])) continue;

                if (file_exists("php/input-$field[Field].php")) {

                  include "php/input-$field[Field].php";
                } else if ($field['Field'] == 'type_id') {

                  $inputFieldKey = 'type_id';
                  $inputFieldTypes = dbGetTableData('usertypes');
                  include "php/input-select.php";
                } else {

                  $pos = strpos($field['Type'], '(');
                  $fieldType = ($pos !== false) ? substr($field['Type'], 0, $pos) : $field['Type'];

                  if (file_exists("php/input-$fieldType.php")) {

                    $inputFieldKey = $field['Field'];
                    include "php/input-$fieldType.php";
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

        </div>
        <!-- /.card -->
      </div>
    </div>
  </div>
</section>
<!-- /.content -->

<?php if ($siteRequestLang == '') : ?>

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
            window.location = "<?= url($siteRequest) ?>/" + data.id;
          }
        },
        error: function(xhr, textStatus, errorThrown) {
          console.log('xhr: ', xhr);
          console.log('errorThrown: ', errorThrown);
        }
      });

    });
  </script>

<?php endif ?>
