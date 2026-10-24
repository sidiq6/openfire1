<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
header('X-Robots-Tag: noindex, nofollow');app_session();
$error='';$done=false;$configuredToken=envv('INSTALL_TOKEN');
try{$adminExists=(int)(sql_one('SELECT COUNT(*) n FROM admins')['n']??0)>0;}catch(Throwable $e){$adminExists=false;$error='Database belum terpasang atau belum bisa diakses. Baca README.md.';}
if($adminExists){http_response_code(403);exit('Instalasi administrator sudah selesai. Halaman ini tidak lagi aktif.');}
if($_SERVER['REQUEST_METHOD']==='POST' && !$error){
 try{
  if(strlen($configuredToken)<32 || str_starts_with($configuredToken,'GANTI_'))throw new RuntimeException('Atur INSTALL_TOKEN acak minimal 32 karakter di .env terlebih dahulu.');
  if(!hash_equals(csrf(),(string)($_POST['csrf']??'')))throw new RuntimeException('Token formulir tidak valid.');
  if(!hash_equals($configuredToken,(string)($_POST['install_token']??'')))throw new RuntimeException('Token instalasi salah.');
  $name=trunc((string)($_POST['name']??''),120);$email=strtolower(trunc((string)($_POST['email']??''),190));$password=(string)($_POST['password']??'');
  if(!$name||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Nama dan email valid wajib diisi.');
  if(strlen($password)<12 || strlen($password)>128)throw new RuntimeException('Password minimal 12 karakter.');
  if(!hash_equals($password,(string)($_POST['confirm']??'')))throw new RuntimeException('Konfirmasi password berbeda.');
  pdo()->beginTransaction();
  if((int)(sql_one('SELECT COUNT(*) n FROM admins FOR UPDATE')['n']??0)>0)throw new RuntimeException('Akun sudah dibuat.');
  sql_run('INSERT INTO admins(name,email,password_hash) VALUES(?,?,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
  pdo()->commit();$done=true;
 }catch(Throwable $e){try{if(pdo()->inTransaction())pdo()->rollBack();}catch(Throwable $ignored){}$error=$e->getMessage();}
}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Setup Aman — OPENFIRE</title><link rel="stylesheet" href="<?=e(site_url('backoffice-openfire/assets/admin.css'))?>"></head><body class="auth-body"><main class="auth-panel"><div class="auth-symbol">♨</div><p class="auth-eyebrow">ONE-TIME SECURE SETUP</p><h1>Administrator<br>pertama.</h1><p class="auth-note">Halaman otomatis terkunci setelah akun pertama dibuat. Jangan bagikan token instalasi.</p>
<?php if($error):?><div class="notice danger"><?=e($error)?></div><?php endif;?>
<?php if($done):?><div class="notice success">Akun berhasil dibuat. Demi keamanan, hapus atau kosongkan INSTALL_TOKEN di .env.</div><a class="btn primary" href="<?=e(site_url('backoffice-openfire/login.php'))?>">Masuk ke Dashboard</a>
<?php elseif(!$error || strlen($configuredToken)>=32):?><form method="post" class="auth-form"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><label>Token instalasi<input type="password" name="install_token" required autocomplete="off"></label><label>Nama administrator<input name="name" required maxlength="120"></label><label>Email admin<input type="email" name="email" required></label><label>Password (minimal 12 karakter)<input type="password" name="password" minlength="12" required autocomplete="new-password"></label><label>Konfirmasi password<input type="password" name="confirm" minlength="12" required autocomplete="new-password"></label><button class="btn primary" type="submit">Buat Admin Pertama</button></form><?php endif;?></main></body></html>
