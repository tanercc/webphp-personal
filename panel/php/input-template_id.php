<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__ . '/../include/output.php';
require_once __DIR__ . '/../include/data_templates.php';
$templateValue = (string) ($rowData['template_id'] ?? '');
$templateChoices = dbGetTableData('templates');
$isCategoryTemplate = ($siteRequest ?? '') === 'cats';
?>
<div class="form-group">
  <label for="template_id"><?= $isCategoryTemplate ? 'Varsayılan görünüm' : 'Görünüm' ?></label>
  <select class="form-control" id="template_id" name="template_id">
    <option value="" <?= $templateValue === '' ? 'selected' : '' ?>><?= $isCategoryTemplate ? 'Otomatik' : 'Kategori varsayılanı / otomatik' ?></option>
    <?php foreach ($templateChoices as $template): ?>
      <?php if (!dataTemplateKeyValid($template['code'] ?? null) || empty($template['code'])) { continue; } ?>
      <option value="<?= panelHtml($template['id']) ?>" <?= $templateValue === (string) $template['id'] ? 'selected' : '' ?>><?= panelHtml($template['name']) ?><?= empty($template['is_active']) ? ' (pasif)' : '' ?></option>
    <?php endforeach ?>
  </select>
  <small class="form-text text-muted"><?= $isCategoryTemplate ? 'Bu kategorideki içeriklerde özel bir görünüm seçilmediyse kullanılır.' : 'Boş bırakılırsa kategorinin görünümü, o da boşsa otomatik görünüm kullanılır. Kategorili görünüm mevcut Kategori alanını kullanır.' ?></small>
</div>
