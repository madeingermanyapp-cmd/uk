<?php
declare(strict_types=1);
namespace App\Engine;
/** Infrastructure-only opt-in. Public clients cannot assert their own TLS/IP. */
final class TrustedProxy
{
    public static function contains(string $address): bool
    {
        $packed=@inet_pton($address);if($packed===false)return false;
        foreach(explode(',',$_ENV['TRUSTED_PROXIES']??'') as $entry){
            $parts=explode('/',trim($entry),2);$network=@inet_pton($parts[0]);
            if($network===false||strlen($network)!==strlen($packed))continue;
            $bits=isset($parts[1])&&ctype_digit($parts[1])?(int)$parts[1]:(isset($parts[1])?-1:strlen($packed)*8);
            if($bits<0||$bits>strlen($packed)*8)continue;
            $bytes=intdiv($bits,8);$remainder=$bits%8;
            if(substr($packed,0,$bytes)!==substr($network,0,$bytes))continue;
            if($remainder&&((ord($packed[$bytes])^ord($network[$bytes]))&(255<<(8-$remainder))))continue;
            return true;
        }
        return false;
    }
}
