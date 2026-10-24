<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);$path=getenv('PERSONAL_DB_FILE')?:$root.'/storage/personal.sqlite';
if(file_exists($path)){fwrite(STDERR,"Database exists; refusing to overwrite. Choose a new PERSONAL_DB_FILE.\n");exit(1);}
foreach([$root.'/storage',$root.'/storage/sessions',$root.'/uploads/files',$root.'/uploads/thumbs'] as $d){if(!is_dir($d)){mkdir($d,0750,true);}}
$temp=$path.'.install-'.bin2hex(random_bytes(6));
try {
 $db=new PDO('sqlite:'.$temp,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $db->exec(file_get_contents($root.'/database/schema.sql'));$db->exec(file_get_contents($root.'/database/seed.sql'));
 if($db->query('PRAGMA integrity_check')->fetchColumn()!=='ok'||$db->query('PRAGMA foreign_key_check')->fetch()){throw new RuntimeException('Database validation failed');}
 $db=null;require __DIR__.'/seed-media.php';if(!rename($temp,$path)){throw new RuntimeException('Could not publish database');}
 echo "Installed clean Personal SQLite demo.\n";
} catch(Throwable $e){if(is_file($temp)){unlink($temp);}fwrite(STDERR,"Installation failed. Existing databases preserved.\n");exit(1);}
