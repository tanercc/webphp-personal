<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);
foreach(['assets/profile.png'=>'pages/1/profile.png','assets/project-studio.png'=>'datas/1/studio.png','assets/project-rota.png'=>'datas/2/rota.png'] as $source=>$relative){
 $target=$root.'/uploads/files/'.$relative;if(!is_dir(dirname($target))){mkdir(dirname($target),0750,true);}if(!is_file($target)&&!copy($root.'/'.$source,$target)){throw new RuntimeException('Cannot copy seed media');}
}
