<?php
declare(strict_types=1);
namespace App\Manor;
use App\Engine\{Database as DB,Mailer,Auth};
use App\Agent\PayloadCipher;
final class MailSettings
{
    public static function get(): array
    {
        $row=DB::query("SELECT secret_cipher FROM manor_secrets WHERE name='smtp'")[0]??null;
        if(!$row)return ['configured'=>false,'password_saved'=>false,'host'=>'','port'=>587,'username'=>'','encryption'=>'tls','sender_email'=>'','sender_name'=>Settings::get()['website_name'],'transport'=>'smtp'];
        $data=(new PayloadCipher())->decrypt($row['secret_cipher']);
        $data['password_saved']=!empty($data['password']);unset($data['password']);$data['configured']=true;
        return $data;
    }
    public static function save(array $input): void
    {
        $old=DB::query("SELECT secret_cipher FROM manor_secrets WHERE name='smtp'")[0]??null;
        $previous=$old?(new PayloadCipher())->decrypt($old['secret_cipher']):[];
        $port=filter_var($input['port']??587,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]);
        $encryption=$input['encryption']??'tls';$transport=$input['transport']??'smtp';
        if(!$port||!in_array($encryption,['tls','ssl','none'],true)||!in_array($transport,['smtp','log'],true))throw new \DomainException('Invalid email transport settings.');
        if(!filter_var($input['sender_email']??'',FILTER_VALIDATE_EMAIL))throw new \DomainException('Enter a valid sender email.');
        $host=trim($input['host']??'');
        if($transport==='smtp'&&(!$host||strlen($host)>253||!preg_match('/^[a-zA-Z0-9.\-:]+$/D',$host)))throw new \DomainException('Enter a valid SMTP hostname or IP.');
        $password=(string)($input['password']??'');if($password==='')$password=$previous['password']??'';if(!empty($input['clear_password']))$password='';
        $data=['host'=>$host,'port'=>$port,'username'=>mb_substr($input['username']??'',0,255),'password'=>$password,'encryption'=>$encryption,'sender_email'=>$input['sender_email'],'sender_name'=>mb_substr($input['sender_name']??Settings::get()['website_name'],0,255),'transport'=>$transport];
        DB::execute("INSERT INTO manor_secrets(name,secret_cipher) VALUES('smtp',?) ON DUPLICATE KEY UPDATE secret_cipher=VALUES(secret_cipher)",[(new PayloadCipher())->encrypt($data)]);
        // Never mirror credentials into the legacy plaintext settings table.
        DB::execute("DELETE FROM settings WHERE `key`='smtp_password'");DB::upsertSetting('mail_transport','manor');DB::execute("DELETE FROM manor_operations WHERE name='smtp'");Mailer::clearConfigCache();Service::audit(null,'smtp_settings_changed');
    }
    /** Decrypt only inside mail delivery, never a UI/API response. */
    public static function transportConfig(): array
    {
        if(!DB::tableExists('manor_secrets'))return [];
        $row=DB::query("SELECT secret_cipher FROM manor_secrets WHERE name='smtp'")[0]??null;if(!$row)return [];
        $d=(new PayloadCipher())->decrypt($row['secret_cipher']);
        return ['mail_transport'=>$d['transport'],'smtp_host'=>$d['host'],'smtp_port'=>(string)$d['port'],'smtp_username'=>$d['username'],'smtp_password'=>$d['password'],'smtp_encryption'=>$d['encryption']==='none'?'':$d['encryption'],'mail_from_address'=>$d['sender_email'],'mail_from_name'=>$d['sender_name']];
    }
    public static function test(string $to): array
    {
        if(!filter_var($to,FILTER_VALIDATE_EMAIL))throw new \DomainException('Enter a valid test recipient.');
        $result=Mailer::send($to,'Wonham Manor House · email configuration test','<h1>'.\App\Engine\View::e(__('manor.email_test_title')).'</h1><p>'.\App\Engine\View::e(__('manor.email_test_body')).'</p>','manor_test');
        $transport=self::transportConfig()['mail_transport']??($_ENV['MAIL_TRANSPORT']??'smtp');
        $success=($result['sent']??false)&&$transport!=='log';
        DB::execute("INSERT INTO manor_operations(name,data,checked_at) VALUES('smtp',?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE data=VALUES(data),checked_at=VALUES(checked_at)",[json_encode(['delivered_to_transport'=>$success,'transport'=>$transport,'log_id'=>$result['log_id']??null],JSON_THROW_ON_ERROR)]);
        Service::audit(null,'smtp_test',['success'=>$success]);return ['success'=>$success,'log_only'=>$transport==='log'];
    }
}
