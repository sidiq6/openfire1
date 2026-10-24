<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
header('X-Content-Type-Options: nosniff');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')json_response(['error'=>'Metode tidak diizinkan'],405);
$lang=(string)($_GET['lang']??'id');
try{json_response(['ok'=>true,'data'=>public_payload($lang)]);}catch(Throwable $e){error_log('Public API: '.$e->getMessage());json_response(['error'=>'Konten belum tersedia. Periksa konfigurasi database.'],503);}
