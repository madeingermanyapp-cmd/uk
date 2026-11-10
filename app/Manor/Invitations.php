<?php
declare(strict_types=1);
namespace App\Manor;
use App\Engine\{Database as DB,Ulid,Auth};
use App\Agent\PayloadCipher;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

final class Invitations
{
    public static function templates(): array
    {
        return DB::query('SELECT * FROM manor_invitation_templates ORDER BY name');
    }
    public static function saveTemplate(array $input): string
    {
        Settings::flag('invitations');
        $id=$input['template_id']??''; if(!$id)$id=Ulid::generate();
        $style=$input['style']??'heritage';
        if(!in_array($style,['heritage','garden','modern'],true)||!trim($input['name']??''))throw new \DomainException('Enter a template name and valid design.');
        $data=[];
        foreach(['heading','message','footer','heading_ar','message_ar','footer_ar'] as $key)$data[$key]=mb_substr(trim($input[$key]??''),0,3000);
        foreach(['accent'=>'#b45d32','background'=>'#fffaf0'] as $key=>$default){
            $value=$input[$key]??$default;if(!preg_match('/^#[a-fA-F0-9]{6}$/D',$value))throw new \DomainException('Use a six-digit colour value.');$data[$key]=$value;
        }
        DB::execute('INSERT INTO manor_invitation_templates(id,name,style,data,enabled) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),style=VALUES(style),data=VALUES(data),enabled=VALUES(enabled)',[$id,mb_substr($input['name'],0,255),$style,json_encode($data,JSON_THROW_ON_ERROR),!empty($input['enabled'])?1:0]);
        Service::audit(null,'invitation_template_saved',['template_id'=>$id]);return $id;
    }
    public static function create(string $requestId,array $input): string
    {
        Settings::flag('invitations');
        return Inventory::run(function()use($requestId,$input){
            $request=Service::request($requestId);
            if($request['status']!=='confirmed')throw new \DomainException('Invitations require a confirmed booking.');
            if(!DB::query('SELECT id FROM manor_invitation_templates WHERE id=? AND enabled=1',[$input['template_id']??'']))throw new \DomainException('Choose an enabled invitation template.');
            $name=trim($input['guest_name']??'');$email=trim($input['guest_email']??'');
            if(!$name||strlen($name)>255||($email&&!filter_var($email,FILTER_VALIDATE_EMAIL)))throw new \DomainException('Enter a valid guest name and email.');
            $count=(int)DB::query('SELECT COUNT(*) AS c FROM manor_guests WHERE request_id=? AND revoked_at IS NULL',[$requestId])[0]['c'];
            if($count>=(int)$request['guests'])throw new \DomainException('Guest invitations exceed the booking attendance limit.');
            $id=Ulid::generate();$token=bin2hex(random_bytes(32));$qr=bin2hex(random_bytes(32));
            $data=[];foreach(['event_name','host_name','venue_label'] as $key)$data[$key]=mb_substr(trim($input[$key]??''),0,255);
            if(!$data['event_name']||!$data['host_name'])throw new \DomainException('Enter the event and host names.');
            if(!$data['venue_label'])$data['venue_label']=$request['unit_name'];
            DB::execute('INSERT INTO manor_guests(id,request_id,template_id,name,email,token_hash,qr_hash,token_cipher,data) VALUES(?,?,?,?,?,?,?,?,?)',[$id,$requestId,$input['template_id'],$name,$email?:null,hash('sha256',$token),hash('sha256',$qr),(new PayloadCipher())->encrypt(['invitation'=>$token,'check_in'=>$qr]),json_encode($data,JSON_THROW_ON_ERROR)]);
            Service::audit($requestId,'guest_invited',['guest_id'=>$id]);
            if($email&&!empty($input['send']))Notifications::guest($id);
            return $id;
        });
    }
    public static function find(string $token,bool $qr=false): array
    {
        Settings::flag('invitations');if($qr)Settings::flag('qr_attendance');
        if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new \DomainException('Invitation not found.');
        $column=$qr?'qr_hash':'token_hash';
        $guest=DB::query("SELECT g.*,t.style,t.data AS template_data,t.enabled AS template_enabled FROM manor_guests g JOIN manor_invitation_templates t ON t.id=g.template_id WHERE g.$column=? AND g.revoked_at IS NULL",[hash('sha256',$token)])[0]??null;
        if(!$guest)throw new \DomainException('Invitation not found.');
        $r=Service::request($guest['request_id']);
        if($r['status']!=='confirmed'||strtotime($r['ends_at'].' UTC')<time()-7*86400)throw new \DomainException('This invitation is no longer active.');
        $guest['request']=$r;return $guest;
    }
    public static function links(array $guest): array
    {
        $tokens=(new PayloadCipher())->decrypt($guest['token_cipher']);$base=Notifications::base();
        return ['invitation'=>$base.'/manor/invitation/'.$tokens['invitation'],'check_in'=>$base.'/manor/check-in/'.$tokens['check_in']];
    }
    public static function rsvp(string $token,string $answer): void
    {
        Settings::flag('invitations');
        Inventory::run(function()use($token,$answer){
            $guest=self::find($token);
            if(!in_array($answer,['accepted','declined'],true))throw new \DomainException('Choose an RSVP response.');
            if($guest['request']['starts_at']<=gmdate('Y-m-d H:i:s'))throw new \DomainException('RSVP has closed for this event.');
            DB::execute('UPDATE manor_guests SET rsvp=?,rsvp_at=UTC_TIMESTAMP() WHERE id=?',[$answer,$guest['id']]);
            Service::audit($guest['request_id'],'guest_rsvp',['guest_id'=>$guest['id'],'rsvp'=>$answer]);
        });
    }
    /** Scanning is an operator action. Possessing a guest QR never grants scanning permission. */
    public static function checkIn(string $token,?string $manualReason=null,bool $undo=false): array
    {
        Settings::flag('invitations');Settings::flag('qr_attendance');
        if(!Auth::isOperator())throw new \DomainException('Operator access required.');
        return Inventory::run(function()use($token,$manualReason,$undo){
            $guest=self::find($token,true);
            $row=DB::query('SELECT * FROM manor_guests WHERE id=? FOR UPDATE',[$guest['id']])[0];
            if($manualReason!==null&&trim($manualReason)==='')throw new \DomainException('A manual attendance reason is required.');
            if($row['rsvp']==='declined'&&$manualReason===null)throw new \DomainException('Guest declined; use an audited manual override if appropriate.');
            if(!$undo&&$row['checked_at'])return ['duplicate'=>true,'guest_id'=>$row['id'],'checked_at'=>$row['checked_at']];
            DB::execute('UPDATE manor_guests SET checked_at=?,checked_by=? WHERE id=?',[$undo?null:gmdate('Y-m-d H:i:s'),Auth::user()['id'],$row['id']]);
            Service::audit($row['request_id'],$undo?'guest_attendance_removed':($manualReason===null?'guest_checked_in':'guest_manual_attendance'),['guest_id'=>$row['id'],'reason'=>$manualReason]);
            return ['duplicate'=>false,'guest_id'=>$row['id'],'checked_at'=>$undo?null:gmdate('Y-m-d H:i:s')];
        });
    }
    public static function revoke(string $id): void
    {
        Settings::flag('invitations');$guest=DB::query('SELECT * FROM manor_guests WHERE id=?',[$id])[0]??null;
        if(!$guest)throw new \DomainException('Guest not found.');
        DB::execute('UPDATE manor_guests SET revoked_at=UTC_TIMESTAMP() WHERE id=?',[$id]);Service::audit($guest['request_id'],'guest_revoked',['guest_id'=>$id]);
    }
    public static function qr(array $guest): string
    {
        Settings::flag('invitations');Settings::flag('qr_attendance');
        $code=new QrCode(data:self::links($guest)['check_in'],size:300,margin:16);
        return (new PngWriter())->write($code)->getString();
    }
    public static function report(string $requestId): array
    {
        Settings::flag('invitations');Settings::flag('qr_attendance');
        $guests=DB::query('SELECT * FROM manor_guests WHERE request_id=? AND revoked_at IS NULL ORDER BY name',[$requestId]);
        return ['guests'=>$guests,'total'=>count($guests),'accepted'=>count(array_filter($guests,fn($g)=>$g['rsvp']==='accepted')),'declined'=>count(array_filter($guests,fn($g)=>$g['rsvp']==='declined')),'attended'=>count(array_filter($guests,fn($g)=>$g['checked_at']!==null))];
    }
}
