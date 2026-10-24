<?php
define('BASEPATH','/');
require dirname(__DIR__).'/config/config.php';
require dirname(__DIR__).'/panel/include/db_functions.php';
require dirname(__DIR__).'/panel/include/output.php';
require dirname(__DIR__).'/panel/include/rich_html.php';
require dirname(__DIR__).'/panel/include/session.php';
require dirname(__DIR__).'/panel/include/csrf.php';
panelStartSession();panelCsrfToken();
header('X-Content-Type-Options: nosniff');header('Referrer-Policy: strict-origin-when-cross-origin');header('X-Frame-Options: SAMEORIGIN');
function e($value):string{return panelHtml($value);}
function siteRows(string $sql,array $params=[]):array{global $conn;return $conn->execute_query($sql,$params)->fetch_all();}
function sitePage(string $slug):array{return siteRows('SELECT p.*,COALESCE(l.name,p.name) AS label,COALESCE(l.title,p.name) AS title,COALESCE(l.content,\'\') AS content FROM pages p LEFT JOIN page_lang l ON l.row_id=p.id AND l.lang_id=1 WHERE p.slug=? AND p.is_active=1 AND p.deleted=0 LIMIT 1',[$slug])[0]??[];}
function siteRecords(string $type):array{return siteRows('SELECT d.*,COALESCE(l.name,d.name) AS label,COALESCE(l.content,\'\') AS content FROM datas d LEFT JOIN data_lang l ON l.row_id=d.id AND l.lang_id=1 WHERE d.type_id=(SELECT id FROM datatypes WHERE slug=? AND is_active=1 AND deleted=0) AND d.is_active=1 AND d.deleted=0 ORDER BY d.sort_order,d.id',[$type]);}
function setting(string $name,string $fallback=''):string{static $settings;if($settings===null){$settings=dbGetSiteVariables(1);}return (string)($settings[$name]??$fallback);}
function siteUrl($value):string{$url=panelMediaUrl($value);return $url!==''?$url:'#';}

function assetAttributes(string $path):string {
 static $cache=[];if(!isset($cache[$path])){$bytes=file_get_contents(dirname(__DIR__).$path);$hash=base64_encode(hash('sha384',$bytes,true));$cache[$path]=['url'=>$path.'?v='.substr(hash('sha256',$bytes),0,12),'integrity'=>'sha384-'.$hash];}
 return 'href="'.e($cache[$path]['url']).'" integrity="'.e($cache[$path]['integrity']).'" crossorigin="anonymous"';
}
function scriptAttributes(string $path):string{return str_replace('href=','src=',assetAttributes($path));}
