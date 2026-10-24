<?php
require __DIR__.'/app/bootstrap.php';
$path=trim(rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)??'/'),'/');
$aliases=['index.html'=>'','resume.html'=>'resume','projects.html'=>'projects','contact.html'=>'contact'];$path=$aliases[$path]??$path;
$pages=[''=>'home','resume'=>'resume','resume/print'=>'resume','projects'=>'projects','contact'=>'contact','privacy'=>'privacy','terms'=>'terms'];
$project=null;
if(preg_match('~^projects/([a-z0-9-]+)$~D',$path,$m)){
 foreach(siteRecords('projects') as $record){if($record['slug']===$m[1]){$project=$record;break;}}
 $slug='projects';$view=$project?'project':'404';
}else{$slug=$pages[$path]??'';$view=$slug?:'404';}
$page=$slug?sitePage($slug):[];
if(!$page){$view='404';}
if($view==='404'){http_response_code(404);$page=['title'=>'Sayfa bulunamadı','content'=>''];}
if($view==='contact'){require __DIR__.'/app/contact.php';}
require __DIR__.'/templates/partials/head.php';
require __DIR__.'/templates/partials/nav.php';
require __DIR__.'/templates/pages/'.($view==='home'?'home':(in_array($view,['privacy','terms'],true)?'text':$view)).'.php';
require __DIR__.'/templates/partials/footer.php';
