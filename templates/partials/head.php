<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=e(($project['label']??$page['title']).' | '.setting('site_name'))?></title>
<meta name="description" content="<?=e(strip_tags($page['content']??''))?>">
<link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet">
<link <?=assetAttributes('/assets/vendor/bootstrap-icons.css')?> rel="stylesheet">
<link <?=assetAttributes('/css/styles.css')?> rel="stylesheet">
<link <?=assetAttributes('/css/site.css')?> rel="stylesheet">
</head><body class="d-flex flex-column h-100 <?=in_array($view,['resume','projects'],true)?'bg-light':''?>"><main class="flex-shrink-0">
