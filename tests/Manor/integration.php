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
 $settings['flags']['enquiries']=true;$settings['flags']['invitations']=true;Settings::save($settings);ok(Settings::get()['flags']['invitations']===true,'invitation feature can be enabled');$settings['flags']['invitations']=false;Settings::save($settings);
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
 // Guest links and attendance are distinct capabilities; scanning always needs an operator.
 $features=Settings::get();$features['flags']['invitations']=true;$features['flags']['qr_attendance']=true;Settings::save($features);
 $_ENV['APP_URL']='http://127.0.0.1:18780';
 $template=DB::query('SELECT id FROM manor_invitation_templates LIMIT 1')[0]['id'];
 $guestId=App\Manor\Invitations::create($other['id'],['template_id'=>$template,'guest_name'=>'Synthetic guest','event_name'=>'Synthetic QA event','host_name'=>'QA host']);
 $guest=DB::query('SELECT * FROM manor_guests WHERE id=?',[$guestId])[0];$secrets=(new App\Agent\PayloadCipher())->decrypt($guest['token_cipher']);
 ok($secrets['invitation']!==$secrets['check_in']&&!str_contains($guest['token_cipher'],$secrets['invitation']),'invitation and QR tokens are distinct and encrypted at rest');
 App\Manor\Invitations::rsvp($secrets['invitation'],'accepted');ok(App\Manor\Invitations::find($secrets['invitation'])['rsvp']==='accepted','guest RSVP persists through private capability');
 fails(fn()=>App\Manor\Invitations::checkIn($secrets['check_in']),'guest QR does not grant staff scanning rights');
 $qr=App\Manor\Invitations::qr($guest);ok(str_starts_with($qr,"\x89PNG"),'guest QR renders a real PNG');
 $qrPath=tempnam(sys_get_temp_dir(),'wonham-qr-');file_put_contents($qrPath,$qr);chmod($qrPath,0600);
 try{$decoded=(new Zxing\QrReader($qrPath))->text();ok($decoded===App\Manor\Invitations::links($guest)['check_in'],'generated QR decodes to token-only check-in URL');}finally{unlink($qrPath);}
 $_SESSION=['auth_type'=>'operator','auth_id'=>DB::query('SELECT id FROM operators LIMIT 1')[0]['id'],'_last_activity'=>time()];
 $first=App\Manor\Invitations::checkIn($secrets['check_in']);$again=App\Manor\Invitations::checkIn($secrets['check_in']);
 ok(!$first['duplicate']&&$again['duplicate'],'duplicate scans do not double-count attendance');
 ok(App\Manor\Invitations::report($other['id'])['attended']===1,'attendance report matches checked-in guest');
 App\Manor\Invitations::checkIn($secrets['check_in'],'QA correction',true);ok(App\Manor\Invitations::report($other['id'])['attended']===0,'audited manual attendance removal updates totals');
 App\Manor\Invitations::revoke($guestId);fails(fn()=>App\Manor\Invitations::find($secrets['invitation']),'revocation invalidates invitation and RSVP capability');
 $features['flags']['invitations']=false;Settings::save($features);fails(fn()=>App\Manor\Invitations::create($other['id'],[]),'disabled invitations are enforced server-side');
 $_SESSION=[];
 // Exercise admin-created independent property, hall and nightly rooms through the real mutation.
 function unitFixture(string $id,?string $parent,string $mode,array $data):void{
  $_POST=['unit_id'=>$id,'parent_id'=>$parent??'','name'=>'Synthetic '.$mode,'mode'=>$mode,'enabled'=>'1','data'=>json_encode($data)];
  try{(new App\Manor\Controller())->saveUnit(new App\Engine\Request());}finally{$_POST=[];}
 }
 $features=Settings::get();$features['flags']['hotel_mode']=true;Settings::save($features);
 $newProperty=Ulid::generate();$suite=Ulid::generate();$hall=Ulid::generate();
 unitFixture($newProperty,null,'ENTIRE_PROPERTY',['duration_modes'=>['night','hour','day'],'price'=>'400.00']);
 unitFixture($suite,$newProperty,'ACCOMMODATION_UNIT',['duration_modes'=>['night'],'price'=>'150.00','min_stay_nights'=>1,'max_stay_nights'=>30,'check_in'=>'15:00','check_out'=>'11:00','capacity'=>8]);
 unitFixture($hall,$newProperty,'EVENT_HALL',['duration_modes'=>['hour','day'],'hourly_rate'=>'40.00','daily_rate'=>'250.00','min_minutes'=>60,'max_minutes'=>1440,'hours'=>['08:00','23:00'],'capacity'=>100]);
 $common=['guests'=>4,'name'=>'Synthetic nightly QA','email'=>'nightly@wonham.test','consent'=>1,'details'=>'Synthetic duration tests'];
 $stay=Service::create($common+['unit_id'=>$suite,'duration_mode'=>'night','check_in_date'=>'2027-07-01','check_out_date'=>'2027-07-03']);review($stay);Service::hold($stay['id']);
 ok(Inventory::local(Service::request($stay['id'])['starts_at'])==='2027-07-01 15:00:00'&&Service::suggestedBase(Service::request($stay['id']))==='300.00','nightly hire uses configured check-in and two-night pricing');
 $adjacent=Service::create($common+['unit_id'=>$suite,'duration_mode'=>'night','check_in_date'=>'2027-07-03','check_out_date'=>'2027-07-04']);review($adjacent);Service::hold($adjacent['id']);ok(true,'adjacent nightly stays allow same-day turnover');
 $exclusive=Service::create($common+['unit_id'=>$newProperty,'duration_mode'=>'night','check_in_date'=>'2027-07-01','check_out_date'=>'2027-07-02']);review($exclusive);fails(fn()=>Service::hold($exclusive['id']),'nightly room prevents whole-property exclusive hold');
 $day=Service::create($common+['unit_id'=>$hall,'duration_mode'=>'day','hire_date'=>'2027-07-01']);review($day);Service::hold($day['id']);ok(Service::suggestedBase(Service::request($day['id']))==='250.00','full-day hall has configured daily price and independent inventory');
 $hour=Service::create($common+['unit_id'=>$hall,'duration_mode'=>'hour','start'=>'2027-07-01T10:00','end'=>'2027-07-01T12:00']);review($hour);fails(fn()=>Service::hold($hour['id']),'full-day hall blocks overlapping hourly hall');
 ok(Service::suggestedBase(Service::request($hour['id']))==='80.00','hourly price uses duration');
 Service::correspondence($stay['id'],'Synthetic customer message',false);ok(count(DB::query('SELECT id FROM manor_correspondence WHERE request_id=?',[$stay['id']]))===1,'secure correspondence persists');
 $features['flags']['hotel_mode']=false;Settings::save($features);fails(fn()=>Service::create($common+['unit_id'=>$suite,'duration_mode'=>'night','check_in_date'=>'2027-08-01','check_out_date'=>'2027-08-02']),'disabled hotel mode rejects nightly enquiries');

 $recoverRecord=Service::request($stay['id']);App\Manor\Notifications::recover($recoverRecord['customer_email'],$stay['id']);
 $recoveryPayload=null;foreach(DB::query("SELECT payload_cipher FROM delivery_outbox WHERE topic='manor.notification' ORDER BY created_at DESC,id DESC") as $outbox){$payload=(new App\Agent\PayloadCipher())->decrypt($outbox['payload_cipher']);if(str_contains($payload['html']??'','/manor/recover/')){$recoveryPayload=$payload;break;}}
 ok($recoveryPayload!==null,'matching customer recovery queues encrypted email');preg_match('#/manor/recover/([a-f0-9]{64})#',$recoveryPayload['html'],$recoveryMatch);
 $newCapability=App\Manor\Notifications::exchange($recoveryMatch[1]);ok(Service::capability($newCapability)['id']===$stay['id'],'customer recovery restores correct request');
 fails(fn()=>Service::capability($stay['token']),'recovery invalidates old request capability');fails(fn()=>App\Manor\Notifications::exchange($recoveryMatch[1]),'recovery token cannot be replayed');
 $navigation=Settings::get();$navigation['navigation']=[['label'=>'Z first','url'=>'/#contact'],['label'=>'A second','url'=>'/#introduction']];Settings::save($navigation);ok(array_column(Settings::get()['navigation'],'label')===['Z first','A second'],'navigation order survives MySQL JSON persistence');
 $navigation['translated_content']['ar']['navigation']=[['label'=>'Unsafe','url'=>'javascript:alert(1)']];fails(fn()=>Settings::save($navigation),'translated navigation rejects executable URL schemes');
 $language=Settings::get();$language['translated_content']['ar']['navigation']=App\Manor\ArabicContent::defaults()['navigation'];$language['languages']=['en','ar'];$language['languages_configured']=true;Settings::save($language);
 App\Engine\View::init(dirname(__DIR__,2).'/templates');App\Engine\Locale::init(dirname(__DIR__,2));$_GET=['lang'=>'ar'];$_SESSION=['auth_type'=>'operator','auth_id'=>DB::query('SELECT id FROM operators LIMIT 1')[0]['id'],'_last_activity'=>time()];$pdfRequest=new App\Engine\Request();$pdfRequest->setAttribute('id',$qid);$pdf=(new App\Manor\QuoteController())->pdf($pdfRequest);ok(App\Engine\Locale::getLocale()==='ar'&&__('manor.quotation')==='عرض السعر','Arabic PDF uses resolved Arabic translation keys');ok($pdf->getStatusCode()===200&&str_starts_with($pdf->getBody(),'%PDF-')&&strlen($pdf->getBody())>5000,'admin-enabled Arabic quotation produces populated authorized PDF');if($pdfDir=getenv('MANOR_TEST_PDF_DIR')){if(!is_dir($pdfDir))mkdir($pdfDir,0700,true);file_put_contents($pdfDir.'/quotation-ar.pdf',$pdf->getBody());}
 $_GET=[];$_SESSION=[];
 App\Manor\MailSettings::save(['host'=>'127.0.0.1','port'=>1025,'transport'=>'log','encryption'=>'none','sender_email'=>'qa@wonham.test','password'=>'synthetic-private-password']);require dirname(__DIR__,2).'/scripts/manor-init.php';
 ok(DB::query("SELECT value FROM settings WHERE `key`='mail_transport'")[0]['value']==='manor','repeat initialization preserves Admin email configuration');
 $smtp=App\Manor\MailSettings::get();$cipher=DB::query("SELECT secret_cipher FROM manor_secrets WHERE name='smtp'")[0]['secret_cipher'];ok(!array_key_exists('password',$smtp)&&!str_contains($cipher,'synthetic-private-password'),'SMTP password is encrypted and omitted from settings responses');
 App\Manor\MailSettings::save(['host'=>'127.0.0.1','port'=>1025,'transport'=>'log','encryption'=>'none','sender_email'=>'qa@wonham.test','password'=>'']);ok(App\Manor\MailSettings::get()['password_saved'],'blank SMTP password retains the secret');
 echo "$count database integration assertions passed.\n";
}finally{$root->exec('DROP DATABASE `'.$db.'`');}
