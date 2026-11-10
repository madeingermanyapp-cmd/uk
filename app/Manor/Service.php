<?php
declare(strict_types=1);
namespace App\Manor;
use App\Engine\{Database as DB,Ulid,CustomerService,Auth,AuditLog};
final class Service
{
    public static function audit(?string $id,string $action,array $data=[]): void
    {
        DB::execute('INSERT INTO manor_history(id,request_id,actor,action,data) VALUES(?,?,?,?,?)',[Ulid::generate(),$id,Auth::user()['id']??'customer',$action,json_encode($data,JSON_THROW_ON_ERROR)]);
        AuditLog::log('manor.'.$action,'manor_request',$id??'settings',$data);
    }
    public static function request(string $id): array
    {
        $r=DB::query('SELECT r.*,u.name AS unit_name,c.name AS customer_name,c.email AS customer_email FROM manor_requests r JOIN manor_units u ON u.id=r.unit_id JOIN customers c ON c.id=r.customer_id WHERE r.id=?',[$id])[0]??null;
        if(!$r) throw new \DomainException('Request not found.');
        return $r;
    }
    public static function capability(string $token): array
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$token)) throw new \DomainException('Request not found.');
        $id=DB::query('SELECT id FROM manor_requests WHERE token_hash=?',[hash('sha256',$token)])[0]['id']??'';
        return self::request($id);
    }
    public static function dates(string $unitId,array $input): array
    {
        $unit=DB::query('SELECT * FROM manor_units WHERE id=? AND enabled=1',[$unitId])[0]??null;
        if(!$unit) throw new \DomainException('Venue is unavailable.');
        Settings::mode($unit['mode']);
        if(in_array($unit['mode'],['BUILDING','FLOOR'],true)) throw new \DomainException('Choose a rentable venue.');
        $start=Rules::instant($input['start']??''); $end=Rules::instant($input['end']??'');
        $minutes=($end->getTimestamp()-$start->getTimestamp())/60;
        $data=json_decode($unit['data'],true);
        if($start<=new \DateTimeImmutable('now',new \DateTimeZone('UTC')) || $minutes<(int)($data['min_minutes']??60) || $minutes>(int)($data['max_minutes']??43200)) throw new \DomainException('Invalid booking duration or date.');
        $guests=filter_var($input['guests']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>10000]]);
        if(!$guests || (!empty($data['capacity']) && $guests>(int)$data['capacity'])) throw new \DomainException('Attendance exceeds the configured capacity.');
        $s=$start->setTimezone(new \DateTimeZone('Europe/London')); $e=$end->setTimezone(new \DateTimeZone('Europe/London'));
        if(!empty($data['hours']) && ($s->format('Y-m-d')!==$e->format('Y-m-d') || $s->format('H:i')<$data['hours'][0] || $e->format('H:i')>$data['hours'][1])) throw new \DomainException('Requested time is outside opening hours.');
        return [$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),$guests];
    }
    public static function create(array $in): array
    {
        Settings::flag('enquiries'); Settings::flag('venues');
        foreach(['unit_id','start','end','name','email','phone','details','website'] as $key)if(isset($in[$key])&&!is_string($in[$key]))throw new \DomainException('Invalid enquiry field.');
        if(empty($in['consent']) || !empty($in['website'])) throw new \DomainException('Accept the privacy and booking terms.');
        $name=trim($in['name']??''); $email=trim($in['email']??''); $details=trim($in['details']??'');
        if(!$name || strlen($name)>255 || !filter_var($email,FILTER_VALIDATE_EMAIL) || !$details || strlen($details)>10000) throw new \DomainException('Enter valid contact and event details.');
        return Inventory::run(function($tenant)use($in,$name,$email,$details){
            [$start,$end,$guests]=self::dates($in['unit_id']??'',$in);
            $customer=CustomerService::findOrCreate($tenant['id'],$name,$email,mb_substr($in['phone']??'',0,50));
            $id=Ulid::generate(); $token=bin2hex(random_bytes(32));
            DB::execute('INSERT INTO manor_requests(id,customer_id,unit_id,token_hash,starts_at,ends_at,guests,details,consent_text,consent_at) VALUES(?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())',[$id,$customer,$in['unit_id'],hash('sha256',$token),$start,$end,$guests,$details,Settings::get()['consent']."\nPrivacy: ".Settings::get()['privacy']."\nTerms: ".Settings::get()['terms']]);
            self::audit($id,'draft_created'); return ['id'=>$id,'token'=>$token];
        });
    }
    public static function move(string $id,string $to,bool $admin): void
    {
        Inventory::run(function($tenant)use($id,$to,$admin){
            $r=self::request($id); Rules::transition($r['status'],$to);
            if(!$admin && !in_array($to,['submitted','cancelled'],true)) throw new \DomainException('Management authorization required.');
            if($to==='submitted') {
                Settings::flag('enquiries');
                if(Settings::get()['flags']['documents']) foreach(self::documentTypes($r) as $type) if($type['required'] && !DB::query('SELECT id FROM manor_documents WHERE request_id=? AND type=?',[$id,$type['key']])) throw new \DomainException('Upload all mandatory documents first.');
            }
            if(in_array($to,['quoted','customer_accepted'],true)) throw new \DomainException('Use quotation creation or acceptance.');
            if($to==='confirmed') {
                if($r['starts_at']<=gmdate('Y-m-d H:i:s'))throw new \DomainException('Cannot confirm a booking that has already started.');
                $unit=DB::query('SELECT mode,enabled FROM manor_units WHERE id=?',[$r['unit_id']])[0]; Settings::mode($unit['mode']); if(!$unit['enabled'])throw new \DomainException('Unit is disabled.');
                $quote=DB::query('SELECT accepted_data FROM manor_quotes WHERE id=? AND request_id=? AND accepted_at IS NOT NULL',[$r['accepted_quote_id'],$id])[0]??null;
                if(!$quote) throw new \DomainException('An accepted quotation is required.');
                $total=json_decode($quote['accepted_data'],true)['total_pence'];
                if(self::balance($id)<$total) throw new \DomainException('Verified net payments do not cover the accepted quotation.');
                Inventory::reserve($r,null,true,$tenant);
            }
            if(in_array($to,['cancelled','rejected','expired'],true)) Inventory::release($id);
            DB::execute('UPDATE manor_requests SET status=? WHERE id=?',[$to,$id]); self::audit($id,'status_changed',['from'=>$r['status'],'to'=>$to]);
        });
    }
    public static function documentTypes(array $r): array
    {
        $unit=DB::query('SELECT data FROM manor_units WHERE id=?',[$r['unit_id']])[0];
        $keys=json_decode($unit['data'],true)['document_keys']??[];
        return array_values(array_filter(Settings::get()['document_types'],fn($t)=>!$keys||in_array($t['key'],$keys,true)));
    }
    public static function hold(string $id): void
    {
        Inventory::run(function($tenant)use($id){
            $r=self::request($id);
            if(in_array($r['status'],['draft','confirmed','cancelled','rejected','expired'],true)) throw new \DomainException('This request cannot receive a provisional hold.');
            $expiry=gmdate('Y-m-d H:i:s',time()+Settings::get()['hold_hours']*3600);
            Inventory::reserve($r,$expiry,false,$tenant);
            DB::execute('UPDATE manor_requests SET hold_until=? WHERE id=?',[$expiry,$id]); self::audit($id,'hold_created',['expires_at'=>$expiry]);
        });
    }
    public static function reschedule(string $id,array $in): void
    {
        Inventory::run(function($tenant)use($id,$in){
            $r=self::request($id); [$start,$end,$guests]=self::dates($r['unit_id'],$in);
            if(in_array($r['status'],['cancelled','rejected','expired'],true)) throw new \DomainException('Terminal request cannot be rescheduled.');
            $new=array_replace($r,['starts_at'=>$start,'ends_at'=>$end,'guests'=>$guests]);
            if($r['status']==='confirmed') Inventory::reserve($new,null,true,$tenant);
            elseif($r['hold_until'] && $r['hold_until']>gmdate('Y-m-d H:i:s')) Inventory::reserve($new,$r['hold_until'],false,$tenant);
            DB::execute('UPDATE manor_requests SET starts_at=?,ends_at=?,guests=? WHERE id=?',[$start,$end,$guests,$id]); self::audit($id,'rescheduled',['old_start'=>$r['starts_at'],'new_start'=>$start]);
        });
    }
    public static function quote(string $id,array $in): void
    {
        Inventory::run(function()use($id,$in){
            $r=self::request($id);
            if(!in_array($r['status'],['under_review','quoted'],true) || $r['accepted_quote_id']) throw new \DomainException('Only unaccepted reviewed requests can be quoted.');
            $items=$in['items']??[];
            if(!Settings::get()['flags']['optional_services']) foreach($items as $item) if(empty($item['mandatory'])) throw new \DomainException('Optional services are disabled.');
            $data=Rules::quote($items); $data['terms']=mb_substr($in['terms']??Settings::get()['terms'],0,10000);
            $data['payment_url']=$in['payment_url']??''; if($data['payment_url']) Settings::url($data['payment_url']);
            $data['bank_details']=Settings::get()['bank_details'];
            $expiry=Rules::instant($in['expires']??'')->format('Y-m-d H:i:s');
            if($expiry<=gmdate('Y-m-d H:i:s')) throw new \DomainException('Quotation validity must be in the future.');
            $version=(int)(DB::query('SELECT MAX(version) AS v FROM manor_quotes WHERE request_id=?',[$id])[0]['v']??0)+1;
            DB::execute('INSERT INTO manor_quotes(id,request_id,version,data,expires_at) VALUES(?,?,?,?,?)',[Ulid::generate(),$id,$version,json_encode($data,JSON_THROW_ON_ERROR),$expiry]);
            DB::execute("UPDATE manor_requests SET status='quoted' WHERE id=?",[$id]); self::audit($id,'quotation_created',['version'=>$version]);
        });
    }
    public static function accept(string $id,string $quoteId,array $selected): void
    {
        Inventory::run(function()use($id,$quoteId,$selected){
            $r=self::request($id);
            if($r['status']!=='quoted') throw new \DomainException('Request is not awaiting quotation acceptance.');
            $q=DB::query('SELECT * FROM manor_quotes WHERE request_id=? ORDER BY version DESC LIMIT 1',[$id])[0]??null;
            if(!$q || $q['id']!==$quoteId || $q['expires_at']<=gmdate('Y-m-d H:i:s') || $q['accepted_at']) throw new \DomainException('Quotation is expired or has been replaced.');
            $data=json_decode($q['data'],true);
            if(!Settings::get()['flags']['optional_services'] && $selected) throw new \DomainException('Optional services are disabled.');
            $snapshot=Rules::quote($data['items'],$selected)+array_intersect_key($data,array_flip(['terms','bank_details','payment_url']));
            DB::execute('UPDATE manor_quotes SET accepted_data=?,accepted_at=UTC_TIMESTAMP() WHERE id=?',[json_encode($snapshot,JSON_THROW_ON_ERROR),$quoteId]);
            DB::execute("UPDATE manor_requests SET status='customer_accepted',accepted_quote_id=? WHERE id=?",[$quoteId,$id]); self::audit($id,'quotation_accepted',['quote_id'=>$quoteId,'total_pence'=>$snapshot['total_pence']]);
        });
    }
    public static function balance(string $id): int
    {
        return (int)DB::query("SELECT COALESCE(SUM(CASE WHEN kind='refund' THEN -amount_pence ELSE amount_pence END),0) AS total FROM manor_payments WHERE request_id=? AND status='verified'",[$id])[0]['total'];
    }
    public static function payment(string $id,array $in): void
    {
        Inventory::run(function()use($id,$in){
            $r=self::request($id);
            if(!$r['accepted_quote_id']) throw new \DomainException('An accepted quotation is required.');
            $kind=$in['kind']??'payment'; $status=$in['status']??'reported'; $amount=Rules::pence($in['amount']??'');
            if(!in_array($kind,['payment','refund','deposit'],true)||!in_array($status,['reported','verified','rejected'],true)||$amount<1||!trim($in['reference']??'')) throw new \DomainException('Invalid payment record.');
            if($kind==='refund' && $status==='verified' && $amount>self::balance($id)) throw new \DomainException('Refund exceeds verified net receipts.');
            if(DB::query('SELECT id FROM manor_payments WHERE request_id=? AND kind=? AND reference=?',[$id,$kind,mb_substr($in['reference'],0,255)]))throw new \DomainException('This payment reference has already been recorded.');
            DB::execute('INSERT INTO manor_payments(id,request_id,kind,amount_pence,reference,status,verified_at) VALUES(?,?,?,?,?,?,?)',[Ulid::generate(),$id,$kind,$amount,mb_substr($in['reference'],0,255),$status,$status==='verified'?gmdate('Y-m-d H:i:s'):null]);
            self::audit($id,'payment_recorded',['kind'=>$kind,'amount_pence'=>$amount,'status'=>$status]);
        });
    }
    public static function verify(string $id,string $paymentId,string $status): void
    {
        Inventory::run(function()use($id,$paymentId,$status){
            if(!in_array($status,['verified','rejected'],true)) throw new \DomainException('Invalid verification status.');
            $p=DB::query('SELECT * FROM manor_payments WHERE id=? AND request_id=? AND status=\'reported\'',[$paymentId,$id])[0]??null;
            if(!$p) throw new \DomainException('Payment has already been reviewed.');
            if($p['kind']==='refund' && $status==='verified' && $p['amount_pence']>self::balance($id)) throw new \DomainException('Refund exceeds receipts.');
            DB::execute('UPDATE manor_payments SET status=?,verified_at=UTC_TIMESTAMP() WHERE id=?',[$status,$paymentId]); self::audit($id,'payment_reviewed',['payment_id'=>$paymentId,'status'=>$status]);
        });
    }
}
