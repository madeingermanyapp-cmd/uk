<?php
declare(strict_types=1);
namespace App\Manor;
use App\Engine\{Database as DB,Request};
final class Readiness
{
    public static function checks(Request $request): array
    {
        $settings=Settings::get();$base=dirname(__DIR__,2);$url=$settings['public_url']?:($_ENV['APP_URL']??'');$parsed=parse_url($url);
        $operations=[];foreach(DB::query('SELECT * FROM manor_operations') as $row)$operations[$row['name']]=['data'=>json_decode($row['data'],true),'checked_at'=>$row['checked_at']];
        $mail=$operations['smtp']??null;$jobs=$operations['background_jobs']??null;$backup=$operations['backup']??null;
        $published=DB::query("SELECT id,data FROM manor_content_versions WHERE status='published' LIMIT 1");
        $paths=['storage','storage/private','storage/keys','storage/media/manor','public/uploads'];$writable=true;foreach($paths as $path)if(!is_dir($base.'/'.$path)||!is_writable($base.'/'.$path))$writable=false;
        $domain=is_array($parsed)&&!empty($parsed['host'])&&in_array($parsed['scheme']??'',['http','https'],true);
        $contentValid=!empty($published)&&$settings['content_confirmed'];
        if($published)foreach(json_decode($published[0]['data'],true)['media']??[] as $media){
            if(preg_match('#^/manor/media/([a-f0-9]{48})\.[a-z0-9]+$#D',$media['url']??'',$match)){
                if(!DB::query("SELECT id FROM manor_media WHERE id=? AND status='published' AND deleted_at IS NULL AND rights<>''",[$match[1]]))$contentValid=false;
            }elseif(empty($media['rights_approved'])||empty($media['rights']))$contentValid=false;
        }
        if($domain)$domain=(bool)(filter_var($parsed['host'],FILTER_VALIDATE_IP)||@gethostbynamel($parsed['host'])||@dns_get_record($parsed['host'],DNS_AAAA));
        $passwordless=(int)DB::query("SELECT COUNT(*) AS c FROM operators WHERE password_hash='' OR password_hash IS NULL")[0]['c'];
        $pending=(int)DB::query("SELECT COUNT(*) AS c FROM delivery_outbox WHERE topic='manor.notification' AND status IN('pending','failed')")[0]['c'];
        $checks=[
            'domain'=>['ok'=>$domain,'detail'=>$url?:'Canonical public URL has not been configured.'],
            'https'=>['ok'=>$request->isSecure()&&($parsed['scheme']??'')==='https','detail'=>$request->isSecure()?'Current connection is HTTPS.':'Current connection is HTTP; suitable for local setup, not a public production release.'],
            'smtp'=>['ok'=>!empty($mail['data']['delivered_to_transport']),'detail'=>$mail?'Last transport test: '.$mail['checked_at'].' UTC':'No successful SMTP test recorded.'],
            'storage'=>['ok'=>$writable,'detail'=>$writable?'Required storage directories are writable.':'One or more storage directories are missing or not writable.'],
            'background_jobs'=>['ok'=>$jobs&&strtotime($jobs['checked_at'].' UTC')>time()-900,'detail'=>$jobs?'Last run '.$jobs['checked_at'].' UTC; pending/failed notifications: '.$pending:'No job heartbeat recorded.'],
            'backup'=>['ok'=>$backup&&strtotime($backup['checked_at'].' UTC')>time()-86400,'detail'=>$backup?'Last completed backup '.$backup['checked_at'].' UTC':'No completed backup recorded by the backup helper.'],
            'content'=>['ok'=>$contentValid,'detail'=>$contentValid?'Published content version and approval are recorded.':'Content is unpublished or a selected asset is missing current rights approval.'],
            'payments'=>['ok'=>(in_array('bank_transfer',$settings['payment_methods'],true)&&!empty(trim($settings['bank_details'])))||(!empty(DB::query("SELECT id FROM manor_quotes WHERE JSON_UNQUOTE(JSON_EXTRACT(data,'$.payment_url')) <> '' LIMIT 1"))&&in_array('external_link',$settings['payment_methods'],true)),'detail'=>'Bank details: '.(!empty($settings['bank_details'])?'configured':'missing').'. External links are attached to individual quotations.'],
            'debug'=>['ok'=>($_ENV['APP_DEBUG']??'false')!=='true','detail'=>'APP_DEBUG='.($_ENV['APP_DEBUG']??'false')],
            'authentication'=>['ok'=>$passwordless===0&&(int)DB::query('SELECT COUNT(*) AS c FROM operators')[0]['c']>0,'detail'=>'Operators require password hashes; venue management requires operator authorization.'],
            'installer'=>['ok'=>\App\Middleware\InstalledMiddleware::isInstalled(),'detail'=>'Initialized installations reject installer routes through middleware.'],
            'source'=>['ok'=>is_file($base.'/LICENSE')&&is_file($base.'/NOTICE_WONHAM.md'),'detail'=>'AGPL licence and modified-work notice are present.'],
        ];
        return $checks;
    }
}
