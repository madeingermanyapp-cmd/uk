<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
while(true){
 try{if(\App\Engine\Database::tableExists('manor_operations'))\App\Manor\Jobs::run();}
 catch(\Throwable $e){\App\Engine\Logger::error('manor_worker_failed',['type'=>get_class($e)]);}
 sleep(30);
}
