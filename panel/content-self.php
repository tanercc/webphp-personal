<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');

if (!$_SESSION["userId"]) {
  include "content-403.php";
  return;
}

$_SESSION['RF']["subfolder"] = "./";

$rowData = dbGetTableRow('users', $_SESSION["userId"]);;

$imageFolder = "users/$_SESSION[userId]";
$_SESSION['RF']["subfolder"] = $imageFolder;
if (!file_exists("$upload_folder/$imageFolder")) {
  mkdir("$upload_folder/$imageFolder", 0777, true);
}

$requestMethod = "PUT";
$requestUrl = "/panel/api/self/$_SESSION[userId]";

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
        <div class="card card-warning">
          <div class="card-header">
            <h3 class="card-title">Kullanıcı Düzenle</h3>
          </div>
          <!-- /.card-header -->


          <!-- form start -->
          <form role="form" id="edit-form">
            <div class="card-body">

              <input type="hidden" name="id" value="<?= panelHtml($rowData['id']) ?>">

              <?php

              include "php/input-name.php";

              include "php/input-email.php";

              include "php/input-password.php";

              $inputFieldKey = 'image';
              include "php/input-image.php";

              ?>

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
            window.location = <?= panelJson(url($siteRequest)) ?>;
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
