<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/vendor/autoload.php';
use App\Engine\{Database as DB,EnvLoader,Ulid,Logger};
use App\Manor\{Service,Settings,Inventory,Rules};
// Own a disposable schema only. Never uses the application's configured database.
$socket=getenv('MANOR_TEST_SOCKET')?:'/tmp/wonham-mysql84.sock';
$dsn=getenv('MANOR_TEST_PORT')&&!getenv('MANOR_TEST_SOCKET')?'mysql:host=127.0.0.1;port='.getenv('MANOR_TEST_PORT'):'mysql:unix_socket='.$socket;
$root=new PDO($dsn,getenv('MANOR_TEST_USER')?:'root',getenv('MANOR_TEST_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db='wonham_test_'.bin2hex(random_bytes(10));
$root->exec('CREATE DATABASE `'.$db.'` CHARACTER SET utf8mb4');
foreach(['DB_HOST'=>'127.0.0.1','DB_PORT'=>getenv('MANOR_TEST_PORT')?:'19387','DB_DATABASE'=>$db,'DB_USERNAME'=>getenv('MANOR_TEST_USER')?:'root','DB_PASSWORD'=>getenv('MANOR_TEST_PASSWORD')?:'','ADMIN_EMAIL'=>'qa@wonham.test','ADMIN_PASSWORD'=>bin2hex(random_bytes(24)),'APP_ENV'=>'local','MAIL_TRANSPORT'=>'log'] as $k=>$v){$_ENV[$k]=$v;putenv($k.'='.$v);}
$count=0;
function ok(bool $condition,string $name): void{global $count;if(!$condition)throw new RuntimeException('FAIL '.$name);$count++;echo "PASS $name\n";}
function fails(callable $call,string $name): void{try{$call();}catch(DomainException){ok(true,$name);return;}ok(false,$name);}
function draft(string $unit,string $start='2027-06-01T10:00',string $end='2027-06-01T12:00'):array{return Service::create(['unit_id'=>$unit,'start'=>$start,'end'=>$end,'guests'=>4,'name'=>'Synthetic QA','email'=>bin2hex(random_bytes(5)).'@wonham.test','details'=>'Automated test fixture; not a customer.','consent'=>1]);}
function review(array $r):void{Service::move($r['id'],'submitted',false);Service::move($r['id'],'under_review',true);}
function quoted(array $r):string{Service::quote($r['id'],['expires'=>'2027-05-01T12:00','items'=>[['name'=>'Base','amount'=>'100','mandatory'=>true,'base'=>true],['name'=>'Equipment','amount'=>'20','mandatory'=>false],['name'=>'Security deposit','amount'=>'50','mandatory'=>true,'refundable'=>true]]]);return DB::query('SELECT id FROM manor_quotes WHERE request_id=?',[$r['id']])[0]['id'];}
try{
 require dirname(__DIR__,2).'/scripts/manor-init.php';
 $units=DB::query("SELECT * FROM manor_units WHERE mode='MEETING_ROOM' ORDER BY name");$property=DB::query("SELECT id FROM manor_units WHERE mode='ENTIRE_PROPERTY'")[0]['id'];
 $r=draft($units[0]['id']);ok(Service::capability($r['token'])['status']==='draft','secure draft capability and creation');
 fails(fn()=>Service::capability(str_repeat('f',64)),'unrelated token denied');
 fails(fn()=>Service::move($r['id'],'confirmed',false),'customer cannot confirm');
 review($r);ok(Service::request($r['id'])['status']==='under_review','submit and admin review');
 Service::hold($r['id']);$other=draft($units[0]['id']);review($other);fails(fn()=>Service::hold($other['id']),'overlapping room hold rejected');
 $different=draft($units[1]['id']);review($different);Service::hold($different['id']);ok(true,'independent rooms can coexist');
 $whole=draft($property);review($whole);fails(fn()=>Service::hold($whole['id']),'partial booking prevents exclusive property hold');
 Service::move($r['id'],'cancelled',true);Service::move($different['id'],'cancelled',true);Service::hold($whole['id']);fails(fn()=>Service::hold($other['id']),'exclusive property prevents partial hold');
 DB::execute('UPDATE manor_allocations SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE request_id=?',[$whole['id']]);Service::hold($other['id']);ok(true,'expired provisional hold releases inventory');
 $qid=quoted($other);Service::accept($other['id'],$qid,['1']);$snapshot=DB::query('SELECT accepted_data FROM manor_quotes WHERE id=?',[$qid])[0]['accepted_data'];ok(json_decode($snapshot,true)['total_pence']===17000,'accepted quotation selected total snapshot');
 fails(fn()=>Service::quote($other['id'],[]),'accepted quotation cannot be overwritten');
 Service::move($other['id'],'awaiting_payment',true);Service::payment($other['id'],['amount'=>'170','reference'=>'SYNTHETIC-RECEIPT','status'=>'reported']);
 Service::move($other['id'],'payment_verification',true);fails(fn()=>Service::move($other['id'],'confirmed',true),'reported evidence cannot confirm');
 $pid=DB::query('SELECT id FROM manor_payments WHERE request_id=?',[$other['id']])[0]['id'];Service::verify($other['id'],$pid,'verified');Service::move($other['id'],'confirmed',true);
 ok(Service::request($other['id'])['status']==='confirmed'&&count(DB::query("SELECT id FROM bookings WHERE status='confirmed'"))===1,'verified funds create canonical VoxelBooking booking');
 fails(fn()=>Service::verify($other['id'],$pid,'verified'),'payment verification replay rejected');
 fails(fn()=>Service::payment($other['id'],['kind'=>'refund','amount'=>'171','reference'=>'QA','status'=>'verified']),'over-refund denied');
 Service::payment($other['id'],['kind'=>'refund','amount'=>'50','reference'=>'QA-DEPOSIT-RETURN','status'=>'verified']);ok(Service::balance($other['id'])===12000,'refund reduces ledger balance');
 fails(fn()=>Service::payment($other['id'],['amount'=>'170','reference'=>'SYNTHETIC-RECEIPT','status'=>'verified']),'duplicate external payment reference rejected');
 $settings=Settings::get();$settings['flags']['enquiries']=false;Settings::save($settings);fails(fn()=>draft($units[1]['id']),'disabled enquiries enforced server-side');
 $settings['flags']['enquiries']=true;$settings['flags']['invitations']=true;fails(fn()=>Settings::save($settings),'unfinished invitation feature cannot enable');$settings['flags']['invitations']=false;Settings::save($settings);
 $required=Settings::get();$required['document_types'][0]['required']=true;Settings::save($required);$needs=draft($units[1]['id'],'2027-06-02T10:00','2027-06-02T12:00');fails(fn()=>Service::move($needs['id'],'submitted',false),'mandatory documents enforced before submission');$required['document_types'][0]['required']=false;Settings::save($required);
 $adj=draft($units[0]['id'],'2027-06-01T12:00','2027-06-01T14:00');review($adj);Service::hold($adj['id']);ok(true,'half-open adjacent interval allowed');
 // Separate processes establish independent MySQL connections: only one can reserve empty inventory.
 $a=draft($units[1]['id'],'2027-06-05T10:00','2027-06-05T12:00');review($a);$b=draft($units[1]['id'],'2027-06-05T10:00','2027-06-05T12:00');review($b);
 $code='require $argv[1]."/vendor/autoload.php"; foreach(getenv() as $k=>$v)$_ENV[$k]=$v; try{App\\Manor\\Service::hold($argv[2]);exit(0);}catch(DomainException){exit(3);}';
 $processes=[];foreach([$a,$b] as $x){$processes[]=proc_open([PHP_BINARY,'-r',$code,dirname(__DIR__,2),$x['id']],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/tmp/wonham-concurrency-errors.log','a']],$pipes);}
 $codes=array_map('proc_close',$processes);sort($codes);ok($codes===[0,3],'concurrent empty-inventory attempts produce exactly one winner');
 ok(DB::query('SELECT accepted_data FROM manor_quotes WHERE id=?',[$qid])[0]['accepted_data']===$snapshot,'accepted quote remains immutable after ledger updates');
 $_SESSION=['auth_type'=>'business_user','auth_id'=>Ulid::generate(),'auth_tenant_id'=>$units[0]['tenant_id'],'auth_role'=>'manager','_last_activity'=>time()];
 ok((new App\Manor\Controller())->dashboard(new App\Engine\Request())->getStatusCode()===403,'business manager denied operator venue console');
 $_SESSION=[];
 $saved=Settings::get();$saved['faqs']=[];Settings::save($saved);ok(Settings::get()['faqs']===[],'admin can remove all default FAQ content');
 echo "$count database integration assertions passed.\n";
}finally{$root->exec('DROP DATABASE `'.$db.'`');}
