<article class="card overflow-hidden shadow rounded-4 border-0 mb-5"><div class="card-body p-0"><div class="d-flex align-items-center project-card">
<div class="p-5 flex-grow-1"><h2 class="fw-bolder"><?=e($record['label'])?></h2><p><?=e($record['info'])?></p><a class="fw-semibold" href="/projects/<?=e(rawurlencode($record['slug']))?>">Projeyi incele <span aria-hidden="true">→</span></a></div>
<?php if($record['image']): ?><img class="img-fluid project-image" width="300" height="400" loading="lazy" src="<?=e(siteUrl($record['image']))?>" alt="<?=e($record['label'])?>"><?php endif ?>
</div></div></article>
