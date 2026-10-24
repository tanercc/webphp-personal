<?php defined('BASEPATH') or exit; $messages=sqlQuery('SELECT * FROM datas WHERE type_id=6 AND deleted=0 ORDER BY id DESC')->fetch_all(); ?>
<section class="content-header"><h1>İletişim mesajları</h1></section><section class="content"><div class="container-fluid">
<?php foreach($messages as $message):?><article class="card"><div class="card-body"><h2 class="h5"><?=panelHtml($message['name'])?></h2><p class="text-muted"><?=panelHtml($message['column_1'])?> · <?=panelHtml($message['column_2'])?></p><p style="white-space:pre-wrap"><?=panelHtml($message['info'])?></p></div></article><?php endforeach ?>
<?php if(!$messages):?><div class="card card-body">Henüz mesaj yok.</div><?php endif ?></div></section>
