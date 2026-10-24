<?php
defined('BASEPATH') or exit;
$customType=!empty($rowData['type_id'])?dbGetTableRow('datatypes',$rowData['type_id']):[];
$customRegistry=require __DIR__.'/../../config/panel-fields.php';
foreach($customRegistry[$customType['slug']??'']??[] as $customKey=>$customLabel):
 if(!preg_match('/^column_[1-9]$/D',$customKey)||!array_key_exists($customKey,$rowData)){continue;}
?>
<div class="form-group"><label for="<?=panelHtml($customKey)?>"><?=panelHtml($customLabel)?></label><input class="form-control" type="text" name="<?=panelHtml($customKey)?>" id="<?=panelHtml($customKey)?>" maxlength="255" value="<?=panelHtml($rowData[$customKey])?>"></div>
<?php endforeach; unset($customRegistry,$customType,$customKey,$customLabel); ?>
