<?php
declare(strict_types=1);
namespace App\Manor;
use App\Engine\{Database as DB,Outbox,Mailer,View,Ulid};
use App\Agent\PayloadCipher;
final class Notifications
{
    public static function base(): string
    {
        $base=rtrim(Settings::get()['public_url']?:($_ENV['APP_URL']??getenv('APP_URL')?:''), '/');
        if(!$base)throw new \DomainException('Set the canonical APP_URL before creating invitation or recovery links.');
        $url=parse_url($base);
        if(!is_array($url)||!in_array($url['scheme']??'',['http','https'],true)||empty($url['host'])||isset($url['user']))throw new \DomainException('Invalid canonical APP_URL.');

        return $base;
    }
    public static function queue(string $requestId,string $event,?string $url=null): void
    {
        if(!(Settings::get()['notification_preferences']['booking']??true))return;
        $r=Service::request($requestId);$settings=Settings::get();
        $meta=DB::query('SELECT * FROM manor_request_details WHERE request_id=?',[$requestId])[0]??null;
        $locale=$meta['locale']??'en';if(!in_array($locale,['en','ar'],true))$locale='en';
        $path=dirname(__DIR__,2).'/lang/'.$locale.'/manor.php';$phrases=is_file($path)?require $path:[];
        if(!$url&&!empty($meta['secret_cipher'])){
            $secret=(new PayloadCipher())->decrypt($meta['secret_cipher']);
            try{$url=self::base().'/manor/request/'.$secret['request_token'].'?lang='.$locale;}catch(\DomainException){}
        }
        $eventLabel=$phrases['status_'.$event]??str_replace('_',' ',$event);
        $statusLabel=$phrases['status_'.$r['status']]??str_replace('_',' ',$r['status']);
        $template=$settings['email_templates'][$locale]??($locale==='ar'?"{{website_name}}\n{{event}}\nمرجع الطلب: {{reference}}\nالحالة: {{status}}\n{{link}}":"{{website_name}}\n{{event}}\nReference: {{reference}}\nStatus: {{status}}\n{{link}}");
        $text=strtr($template,['{{website_name}}'=>$settings['website_name'],'{{event}}'=>$eventLabel,'{{reference}}'=>$r['id'],'{{status}}'=>$statusLabel,'{{link}}'=>$url??'']);
        $body='<div lang="'.$locale.'" dir="'.($locale==='ar'?'rtl':'ltr').'">'.nl2br(View::e($text));
        if($url)$body.='<p><a href="'.View::e($url).'">'.View::e($phrases['open_secure_request']??__('manor.open_secure_request')).'</a></p>';
        $body.='</div>';
        Outbox::enqueue('manor.notification',['category'=>'booking','to'=>$r['customer_email'],'subject'=>$settings['website_name'].' · '.$eventLabel.' · '.$r['id'],'html'=>$body],null,Ulid::generate());
    }

    public static function guest(string $id): void
    {
        Settings::flag('invitations');
        if(!(Settings::get()['notification_preferences']['invitation']??true))return;
        $guest=DB::query('SELECT * FROM manor_guests WHERE id=? AND revoked_at IS NULL',[$id])[0]??null;
        if(!$guest||!$guest['email'])throw new \DomainException('Guest email is required.');
        $link=Invitations::links($guest)['invitation'];
        Outbox::enqueue('manor.notification',['category'=>'invitation','guest_id'=>$id,'to'=>$guest['email'],'subject'=>Settings::get()['website_name'].' · '.__('manor.invitation_subject'),'html'=>'<h1>'.View::e(__('manor.invitation_heading')).'</h1><p><a href="'.View::e($link).'">'.View::e(__('manor.invitation_link')).'</a></p>'],null,Ulid::generate());
    }
    public static function deliver(array $payload): array
    {
        if(($payload['category']??'')==='invitation'){
            if(empty(Settings::get()['flags']['invitations'])||!(Settings::get()['notification_preferences']['invitation']??true))return ['sent'=>false,'skipped'=>true];
            $guest=DB::query('SELECT request_id FROM manor_guests WHERE id=? AND revoked_at IS NULL',[$payload['guest_id']])[0]??null;
            if(!$guest||Service::request($guest['request_id'])['status']!=='confirmed')return ['sent'=>false,'skipped'=>true];
        }
        if(($payload['category']??'')==='booking'&&!(Settings::get()['notification_preferences']['booking']??true))return ['sent'=>false,'skipped'=>true];
        return Mailer::send($payload['to'],$payload['subject'],$payload['html'],'manor_notification');
    }
    public static function flush(): array
    {
        return Outbox::deliverEnqueued(['manor.notification'],['manor.notification'=>[self::class,'deliver']]);
    }
    public static function recover(string $email,string $reference): void
    {
        // Always return the same public outcome; only a matching recipient receives a short-lived link.
        $r=DB::query('SELECT r.id FROM manor_requests r JOIN customers c ON c.id=r.customer_id WHERE r.id=? AND c.email=?',[$reference,mb_strtolower(trim($email))])[0]??null;
        if(!$r)return;
        $token=bin2hex(random_bytes(32));
        DB::execute('INSERT INTO manor_access_tokens(id,request_id,token_hash,expires_at) VALUES(?,?,?,?)',[Ulid::generate(),$r['id'],hash('sha256',$token),gmdate('Y-m-d H:i:s',time()+1800)]);
        Outbox::enqueue('manor.notification',['to'=>$email,'subject'=>Settings::get()['website_name'].' · '.__('manor.recovery_subject'),'html'=>'<p><a href="'.View::e(self::base().'/manor/recover/'.$token).'">'.View::e(__('manor.recovery_link')).'</a></p><p>'.View::e(__('manor.recovery_expiry')).'</p>'],null,Ulid::generate());
    }
    public static function exchange(string $token): string
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new \DomainException('Recovery link has expired.');
        return Inventory::run(function()use($token){
            $row=DB::query('SELECT * FROM manor_access_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>UTC_TIMESTAMP() FOR UPDATE',[hash('sha256',$token)])[0]??null;
            if(!$row)throw new \DomainException('Recovery link has expired.');
            $new=bin2hex(random_bytes(32));
            DB::execute('UPDATE manor_requests SET token_hash=? WHERE id=?',[hash('sha256',$new),$row['request_id']]);
            DB::execute('UPDATE manor_request_details SET secret_cipher=? WHERE request_id=?',[(new PayloadCipher())->encrypt(['request_token'=>$new]),$row['request_id']]);
            DB::execute('UPDATE manor_access_tokens SET used_at=UTC_TIMESTAMP() WHERE request_id=?',[$row['request_id']]);Service::audit($row['request_id'],'customer_access_rotated');
            return $new;
        });
    }
}
