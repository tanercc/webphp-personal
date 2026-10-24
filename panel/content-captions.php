<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');

$datas = dbGetTableData('datas');

$selectedData = $_GET['data_id'] ?? null;

?>

<!-- Content Header (Page header) -->
<section class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1>Resim Başlıkları</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="<?= panelHtml(url('/')) ?>">Anasayfa</a></li>
          <li class="breadcrumb-item active">Başlıklar</li>
        </ol>
      </div>
    </div>
  </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">
  <div class="card h-100">
    <div class="card-header d-flex align-items-center">
      <h3 class="card-title">Başlık Listesi</h3>
      <select id="data" class="custom-select w-50 ml-auto">
        <option value="">Hepsi</option>
        <?php foreach ($datas as $data) : ?>
          <option value="<?= panelHtml($data['id']) ?>" <?= $data['id'] == $selectedData ? 'selected' : '' ?>>
            <?= panelHtml($data['name']) ?>
          </option>
        <?php endforeach ?>
      </select>
      <input id="search" class="form-control w-25 ml-auto" type=text placeholder="Arama...">
      <a href="<?= panelHtml(url("$siteRequest/new")) ?>" class="btn btn-success btm-sm ml-2">
        <i class="fas fa-plus"></i> Ekle
      </a>
    </div>
    <!-- /.card-header -->
    <div class="card-body">
      <div id="jsGrid"></div>
    </div>
    <!-- /.card-body -->
  </div>
  <!-- /.card -->
</section>
<!-- /.content -->

<!-- page script -->
<script>
  $(function() {

    $.ajax({
      type: "HEAD",
      url: "/panel/api/<?= $siteRequest ?>"
    }).done(function() {

      $("#jsGrid").jsGrid({
        width: "100%",
        editing: false,
        inserting: false,
        sorting: true,
        paging: true,
        autoload: true,
        pageLoading: true,
        pageSize: 10,
        pageButtonCount: 5,
        deleteConfirm: "Silmek istiyor musunuz?",
        controller: {
          loadData: function(filter) {
            filter.typeTable = 'datas';
            filter.data_id = <?= panelJson($selectedData) ?>;
            filter.q = $("input#search").val();
            return $.ajax({
              type: "GET",
              url: "/panel/api/<?= $siteRequest ?>",
              data: filter,
            });
          },
          deleteItem: function(item) {
            return $.ajax({
              type: "DELETE",
              url: "/panel/api/<?= $siteRequest ?>/" + item.id,
              data: item
            });
          }
        },
        rowClick: function(args) {
          var data = args.item;
        },
        editItem: function(item) {
          location.replace("/panel/<?= $siteRequest ?>/" + item.id);
        },
        fields: [{
            name: "id",
            title: "ID",
            type: "number",
            width: 20
          }, {
            name: "filename",
            title: "FileName",
            type: "text",
            width: 100
          }, {
            name: "type",
            title: "Tip",
            type: "text",
            width: 100
          }, {
            name: "is_active",
            title: "Aktifmi",
            type: "checkbox",
            width: 50,
            sorting: false,
            filtering: false
          },
          {
            type: "control"
          }
        ]
      });
    });

    $("input#search").on("input", function() {
      $("#jsGrid").jsGrid("loadData");
    });

    $("select#data").change(function() {
      location.replace("/panel/<?= $siteRequest ?>?data_id=" + this.value);
    });

  });
</script>

<?php

include "php/block-type.php";

?>
