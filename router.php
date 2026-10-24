<?php
// Development router. Apache/ServBay applies the equivalent .htaccess rules.
$root=__DIR__;$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
for($i=0;$i<5;$i++){$next=rawurldecode($path);if($next===$path){break;}$path=$next;}
if(str_contains($path,'%')||preg_match('~[\x00-\x1f\x7f\\\\]|(?:^|/)\.{1,2}(?:/|$)~',$path)){http_response_code(403);exit('Forbidden');}
if(preg_match('~^/(?:config|app|templates|database|tools|storage|docs|tests|starter|upstream|\.git|\.build)(?:/|$)|(?:^|/)\.(?!well-known)|^/panel/(?:include|sql|temp)(?:/|$)|^/panel/plugins/(?:[^/]+/)*(?:samples|examples|demos|tests?)(?:/|$)|^/panel/plugins/(?:htmlpurifier|util)(?:/|$)~i',$path)){http_response_code(403);exit('Forbidden');}
$file=$root.$path;
if(str_starts_with($path,'/uploads/')){
 if(!is_file($file)||preg_match('/\.(?:php[0-9]*|phtml|pht|phar|cgi|pl|py|sh|shtml)(?:\.|$)/i',$path)){http_response_code(403);exit('Forbidden');}
 header('X-Content-Type-Options: nosniff');header("Content-Security-Policy: sandbox; default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; frame-ancestors 'self'");
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file);header('Content-Type: '.$mime);
 if(preg_match('/\.(?:html?|xhtml|xml|xsl|xslt|js|mjs|css)$/i',$path)){header('Content-Type: text/plain');header('Content-Disposition: attachment');}
 readfile($file);return true;
}
if(str_starts_with($path,'/panel/api/')||$path==='/panel/api'){$entry=$root.'/panel/api/index.php';}
elseif(str_starts_with($path,'/panel/')){
 if(is_file($file)&&!str_ends_with(strtolower($path),'.php')){return false;}
 if(is_file($file)){
  $allowed=['/panel/index.php','/panel/api/index.php'];foreach(['dialog','upload','execute','ajax_calls','force_download'] as $name){$allowed[]='/panel/plugins/filemanager/'.$name.'.php';}
  if(!in_array($path,$allowed,true)){http_response_code(403);exit('Forbidden');}$entry=$file;
 }else{$entry=$root.'/panel/index.php';}
}elseif($path==='/panel'){$entry=$root.'/panel/index.php';}
elseif(is_file($file)&&$path!=='/index.php'){
 if(str_ends_with(strtolower($path),'.php')){http_response_code(403);exit('Forbidden');}return false;
}else{$entry=$root.'/index.php';}
$_SERVER['SCRIPT_FILENAME']=$entry;chdir(dirname($entry));require $entry;return true;