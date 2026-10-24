<?php
// Development server router: php -S localhost:8000 router.php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$file=__DIR__.rawurldecode($path);
if ($path !== '/' && is_file($file)) { return false; }
if ($path==='/' || $path==='/index.php') { require __DIR__.'/index.php'; return true; }
http_response_code(404); echo '404 — Halaman tidak ditemukan';
