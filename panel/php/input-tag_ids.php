<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__ . '/../include/output.php';

// Tags of a data record (data_tags), submitted as comma-separated `tag_ids`.
global $conn;
$tagRows = $siteRequestId > 0
  ? $conn->execute_query('SELECT t.id, t.name FROM data_tags dt JOIN tags t ON t.id = dt.tag_id WHERE dt.data_id = ? AND t.deleted = 0 ORDER BY t.name', [(int) $siteRequestId])->fetch_all()
  : [];
?>

<div class="form-group">
  <label for="tag_ids_select">Etiketler</label>
  <input type="hidden" id="tag_ids" name="tag_ids" value="<?= panelHtml(implode(',', array_column($tagRows, 'id'))) ?>">
  <select class="form-control" style="width: 100%;" id="tag_ids_select" multiple>
    <?php foreach ($tagRows as $tag) : ?>
      <option value="<?= panelHtml($tag['id']) ?>" selected><?= panelHtml($tag['name']) ?></option>
    <?php endforeach ?>
  </select>
</div>
<!-- /.form-group -->

<script>
  $(function() {
    $('#tag_ids_select').select2({
      theme: 'bootstrap4',
      placeholder: 'Etiket seçin...',
      ajax: {
        type: 'POST',
        url: '/panel/api/select2',
        dataType: 'json',
        data: function(params) { return {table: 'tags', q: params.term, page: params.page}; },
        processResults: function(data) {
          return {results: data.filter(function(item) { return String(item.id) !== '0'; })};
        }
      }
    }).on('change', function() {
      $('#tag_ids').val(($(this).val() || []).join(','));
    });
  });
</script>
