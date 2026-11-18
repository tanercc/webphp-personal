<?php
$contactError='';$contactSuccess=false;
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 if(!panelCsrfValid($_SERVER,$_POST)){http_response_code(403);$contactError='Oturum doğrulanamadı. Sayfayı yenileyip tekrar deneyin.';}
 else{
  $values=[];foreach(['name','email','phone','message','website'] as $key){$values[$key]=is_string($_POST[$key]??'')?trim($_POST[$key]??''):'';}
  if($values['website']!==''||mb_strlen($values['name'])<2||mb_strlen($values['name'])>100||!filter_var($values['email'],FILTER_VALIDATE_EMAIL)||strlen($values['email'])>254||strlen($values['phone'])>50||mb_strlen($values['message'])<10||mb_strlen($values['message'])>5000){http_response_code(422);$contactError='Ad, geçerli e-posta ve en az 10 karakterlik bir mesaj girin.';}
  else{
   try{
    $conn->begin_transaction();$key=hash('sha256',$_SERVER['REMOTE_ADDR']??'');$window=intdiv(time(),900);
    $limit=$conn->execute_query('SELECT `window_start`,`hits` FROM `contact_limits` WHERE `limit_key`=?',[$key])->fetch_assoc();
    $hits=$limit&&(int)$limit['window_start']===$window?(int)$limit['hits']+1:1;
    if($hits>5){$conn->rollback();http_response_code(429);$contactError='Biraz ara verip tekrar deneyin.';}
    else{
     $conn->upsert('contact_limits',['limit_key'=>$key,'window_start'=>$window,'hits'=>$hits],['limit_key']);
     $conn->execute_query('INSERT INTO datas(name,type_id,info,column_1,column_2,is_active,deleted,sort_order) VALUES(?,6,?,?,?,1,0,50)',[$values['name'],$values['message'],$values['email'],$values['phone']]);
     $conn->commit();$_SESSION['contact_sent']=true;header('Location: /contact',true,303);exit;
    }
   }catch(Throwable $error){$conn->rollback();http_response_code(503);$contactError='Mesaj kaydedilemedi. Biraz sonra tekrar deneyin.';}
  }
 }
}
if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'&&!empty($_SESSION['contact_sent'])){$contactSuccess=true;unset($_SESSION['contact_sent']);}
