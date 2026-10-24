<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__ . '/include/data_templates.php';
?>
<section class="content-header"><div class="container-fluid"><h1>İçerik Şablonları</h1></div></section>
<section class="content">
  <div class="card"><div class="card-body">
    <p>Kategoriler varsayılan görünümü belirler. İçerikte yapılan özel seçim bu varsayılanın önüne geçer.</p>
    <div id="template-grid"></div>
  </div></div>
  <div class="card"><div class="card-header"><h3 class="card-title" id="template-heading">Şablon ekle</h3></div><div class="card-body">
    <form id="template-form">
      <input type="hidden" name="id" value="">
      <div class="form-group"><label for="template-name">Ad</label><input class="form-control" id="template-name" name="name" maxlength="100" required></div>
      <div class="form-group"><label for="template-code">Görünüm</label>
        <select class="form-control" id="template-code" name="code" required>
          <?php foreach (dataTemplateRegistry() as $code => $definition): ?>
            <option value="<?= panelHtml($code) ?>"><?= panelHtml($definition['label']) ?> (<?= panelHtml($code) ?>)</option>
          <?php endforeach ?>
        </select>
        <small class="form-text text-muted">Kayıtlı şablonun kodu sabittir. Pasife alınan veya silinen şablonlarda kategori varsayılanı / otomatik görünüm kullanılır.</small>
      </div>
      <div class="form-group"><label for="template-order">Sıra</label><input class="form-control" id="template-order" name="sort_order" type="number" value="50" required></div>
      <div class="form-group"><label for="template-active">Durum</label><select class="form-control" id="template-active" name="is_active"><option value="1">Aktif</option><option value="0">Pasif</option></select></div>
      <p id="template-error" class="text-danger" role="alert"></p>
      <button class="btn btn-primary" type="submit">Kaydet</button>
      <button class="btn btn-secondary" id="template-new" type="button">Yeni kayıt</button>
    </form>
  </div></div>
</section>
<script>
$(function () {
  var form = document.getElementById('template-form');
  var error = document.getElementById('template-error');
  function reset() {
    form.reset(); form.elements.id.value = ''; form.elements.code.disabled = false;
    document.getElementById('template-heading').textContent = 'Şablon ekle'; error.textContent = '';
  }
  $('#template-new').on('click', reset);
  $('#template-grid').jsGrid({
    width: '100%', sorting: true, paging: true, pageLoading: true, autoload: true,
    pageSize: 10, deleteConfirm: 'Şablon silinsin mi? Bağlı kayıtlar varsayılan görünüme döner.',
    controller: {
      loadData: function (filter) { return $.getJSON('/panel/api/templates', filter); },
      deleteItem: function (item) { return $.ajax({type:'DELETE', url:'/panel/api/templates/' + item.id}); }
    },
    rowClick: function (args) {
      var row = args.item;
      ['id','name','code','sort_order'].forEach(function (key) { form.elements[key].value = row[key]; });
      form.elements.is_active.value = row.is_active === true || String(row.is_active) === '1' ? '1' : '0';
      form.elements.code.disabled = true;
      document.getElementById('template-heading').textContent = 'Şablon düzenle'; error.textContent = '';
    },
    fields: [
      {name:'id',title:'ID',type:'number',width:25},
      {name:'name',title:'Ad',type:'text'},
      {name:'code',title:'Kod',type:'text'},
      {name:'is_active',title:'Aktif',type:'checkbox',width:40},
      {name:'sort_order',title:'Sıra',type:'number',width:40},
      {type:'control',editButton:false}
    ]
  });
  $(form).on('submit', function (event) {
    event.preventDefault(); if (!form.reportValidity()) { return; }
    var id = form.elements.id.value;
    $.ajax({type:id ? 'PUT' : 'POST',url:'/panel/api/templates' + (id ? '/' + id : ''),data:$(form).serialize()})
      .done(function () { reset(); $('#template-grid').jsGrid('loadData'); })
      .fail(function () { error.textContent = 'Kaydedilemedi. Kod benzersiz olmalı; alanları ve oturumunuzu kontrol edin.'; });
  });
});
</script>
