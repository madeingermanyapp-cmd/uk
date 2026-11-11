<?php
declare(strict_types=1);
namespace App\Manor;
use App\Engine\Database as DB;
final class Settings
{
    public static function defaults(): array
    {
        return ['flags'=>['entire_property'=>true,'partial_venue'=>true,'hotel_mode'=>false,'public_pricing'=>false,'price_on_request'=>true,'invitations'=>false,'qr_attendance'=>false,'optional_services'=>true,'documents'=>true,'newsletter'=>false,'contact'=>true,'venues'=>true,'enquiries'=>true],
        'hero_title'=>'Discover a setting for your next occasion.','hero_eyebrow'=>'Wonham Manor House · Surrey', 'hero_text'=>'Enquire about exclusive property hire, meetings and private events.', 'cta'=>'Enquire about your event',
        'intro_title'=>'Your occasion. A setting of its own.','intro_text'=>'Discover a setting for your next gathering. Our team will help you explore the spaces and arrangements that suit your plans.',
        'heritage_title'=>'Space to come together.','heritage_text'=>'Plan a meeting, celebration or private gathering with a quotation tailored to your requirements.',
        'why_texts'=>['A distinctive backdrop for your plans.','Explore spaces to suit your occasion.','A quotation shaped around your requirements.'],'why_title'=>'Why choose Wonham','why_items'=>['A considered setting','Flexible venue enquiries','Personal planning support'],
        'grounds_title'=>'The lake and gardens','grounds_text'=>'Discover the water, green spaces and views around Wonham Manor House. Contact our team to discuss access and arrangements for your occasion.','gallery_title'=>'A closer look at Wonham','gallery_text'=>'Explore the house, lake and grounds through photographs.','venues_title'=>'Explore the spaces','faq_title'=>'A few things to know','faqs'=>[['question'=>'Is my enquiry a confirmed booking?','answer'=>'No. Management will review your request and provide a quotation. Your booking is confirmed only after payment verification and availability checks.'],['question'=>'Can I hire the whole property?','answer'=>'You can request exclusive hire. Our team will confirm the spaces, terms and availability included in your quotation.']],
        'contact_title'=>'Let’s plan something memorable.','contact_text'=>'Tell us what you have in mind.','email'=>'','phone'=>'','address'=>'Surrey, United Kingdom','social_links'=>[],
        'seo_title'=>'Wonham Manor House | Venue enquiries in Surrey','seo_description'=>'Enquire about meetings, events and exclusive property hire at Wonham Manor House, Surrey.',
        'sections'=>array_fill_keys(['hero','introduction','heritage','why','grounds','gallery','venues','faq','contact','information','newsletter'],true),
        'media'=>[], 'bank_details'=>'','terms'=>'Booking is subject to management approval, an accepted quotation and verified payment.','privacy'=>'Your details are used to review and manage your enquiry. Contact management to discuss access or deletion.',
        'consent'=>'I accept the privacy notice and booking terms and understand this is an enquiry, not a confirmed booking.',
        'hold_hours'=>24,'document_types'=>[['key'=>'supporting','name'=>'Supporting document','required'=>false,'mimes'=>['application/pdf','image/jpeg','image/png'],'max_mb'=>5]],
        /* i18n-ignore: editable default English CMS content; Arabic has a separate draft. */ 'logo_url'=>'','favicon_url'=>'','navigation'=>[['label'=>'The manor','url'=>'/#introduction'],['label'=>'Our spaces','url'=>'/#venues'],['label'=>'Contact','url'=>'/#contact']],'newsletter_title'=>'Stay in touch.','newsletter_text'=>'Receive news from Wonham Manor House.','setup_complete'=>false,'payment_methods'=>['bank_transfer','external_link'],'payment_verification_required'=>true,'deposit_default'=>'0.00','public_url'=>'','website_name'=>'Wonham Manor House','currency'=>'GBP','timezone'=>'Europe/London','languages'=>['en'],'languages_configured'=>false,'notification_preferences'=>['booking'=>true,'invitation'=>true],'email_templates'=>[],'translated_content'=>['ar'=>ArabicContent::defaults()], 'content_confirmed'=>false];
    }
    public static function get(): array
    {
        $row=DB::query('SELECT data FROM manor_config WHERE id=1')[0]??null;
        $defaults=self::defaults(); $stored=$row?json_decode($row['data'],true):[];
        $result=array_replace($defaults,$stored);
        foreach(['flags','sections'] as $key)$result[$key]=array_replace($defaults[$key],$stored[$key]??[]);
        if(empty($result['languages_configured']))$result['languages']=['en'];
        $result['translated_content']=array_replace($defaults['translated_content'],$stored['translated_content']??[]);
        $result['navigation']=self::navigation($result['navigation']);
        return $result;
    }
    public static function flag(string $flag): void
    {
        if (empty(self::get()['flags'][$flag])) throw new \DomainException('This feature is currently disabled.');
    }
    public static function mode(string $mode): void
    {
        self::flag($mode==='ENTIRE_PROPERTY'?'entire_property':($mode==='ACCOMMODATION_UNIT'?'hotel_mode':'partial_venue'));
    }
    public static function validate(array $data): void
    {
        $defaults=self::defaults();
        if (array_diff(array_keys($data),array_keys($defaults))) throw new \DomainException('Unknown settings key.');
        foreach($data as $key=>$value) {
            if(is_string($defaults[$key])&&!is_string($value))throw new \DomainException('Text settings must contain text.');
            if(is_array($defaults[$key])&&!is_array($value))throw new \DomainException('List settings must contain valid lists.');
        }
        foreach($data['faqs']??[] as $faq)if(!is_array($faq)||!is_string($faq['question']??null)||!is_string($faq['answer']??null))throw new \DomainException('FAQ requires question and answer text.');
        foreach($data['sections']??[] as $key=>$value)if(!array_key_exists($key,$defaults['sections'])||!is_bool($value))throw new \DomainException('Invalid section visibility.');
        foreach ($data['flags']??[] as $key=>$value) {
            if (!array_key_exists($key,$defaults['flags']) || !is_bool($value)) throw new \DomainException('Invalid feature flag.');

        }
        foreach (['email','phone','address','bank_details','consent','terms','privacy','seo_title','seo_description'] as $key) if (isset($data[$key]) && (!is_string($data[$key]) || strlen($data[$key])>10000)) throw new \DomainException('Invalid text setting.');
        if (!empty($data['email']) && !filter_var($data['email'],FILTER_VALIDATE_EMAIL)) throw new \DomainException('Invalid contact email.');
        foreach ($data['social_links']??[] as $url) self::url($url);
        foreach ($data['media']??[] as $media) { if (!preg_match('#^/(?:uploads/manor|manor/media)/[a-f0-9]+\.(jpg|png|webp|mp4)$#D',$media['url']??'')) self::url($media['url']??''); }
        if ((int)($data['hold_hours']??24)<1 || (int)($data['hold_hours']??24)>168) throw new \DomainException('Hold duration must be 1–168 hours.');
        if(empty($data['flags']['documents']))foreach($data['document_types']??[] as $policy)if(!empty($policy['required']))throw new \DomainException('Clear mandatory document requirements before disabling customer uploads.');
        foreach ($data['document_types']??[] as $type) {
            if (!preg_match('/^[a-z_]{1,60}$/D',$type['key']??'') || empty($type['name']) || !is_bool($type['required']??null) || (int)($type['max_mb']??0)<1 || (int)$type['max_mb']>10 || empty($type['mimes']) || array_diff($type['mimes'],['application/pdf','image/jpeg','image/png'])) throw new \DomainException('Invalid document policy.');
        }
        if(!empty($data['public_url'])){
            $origin=parse_url($data['public_url']);
            if(!filter_var($data['public_url'],FILTER_VALIDATE_URL)||!is_array($origin)||empty($origin['host'])||!in_array($origin['scheme']??'',['http','https'],true)||isset($origin['user'])||!empty($origin['query'])||!empty($origin['fragment'])||!in_array($origin['path']??'',['','/'],true))throw new \DomainException('Use an HTTP or HTTPS public origin without a path.');
        }
        foreach($data['translated_content']??[] as $locale=>$content)if(!in_array($locale,['ar','en'],true)||!is_array($content)||array_diff(array_keys($content),array_diff(Content::KEYS,['translated_content','media','logo_url','favicon_url','sections'])))throw new \DomainException('Invalid translated content fields.');
        foreach(self::navigation($data['navigation']??[]) as $entry){$label=$entry['label'];$url=$entry['url'];if(!is_string($label)||!is_string($url)||(!str_starts_with($url,'/#')&&!preg_match('#^/(manor|page)/[a-z0-9/-]+$#D',$url)))throw new \DomainException('Use internal navigation destinations.');}
        foreach($data['translated_content']??[] as $content){
            foreach($content as $key=>$value)if((is_string($defaults[$key]??null)&&!is_string($value))||(is_array($defaults[$key]??null)&&!is_array($value)))throw new \DomainException('Invalid translated content fields.');
            foreach(self::navigation($content['navigation']??[]) as $entry)if(!str_starts_with($entry['url'],'/#')&&!preg_match('#^/(manor|page)/[a-z0-9/-]+$#D',$entry['url']))throw new \DomainException('Use internal navigation destinations.');
            foreach($content['faqs']??[] as $faq)if(!is_array($faq)||!is_string($faq['question']??null)||!is_string($faq['answer']??null))throw new \DomainException('FAQ requires question and answer text.');
            foreach(['why_items','why_texts'] as $key)foreach($content[$key]??[] as $value)if(!is_string($value))throw new \DomainException('Invalid translated content fields.');
        }
        if(!in_array($data['currency']??'GBP',['GBP','EUR','USD','AED'],true))throw new \DomainException('Choose a supported two-decimal currency.');
        if(($data['timezone']??'Europe/London')!=='Europe/London')throw new \DomainException('Venue booking rules use Europe/London.');
    }
    public static function navigation(array $input): array
    {
        if(array_is_list($input)){foreach($input as $entry)if(!is_array($entry)||!is_string($entry['label']??null)||!is_string($entry['url']??null))throw new \DomainException('Use internal navigation destinations.');return $input;}
        foreach([['The manor'=>'/#introduction','Our spaces'=>'/#venues','Contact'=>'/#contact'],['العقار'=>'/#introduction','الأماكن'=>'/#venues','التواصل'=>'/#contact']] as $legacy)if($input==$legacy)$input=$legacy;
        $rows=[];foreach($input as $label=>$url)$rows[]=['label'=>(string)$label,'url'=>$url];return $rows;
    }
    public static function save(array $data): void
    {
        if(isset($data['navigation']))$data['navigation']=self::navigation($data['navigation']);
        self::validate($data);
        DB::execute('UPDATE manor_config SET data=? WHERE id=1',[json_encode($data,JSON_THROW_ON_ERROR)]);
    }
    public static function url(string $url): void
    {
        if (!filter_var($url,FILTER_VALIDATE_URL) || parse_url($url,PHP_URL_SCHEME)!=='https' || parse_url($url,PHP_URL_USER)) throw new \DomainException('Use a valid HTTPS URL.');
    }
}
