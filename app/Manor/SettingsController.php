<?php
declare(strict_types=1);
namespace App\Manor;
use App\Engine\{Request,Response,View,Auth,Database as DB,Ulid,Mailer};
final class SettingsController
{
    public const SECTIONS=['setup','account','general','features','content','pages','media','venues','documents','services','payments','email','notifications','readiness'];
    private function allowed(): bool{return Auth::isOperator();}
    public function show(Request $r): Response
    {
        if(!$this->allowed())return Response::html('Forbidden',403);
        Language::apply();$section=$r->getAttribute('section')?:'general';if(!in_array($section,self::SECTIONS,true))return Response::html('Not found',404);
        if($section==='setup'&&Settings::get()['setup_complete'])return Response::redirect('/admin/manor/settings/readiness');
        return View::response('manor.settings',['section'=>$section,'settings'=>Settings::get(),'draft'=>Content::draft(),'smtp'=>MailSettings::get(),'checks'=>$section==='readiness'?Readiness::checks($r):[],'pages'=>DB::query('SELECT * FROM manor_pages ORDER BY slug,locale'),'media'=>DB::query('SELECT * FROM manor_media WHERE deleted_at IS NULL ORDER BY created_at DESC'),'units'=>DB::query('SELECT * FROM manor_units ORDER BY name'),'services'=>DB::query('SELECT * FROM manor_services ORDER BY name'),'deliveries'=>DB::query("SELECT id,status,attempts,last_error_code,created_at,delivered_at FROM delivery_outbox WHERE topic='manor.notification' ORDER BY created_at DESC LIMIT 100"),'mail_logs'=>DB::query("SELECT id,type,status,error,sent_at FROM email_log WHERE type IN('manor_notification','manor_test') ORDER BY sent_at DESC LIMIT 100"),'test_result'=>$_SESSION['manor_test_result']??null])->header('Cache-Control','no-store')->header('Referrer-Policy','no-referrer');
    }
    public function save(Request $r): Response
    {
        if(!$this->allowed())return Response::html('Forbidden',403);
        Language::apply();$section=$r->getAttribute('section');$s=Settings::get();
        try{
            switch($r->string('action')){
                case 'account':
                    $user=Auth::user();$operator=DB::query('SELECT * FROM operators WHERE id=?',[$user['id']])[0];
                    if(!password_verify($r->string('current_password'),$operator['password_hash']))throw new \DomainException('The current password is incorrect.');
                    $email=$r->string('admin_email');$password=$r->string('admin_password');
                    if(!filter_var($email,FILTER_VALIDATE_EMAIL)||($password!==''&&strlen($password)<16))throw new \DomainException('Use a valid administrator email and a password of at least 16 characters.');
                    Inventory::run(function()use($r,$user,$operator,$email,$password){
                        DB::execute('UPDATE operators SET email=?,name=?,password_hash=? WHERE id=?',[$email,$r->string('admin_name'),$password!==''?password_hash($password,PASSWORD_DEFAULT):$operator['password_hash'],$user['id']]);
                        DB::execute('UPDATE auth_emails SET email=? WHERE user_id=? AND user_type=?',[$email,$user['id'],'operator']);
                    });
                    $_SESSION['auth_email']=$email;$_SESSION['auth_name']=$r->string('admin_name');session_regenerate_id(true);break;
                case 'general':
                    foreach(['website_name','public_url','email','phone','address','currency'] as $key)$s[$key]=$r->string($key);
                    $url=parse_url($s['public_url']);if(!is_array($url)||empty($url['host'])||!in_array($url['scheme']??'',['http','https'],true)||isset($url['user'])||!empty($url['query'])||!empty($url['fragment'])||!in_array($url['path']??'',['','/'],true))throw new \DomainException('Enter an HTTP or HTTPS public origin without a path.');
                    if($s['currency']!==Settings::get()['currency']&&DB::query('SELECT id FROM manor_quotes LIMIT 1'))throw new \DomainException('Currency cannot change after quotations exist.');
                    $s['hold_hours']=$r->int('hold_hours',24);
                    $s['languages_configured']=true;$s['languages']=array_values(array_unique(array_merge(['en'],array_intersect(['ar'],$r->all()['languages']??[]))));
                    $s['public_url']=rtrim($s['public_url'],'/');$s['social_links']=[];
                    foreach(['instagram','facebook','youtube','linkedin'] as $social)if($r->string('social_'.$social)){$s['social_links'][$social]=$r->string('social_'.$social);}
                    Settings::save($s);DB::execute("UPDATE tenants SET currency=? WHERE slug='wonham-manor'",[$s['currency']]);break;
                case 'features':foreach(array_keys($s['flags']) as $flag)$s['flags'][$flag]=$r->bool('flag_'.$flag);Settings::save($s);break;
                case 'payments':$s['bank_details']=$r->string('bank_details');$s['payment_methods']=array_values(array_intersect(['bank_transfer','external_link'],$r->all()['payment_methods']??[]));$s['payment_verification_required']=true;$s['deposit_default']=$r->string('deposit_default','0.00');Rules::pence($s['deposit_default']);Settings::save($s);break;
                case 'email':MailSettings::save($r->all());break;
                case 'test_email':$_SESSION['manor_test_result']=MailSettings::test($r->string('recipient'));break;
                case 'preferences':$s['notification_preferences']=['booking'=>$r->bool('booking_notifications'),'invitation'=>$r->bool('invitation_notifications')];$s['email_templates']=$r->all()['email_templates']??[];Settings::save($s);break;
                case 'retry':DB::execute("UPDATE delivery_outbox SET status='pending',available_at=UTC_TIMESTAMP(),attempts=0 WHERE id=? AND topic='manor.notification' AND status='failed'",[$r->string('delivery_id')]);break;
                case 'jobs':Jobs::run();break;
                case 'content':$this->contentDraft($r);break;
                case 'publish':Content::publish($r->string('version_id'),$r->bool('approved'));break;
                case 'page':Content::page($r->all());break;
                case 'media_upload':$url=Files::media($r->file('media')??[]);$draft=Content::draft()['data'];$draft['media'][]=['url'=>$url,'alt'=>$r->string('alt'),'section'=>$r->string('section'),'kind'=>str_ends_with($url,'.mp4')?'video':'image'];if(in_array($r->string('section'),['logo','favicon'],true))$draft[$r->string('section').'_url']=$url;Content::saveDraft($draft);break;
                case 'media_review':Content::mediaReview($r->string('media_id'),$r->string('alt'),$r->string('rights'),$r->bool('approved'));break;
                case 'media_remove':Content::removeMedia($r->string('media_id'));break;
                case 'media_entry_remove':$draft=Content::draft()['data'];$i=$r->int('index',-1);if(!isset($draft['media'][$i]))throw new \DomainException('Invalid media ordering request.');array_splice($draft['media'],$i,1);Content::saveDraft($draft);break;
                case 'media_order':$this->orderMedia($r);break;
                case 'external_media':$draft=Content::draft()['data'];Settings::url($r->string('url'));$draft['media'][]=['url'=>$r->string('url'),'alt'=>$r->string('alt'),'section'=>$r->string('section'),'kind'=>'video','rights'=>$r->string('rights'),'rights_approved'=>$r->bool('approved')];Content::saveDraft($draft);break;
                case 'documents':$this->documents($r);break;
                case 'service':$this->service($r);break;
                case 'venue':$this->venue($r);break;
                case 'setup':$this->setup($r);break;
                default:throw new \DomainException('Unknown action.');
            }
            Service::audit(null,'admin_configuration_changed',['section'=>$section]);Notifications::flush();return Response::redirect('/admin/manor/settings/'.$section);
        }catch(\DomainException $e){return View::response('manor.error',['settings'=>$s,'message'=>Language::error($e->getMessage())],422);}
    }
    private function contentDraft(Request $r): void
    {
        $draft=Content::draft()['data'];$input=$r->all()['content']??[];
        $scalar=array_filter(Content::KEYS,fn($key)=>!in_array($key,['logo_url','favicon_url'],true)&&is_string(Settings::defaults()[$key]??null));
        foreach(['en','ar'] as $locale){
            $values=[];foreach($scalar as $key)$values[$key]=mb_substr(trim((string)($input[$locale][$key]??'')),0,20000);
            foreach(['why_items','why_texts'] as $key)$values[$key]=array_slice(array_values(array_filter(array_map('trim',explode("\n",$input[$locale][$key]??'')))),0,12);
            $values['faqs']=[];foreach(($input[$locale]['faq_questions']??[]) as $i=>$question)if(trim($question))$values['faqs'][]=['question'=>mb_substr($question,0,1000),'answer'=>mb_substr($input[$locale]['faq_answers'][$i]??'',0,10000)];
            if($locale==='en')$draft=array_replace($draft,$values);else $draft['translated_content']['ar']=$values;
        }
        foreach(array_keys($draft['sections']) as $key)$draft['sections'][$key]=$r->bool('section_'.$key);
        $draft['navigation']=[];foreach(($r->all()['nav_labels']??[]) as $i=>$label)if(trim($label))$draft['navigation'][]=['label'=>mb_substr($label,0,100),'url'=>$r->all()['nav_urls'][$i]??''];
        $draft['translated_content']['ar']['navigation']=[];foreach(($r->all()['nav_labels_ar']??[]) as $i=>$label)if(trim($label))$draft['translated_content']['ar']['navigation'][]=['label'=>mb_substr($label,0,100),'url'=>$r->all()['nav_urls_ar'][$i]??''];
        Content::saveDraft($draft);
    }
    private function orderMedia(Request $r): void
    {
        $draft=Content::draft()['data'];$index=$r->int('index',-1);$move=$r->int('move');
        if(!isset($draft['media'][$index])||!in_array($move,[-1,1],true)||!isset($draft['media'][$index+$move]))throw new \DomainException('Invalid media ordering request.');
        [$draft['media'][$index],$draft['media'][$index+$move]]=[$draft['media'][$index+$move],$draft['media'][$index]];Content::saveDraft($draft);
    }
    private function documents(Request $r): void
    {
        $s=Settings::get();$types=[];foreach(($r->all()['doc_key']??[]) as $i=>$key){if(!trim($key))continue;$types[]=['key'=>$key,'name'=>$r->all()['doc_name'][$i]??'','required'=>!empty($r->all()['doc_required'][$i]),'max_mb'=>(int)($r->all()['doc_size'][$i]??5),'mimes'=>array_values(array_intersect(['application/pdf','image/jpeg','image/png'],$r->all()['doc_mimes'][$i]??[]))];}$s['document_types']=$types;Settings::save($s);
    }
    private function service(Request $r): void
    {
        $id=$r->string('service_id')?:Ulid::generate();$amount=$r->string('amount');Rules::pence($amount);$tax=$r->int('tax_bps');if(!$r->string('name')||$tax<0||$tax>10000)throw new \DomainException('Enter a service name and valid tax rate.');
        DB::execute('INSERT INTO manor_services(id,name,description,amount,refundable,tax_bps,enabled) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description),amount=VALUES(amount),refundable=VALUES(refundable),tax_bps=VALUES(tax_bps),enabled=VALUES(enabled)',[$id,$r->string('name'),$r->string('description'),$amount,$r->bool('refundable')?1:0,$tax,$r->bool('enabled')?1:0]);
    }
    private function venue(Request $r): void
    {
        $data=['description'=>$r->string('description'),'description_ar'=>$r->string('description_ar'),'name_ar'=>$r->string('name_ar'),'capacity'=>$r->int('capacity')?:null,'min_minutes'=>$r->int('min_minutes',60),'max_minutes'=>$r->int('max_minutes',43200),'hours'=>$r->bool('restrict_hours')?[$r->string('open'),$r->string('close')]:null,'pricing_mode'=>$r->string('pricing_mode'),'price'=>$r->string('price','0.00'),'inherit_documents'=>$r->bool('inherit_documents'),'document_keys'=>$r->all()['document_keys']??[],'included_services'=>$r->all()['included_services']??[],'optional_services'=>$r->all()['optional_services']??[],'duration_modes'=>$r->all()['duration_modes']??['hour','day'],'hourly_rate'=>$r->string('hourly_rate','0.00'),'daily_rate'=>$r->string('daily_rate','0.00'),'slot_minutes'=>$r->int('slot_minutes',60),'min_stay_nights'=>$r->int('min_stay_nights',1),'max_stay_nights'=>$r->int('max_stay_nights',30),'check_in'=>$r->string('check_in','15:00'),'check_out'=>$r->string('check_out','11:00')];
        $data['seasonal_rates']=[];foreach(($r->all()['season_start']??[]) as $i=>$start)if(trim($start))$data['seasonal_rates'][]=['start'=>$start,'end'=>$r->all()['season_end'][$i]??'','price'=>$r->all()['season_price'][$i]??'','label'=>$r->all()['season_label'][$i]??''];
        $input=$r->all();$input['action']='unit';$input['data']=json_encode($data,JSON_THROW_ON_ERROR);
        // Preserve the existing validated hierarchy mutation and its centralized inventory lock.
        $previous=$_POST;$_POST=$input;
        try{(new Controller())->saveUnit(new Request());}finally{$_POST=$previous;}
    }
    private function setup(Request $r): void
    {
        Inventory::run(function()use($r){
        $s=Settings::get();if($s['setup_complete'])throw new \DomainException('Setup has already been completed.');
        $email=$r->string('admin_email');$password=$r->string('admin_password');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||($password!==''&&strlen($password)<16))throw new \DomainException('Use a valid administrator email and a password of at least 16 characters.');
        $user=Auth::user();$old=DB::query('SELECT email FROM operators WHERE id=?',[$user['id']])[0]['email'];
        DB::execute('UPDATE operators SET email=?,name=? WHERE id=?',[$email,$r->string('admin_name'),$user['id']]);DB::execute('UPDATE auth_emails SET email=? WHERE email=? AND user_id=?',[$email,$old,$user['id']]);
        if($password!=='')DB::execute('UPDATE operators SET password_hash=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$user['id']]);
        $s['website_name']=$r->string('website_name');$s['address']=$r->string('address');$s['public_url']=$r->string('public_url');foreach(['entire_property','partial_venue','hotel_mode'] as $flag)$s['flags'][$flag]=$r->bool('flag_'.$flag);$s['bank_details']=$r->string('bank_details');$s['setup_complete']=true;Settings::save($s);
        $_SESSION['auth_email']=$email;$_SESSION['auth_name']=$r->string('admin_name');session_regenerate_id(true);
        });
    }
}
