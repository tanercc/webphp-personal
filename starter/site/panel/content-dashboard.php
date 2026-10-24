<?php defined('BASEPATH') or exit; ?>
<section class="content-header"><h1><?=panelHtml($panel_name)?></h1></section>
<section class="content"><div class="container-fluid"><div class="row">
<?php foreach([['Sayfalar','pages'],['Veriler','datas'],['Menüler','menus'],['Site ayarları','variables']] as [$label,$route]): ?>
<?php if(isUserAllowed($route)): ?><div class="col-md-6"><div class="card card-body"><h2 class="h5"><?=panelHtml($label)?></h2><a href="<?=panelHtml(url($route))?>">Düzenle</a></div></div><?php endif ?><?php endforeach ?>
</div><a href="/" class="btn btn-primary" target="_blank" rel="noopener">Siteyi aç</a></div></section>
