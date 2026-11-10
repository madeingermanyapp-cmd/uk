<?php
declare(strict_types=1);
namespace App\Manor;
use App\Engine\{Request,Response,View,Database as DB,Auth,Ulid};
final class Controller
{
    private function render(string $template,array $data=[]): Response
    {
        return View::response('manor.'.$template,$data+['settings'=>Settings::get()])->header('Cache-Control','no-store')->header('Referrer-Policy','no-referrer')->header('X-Robots-Tag','noindex')->header('Content-Security-Policy',self::csp());
    }
    public function home(Request $r): Response
    {
        if(!DB::query("SELECT id FROM tenants WHERE slug='wonham-manor' LIMIT 1"))return (new \App\Controllers\HomeController())->index($r);
        return View::response('manor.home',['settings'=>Settings::get(),'units'=>DB::query("SELECT * FROM manor_units WHERE enabled=1 ORDER BY CASE WHEN mode='ENTIRE_PROPERTY' THEN 1 ELSE 0 END,name DESC")])->header('Content-Security-Policy',self::csp());
    }
    private static function json(string $value): array
    {
        $data=json_decode($value,true,flags:JSON_THROW_ON_ERROR);
        if(!is_array($data))throw new \DomainException('Expected a JSON object or list.');
        return $data;
    }
    private static function csp(): string
    {
        return "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: https:; media-src 'self' https:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'";
    }
    public function enquiry(Request $r): Response
    {
        return $this->render('enquiry',['units'=>DB::query('SELECT * FROM manor_units WHERE enabled=1 ORDER BY name')]);
    }
    public function create(Request $r): Response
    {
        try{$result=Service::create($r->all());return Response::redirect('/manor/request/'.$result['token']);}
        catch(\DomainException $e){return $this->render('error',['message'=>$e->getMessage()],)->status(422);}
    }
    public function portal(Request $r): Response
    {
        try{$request=Service::capability($r->getAttribute('token'));return $this->detail($request,false,$r->getAttribute('token'));}
        catch(\DomainException){return Response::html('Request not found.',404);}
    }
    private function detail(array $request,bool $admin,string $token=''): Response
    {
        return $this->render('request',['record'=>$request,'admin'=>$admin,'token'=>$token,'quotes'=>DB::query('SELECT * FROM manor_quotes WHERE request_id=? ORDER BY version DESC',[$request['id']]),'documents'=>DB::query('SELECT * FROM manor_documents WHERE request_id=?',[$request['id']]),'payments'=>DB::query('SELECT * FROM manor_payments WHERE request_id=? ORDER BY created_at',[$request['id']]),'history'=>$admin?DB::query('SELECT * FROM manor_history WHERE request_id=? ORDER BY created_at',[$request['id']]):[]]);
    }
    public function customerAction(Request $r): Response
    {
        try {
            $record=Service::capability($r->getAttribute('token')); $id=$record['id'];
            match($r->string('action')){
                'submit'=>Service::move($id,'submitted',false), 'cancel'=>Service::move($id,'cancelled',false),
                'accept'=>Service::accept($id,$r->string('quote_id'),is_array($r->all()['selected']??null)?$r->all()['selected']:[]),
                'upload'=>Files::upload($id,$r->string('type'),$r->file('document')??[]),
                default=>throw new \DomainException('Unknown action.'),
            };
            return Response::redirect('/manor/request/'.$r->getAttribute('token'));
        }catch(\DomainException $e){return $this->render('error',['message'=>$e->getMessage()])->status(422);}
    }
    private function admin(): void
    {
        if(!Auth::isOperator()) throw new \DomainException('Operator access required.');
    }
    public function dashboard(Request $r): Response
    {
        try{$this->admin();}catch(\DomainException){return Response::html('Forbidden',403);}
        if($r->string('id')) return $this->detail(Service::request($r->string('id')),true);
        return $this->render('admin',['records'=>DB::query('SELECT r.*,u.name AS unit_name FROM manor_requests r JOIN manor_units u ON u.id=r.unit_id ORDER BY r.created_at DESC LIMIT 200'),'units'=>DB::query('SELECT * FROM manor_units ORDER BY name'),'blocks'=>DB::query('SELECT DISTINCT request_id,starts_at,ends_at FROM manor_allocations WHERE request_id NOT IN(SELECT id FROM manor_requests)'), 'messages'=>DB::query('SELECT * FROM manor_messages ORDER BY created_at DESC LIMIT 100')]);
    }
    public function adminAction(Request $r): Response
    {
        try {
            $this->admin(); $id=$r->string('id');
            switch($r->string('action')) {
                case 'transition':Service::move($id,$r->string('status'),true);break;
                case 'hold':Service::hold($id);break;
                case 'reschedule':Service::reschedule($id,$r->all());break;
                case 'quote':Service::quote($id,self::json($r->string('quote')));break;
                case 'payment':Service::payment($id,$r->all());break;
                case 'verify':Service::verify($id,$r->string('payment_id'),$r->string('status'));break;
                case 'settings':Settings::save(self::json($r->string('settings')));Service::audit(null,'settings_changed');break;
                case 'unit':$this->saveUnit($r);break;
                case 'media':$url=Files::media($r->file('media')??[]);$s=Settings::get();$s['media'][]=['url'=>$url,'alt'=>$r->string('alt'),'section'=>$r->string('section'),'kind'=>str_ends_with($url,'.mp4')?'video':'image'];Settings::save($s);Service::audit(null,'media_uploaded');break;
                case 'block':$this->block($r);break;
                case 'unblock':Inventory::run(function()use($r){$block=$r->string('block_id');if(DB::query('SELECT id FROM manor_requests WHERE id=?',[$block]))throw new \DomainException('Cancel the request to release its inventory.');Inventory::release($block);Service::audit(null,'inventory_unblocked',['block_id'=>$block]);});break;
                default:throw new \DomainException('Unknown action.');
            }
            return Response::redirect('/admin/manor'.($id?'?id='.rawurlencode($id):''));
        }catch(\DomainException|\JsonException $e){return $this->render('error',['message'=>$e->getMessage()])->status(422);}
    }
    private function saveUnit(Request $r): void
    {
        Inventory::run(function($tenant)use($r){
            $id=$r->string('unit_id')?:Ulid::generate();$parent=$r->string('parent_id')?:null;$mode=$r->string('mode');
            if(!in_array($mode,Rules::MODES,true)||!$r->string('name')) throw new \DomainException('Enter a name and valid rental mode.');
            if($parent && ($parent===$id || !DB::query('SELECT id FROM manor_units WHERE id=?',[$parent]))) throw new \DomainException('Invalid parent.');
            $ancestor=$parent; $seen=[$id]; while($ancestor){if(in_array($ancestor,$seen,true))throw new \DomainException('Hierarchy cycle.');$seen[]=$ancestor;$ancestor=DB::query('SELECT parent_id FROM manor_units WHERE id=?',[$ancestor])[0]['parent_id']??null;}
            $existing=DB::query('SELECT * FROM manor_units WHERE id=?',[$id])[0]??null;
            if($existing && ($existing['parent_id']!==$parent || $existing['mode']!==$mode)) throw new \DomainException('Existing inventory topology is immutable; create a new unit to preserve reservation semantics.');
            $data=self::json($r->string('data','{}'));
            if(!in_array($data['pricing_mode']??'PRICE_ON_REQUEST',['SHOW_PRICE','PRICE_ON_REQUEST','HIDDEN_FROM_PUBLIC'],true)) throw new \DomainException('Invalid pricing display mode.');
            if(isset($data['price'])) Rules::pence((string)$data['price']);
            $resource=$existing['resource_id']??null;
            if(!$existing && DB::query('SELECT id FROM manor_allocations WHERE expires_at IS NULL OR expires_at>UTC_TIMESTAMP() LIMIT 1')) throw new \DomainException('Resolve active reservations and blocks before extending physical inventory.');
            if(!$existing && !in_array($mode,['ENTIRE_PROPERTY','PARTIAL_VENUE','BUILDING','FLOOR'],true)){
                $resource=Ulid::generate();DB::execute('INSERT INTO resources(id,tenant_id,name) VALUES(?,?,?)',[$resource,$tenant['id'],$r->string('name')]);
            }
            DB::execute('INSERT INTO manor_units(id,tenant_id,parent_id,resource_id,name,mode,data,enabled) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),data=VALUES(data),enabled=VALUES(enabled)',[$id,$tenant['id'],$parent,$resource,$r->string('name'),$mode,json_encode($data),$r->bool('enabled')?1:0]);Service::audit(null,'unit_saved',['unit_id'=>$id]);
        });
    }
    private function block(Request $r): void
    {
        Inventory::run(function($tenant)use($r){
            [$start,$end,$guests]=Service::dates($r->string('unit_id'),$r->all());
            $id=Ulid::generate();
            // Admin blocks consume exactly the same resources as reservations; no fabricated customers.
            Inventory::reserve(['id'=>$id,'unit_id'=>$r->string('unit_id'),'starts_at'=>$start,'ends_at'=>$end],null,false,$tenant);
            Service::audit(null,'inventory_blocked',['block_id'=>$id]);
        });
    }
    public function download(Request $r): Response
    {
        $doc=DB::query('SELECT * FROM manor_documents WHERE id=?',[$r->getAttribute('id')])[0]??null;
        if(!$doc)return Response::html('Not found',404);
        $allowed=Auth::isOperator();
        if(!$allowed){try{$allowed=Service::capability($r->string('token'))['id']===$doc['request_id'];}catch(\DomainException){}}
        if(!$allowed)return Response::html('Not found',404);
        $path=dirname(__DIR__,2).'/storage/private/manor/'.$doc['storage_name'];
        if(!is_file($path))return Response::html('Not found',404);
        return (new Response())->body(file_get_contents($path))->header('Content-Type','application/octet-stream')->header('Content-Disposition','attachment; filename="document-'. $doc['id'].'.'.(['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'][$doc['mime']]??'bin').'"')->header('Cache-Control','no-store')->header('X-Content-Type-Options','nosniff');
    }
    public function message(Request $r): Response
    {
        try {
            $kind=$r->string('kind'); Settings::flag($kind==='newsletter'?'newsletter':'contact');
            if(!in_array($kind,['newsletter','contact'],true)||!filter_var($r->string('email'),FILTER_VALIDATE_EMAIL)||!$r->bool('consent')||$r->string('website')) throw new \DomainException('Enter a valid email and consent.');
            DB::execute('INSERT INTO manor_messages(id,kind,data) VALUES(?,?,?)',[Ulid::generate(),$kind,json_encode(['email'=>$r->string('email'),'message'=>mb_substr($r->string('message'),0,5000),'consent_at'=>gmdate('c')])]);
            return $this->render('error',['message'=>__('manor.message_received')]);
        }catch(\DomainException $e){return $this->render('error',['message'=>$e->getMessage()])->status(422);}
    }
}
