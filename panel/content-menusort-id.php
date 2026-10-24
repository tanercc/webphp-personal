<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');

$menuTypes = dbGetRawTableData('menutypes');

if (empty($menuTypes)) {
  include "content-204.php";
  return;
}

!empty($siteRequestId) ?: $siteRequestId = reset($menuTypes)['id'];

$menuDatas = dbGetTableData('menus', ['id', 'name', 'parent_id'], ['type_id' => $siteRequestId]);

?>

<!-- Content Header (Page header) -->
<section class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1>Menüler</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="<?= panelHtml(url('/')) ?>">Anasayfa</a></li>
          <li class="breadcrumb-item active">Menü Sıralama</li>
        </ol>
      </div>
    </div>
  </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">
  <div class="card card-primary">
    <div class="card-header d-flex align-items-center">
      <h3 class="card-name">Menü Sıralama</h3>

      <div class="w-25 ml-auto">
        <select class="form-control custom-select" id="menutype-select">
          <option selected disabled>Seçiniz...</option>
          <?php foreach ($menuTypes as $type) : ?>
          <option value="<?= panelHtml($type['id']) ?>" <?= $type['id'] == $siteRequestId ? 'selected' : '' ?>>
              <?= panelHtml($type['name']) ?>
            </option>
          <?php endforeach ?>
        </select>
      </div>
    </div>
    <!-- /.card-header -->
    <div class="card-body">
      <div id="sort-container" class="list-group nested-sortable"></div>
    </div>
    <!-- /.card-body -->
    <div class="card-footer">
      <button type="button" class="btn btn-primary" onclick="save()">Kaydet</button>
    </div>
  </div>
  <!-- /.card -->
</section>
<!-- /.content -->

<!-- page script -->
<script>
  // [{id: 1,name:'Name',parent_id:0}, ... ]
  var arr = <?= panelJson(array_values($menuDatas)) ?>;

  // Build nested tree
  function getNestedChildren(arr, parent_id) {
    var result = []
    for (var i in arr) {
      if (arr[i].parent_id == parent_id) {
        var children = getNestedChildren(arr, arr[i].id)

        if (children.length) {
          arr[i].children = children
        }
        result.push(arr[i])
      }
    }
    return result;
  }
  var res = getNestedChildren(arr, null);

  // Build nested nodes
  function buildNodes(node, parent) {
    $(node).each(function() {
      var element = this;
      var listItem = $("<div />", {
          text: this.name
        }).attr("data-id", this.id)
        .addClass("list-group-item");
      if (this.hasOwnProperty("children")) {
        var tree = $("<div />").addClass("list-group nested-sortable");
        buildNodes(this.children, tree);
        listItem.append(tree);
      } else {
        var dropItem = $("<div />", {
          class: "list-group nested-sortable"
        });
        listItem.append(dropItem);
      }
      parent.append(listItem);
    });
  }

  var tree = buildNodes(res, $("#sort-container"));

  const nestedQuery = '.nested-sortable';
  const identifier = 'id';
  const root = document.getElementById('sort-container');

  function serialize(sortable) {
    var serialized = [];
    var children = [].slice.call(sortable.children);
    for (var i in children) {
      var nested = children[i].querySelector(nestedQuery);
      serialized.push({
        id: children[i].dataset[identifier],
        children: nested ? serialize(nested) : []
      });
    }
    return serialized
  }

  // Print start
  // console.log(JSON.stringify(serialize(root)));

  function flattenSerialized(serialized) {
    var result = []
    this.traverse = function(node, parentId) {
      if (node) {
        result.push({
          id: node.id,
          parent_id: parentId
        });
        for (const child of node.children) {
          traverse(child, node.id, result);
        }
      }
      return result;
    };
    for (const item of serialized) {
      this.traverse(item, null);
    }
    return result;
  }

  function save() {
    waitingDialog.show("Kaydediliyor", {
      dialogSize: "sm",
      progressType: "warning"
    });

    var items = flattenSerialized(serialize(root));;
    //console.log(JSON.stringify(items));

    var promises = [];

    for (var i = 0; i < items.length; i++) {

      item = items[i];
      item.sort_order = (i + 1) * 10;

      var request = $.ajax({
        type: "PUT",
        url: "<?= url('api/menus') ?>/" + item.id,
        data: item
      });

      promises.push(request);
    }

    $.when.apply(null, promises).done(function() {
      waitingDialog.hide();
    });
  }

  $(document).ready(function() {
    var nestedSortables = $(".nested-sortable");

    // Loop through each nested sortable element
    for (var i = 0; i < nestedSortables.length; i++) {
      new Sortable(nestedSortables[i], {
        group: 'nested',
        animation: 150,
        swapThreshold: 0.65,
        onSort: function(e) {
          var items = e.to.children;
          var result = [];
          for (var i = 0; i < items.length; i++) {
            result.push($(items[i]).data('id'));
          }
          //console.log(result);
        }
      });
    }
  });

  $('#menutype-select').change(function() {
    window.location = "<?= url($siteRequest) ?>/" + this.value;
  });
</script>
