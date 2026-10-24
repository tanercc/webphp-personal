<?php require __DIR__.'/../partials/hero.php'; $about=sitePage('about'); ?>
<?php if($about): ?><section class="bg-light py-5"><div class="container px-5"><div class="row gx-5 justify-content-center"><div class="col-xxl-8"><div class="text-center my-5">
<h2 class="display-5 fw-bolder"><span class="text-gradient d-inline"><?=e($about['title'])?></span></h2>
<p class="lead fw-light mb-4"><?=e($about['label'])?></p><div class="text-muted"><?=panelRichHtml($about['content'])?></div>
<div class="d-flex justify-content-center fs-2 gap-4">
<a class="text-gradient" aria-label="LinkedIn" rel="noopener noreferrer" target="_blank" href="<?=e(siteUrl(setting('linkedin_url')))?>"><i class="bi bi-linkedin" aria-hidden="true"></i></a>
<a class="text-gradient" aria-label="GitHub" rel="noopener noreferrer" target="_blank" href="<?=e(siteUrl(setting('github_url')))?>"><i class="bi bi-github" aria-hidden="true"></i></a>
</div></div></div></div></div></section><?php endif ?>
