<section class="py-5"><div class="container px-5"><div class="bg-light rounded-4 py-5 px-4 px-md-5"><div class="text-center mb-5"><div class="feature bg-primary bg-gradient-primary-to-secondary text-white rounded-3 mb-3"><i class="bi bi-envelope" aria-hidden="true"></i></div><h1 class="fw-bolder"><?=e($page['title'])?></h1><div class="lead fw-normal text-muted mb-0"><?=panelRichHtml($page['content'])?></div></div><div class="row gx-5 justify-content-center"><div class="col-lg-8 col-xl-6">
<?php if($contactSuccess):?><div role="status" class="alert alert-success">Mesajınız kaydedildi. Teşekkür ederiz.</div><?php endif ?>
<?php if($contactError):?><div role="alert" class="alert alert-danger"><?=e($contactError)?></div><?php endif ?>
<form id="contactForm" action="/contact" method="post"><input type="hidden" name="csrf_token" value="<?=e(panelCsrfToken())?>">
<div class="contact-honeypot" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
<?php foreach([['name','Adınız soyadınız','text',100,true],['email','E-posta adresiniz','email',254,true],['phone','Telefon (isteğe bağlı)','tel',50,false]] as [$key,$label,$type,$max,$required]):?>
<div class="form-floating mb-3"><input class="form-control" id="<?=$key?>" name="<?=$key?>" type="<?=$type?>" maxlength="<?=$max?>" placeholder="<?=$label?>" <?=$required?'required':''?> value="<?=e($values[$key]??'')?>"><label for="<?=$key?>"><?=$label?></label></div><?php endforeach ?>
<div class="form-floating mb-3"><textarea class="form-control" id="message" name="message" style="height:10rem" minlength="10" maxlength="5000" placeholder="Mesajınız" required><?=e($values['message']??'')?></textarea><label for="message">Mesajınız</label></div>
<div class="d-grid"><button class="btn btn-primary btn-lg" type="submit">Mesaj gönder</button></div></form>
<p class="small text-muted mt-3 mb-0">Doğrudan iletişim: <a href="mailto:<?=e(setting('email'))?>"><?=e(setting('email'))?></a></p>
</div></div></div></div></section>
