<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');
const ROOT_DIR=__DIR__.'/..';
function env_data(): array {
 static $data=null;
 if ($data===null) {
  $p=ROOT_DIR.'/.env';$data=is_file($p)?(parse_ini_file($p,false,INI_SCANNER_RAW)?:[]):[];
 }
 return $data;
}
function envv(string $key,string $default=''): string {return (string)(env_data()[$key]??$default);}
function base_path(): string {
 $explicit=trim(envv('APP_BASE_PATH'));
 if($explicit!=='') return '/'.trim($explicit,'/');
 $script=str_replace('\\','/',$_SERVER['SCRIPT_NAME']??'/index.php');
 foreach(['/backoffice-openfire/','/api/','/public/'] as $part){$p=strpos($script,$part);if($p!==false)return rtrim(substr($script,0,$p),'/');}
 return rtrim(dirname($script),'/')==='/'?'':rtrim(dirname($script),'/');
}
function site_url(string $path=''): string {return base_path().'/'.ltrim($path,'/');}
function absolute_url(string $path=''): string {
 $host=envv('APP_URL');if($host===''){$tls=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';$host=$tls.'://'.($_SERVER['HTTP_HOST']??'localhost');}
 return rtrim($host,'/').site_url($path);
}
function e(?string $v): string {return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function pdo(): PDO {
 static $db=null;
 if($db===null){
  if(envv('DB_NAME')==='' || envv('DB_USER')==='')throw new RuntimeException('Konfigurasi database belum lengkap. Lihat README.md.');
  $dsn='mysql:host='.envv('DB_HOST','127.0.0.1').';port='.envv('DB_PORT','3306').';dbname='.envv('DB_NAME').';charset=utf8mb4';
  try{$db=new PDO($dsn,envv('DB_USER'),envv('DB_PASS'),[
   PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
   PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_PERSISTENT=>false
  ]);}catch(Throwable $ex){error_log('OPENFIRE DB: '.$ex->getMessage());throw new RuntimeException('Database belum terhubung. Periksa .env dan instalasi MySQL.');}
 }
 return $db;
}
function sql_all(string $sql,array $args=[]):array {$s=pdo()->prepare($sql);$s->execute($args);return $s->fetchAll();}
function sql_one(string $sql,array $args=[]):?array {$s=pdo()->prepare($sql);$s->execute($args);$row=$s->fetch();return $row===false?null:$row;}
function sql_run(string $sql,array $args=[]):int {$s=pdo()->prepare($sql);$s->execute($args);return $s->rowCount();}
function json_response(array $data,int $status=200):never {http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
function request_json():array {$raw=file_get_contents('php://input');$d=json_decode($raw?:'{}',true);if(!is_array($d))throw new InvalidArgumentException('Format JSON tidak valid.');return $d;}
function must_post():void {if(($_SERVER['REQUEST_METHOD']??'')!=='POST')json_response(['error'=>'Metode tidak diizinkan'],405);}
function trunc(string $text,int $limit):string {return function_exists('mb_substr')?mb_substr(trim($text),0,$limit,'UTF-8'):substr(trim($text),0,$limit);}
function req_string(array $d,string $key,int $max=5000):string {return trunc((string)($d[$key]??''),$max);}
function boolint(mixed $n):int{return filter_var($n,FILTER_VALIDATE_BOOLEAN)?1:0;}
function valid_language(string $lang):bool {return (bool)preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/',$lang) && sql_one('SELECT code FROM supported_languages WHERE code=?',[$lang])!==null;}
function app_session():void {
 if(session_status()===PHP_SESSION_ACTIVE)return;
 $https=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off') || str_starts_with(envv('APP_URL'),'https://');
 ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');ini_set('session.cookie_httponly','1');
 session_name('OPENFIRESESSID');session_set_cookie_params(['lifetime'=>0,'path'=>site_url(), 'secure'=>$https,'httponly'=>true,'samesite'=>'Lax']);session_start();
}
function csrf():string {app_session();return $_SESSION['csrf']??=bin2hex(random_bytes(32));}
function check_csrf(?string $token):void {app_session();if(!$token||!hash_equals(csrf(),$token))json_response(['error'=>'Sesi keamanan tidak valid. Muat ulang halaman.'],419);}
function ip_address():string{return substr((string)($_SERVER['REMOTE_ADDR']??'unknown'),0,45);}
function current_admin():?array {
 app_session();$id=(int)($_SESSION['admin_id']??0);$token=$_SESSION['admin_token']??'';
 if(!$id||!is_string($token)||strlen($token)<32)return null;
 try{$row=sql_one('SELECT a.id,a.name,a.email,s.id AS session_id,s.last_seen FROM admins a JOIN admin_sessions s ON s.admin_id=a.id WHERE a.id=? AND s.token_hash=? AND s.expires_at>NOW()',[$id,hash('sha256',$token)]);}
 catch(Throwable $e){return null;}
 if(!$row)return null;
 if(strtotime($row['last_seen'])<time()-300)sql_run('UPDATE admin_sessions SET last_seen=NOW() WHERE id=?',[$row['session_id']]);
 return $row;
}
function require_admin(bool $redirect=false):array {
 $admin=current_admin();if(!$admin){if($redirect){header('Location: '.site_url('backoffice-openfire/login.php'));exit;}json_response(['error'=>'Sesi berakhir. Silakan masuk kembali.'],401);}return $admin;
}
function destroy_login():void {
 app_session();if(!empty($_SESSION['admin_token'])){try{sql_run('DELETE FROM admin_sessions WHERE token_hash=?',[hash('sha256',$_SESSION['admin_token'])]);}catch(Throwable $ignored){}}
 $_SESSION=[];if(ini_get('session.use_cookies')){$c=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$c['path'],$c['domain'],$c['secure'],$c['httponly']);}session_destroy();
}
function audit(int $id,string $action,string $subject):void {sql_run('INSERT INTO admin_activity_logs(admin_id,action,subject) VALUES (?,?,?)',[$id,$action,trunc($subject,130)]);}
function media_src(?string $path):string {return $path?site_url(ltrim($path,'/')):'';}
function map_links(array $settings):array {
 $address=trim((string)($settings['address']??''));
 $lat=trim((string)($settings['latitude']??''));$lon=trim((string)($settings['longitude']??''));
 $destination=($lat!=='' && $lon!=='' && is_numeric($lat) && is_numeric($lon) && abs((float)$lat)<=90 && abs((float)$lon)<=180)?$lat.','.$lon:$address;
 $directions=(string)($settings['maps_directions_url']??'');
 $embed=(string)($settings['maps_embed_url']??'');
 if(!$directions&&$destination)$directions='https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($destination);
 if(!$embed&&$destination)$embed='https://www.google.com/maps?q='.rawurlencode($destination).'&output=embed';
 return ['embed'=>$embed,'directions'=>$directions];
}
function public_payload(string $lang='id'):array {
 $langs=sql_all('SELECT code,name FROM supported_languages WHERE is_active=1 ORDER BY is_default DESC,code ASC');
 if(!in_array($lang,array_column($langs,'code'),true))$lang='id';
 $settings=[];foreach(sql_all('SELECT setting_key,setting_value FROM website_settings') as $s)$settings[$s['setting_key']]=$s['setting_value'];
 $sections=sql_all('SELECT p.id,p.slug,p.sort_order,p.media_id,COALESCE(t.eyebrow,fb.eyebrow,\'\') eyebrow,COALESCE(t.title,fb.title,\'\') title,COALESCE(t.body,fb.body,\'\') body,COALESCE(t.button_label,fb.button_label,\'\') button_label,COALESCE(t.button_label_secondary,fb.button_label_secondary,\'\') button_label_secondary,m.file_path image,m.alt_id,m.alt_en,mt.alt_text alt_local FROM page_sections p LEFT JOIN section_translations t ON t.section_id=p.id AND t.lang=? LEFT JOIN section_translations fb ON fb.section_id=p.id AND fb.lang=\'id\' LEFT JOIN media_library m ON m.id=p.media_id LEFT JOIN media_translations mt ON mt.media_id=m.id AND mt.lang=? WHERE p.active=1 ORDER BY p.sort_order,p.id',[$lang,$lang]);
 $sectionsBy=[];foreach($sections as $s){$s['image']=$s['image']?media_src($s['image']):null;$s['alt']=$s['alt_local']??($lang==='en'?$s['alt_en']:$s['alt_id']);$sectionsBy[$s['slug']]=$s;}
 $cats=sql_all('SELECT c.id,c.slug,c.sort_order,COALESCE(t.name,fb.name,c.slug) name,COALESCE(t.description,fb.description,\'\') description FROM menu_categories c LEFT JOIN menu_category_translations t ON t.category_id=c.id AND t.lang=? LEFT JOIN menu_category_translations fb ON fb.category_id=c.id AND fb.lang=\'id\' WHERE c.active=1 ORDER BY c.sort_order,c.id',[$lang]);
 $menu=sql_all('SELECT i.id,i.slug,i.category_id,i.price,i.show_price,i.featured,i.is_available,i.sort_order,COALESCE(t.name,fb.name,i.slug) name,COALESCE(t.description,fb.description,\'\') description,m.file_path image,m.thumbnail_path thumbnail,m.alt_id,m.alt_en,mt.alt_text alt_local FROM menu_items i JOIN menu_categories c ON c.id=i.category_id AND c.active=1 LEFT JOIN menu_item_translations t ON t.item_id=i.id AND t.lang=? LEFT JOIN menu_item_translations fb ON fb.item_id=i.id AND fb.lang=\'id\' LEFT JOIN media_library m ON m.id=i.media_id LEFT JOIN media_translations mt ON mt.media_id=m.id AND mt.lang=? WHERE i.active=1 ORDER BY i.featured DESC,i.sort_order,i.id',[$lang,$lang]);
 foreach($menu as &$i){$origImage=$i['image'];$i['image']=media_src($origImage);$i['thumbnail']=media_src($i['thumbnail']??$origImage);$i['alt']=$i['alt_local']??($lang==='en'?$i['alt_en']:$i['alt_id']);}unset($i);
 $gallery=sql_all('SELECT g.id,g.sort_order,m.file_path image,m.thumbnail_path thumbnail,m.alt_id,m.alt_en,m.caption_id,m.caption_en,mt.alt_text alt_local,mt.caption caption_local FROM gallery_items g JOIN media_library m ON m.id=g.media_id LEFT JOIN media_translations mt ON mt.media_id=m.id AND mt.lang=? WHERE g.active=1 ORDER BY g.sort_order,g.id',[$lang]);
 foreach($gallery as &$i){$origImage=$i['image'];$i['image']=media_src($origImage);$i['thumbnail']=media_src($i['thumbnail']??$origImage);$i['alt']=$i['alt_local']??($lang==='en'?$i['alt_en']:$i['alt_id']);$i['caption']=$i['caption_local']??($lang==='en'?$i['caption_en']:$i['caption_id']);}unset($i);
 $ui=[];foreach(sql_all('SELECT translation_key,translated_value FROM ui_translations WHERE lang=?',[$lang]) as $i)$ui[$i['translation_key']]=$i['translated_value'];
 $links=map_links($settings);
 $settings['map_embed_resolved']=$links['embed'];$settings['map_directions_resolved']=$links['directions'];
 $settings['whatsapp_url']='https://wa.me/'.preg_replace('/\D/','',(string)($settings['whatsapp_number']??'')).'?text='.rawurlencode((string)($settings['whatsapp_message']??''));
 return compact('lang','langs','settings','sectionsBy','cats','menu','gallery','ui');
}
