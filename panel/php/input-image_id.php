<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__ . '/../include/output.php';

// Record media card: upload, per-language title/alt/caption, order, visibility, delete, and main image (image_id).
$mediaOwnerTable = $siteRequest === 'self' ? 'users' : $siteRequest;
$mediaOwnerId = $siteRequest === 'self' ? (int) ($_SESSION['userId'] ?? 0) : (int) $siteRequestId;
$mediaImageId = (string) ($rowData['image_id'] ?? '');
$mediaLanguages = array_map(static fn ($lang) => ['id' => (string) $lang['id'], 'name' => (string) $lang['name']], $siteLanguages ?? []);
?>

<div class="form-group">
  <label>Medya</label>
  <?php if ($mediaOwnerId < 1) : ?>
    <p class="text-muted mb-0">Medya eklemek için önce kaydı kaydedin.</p>
  <?php else : ?>
    <input type="hidden" id="image_id" name="image_id" value="<?= panelHtml($mediaImageId) ?>">
    <div class="input-group mb-2">
      <div class="custom-file">
        <input type="file" class="custom-file-input" id="media-file" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf">
        <label class="custom-file-label" for="media-file">Dosya seçin (jpg, png, webp, gif, pdf; en fazla 10 MB)</label>
      </div>
      <div class="input-group-append">
        <button class="btn btn-outline-primary" type="button" id="media-upload">Yükle</button>
      </div>
    </div>
    <div class="text-danger small mb-2" id="media-error" role="alert"></div>
    <p class="text-muted small">Ana görsel seçimi formdaki <strong>Kaydet</strong> ile kaydedilir.</p>
    <div id="media-list"></div>
  <?php endif ?>
</div>
<!-- /.form-group -->

<?php if ($mediaOwnerId > 0) : ?>
<script>
  (function() {
    var owner = {owner_table: <?= panelJson($mediaOwnerTable) ?>, owner_id: <?= panelJson((string) $mediaOwnerId) ?>};
    var languages = <?= panelJson($mediaLanguages) ?>;
    var $list = $('#media-list'), $error = $('#media-error'), $image = $('#image_id');

    function fail(xhr) {
      var message = (xhr.responseJSON && xhr.responseJSON.error) || 'İşlem başarısız';
      $error.text(message);
    }

    function field(label, name, value, multiline) {
      var $input = multiline ? $('<textarea rows="2">') : $('<input type="text" maxlength="255">');
      $input.addClass('form-control form-control-sm').attr('data-name', name).val(value || '');
      return $('<div class="col-md-4 mb-1">').append($('<small class="text-muted">').text(label), $input);
    }

    function render(items) {
      $list.empty();
      if (!items.length) { $list.append($('<p class="text-muted small">').text('Henüz medya yok.')); }
      items.forEach(function(item) {
        var isImage = item.mime.indexOf('image/') === 0;
        var isMain = $image.val() === item.id;
        var $card = $('<div class="border rounded p-2 mb-2">').attr('data-id', item.id);
        var $head = $('<div class="d-flex align-items-center flex-wrap">');
        var $preview = isImage
          ? $('<img class="img-thumbnail mr-2" style="max-width:120px;max-height:90px" alt="">').attr('src', item.path)
          : $('<a class="mr-2" target="_blank" rel="noopener"><i class="fas fa-file-pdf fa-2x"></i></a>').attr('href', item.path);
        $head.append($preview, $('<span class="mr-auto small">').text(item.original_name || item.path));
        if (isMain) { $head.append($('<span class="badge badge-success mr-2">').text('Ana görsel')); }
        if (isImage && !isMain && item.is_active) {
          $head.append($('<button type="button" class="btn btn-sm btn-outline-success mr-1">').text('Ana görsel yap').on('click', function() { $image.val(item.id); render(items); }));
        }
        if (isMain) {
          $head.append($('<button type="button" class="btn btn-sm btn-outline-secondary mr-1">').text('Ana görseli kaldır').on('click', function() { $image.val(''); render(items); }));
        }
        $head.append($('<button type="button" class="btn btn-sm btn-outline-danger">').text('Sil').on('click', function() { remove(item); }));

        var $meta = $('<div class="form-row mt-2">');
        $meta.append($('<div class="col-md-2 mb-1">').append($('<small class="text-muted">').text('Sıra'),
          $('<input type="number" class="form-control form-control-sm" data-name="sort_order">').val(item.sort_order)));
        $meta.append($('<div class="col-md-2 mb-1 pt-4">').append($('<label class="small mb-0">').append(
          $('<input type="checkbox" class="mr-1" data-name="is_active">').prop('checked', item.is_active), 'Aktif')));
        var $langs = $('<div class="mt-1">');
        languages.forEach(function(lang) {
          var value = item.lang[lang.id] || {};
          $langs.append($('<div class="form-row" data-lang>').attr('data-lang', lang.id).append(
            $('<div class="col-12"><small class="font-weight-bold"></small></div>').find('small').text(lang.name).end(),
            field('Başlık', 'title', value.title), field('Alt metin', 'alt', value.alt), field('Açıklama', 'caption', value.caption, true)));
        });
        var $save = $('<button type="button" class="btn btn-sm btn-primary mt-1">').text('Medyayı kaydet').on('click', function() { save(item, $card); });
        $list.append($card.append($head, $meta, $langs, $save));
      });
    }

    function load() {
      $.getJSON('/panel/api/media', owner).done(function(result) { render(result.data); }).fail(fail);
    }

    function put(id, data) {
      return $.ajax({type: 'PUT', url: '/panel/api/media/' + encodeURIComponent(id), data: $.param(data), dataType: 'json'});
    }

    function save(item, $card) {
      $error.text('');
      var active = $card.find('[data-name=is_active]').prop('checked');
      if (!active && $image.val() === item.id) { $image.val(''); }
      var requests = [put(item.id, {
        sort_order: $card.find('[data-name=sort_order]').val(),
        is_active: active ? '1' : '0'
      })];
      $card.find('[data-lang]').each(function() {
        var $row = $(this), data = {lang_id: $row.attr('data-lang')};
        $row.find('[data-name]').each(function() { data[$(this).attr('data-name')] = $(this).val(); });
        requests.push(put(item.id, data));
      });
      $.when.apply($, requests).done(load).fail(fail);
    }

    function remove(item) {
      if (!window.confirm('Bu medya silinsin mi?')) { return; }
      $error.text('');
      $.ajax({type: 'DELETE', url: '/panel/api/media/' + encodeURIComponent(item.id), dataType: 'json'}).done(function() {
        if ($image.val() === item.id) { $image.val(''); }
        load();
      }).fail(fail);
    }

    $('#media-file').on('change', function() {
      $(this).next('.custom-file-label').text(this.files.length ? this.files[0].name : 'Dosya seçin');
    });

    $('#media-upload').on('click', function() {
      var input = document.getElementById('media-file');
      if (!input.files.length) { $error.text('Önce bir dosya seçin'); return; }
      if (input.files[0].size > 10 * 1024 * 1024) { $error.text('Dosya 10 MB sınırını aşıyor'); return; }
      $error.text('');
      var data = new FormData();
      data.append('owner_table', owner.owner_table);
      data.append('owner_id', owner.owner_id);
      data.append('file', input.files[0]);
      $.ajax({type: 'POST', url: '/panel/api/media', data: data, processData: false, contentType: false, dataType: 'json'})
        .done(function() { input.value = ''; $('#media-file').trigger('change'); load(); })
        .fail(fail);
    });

    load();
  })();
</script>
<?php endif ?>

<?php

$inputFieldKey = null;
$inputFieldValue = null;
