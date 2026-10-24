<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
app_session();if(current_admin()){header('Location: '.site_url('backoffice-openfire/'));exit;}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!hash_equals(csrf(),(string)($_POST['csrf']??'')))throw new RuntimeException('Token keamanan kedaluwarsa. Muat ulang halaman.');
  $email=strtolower(trim((string)($_POST['email']??'')));$password=(string)($_POST['password']??'');
  $fingerprint=hash('sha256',ip_address().'|'.$email);
  $count=(int)(sql_one('SELECT COUNT(*) n FROM login_attempts WHERE fingerprint=? AND attempted_at>DATE_SUB(NOW(), INTERVAL 15 MINUTE)',[$fingerprint])['n']??0);
  if($count>=8)throw new RuntimeException('Terlalu banyak percobaan. Coba lagi setelah 15 menit.');
  $user=sql_one('SELECT * FROM admins WHERE email=?',[$email]);
  if(!$user || !password_verify($password,$user['password_hash'])){
   sql_run('INSERT INTO login_attempts(fingerprint) VALUES(?)',[$fingerprint]);
   throw new RuntimeException('Email atau password tidak sesuai.');
  }
  sql_run('DELETE FROM login_attempts WHERE fingerprint=?',[$fingerprint]);
  session_regenerate_id(true);
  $token=bin2hex(random_bytes(32));$_SESSION['admin_id']=(int)$user['id'];$_SESSION['admin_token']=$token;$_SESSION['csrf']=bin2hex(random_bytes(32));
  sql_run('INSERT INTO admin_sessions(admin_id,token_hash,ip_address,user_agent,expires_at,last_seen) VALUES(?,?,?,?,DATE_ADD(NOW(),INTERVAL 12 HOUR),NOW())',[(int)$user['id'],hash('sha256',$token),ip_address(),trunc((string)($_SERVER['HTTP_USER_AGENT']??''),255)]);
  audit((int)$user['id'],'login','admin login');header('Location: '.site_url('backoffice-openfire/'));exit;
 }catch(Throwable $e){$error=$e->getMessage();if($e instanceof PDOException){error_log($error);$error='Database belum tersedia.';}}
}
$token=csrf();
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Masuk — OPENFIRE Backoffice</title><link rel="stylesheet" href="<?=e(site_url('backoffice-openfire/assets/admin.css'))?>"></head><body class="auth-body">
<main class="auth-panel"><div class="auth-symbol">♨</div><p class="auth-eyebrow">PRIVATE BACKOFFICE</p><h1>Selamat datang<br>kembali.</h1><p class="auth-note">Masuk untuk mengelola konten OPENFIRE.</p>
<?php if($error):?><div class="notice danger" role="alert"><?=e($error)?></div><?php endif;?>
<form method="post" class="auth-form"><input type="hidden" name="csrf" value="<?=e($token)?>"><label>Email administrator<input type="email" name="email" autocomplete="username" required maxlength="190"></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><button class="btn primary" type="submit">Masuk ke dashboard <span aria-hidden="true">↗</span></button></form>
<a class="back-link" href="<?=e(site_url())?>">← Kembali ke website</a></main></body></html>
