<?php
declare(strict_types=1);
// Idempotent initialization; no built-in password and no real customer fixtures.
require dirname(__DIR__).'/vendor/autoload.php';
use App\Engine\{Database as DB,EnvLoader,Migrator,Ulid,Logger};
EnvLoader::load(dirname(__DIR__).'/.env');Logger::init(dirname(__DIR__).'/storage');
(new Migrator(dirname(__DIR__).'/app/Migrations'))->migrate();
$local=($_ENV['APP_ENV']??getenv('APP_ENV'))==='local';
$email=$_ENV['ADMIN_EMAIL']??getenv('ADMIN_EMAIL')?:'';
$password=$_ENV['ADMIN_PASSWORD']??getenv('ADMIN_PASSWORD')?:'';
if(!DB::query('SELECT id FROM operators LIMIT 1')){
 if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<16)throw new RuntimeException('Set ADMIN_EMAIL and ADMIN_PASSWORD (16+ characters) before first initialization.');
 $id=Ulid::generate();DB::execute('INSERT INTO operators(id,name,email,password_hash) VALUES(?,?,?,?)',[$id,'Wonham management',$email,password_hash($password,PASSWORD_DEFAULT)]);
 DB::execute('INSERT INTO auth_emails(email,user_type,user_id) VALUES(?,?,?)',[$email,'operator',$id]);
}
if(!DB::query("SELECT id FROM tenants WHERE slug='wonham-manor'")){
 DB::transaction(function()use($email){
  $id=Ulid::generate();DB::execute("INSERT INTO tenants(id,name,email,slug,booking_pattern,timezone,locale,currency,booking_requires_approval,requires_consent,status) VALUES(?,?,?,'wonham-manor','resource','Europe/London','en','GBP',1,1,'active')",[$id,'Wonham Manor House',$email]);
  $property=Ulid::generate();$building=Ulid::generate();$floor=Ulid::generate();
  foreach([[$property,null,'Entire property','ENTIRE_PROPERTY'],[$building,$property,'Manor building','BUILDING'],[$floor,$building,'Meeting spaces','FLOOR']] as [$uid,$parent,$name,$mode])DB::execute('INSERT INTO manor_units(id,tenant_id,parent_id,name,mode,data) VALUES(?,?,?,?,?,?)',[$uid,$id,$parent,$name,$mode,json_encode(['description'=>'Draft — management must confirm details, scope and availability.','pricing_mode'=>'PRICE_ON_REQUEST','min_minutes'=>60,'max_minutes'=>43200])]);
  foreach(['Suite Meeting Room','Countryside Meeting Room'] as $name){$resource=Ulid::generate();DB::execute('INSERT INTO resources(id,tenant_id,name) VALUES(?,?,?)',[$resource,$id,$name]);DB::execute("INSERT INTO manor_units(id,tenant_id,parent_id,resource_id,name,mode,data) VALUES(?,?,?,?,?,'MEETING_ROOM',?)",[Ulid::generate(),$id,$floor,$resource,$name,json_encode(['description'=>'Draft example — capacity, facilities and images require management confirmation.','capacity'=>null,'pricing_mode'=>'PRICE_ON_REQUEST','min_minutes'=>60,'max_minutes'=>1440,'hours'=>['08:00','23:00'],'document_keys'=>[]])]);}
 });
}
DB::upsertSetting('installed_at',gmdate('c'));
DB::upsertSetting('app_name','Wonham Manor House');
if($local)DB::upsertSetting('mail_transport','log');
echo "Wonham initialization complete. Existing records preserved.\n";
