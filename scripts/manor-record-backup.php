<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
\App\Engine\Database::execute("INSERT INTO manor_operations(name,data,checked_at) VALUES('backup',?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE data=VALUES(data),checked_at=VALUES(checked_at)",[json_encode(['database_dump'=>true,'storage_copy'=>true,'uploads_copy'=>true],JSON_THROW_ON_ERROR)]);
