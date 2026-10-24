<?php defined('BASEPATH') or exit; ?>
<!doctype html><html lang="tr"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=e($page['title'].' | '.setting('site_name'))?></title>
<link <?=assetAttributes('/css/site.css')?> rel="stylesheet">
</head><body><header class="container site-header">
<a class="brand" href="/"><?=e(setting('site_name'))?></a><nav aria-label="Ana menü">
<?php foreach(siteRows('SELECT name,link FROM menus WHERE type_id=1 AND is_active=1 AND deleted=0 ORDER BY sort_order,id') as $item): ?>
<a href="<?=e(siteUrl($item['link']))?>"><?=e($item['name'])?></a><?php endforeach ?>
</nav></header><main class="container"><article><h1><?=e($page['title'])?></h1><?=panelRichHtml($page['content'])?></article></main>
<footer class="container"><p><?=e(setting('footer_text'))?></p><a href="/panel/">Yönetim paneli</a></footer>
</body></html>
