<?php
declare(strict_types=1);
namespace App\Manor;
use App\Engine\{Request,Response,View,Auth,Database as DB};
final class GuestController
{
    private function render(string $name,array $data=[]): Response
    {
        Language::apply();
        return View::response('manor.'.$name,$data+['settings'=>Content::publicSettings()])->header('Cache-Control','no-store')->header('Referrer-Policy','no-referrer')->header('X-Robots-Tag','noindex');
    }
    public function show(Request $r): Response
    {
        try{$guest=Invitations::find($r->getAttribute('token'));return $this->render('invitation',['guest'=>$guest,'token'=>$r->getAttribute('token')]);}
        catch(\DomainException){return Response::html(Language::error('Invitation unavailable.'),404);}
    }
    public function rsvp(Request $r): Response
    {
        try{Invitations::rsvp($r->getAttribute('token'),$r->string('rsvp'));return Response::redirect('/manor/invitation/'.$r->getAttribute('token'));}
        catch(\DomainException $e){return $this->render('error',['message'=>Language::error($e->getMessage())])->status(422);}
    }
    public function qr(Request $r): Response
    {
        try{$guest=Invitations::find($r->getAttribute('token'));return (new Response())->body(Invitations::qr($guest))->header('Content-Type','image/png')->header('Cache-Control','no-store')->header('Referrer-Policy','no-referrer');}
        catch(\DomainException){return Response::html(Language::error('QR unavailable.'),404);}
    }
    public function scan(Request $r): Response
    {
        if(!Auth::isOperator())return Response::html(Language::error('Forbidden'),403);
        try{
            $token=$r->getAttribute('token');$guest=Invitations::find($token,true);$result=null;
            if($r->method()==='POST')$result=Invitations::checkIn($token,$r->has('reason')?$r->string('reason'):null,$r->bool('undo'));
            return $this->render('check-in',['guest'=>$guest,'result'=>$result]);
        }catch(\DomainException $e){return $this->render('error',['message'=>Language::error($e->getMessage())])->status(422);}
    }
    public function manage(Request $r): Response
    {
        if(!Auth::isOperator())return Response::html(Language::error('Forbidden'),403);
        try{
            Settings::flag('invitations');$id=$r->query('request_id');$request=$id?Service::request($id):null;
            $guests=$id?DB::query('SELECT * FROM manor_guests WHERE request_id=? ORDER BY name',[$id]):[];
            return $this->render('guests',['record'=>$request,'guests'=>$guests,'templates'=>Invitations::templates(),'bookings'=>DB::query("SELECT r.id,u.name FROM manor_requests r JOIN manor_units u ON u.id=r.unit_id WHERE r.status='confirmed' ORDER BY r.starts_at"),'report'=>$id&&Settings::get()['flags']['qr_attendance']?Invitations::report($id):null]);
        }catch(\DomainException $e){return $this->render('error',['message'=>Language::error($e->getMessage())])->status(422);}
    }
    public function mutate(Request $r): Response
    {
        if(!Auth::isOperator())return Response::html(Language::error('Forbidden'),403);
        try{
            Settings::flag('invitations');
            switch($r->string('action')){
                case 'template':Invitations::saveTemplate($r->all());break;
                case 'invite':Invitations::create($r->string('request_id'),$r->all());break;
                case 'revoke':Invitations::revoke($r->string('guest_id'));break;
                case 'send':Notifications::guest($r->string('guest_id'));break;
                case 'attendance':$guest=DB::query('SELECT * FROM manor_guests WHERE id=?',[$r->string('guest_id')])[0]??null;if(!$guest)throw new \DomainException('Guest not found.');$tokens=(new \App\Agent\PayloadCipher())->decrypt($guest['token_cipher']);Invitations::checkIn($tokens['check_in'],$r->string('reason'),$r->bool('undo'));break;
                default:throw new \DomainException('Unknown action.');
            }
            Notifications::flush();return Response::redirect('/admin/manor/guests'.($r->string('request_id')?'?request_id='.rawurlencode($r->string('request_id')):''));
        }catch(\DomainException $e){return $this->render('error',['message'=>Language::error($e->getMessage())])->status(422);}
    }
    public function scanner(Request $r): Response
    {
        if(!Auth::isOperator())return Response::html(Language::error('Forbidden'),403);
        try{
            Settings::flag('qr_attendance');
            if($r->method()==='POST'){
                $file=$r->file('photo')??[];
                if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||($file['size']??0)>8*1024*1024||!is_uploaded_file($file['tmp_name']))throw new \DomainException('Upload a QR photograph up to 8 MB.');
                $size=@getimagesize($file['tmp_name']);
                if(!$size||$size[0]>4096||$size[1]>4096||!in_array($size['mime'],['image/png','image/jpeg'],true))throw new \DomainException('Use a JPEG or PNG QR photograph up to 4096 pixels.');
                $decoded=(new \Zxing\QrReader($file['tmp_name']))->text();
                $path=is_string($decoded)?parse_url($decoded,PHP_URL_PATH):null;
                if(!is_string($path)||!preg_match('#^/manor/check-in/([a-f0-9]{64})$#D',$path,$match))throw new \DomainException('No valid attendance QR code was found.');
                Invitations::find($match[1],true);
                return Response::redirect('/manor/check-in/'.$match[1]);
            }
            return $this->render('scanner');
        }catch(\DomainException $e){return $this->render('error',['message'=>Language::error($e->getMessage())])->status(422);}
    }
    public function csv(Request $r): Response
    {
        if(!Auth::isOperator())return Response::html(Language::error('Forbidden'),403);
        try{
            $report=Invitations::report($r->query('request_id'));$stream=fopen('php://temp','w+');fputcsv($stream,['Guest','RSVP','Checked in UTC'],',','"','');
            foreach($report['guests'] as $g){$name=preg_match('/^[=+@\-\t\r]/',$g['name'])?"'".$g['name']:$g['name'];fputcsv($stream,[$name,$g['rsvp'],$g['checked_at']],',','"','');}
            rewind($stream);$body=stream_get_contents($stream);fclose($stream);
            return (new Response())->body($body)->header('Content-Type','text/csv; charset=utf-8')->header('Content-Disposition','attachment; filename="attendance.csv"')->header('Cache-Control','no-store');
        }catch(\DomainException){return Response::html(Language::error('Report unavailable.'),404);}
    }
}
