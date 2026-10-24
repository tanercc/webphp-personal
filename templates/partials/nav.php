<nav class="navbar navbar-expand-lg navbar-light bg-white py-3"><div class="container px-5">
<a class="navbar-brand" href="/"><span class="fw-bolder text-primary"><?=e(setting('site_name'))?></span></a>
<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Menüyü aç"><span class="navbar-toggler-icon"></span></button>
<div class="collapse navbar-collapse" id="navbarSupportedContent"><ul class="navbar-nav ms-auto mb-2 mb-lg-0 small fw-bolder">
<?php foreach(siteRows('SELECT m.* FROM menus m JOIN menutypes t ON t.id=m.type_id WHERE t.id=1 AND m.is_active=1 AND m.deleted=0 ORDER BY m.sort_order,m.id') as $item): ?>
<li class="nav-item"><a class="nav-link <?=($path===trim((string)$item['link'],'/'))?'active':''?>" href="<?=e(siteUrl($item['link']))?>" <?=($path===trim((string)$item['link'],'/'))?'aria-current="page"':''?>><?=e($item['name'])?></a></li>
<?php endforeach ?></ul></div></div></nav>
