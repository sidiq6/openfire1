<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
header('X-Content-Type-Options: nosniff');
$admin=require_admin();
function bound_id(mixed $v):int {return max(0,(int)$v);}
function asserted(bool $v,string $message):void {if(!$v)throw new InvalidArgumentException($message);}
function lang_check(string $v):void {asserted(valid_language($v),'Kode bahasa tidak tersedia.');}
function data_bundle():array {
 $settings=[];foreach(sql_all('SELECT setting_key,setting_value FROM website_settings ORDER BY setting_key') as $r)$settings[$r['setting_key']]=$r['setting_value'];
 $sections=sql_all('SELECT p.*,m.file_path image FROM page_sections p LEFT JOIN media_library m ON m.id=p.media_id ORDER BY p.sort_order,p.id');
 $sectionTranslations=sql_all('SELECT * FROM section_translations ORDER BY section_id,lang');
 $categories=sql_all('SELECT * FROM menu_categories ORDER BY sort_order,id');
 $categoryTranslations=sql_all('SELECT * FROM menu_category_translations ORDER BY category_id,lang');
 $items=sql_all('SELECT i.*,m.file_path image FROM menu_items i LEFT JOIN media_library m ON m.id=i.media_id ORDER BY i.sort_order,i.id');
 $itemTranslations=sql_all('SELECT * FROM menu_item_translations ORDER BY item_id,lang');
 $media=sql_all('SELECT * FROM media_library ORDER BY id DESC');
 $mediaTranslations=sql_all('SELECT * FROM media_translations ORDER BY media_id,lang');
 $gallery=sql_all('SELECT g.*,m.file_path image FROM gallery_items g JOIN media_library m ON m.id=g.media_id ORDER BY g.sort_order,g.id');
 $languages=sql_all('SELECT * FROM supported_languages ORDER BY is_default DESC,code');
 $uiTranslations=sql_all('SELECT * FROM ui_translations ORDER BY translation_key,lang');
 $stats=['products'=>(int)(sql_one('SELECT COUNT(*) n FROM menu_items')['n']??0),
  'active_products'=>(int)(sql_one('SELECT COUNT(*) n FROM menu_items WHERE active=1')['n']??0),
  'gallery'=>(int)(sql_one('SELECT COUNT(*) n FROM gallery_items WHERE active=1')['n']??0),
  'categories'=>(int)(sql_one('SELECT COUNT(*) n FROM menu_categories')['n']??0)];
 $logs=sql_all('SELECT l.*,a.name admin_name FROM admin_activity_logs l LEFT JOIN admins a ON a.id=l.admin_id ORDER BY l.id DESC LIMIT 12');
 $sessions=sql_all('SELECT id,ip_address,user_agent,expires_at,last_seen FROM admin_sessions WHERE admin_id=? AND expires_at>NOW() ORDER BY last_seen DESC',[(int)$_SESSION['admin_id']]);
 return compact('settings','sections','sectionTranslations','categories','categoryTranslations','items','itemTranslations','media','mediaTranslations','gallery','languages','uiTranslations','stats','logs','sessions');
}
function save_setting(string $key,string $value):void {sql_run('INSERT INTO website_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[$key,$value]);}
function safe_slug(string $value):string {return strtolower(trim((string)preg_replace('/[^a-z0-9-]+/','-',strtolower($value)),'-'));}
function verify_external(string $url,array $domains):bool {
 if($url==='')return true;
 $p=parse_url($url);if(!$p||strtolower($p['scheme']??'')!=='https')return false;
 $host=strtolower($p['host']??'');foreach($domains as $d){if($host===$d||str_ends_with($host,'.'.$d))return true;}return false;
}
function file_upload():array {
 asserted(isset($_FILES['file']) && is_array($_FILES['file']),'Pilih file gambar terlebih dahulu.');
 $f=$_FILES['file'];asserted(($f['error']??1)===UPLOAD_ERR_OK,'Upload gagal.');
 $temp=$f['tmp_name'];asserted(is_uploaded_file($temp),'Sumber file tidak valid.');
 $size=(int)$f['size'];asserted($size>0 && $size<=8*1024*1024,'File maksimal 8 MB.');
 $det=@getimagesize($temp);asserted(is_array($det) && isset($det[0],$det[1]),'Gambar tidak dapat dibaca.');
 [$w,$h]=$det;asserted($w>=100 && $h>=100 && $w<=6500 && $h<=6500 && $w*$h<=25000000,'Dimensi gambar tidak diperbolehkan.');
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($temp);
 $allow=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];asserted(isset($allow[$mime]) && ($det['mime']??'')===$mime,'Hanya JPEG, PNG, dan WebP yang valid.');
 $bytes=file_get_contents($temp);asserted($bytes!==false,'File gagal dibaca.');
 $name=bin2hex(random_bytes(18));$dir=ROOT_DIR.'/public/uploads';
 asserted(is_dir($dir) && is_writable($dir),'Folder uploads tidak bisa ditulis.');
 $outMime=$mime;$origExt=$allow[$mime];
 $originalRelative='/public/uploads/'.$name.'.'.$origExt;$thumbnailRelative='';$actualW=$w;$actualH=$h;
 if(function_exists('imagecreatefromstring') && function_exists('imagewebp')){
  $im=@imagecreatefromstring($bytes);asserted($im!==false,'Gambar tidak dapat diproses.');
  $max=1920; if(max($w,$h)>$max){$s=$max/max($w,$h);$nw=max(1,(int)round($w*$s));$nh=max(1,(int)round($h*$s));$res=imagecreatetruecolor($nw,$nh);imagealphablending($res,false);imagesavealpha($res,true);imagecopyresampled($res,$im,0,0,0,0,$nw,$nh,$w,$h);imagedestroy($im);$im=$res;$actualW=$nw;$actualH=$nh;}
  $outMime='image/webp';$originalRelative='/public/uploads/'.$name.'.webp';
  asserted(imagewebp($im,ROOT_DIR.$originalRelative,82),'Gagal menyimpan file gambar.');
  $ratio=min(1,560/max($actualW,$actualH));$tw=max(1,(int)($actualW*$ratio));$th=max(1,(int)($actualH*$ratio));
  $thumb=imagecreatetruecolor($tw,$th);imagecopyresampled($thumb,$im,0,0,0,0,$tw,$th,$actualW,$actualH);$thumbnailRelative='/public/uploads/'.$name.'-thumb.webp';imagewebp($thumb,ROOT_DIR.$thumbnailRelative,78);
  imagedestroy($thumb);imagedestroy($im);
 }else{
  asserted(move_uploaded_file($temp,ROOT_DIR.$originalRelative),'Gagal memindahkan file gambar.');
  $thumbnailRelative=$originalRelative;
 }
 $safeName=trunc(basename((string)$f['name']),240);
 $actualSize=filesize(ROOT_DIR.$originalRelative);
 sql_run('INSERT INTO media_library(file_path,thumbnail_path,original_filename,mime_type,width,height,byte_size,origin) VALUES (?,?,?,?,?,?,?,\'upload\')',[$originalRelative,$thumbnailRelative,$safeName,$outMime,$actualW,$actualH,$actualSize]);
 return ['id'=>(int)pdo()->lastInsertId(),'file_path'=>$originalRelative,'thumbnail_path'=>$thumbnailRelative];
}
try {
 if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){json_response(['ok'=>true,'data'=>data_bundle(),'admin'=>['id'=>$admin['id'],'name'=>$admin['name'],'email'=>$admin['email']],'csrf'=>csrf(),'base'=>base_path()]);}
 must_post();$multipart=str_contains($_SERVER['CONTENT_TYPE']??'','multipart/form-data');
 $d=$multipart?$_POST:request_json();check_csrf((string)($d['csrf']??($_SERVER['HTTP_X_CSRF_TOKEN']??'')));
 $action=req_string($d,'action',80);$uid=(int)$admin['id'];$result=[];
 if($action!=='media_upload')pdo()->beginTransaction();
 switch($action){
 case 'section_save':
  $id=bound_id($d['id']??0);$lang=req_string($d,'lang',8);lang_check($lang);asserted(sql_one('SELECT id FROM page_sections WHERE id=?',[$id])!==null,'Bagian tidak ditemukan.');
  $eyebrow=req_string($d,'eyebrow',500);$title=req_string($d,'title',500);$body=req_string($d,'body',10000);$button=req_string($d,'button_label',150);$button2=req_string($d,'button_label_secondary',150);
  asserted($title!=='','Judul wajib diisi.');
  sql_run('INSERT INTO section_translations(section_id,lang,eyebrow,title,body,button_label,button_label_secondary) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE eyebrow=VALUES(eyebrow),title=VALUES(title),body=VALUES(body),button_label=VALUES(button_label),button_label_secondary=VALUES(button_label_secondary)',[$id,$lang,$eyebrow,$title,$body,$button,$button2]);
  if(array_key_exists('media_id',$d)){$media=bound_id($d['media_id']);if($media)asserted(sql_one('SELECT id FROM media_library WHERE id=?',[$media])!==null,'Gambar tidak ditemukan.');sql_run('UPDATE page_sections SET media_id=? WHERE id=?',[$media?:null,$id]);}
  audit($uid,$action,'section '.$id.' '.$lang);break;
 case 'settings_save':
  $inputs=is_array($d['values']??null)?$d['values']:[];
  $allowed=['whatsapp_number','whatsapp_message','instagram_url','tiktok_url','address','landmark','maps_embed_url','maps_directions_url','latitude','longitude','contact_email','footer_note','site_title_id','site_title_en','site_description_id','site_description_en','show_prices','default_lang'];
  foreach($inputs as $key=>$val){if(!in_array($key,$allowed,true)){asserted((bool)preg_match('/^site_(title|description)_([a-z]{2})$/',$key,$parts),'Pengaturan tidak diizinkan.');lang_check($parts[2]);}$v=trunc((string)$val,2500);
   if($key==='whatsapp_number')asserted((bool)preg_match('/^\+?[0-9\s-]{8,25}$/',$v),'Nomor WhatsApp tidak valid.');
   if($key==='instagram_url')asserted(verify_external($v,['instagram.com']),'URL Instagram harus https://instagram.com');
   if($key==='tiktok_url')asserted(verify_external($v,['tiktok.com']),'URL TikTok harus https://tiktok.com');
   if($key==='maps_embed_url')asserted(verify_external($v,['google.com','google.co.id']),'Embed harus berasal dari Google Maps melalui HTTPS.');
   if($key==='maps_directions_url')asserted(verify_external($v,['google.com','google.co.id']),'Navigasi harus berasal dari Google Maps melalui HTTPS.');
   if($key==='contact_email'&&$v!=='')asserted(filter_var($v,FILTER_VALIDATE_EMAIL)!==false,'Email tidak valid.');
   if(($key==='latitude'||$key==='longitude')&&$v!=='')asserted(is_numeric($v) && abs((float)$v)<=($key==='latitude'?90:180),'Koordinat tidak valid.');
   if($key==='show_prices')$v=boolint($v)?'1':'0';
   if($key==='default_lang'){lang_check($v);asserted(sql_one('SELECT is_active FROM supported_languages WHERE code=?',[$v])['is_active']==1,'Bahasa belum aktif.');sql_run('UPDATE supported_languages SET is_default=(code=?)',[$v]);}
   save_setting($key,$v);
  }
  audit($uid,$action,'settings');break;
 case 'category_save':
  $id=bound_id($d['id']??0);$slug=safe_slug(req_string($d,'slug',120));asserted(strlen($slug)>1,'Slug kategori tidak valid.');
  $order=(int)($d['sort_order']??0);$active=boolint($d['active']??1);
  if($id){asserted(sql_one('SELECT id FROM menu_categories WHERE id=?',[$id])!==null,'Kategori tidak ditemukan.');sql_run('UPDATE menu_categories SET slug=?,sort_order=?,active=? WHERE id=?',[$slug,$order,$active,$id]);}
  else{sql_run('INSERT INTO menu_categories(slug,sort_order,active) VALUES (?,?,?)',[$slug,$order,$active]);$id=(int)pdo()->lastInsertId();}
  foreach(($d['translations']??[]) as $lang=>$t){lang_check((string)$lang);$n=trunc((string)($t['name']??''),180);asserted($n!=='','Nama kategori tidak boleh kosong.');sql_run('INSERT INTO menu_category_translations(category_id,lang,name,description) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description)',[$id,$lang,$n,trunc((string)($t['description']??''),3000)]);}
  audit($uid,$action,'category '.$id);$result=['id'=>$id];break;
 case 'category_delete':
  $id=bound_id($d['id']??0);sql_run('UPDATE menu_categories SET active=0 WHERE id=?',[$id]);audit($uid,$action,'category '.$id);break;
 case 'menu_save':
  $id=bound_id($d['id']??0);$category=bound_id($d['category_id']??0);$media=bound_id($d['media_id']??0);
  asserted(sql_one('SELECT id FROM menu_categories WHERE id=?',[$category])!==null,'Kategori tidak tersedia.');
  if($media)asserted(sql_one('SELECT id FROM media_library WHERE id=?',[$media])!==null,'Gambar tidak tersedia.');
  $slug=safe_slug(req_string($d,'slug',150));asserted(strlen($slug)>1,'Slug produk wajib diisi.');
  $raw=$d['price']??null;$price=($raw===null||$raw==='')?null:(float)$raw;
  asserted($price===null||(is_numeric($raw)&&$price>=0&&$price<=999999999),'Harga tidak valid.');
  $show=boolint($d['show_price']??0);if($price===null)$show=0;
  $values=[$slug,$category,$media?:null,$price,$show,boolint($d['featured']??0),boolint($d['is_available']??1),boolint($d['active']??1),(int)($d['sort_order']??0)];
  if($id){asserted(sql_one('SELECT id FROM menu_items WHERE id=?',[$id])!==null,'Produk tidak ditemukan.');sql_run('UPDATE menu_items SET slug=?,category_id=?,media_id=?,price=?,show_price=?,featured=?,is_available=?,active=?,sort_order=? WHERE id=?',[...$values,$id]);}
  else {sql_run('INSERT INTO menu_items(slug,category_id,media_id,price,show_price,featured,is_available,active,sort_order) VALUES(?,?,?,?,?,?,?,?,?)',$values);$id=(int)pdo()->lastInsertId();}
  foreach(($d['translations']??[]) as $lang=>$t){lang_check((string)$lang);$name=trunc((string)($t['name']??''),200);asserted($name!=='','Nama menu tidak boleh kosong.');sql_run('INSERT INTO menu_item_translations(item_id,lang,name,description) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description)',[$id,$lang,$name,trunc((string)($t['description']??''),4000)]);}
  audit($uid,$action,'item '.$id);$result=['id'=>$id];break;
 case 'menu_delete':
  $id=bound_id($d['id']??0);sql_run('UPDATE menu_items SET active=0 WHERE id=?',[$id]);audit($uid,$action,'item '.$id);break;
 case 'gallery_save':
  $id=bound_id($d['id']??0);$media=bound_id($d['media_id']??0);asserted(sql_one('SELECT id FROM media_library WHERE id=?',[$media])!==null,'Gambar tidak ditemukan.');
  $order=(int)($d['sort_order']??0);$active=boolint($d['active']??1);
  if($id)sql_run('UPDATE gallery_items SET media_id=?,sort_order=?,active=? WHERE id=?',[$media,$order,$active,$id]);
  else{sql_run('INSERT INTO gallery_items(media_id,sort_order,active) VALUES(?,?,?)',[$media,$order,$active]);$id=(int)pdo()->lastInsertId();}
  audit($uid,$action,'gallery '.$id);$result=['id'=>$id];break;
 case 'gallery_delete':
  $id=bound_id($d['id']??0);sql_run('DELETE FROM gallery_items WHERE id=?',[$id]);audit($uid,$action,'gallery '.$id);break;
 case 'media_save':
  $id=bound_id($d['id']??0);asserted(sql_one('SELECT id FROM media_library WHERE id=?',[$id])!==null,'Media tidak ditemukan.');
  sql_run('UPDATE media_library SET alt_id=?,alt_en=?,caption_id=?,caption_en=? WHERE id=?',[req_string($d,'alt_id',255),req_string($d,'alt_en',255),req_string($d,'caption_id',255),req_string($d,'caption_en',255),$id]);
  foreach(['id'=>['alt_id','caption_id'],'en'=>['alt_en','caption_en']] as $lang=>$fields){sql_run('INSERT INTO media_translations(media_id,lang,alt_text,caption) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE alt_text=VALUES(alt_text),caption=VALUES(caption)',[$id,$lang,req_string($d,$fields[0],255),req_string($d,$fields[1],255)]);}
  foreach(($d['translations']??[]) as $lang=>$t){lang_check((string)$lang);sql_run('INSERT INTO media_translations(media_id,lang,alt_text,caption) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE alt_text=VALUES(alt_text),caption=VALUES(caption)',[$id,$lang,trunc((string)($t['alt_text']??''),255),trunc((string)($t['caption']??''),255)]);}
  audit($uid,$action,'media '.$id);break;
 case 'media_delete':
  $id=bound_id($d['id']??0);$m=sql_one('SELECT * FROM media_library WHERE id=?',[$id]);asserted($m!==null,'Media tidak ditemukan.');asserted($m['origin']==='upload','Aset bawaan tidak dapat dihapus.');
  foreach(['page_sections','menu_items','gallery_items'] as $table)asserted((int)(sql_one("SELECT COUNT(*) n FROM $table WHERE media_id=?",[$id])['n']??0)===0,'Gambar masih digunakan oleh halaman atau produk.');
  sql_run('DELETE FROM media_library WHERE id=?',[$id]);foreach([$m['file_path'],$m['thumbnail_path']] as $path){if(str_starts_with((string)$path,'/public/uploads/')){@unlink(ROOT_DIR.$path);}}audit($uid,$action,'media '.$id);break;
 case 'media_upload':
  $result=file_upload();audit($uid,$action,'media '.$result['id']);break;
 case 'ui_save':
  $lang=req_string($d,'lang',8);lang_check($lang);$key=req_string($d,'key',120);
  asserted((bool)preg_match('/^[a-z0-9_.-]{2,120}$/',$key),'Kode label tidak valid.');
  $value=req_string($d,'value',5000);asserted($value!=='','Teks terjemahan tidak boleh kosong.');
  sql_run('INSERT INTO ui_translations(lang,translation_key,translated_value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE translated_value=VALUES(translated_value)',[$lang,$key,$value]);audit($uid,$action,$lang.' '.$key);break;
 case 'language_save':
  $code=req_string($d,'code',8);asserted((bool)preg_match('/^[a-z]{2}$/',$code),'Kode bahasa dua huruf kecil (contoh: fr).');
  $name=req_string($d,'name',80);asserted($name!=='','Nama bahasa wajib diisi.');$active=boolint($d['is_active']??0);
  if($active && $code!=='id'){
   $missingUI=(int)(sql_one('SELECT COUNT(*) n FROM ui_translations i LEFT JOIN ui_translations t ON t.translation_key=i.translation_key AND t.lang=? WHERE i.lang=\'id\' AND (t.translation_key IS NULL OR t.translated_value=\'\')',[$code])['n']??0);
   $missingSections=(int)(sql_one('SELECT COUNT(*) n FROM page_sections p LEFT JOIN section_translations t ON t.section_id=p.id AND t.lang=? WHERE p.active=1 AND (t.section_id IS NULL OR t.title=\'\' OR t.body=\'\')',[$code])['n']??0);
   $missingCats=(int)(sql_one('SELECT COUNT(*) n FROM menu_categories c LEFT JOIN menu_category_translations t ON t.category_id=c.id AND t.lang=? WHERE c.active=1 AND (t.category_id IS NULL OR t.name=\'\' OR t.description=\'\')',[$code])['n']??0);
   $missingMenu=(int)(sql_one('SELECT COUNT(*) n FROM menu_items i LEFT JOIN menu_item_translations t ON t.item_id=i.id AND t.lang=? WHERE i.active=1 AND (t.item_id IS NULL OR t.name=\'\' OR t.description=\'\')',[$code])['n']??0);
   $missingGallery=(int)(sql_one('SELECT COUNT(*) n FROM gallery_items g LEFT JOIN media_translations t ON t.media_id=g.media_id AND t.lang=? WHERE g.active=1 AND (t.media_id IS NULL OR t.alt_text=\'\' OR t.caption=\'\')',[$code])['n']??0);
   $missingMedia=(int)(sql_one('SELECT COUNT(*) n FROM menu_items i LEFT JOIN media_translations t ON t.media_id=i.media_id AND t.lang=? WHERE i.active=1 AND i.media_id IS NOT NULL AND (t.media_id IS NULL OR t.alt_text=\'\')',[$code])['n']??0);
   $metaTitle=sql_one('SELECT setting_value FROM website_settings WHERE setting_key=?',['site_title_'.$code]);
   $metaDesc=sql_one('SELECT setting_value FROM website_settings WHERE setting_key=?',['site_description_'.$code]);
   asserted($missingUI+$missingSections+$missingCats+$missingMenu+$missingGallery+$missingMedia===0 && trim($metaTitle['setting_value']??'')!=='' && trim($metaDesc['setting_value']??'')!=='','Bahasa belum lengkap. Terjemahkan semua label UI, halaman, kategori, produk, media, galeri dan SEO.');
  }
  asserted(!($code==='id' && !$active),'Bahasa Indonesia tidak dapat dinonaktifkan.');
  sql_run('INSERT INTO supported_languages(code,name,is_active) VALUES(?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),is_active=VALUES(is_active)',[$code,$name,$active]);
  $default=sql_one('SELECT is_default FROM supported_languages WHERE code=?',[$code]);asserted(!(!$active && $default && (int)$default['is_default']===1),'Bahasa default tidak dapat dinonaktifkan.');
  audit($uid,$action,$code);break;
 case 'profile_save':
  $name=req_string($d,'name',120);$email=req_string($d,'email',190);asserted($name!==''&&filter_var($email,FILTER_VALIDATE_EMAIL)!==false,'Nama atau email tidak valid.');
  sql_run('UPDATE admins SET name=?,email=? WHERE id=?',[$name,$email,$uid]);audit($uid,$action,'account');break;
 case 'password_change':
  $old=(string)($d['old_password']??'');$new=(string)($d['new_password']??'');
  asserted(strlen($new)>=12 && strlen($new)<=128,'Password baru minimal 12 karakter.');
  $hash=sql_one('SELECT password_hash FROM admins WHERE id=?',[$uid]);asserted($hash && password_verify($old,$hash['password_hash']),'Password saat ini salah.');
  sql_run('UPDATE admins SET password_hash=? WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$uid]);
  sql_run('DELETE FROM admin_sessions WHERE admin_id=? AND id<>?',[$uid,(int)$admin['session_id']]);audit($uid,$action,'password');break;
 case 'session_revoke':
  $sid=bound_id($d['id']??0);asserted($sid!==(int)$admin['session_id'],'Keluar dari sesi saat ini melalui tombol Logout.');
  sql_run('DELETE FROM admin_sessions WHERE id=? AND admin_id=?',[$sid,$uid]);audit($uid,$action,'session '.$sid);break;
 default: throw new InvalidArgumentException('Aksi tidak dikenal.');
 }
 if(pdo()->inTransaction())pdo()->commit();
 json_response(['ok'=>true,'result'=>$result]);
}catch(InvalidArgumentException $e){try{if(pdo()->inTransaction())pdo()->rollBack();}catch(Throwable $ignored){}json_response(['error'=>$e->getMessage()],422);}catch(PDOException $e){try{if(pdo()->inTransaction())pdo()->rollBack();}catch(Throwable $ignored){}error_log('Admin DB '.$e->getMessage());$message=($e->getCode()==='23000')?'Data duplikat atau masih digunakan. Periksa slug/email/relasi.':'Database tidak dapat menyimpan perubahan.';json_response(['error'=>$message],409);}catch(Throwable $e){try{if(pdo()->inTransaction())pdo()->rollBack();}catch(Throwable $ignored){}error_log('Admin '.$e->getMessage());json_response(['error'=>'Terjadi kesalahan server. Coba lagi atau cek log.'],500);}
