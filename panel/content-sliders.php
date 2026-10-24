<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');

$sliderTypes = dbGetTableData('slidertypes');

$selectedType = $_GET['type_id'] ?? null;

?>

<!-- Content Header (Page header) -->
<section class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1>Sliderlar</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="<?= panelHtml(url('/')) ?>">Anasayfa</a></li>
          <li class="breadcrumb-item active">Sliderlar</li>
        </ol>
      </div>
    </div>
  </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">
  <div class="card h-100">
    <div class="card-header d-flex align-items-center">
      <h3 class="card-title">Slider Listesi</h3>
      <select id="slidertype" class="custom-select w-25 ml-auto">
        <?php foreach ($sliderTypes as $type) : ?>
          <option value="<?= panelHtml($type['id']) ?>" <?= $type['id'] == $selectedType ? 'selected' : '' ?>>
            <?= panelHtml($type['name']) ?>
          </option>
        <?php endforeach ?>
      </select>
      <a href="<?= panelHtml(url("$siteRequest/new") . (!empty($selectedType) ? "?type_id=$selectedType" : '')) ?>" class="btn btn-success btm-sm ml-2">
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
            filter.typeTable = 'slidertypes';
            filter.type_id = <?= panelJson($selectedType) ?>;
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
          name: "name",
          title: "Name",
          type: "text",
          width: 150
        }, {
          name: "type",
          title: "Tip",
          type: "text",
          width: 100
        }, {
          name: "info",
          title: "Info",
          type: "text",
          width: 200
        }, {
          name: "image",
          title: "Image",
          type: "text",
          width: 150,
          filtering: false
        }, {
          name: "is_active",
          title: "Aktifmi",
          type: "checkbox",
          width: 50,
          sorting: false,
          filtering: false
        }, {
          name: "sort_order",
          type: "number",
          title: "Sıra",
          width: 30,
          filtering: false
        }, {
          type: "control"
        }]
      });
    });

    $("select#slidertype").change(function() {
      location.replace("/panel/<?= $siteRequest ?>?type_id=" + this.value);
    });

  });
</script>
