<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');

include_once "include/db_names.php";

$typeFields = dbGetTableFields($siteRequest);
$rowData = [];
foreach ($typeFields as $field) {
  $rowData[$field['Field']] = $field['Default'] ?? '';
}

?>

<div class="modal fade" id="type-modal" data-backdrop="static">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title">Default Modal</h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form role="form" id="modal-form">
        <div class="modal-body">
          <!-- form start -->

          <input type="hidden" name="id" value="">

          <?php

          foreach ($typeFields as $field) {

            if (file_exists("php/input-$field[Field].php")) {

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
        <div class="modal-footer justify-content-between">
          <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary"><i calss="fas fa-snipper fa-spin fa-sm" style="display:none"></i> Kaydet </button>
        </div>
      </form>
    </div>
    <!-- /.modal-content -->
  </div>
  <!-- /.modal-dialog -->
</div>
<!-- /.modal -->



<script>
  $("#slug").change(function() {
    var stripped = this.value.replace(/<[^>]*>?/gm, '');
    var value = URLify(stripped);
    $(this).val(value);
  });

  $('#type-modal').on('show.bs.modal', function(event) {
    var id = $('input[name=id]').val();

    var modal = $(this);
    modal.find('.modal-title').text(id ? 'Düzenle' : 'Ekle');
  });

  $('#type-modal').on('hidden.bs.modal', function(event) {
    var form = document.getElementById('modal-form');
    form.reset();
    $('input[name=id]').val('');
  });

  $('#modal-form').submit(function(event) {
    event.preventDefault();
    var form = this;
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    var id = $('input[name=id]').val();

    $.ajax({
      type: id ? "PUT" : "POST",
      url: "/panel/api/<?= $siteRequest ?>" + (id ? "/" + id : ""),
      data: $(form).serialize(),
      beforeSend: function() {
        $('#type-modal').find('button').prop('disabled', true);
        $('button[type=submit]').find('i').show();
      },
      complete: function() {
        $('#type-modal').find('button').prop('disabled', false);
        $('button[type=submit]').find('i').hide();
      },
      success: function(data) {
        form.reset();
        $('#type-modal').modal('show');
        window.location = <?= panelJson(url("$siteRequest")) ?>;
      },
      error: function(xhr, textStatus, errorThrown) {
        console.log('xhr: ', xhr);
        console.log('errorThrown: ', errorThrown);
      }
    });

  });
</script>
