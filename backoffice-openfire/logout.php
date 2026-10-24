<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('POST only');}
app_session();if(!hash_equals(csrf(),(string)($_POST['csrf']??''))){http_response_code(419);exit('CSRF invalid');}
destroy_login();header('Location: '.site_url('backoffice-openfire/login.php'));exit;
