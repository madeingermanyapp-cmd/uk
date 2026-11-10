<?php
declare(strict_types=1);
namespace App\Manor;
use App\Engine\Locale;
final class Language
{
    public static function apply(): string
    {
        $locale=$_GET['lang']??($_SESSION['manor_locale']??'en');
        if(!is_string($locale)||!in_array($locale,['en','ar'],true))$locale='en';
        if($locale==='ar'&&\App\Engine\Database::tableExists('manor_config')&&!in_array('ar',Settings::get()['languages'],true))$locale='en';
        if(session_status()===PHP_SESSION_ACTIVE)$_SESSION['manor_locale']=$locale;
        Locale::setLocale($locale);return $locale;
    }
    public static function link(string $locale): string
    {
        $query=$_GET;$query['lang']=$locale;
        return (parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/').'?'.http_build_query($query);
    }
    public static function error(string $english): string
    {
        $key='manor_errors.'.substr(hash('sha256',$english),0,16);$value=__($key);return $value===$key?$english:$value;
    }
}
