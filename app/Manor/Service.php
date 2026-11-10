<?php
declare(strict_types=1);
namespace App\Manor;
use App\Engine\{Database as DB,Ulid,CustomerService,Auth,AuditLog};
final class Service
{
    public static function correspondence(string $id,string $body,bool $admin): void
    {
        $body=trim($body);if($body===''||mb_strlen($body)>10000)throw new \DomainException('Enter a message up to 10,000 characters.');
        Inventory::run(function()use($id,$body,$admin){
            self::request($id);
            DB::execute('INSERT INTO manor_correspondence(id,request_id,sender,body) VALUES(?,?,?,?)',[\App\Engine\Ulid::generate(),$id,$admin?'management':'customer',$body]);
            self::audit($id,'correspondence_added',['sender'=>$admin?'management':'customer']);
            if($admin)Notifications::queue($id,'message');
        });
    }
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
        if(!$unit||!Inventory::enabled($unitId))throw new \DomainException('Venue is unavailable.');
        Settings::mode($unit['mode']);
        if(in_array($unit['mode'],['BUILDING','FLOOR'],true))throw new \DomainException('Choose a rentable venue.');
        $data=json_decode($unit['data'],true);
        $modes=$unit['mode']==='ACCOMMODATION_UNIT'?['night']:($data['duration_modes']??['hour','day']);
        $basis=$input['duration_mode']??$modes[0];if(!is_string($basis)||!in_array($basis,$modes,true))throw new \DomainException('Choose an allowed booking duration mode.');
        $startText=$input['start']??'';$endText=$input['end']??'';
        if($basis==='night'&&!empty($input['check_in_date'])){
            $startText=$input['check_in_date'].'T'.($data['check_in']??'15:00');$endText=($input['check_out_date']??'').'T'.($data['check_out']??'11:00');
        }elseif($basis==='day'&&!empty($input['hire_date'])){
            $day=$input['hire_date'];$open=$data['hours'][0]??'00:00';$close=$data['hours'][1]??'00:00';
            $startText=$day.'T'.$open;$endDay=$day;
            if($close<=$open){$parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$day);if(!$parsed||$parsed->format('Y-m-d')!==$day)throw new \DomainException('Choose a valid hire date.');$endDay=$parsed->modify('+1 day')->format('Y-m-d');}
            $endText=$endDay.'T'.$close;
        }
        if(!is_string($startText)||!is_string($endText))throw new \DomainException('Invalid booking date.');
        $start=Rules::instant($startText);$end=Rules::instant($endText);$minutes=(int)(($end->getTimestamp()-$start->getTimestamp())/60);
        if($start<=new \DateTimeImmutable('now',new \DateTimeZone('UTC'))||$end<=$start)throw new \DomainException('Invalid booking duration or date.');
        $s=$start->setTimezone(new \DateTimeZone('Europe/London'));$e=$end->setTimezone(new \DateTimeZone('Europe/London'));
        if($basis==='night'){
            $nights=(int)(new \DateTimeImmutable($s->format('Y-m-d')))->diff(new \DateTimeImmutable($e->format('Y-m-d')))->days;
            if($nights<(int)($data['min_stay_nights']??1)||$nights>(int)($data['max_stay_nights']??30)||$s->format('H:i')!==($data['check_in']??'15:00')||$e->format('H:i')!==($data['check_out']??'11:00'))throw new \DomainException('Stay dates or check-in/out times do not meet the accommodation rules.');
        }elseif($basis==='hour'&&($minutes<(int)($data['min_minutes']??60)||$minutes>(int)($data['max_minutes']??43200)||($basis==='hour'&&$minutes%max(1,(int)($data['slot_minutes']??60))!==0)))throw new \DomainException('Invalid booking duration or date.');
        $guests=filter_var($input['guests']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>10000]]);
        if(!$guests||(!empty($data['capacity'])&&$guests>(int)$data['capacity']))throw new \DomainException('Attendance exceeds the configured capacity.');
        if($basis==='day'){
            $open=$data['hours'][0]??'00:00';$close=$data['hours'][1]??'00:00';
            $expectedEnd=$close<=$open?$s->modify('+1 day')->format('Y-m-d'):$s->format('Y-m-d');
            if($s->format('H:i')!==$open||$e->format('H:i')!==$close||$e->format('Y-m-d')!==$expectedEnd)throw new \DomainException('Full-day hire must use the configured opening and closing times.');
        }elseif($basis==='hour'&&!empty($data['hours'])){
            $inside=false;
            foreach([$s->format('Y-m-d'),$s->modify('-1 day')->format('Y-m-d')] as $day){
                $open=Rules::instant($day.'T'.$data['hours'][0]);$closeDay=$data['hours'][1]<=$data['hours'][0]?(new \DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d'):$day;
                $close=Rules::instant($closeDay.'T'.$data['hours'][1]);
                if($start>=$open&&$end<=$close)$inside=true;
            }
            if(!$inside)throw new \DomainException('Requested time is outside opening hours.');
        }

        return [$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),$guests];
    }
    public static function create(array $in): array
    {
        Settings::flag('enquiries'); Settings::flag('venues');
        foreach(['unit_id','start','end','name','email','phone','details','website','duration_mode','hire_date','check_in_date','check_out_date'] as $key)if(isset($in[$key])&&!is_string($in[$key]))throw new \DomainException('Invalid enquiry field.');
        if(!empty($in['_browser'])&&($in['consent_version']??'')!==Content::consentVersion())throw new \DomainException('The booking terms have changed. Refresh the enquiry form before accepting them.');
        if(empty($in['consent']) || !empty($in['website'])) throw new \DomainException('Accept the privacy and booking terms.');
        $name=trim($in['name']??''); $email=trim($in['email']??''); $details=trim($in['details']??'');
        if(!$name || strlen($name)>255 || !filter_var($email,FILTER_VALIDATE_EMAIL) || !$details || strlen($details)>10000) throw new \DomainException('Enter valid contact and event details.');
        return Inventory::run(function($tenant)use($in,$name,$email,$details){
            [$start,$end,$guests]=self::dates($in['unit_id']??'',$in);
            $selectedUnit=DB::query('SELECT mode,data FROM manor_units WHERE id=?',[$in['unit_id']])[0];
            $durationMode=$in['duration_mode']??($selectedUnit['mode']==='ACCOMMODATION_UNIT'?'night':(json_decode($selectedUnit['data'],true)['duration_modes'][0]??'hour'));
            $customer=CustomerService::findOrCreate($tenant['id'],$name,$email,mb_substr($in['phone']??'',0,50));
            $id=Ulid::generate(); $token=bin2hex(random_bytes(32));
            DB::execute('INSERT INTO manor_requests(id,customer_id,unit_id,token_hash,starts_at,ends_at,guests,details,consent_text,consent_at) VALUES(?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())',[$id,$customer,$in['unit_id'],hash('sha256',$token),$start,$end,$guests,$details,Content::publicSettings()['consent']."\nPrivacy: ".Content::publicSettings()['privacy']."\nTerms: ".Content::publicSettings()['terms']]);
            DB::execute('INSERT INTO manor_request_details(request_id,locale,rental_mode,secret_cipher,data) VALUES(?,?,?,?,?)',[$id,\App\Engine\Locale::getLocale(),$selectedUnit['mode'],(new \App\Agent\PayloadCipher())->encrypt(['request_token'=>$token]),json_encode(['duration_mode'=>$durationMode])]);
            self::audit($id,'draft_created');
            if(Settings::get()['notification_preferences']['booking']??true)Notifications::queue($id,'enquiry_created',Settings::get()['public_url']||!empty($_ENV['APP_URL'])?Notifications::base().'/manor/request/'.$token:null); return ['id'=>$id,'token'=>$token];
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
                $metadata=DB::query('SELECT data FROM manor_request_details WHERE request_id=?',[$id])[0]??null;
                self::dates($r['unit_id'],['start'=>str_replace(' ','T',substr(Inventory::local($r['starts_at']),0,16)),'end'=>str_replace(' ','T',substr(Inventory::local($r['ends_at']),0,16)),'guests'=>$r['guests'],'duration_mode'=>$metadata?(json_decode($metadata['data'],true)['duration_mode']??'hour'):'hour']);
                $quote=DB::query('SELECT accepted_data FROM manor_quotes WHERE id=? AND request_id=? AND accepted_at IS NOT NULL',[$r['accepted_quote_id'],$id])[0]??null;
                if(!$quote) throw new \DomainException('An accepted quotation is required.');
                $total=json_decode($quote['accepted_data'],true)['total_pence'];
                if(self::balance($id)<$total) throw new \DomainException('Verified net payments do not cover the accepted quotation.');
                Inventory::reserve($r,null,true,$tenant);
            }
            if(in_array($to,['cancelled','rejected','expired'],true)) Inventory::release($id);
            DB::execute('UPDATE manor_requests SET status=? WHERE id=?',[$to,$id]); self::audit($id,'status_changed',['from'=>$r['status'],'to'=>$to]);
            if(Settings::get()['notification_preferences']['booking']??true)Notifications::queue($id,$to);
        });
    }
    public static function documentTypes(array $r): array
    {
        $unit=DB::query('SELECT data FROM manor_units WHERE id=?',[$r['unit_id']])[0];
        $data=json_decode($unit['data'],true);$keys=$data['document_keys']??[];$inherit=$data['inherit_documents']??true;
        return array_values(array_filter(Settings::get()['document_types'],fn($t)=>$inherit||in_array($t['key'],$keys,true)));
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
            $r=self::request($id);$metadata=DB::query('SELECT data FROM manor_request_details WHERE request_id=?',[$id])[0]??null;$in['duration_mode']??=$metadata?(json_decode($metadata['data'],true)['duration_mode']??'hour'):'hour'; [$start,$end,$guests]=self::dates($r['unit_id'],$in);
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
            $data=Rules::quote($items);$data['currency']=Settings::get()['currency']; $data['terms']=mb_substr($in['terms']??Settings::get()['terms'],0,10000);
            $data['payment_url']=in_array('external_link',Settings::get()['payment_methods'],true)?($in['payment_url']??''):''; if($data['payment_url']) Settings::url($data['payment_url']);
            $data['bank_details']=in_array('bank_transfer',Settings::get()['payment_methods'],true)?Settings::get()['bank_details']:'';
            $expiry=Rules::instant($in['expires']??'')->format('Y-m-d H:i:s');
            if($expiry<=gmdate('Y-m-d H:i:s')) throw new \DomainException('Quotation validity must be in the future.');
            $version=(int)(DB::query('SELECT MAX(version) AS v FROM manor_quotes WHERE request_id=?',[$id])[0]['v']??0)+1;
            DB::execute('INSERT INTO manor_quotes(id,request_id,version,data,expires_at) VALUES(?,?,?,?,?)',[Ulid::generate(),$id,$version,json_encode($data,JSON_THROW_ON_ERROR),$expiry]);
            DB::execute("UPDATE manor_requests SET status='quoted' WHERE id=?",[$id]); self::audit($id,'quotation_created',['version'=>$version]);
            if(Settings::get()['notification_preferences']['booking']??true)Notifications::queue($id,'quotation_available');
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
            $snapshot['currency']=$data['currency'];
            DB::execute('UPDATE manor_quotes SET accepted_data=?,accepted_at=UTC_TIMESTAMP() WHERE id=?',[json_encode($snapshot,JSON_THROW_ON_ERROR),$quoteId]);
            DB::execute("UPDATE manor_requests SET status='customer_accepted',accepted_quote_id=? WHERE id=?",[$quoteId,$id]); self::audit($id,'quotation_accepted',['quote_id'=>$quoteId,'total_pence'=>$snapshot['total_pence']]);
            if(Settings::get()['notification_preferences']['booking']??true)Notifications::queue($id,'quotation_accepted');
        });
    }
    public static function balance(string $id): int
    {
        return (int)DB::query("SELECT COALESCE(SUM(CASE WHEN kind='refund' THEN -amount_pence ELSE amount_pence END),0) AS total FROM manor_payments WHERE request_id=? AND status='verified'",[$id])[0]['total'];
    }
    public static function suggestedBase(array $request): string
    {
        $unit=DB::query('SELECT * FROM manor_units WHERE id=?',[$request['unit_id']])[0];$data=json_decode($unit['data'],true);
        $meta=DB::query('SELECT data FROM manor_request_details WHERE request_id=?',[$request['id']])[0]??null;
        $basis=$meta?(json_decode($meta['data'],true)['duration_mode']??'hour'):($unit['mode']==='ACCOMMODATION_UNIT'?'night':'hour');
        if($basis==='night'){
            $start=substr(Inventory::local($request['starts_at']),0,10);$end=substr(Inventory::local($request['ends_at']),0,10);
            if($unit['resource_id']){
                $tenant=DB::query('SELECT * FROM tenants WHERE id=?',[$unit['tenant_id']])[0];
                return \App\Engine\ResourceCalculator::quoteNightly($tenant,$unit['resource_id'],$start,$end)['total']??'0.00';
            }
            $nights=(int)(new \DateTimeImmutable($start))->diff(new \DateTimeImmutable($end))->days;
            return \App\Engine\Money::fromCents(Rules::pence((string)($data['price']??'0.00'))*$nights);
        }
        if($basis==='day')return (string)($data['daily_rate']??$data['price']??'0.00');
        $minutes=(int)((strtotime($request['ends_at'].' UTC')-strtotime($request['starts_at'].' UTC'))/60);
        return \App\Engine\Money::fromCents(intdiv(Rules::pence((string)($data['hourly_rate']??$data['price']??'0.00'))*$minutes+30,60));
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
