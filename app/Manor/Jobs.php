<?php
declare(strict_types=1);
namespace App\Manor;
use App\Engine\{Database as DB,Outbox,Mailer};
final class Jobs
{
    public static function run(): array
    {
        Mailer::clearConfigCache();$result=Outbox::dispatch(50);
        DB::execute("INSERT INTO manor_operations(name,data,checked_at) VALUES('background_jobs',?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE data=VALUES(data),checked_at=VALUES(checked_at)",[json_encode($result,JSON_THROW_ON_ERROR)]);
        DB::execute('DELETE FROM manor_access_tokens WHERE expires_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY)');
        return $result;
    }
}
