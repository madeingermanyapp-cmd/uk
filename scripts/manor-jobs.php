<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
echo json_encode(\App\Manor\Jobs::run(),JSON_THROW_ON_ERROR).PHP_EOL;
