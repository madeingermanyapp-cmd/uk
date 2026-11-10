<?php
declare(strict_types=1);
namespace App\Engine;
/** Availability clock. Production always uses real time; only the isolated test
 * harness can freeze a historical DST fixture across CLI and HTTP workers. */
final class Clock
{
    private static ?\DateTimeImmutable $fixed=null;
    private static bool $loaded=false;
    public static function now(\DateTimeZone|string|null $timezone=null): \DateTimeImmutable
    {
        $tz=is_string($timezone)?new \DateTimeZone($timezone):($timezone??new \DateTimeZone('UTC'));
        if(!defined('VOXELBOOKING_TEST_PROCESS'))return new \DateTimeImmutable('now',$tz);
        if(!self::$loaded){
            self::$loaded=true;
            try{$value=Database::query("SELECT value FROM settings WHERE `key`='_isolated_test_clock'")[0]['value']??null;if($value)self::$fixed=new \DateTimeImmutable($value,new \DateTimeZone('UTC'));}catch(\PDOException){}
        }
        return self::$fixed?self::$fixed->setTimezone($tz):new \DateTimeImmutable('now',$tz);
    }
    public static function freezeForTests(?string $value): void
    {
        if(!defined('VOXELBOOKING_TEST_PROCESS'))throw new \LogicException('Only the isolated test harness can freeze time.');
        self::$fixed=$value?new \DateTimeImmutable($value,new \DateTimeZone('UTC')):null;self::$loaded=true;
        if(Database::tableExists('settings')){
            if($value===null)Database::execute("DELETE FROM settings WHERE `key`='_isolated_test_clock'");
            else Database::upsertSetting('_isolated_test_clock',$value);
        }
    }
}
