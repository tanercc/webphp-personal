<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');

$_SESSION['RF']["subfolder"] = "./";

$typeFields = dbGetTableFields('datas');

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
        <h1>Veri</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="<?= panelHtml(url('/')) ?>">Anasayfa</a></li>
          <li class="breadcrumb-item active"><a href="<?= panelHtml(url("datas?type_id=$rowData[type_id]")) ?>">Veriler</a></li>
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
        <div class="card card-primary card-tabs">
          <div class="card-header p-0 pt-1">
            <ul class="nav nav-tabs" id="custom-tabs-two-tab" role="tablist">
              <li class="pt-2 px-3">
                <h3 class="card-title">Veri <?= ($siteRequestId > 0) ? 'Düzenle' : 'Ekle' ?></h3>
              </li>

              <li class="nav-item">
                <a class="nav-link <?= $siteRequestLang == '' ? 'active' : '' ?>" href="<?= panelHtml(url("$siteRequest/$siteRequestId")) ?>" role="tab">Genel</a>
              </li>

              <?php if ($siteRequestId) : ?>

                <?php foreach ($siteLanguages as $lang) : ?>
                  <li class="nav-item">
                    <a class="nav-link <?= $siteRequestLang == $lang['slug'] ? 'active' : '' ?>" href="<?= panelHtml(url("$siteRequest/$siteRequestId/$lang[slug]")) ?>" role="tab"><?= panelHtml($lang['name']) ?></a>
                  </li>
                <?php endforeach ?>

                <li class="px-1 ml-auto">
                  <a href="<?= panelHtml(url("$siteRequest/new")) ?>" class="btn btn-success btm-sm">
                    <i class="fas fa-plus"></i> Yeni Ekle
                  </a>
                </li>
              <?php endif ?>
            </ul>
          </div>
          <!-- /.card-header -->

          <?php if ($siteRequestLang != '') : ?>

            <?php

            $langTable = "data_lang";
            include "php/block-lang.php";

            ?>
          <?php else : ?>
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

                include "php/input-name.php";

                include "php/input-slug.php";

                $inputFieldKey = 'type_id';
                $inputFieldTypes = dbGetTableData('datatypes');
                include "php/input-select.php";

                $inputFieldKey = 'info';
                if (in_array('template_id', array_column($typeFields, 'Field'), true)) {
                  include "php/input-template_id.php";
                }
                include "php/input-varchar.php";

                if ($siteRequestId > 0) {

                  $inputFieldKey = 'icon';
                  include "php/input-image.php";

                  $inputFieldKey = 'image';
                  include "php/input-image.php";

                  include "php/input-file.php";
                }

                $inputFieldKey = 'video';
                include "php/input-varchar.php";

                $inputFieldKey = 'link';
                include "php/input-varchar.php";

                $inputFieldKey = 'writer';
                include "php/input-varchar.php";

                $inputFieldKey = 'write_at';
                include "php/input-datetime.php";

                $inputFieldKey = 'coordinate';
                include "php/input-varchar.php";

                $inputFieldKey = 'include';
                include "php/input-varchar.php";

                $inputFieldKey = 'cat_id';
                $inputFieldTypes = dbGetTableData('cats');
                include "php/input-select.php";

                $inputFieldKey = 'parent_id';
                $inputFieldTable = 'datas';
                include "php/input-select2.php";

                include 'php/input-custom-fields.php';
                $inputFieldKey = 'sort_order';
                include "php/input-int.php";

                include "php/input-is_active.php";
                ?>

              </div>
              <!-- /.card-body -->

              <div class="card-footer d-flex">
                <button type="submit" id="submit" class="btn btn-primary ml-auto">Kaydet</button>
              </div>
              <!-- /.card-footer -->

            </form>

          <?php endif ?>

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
