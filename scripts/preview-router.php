<?php
// Development server only; production uses Apache/Nginx with public/ document root.
$public=realpath(dirname(__DIR__).'/public');
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
if(str_starts_with($path,'/uploads/manor/')){http_response_code(404);exit;}
$file=realpath($public.'/'.ltrim($path,'/'));
if($file && str_starts_with($file,$public.'/') && is_file($file) && pathinfo($file,PATHINFO_EXTENSION)!=='php') return false;
require $public.'/index.php';
