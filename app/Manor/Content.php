<?php
declare(strict_types=1);
namespace App\Manor;
use App\Engine\{Database as DB,Ulid,Auth};
final class Content
{
    public const KEYS=['logo_url','favicon_url','hero_title','hero_eyebrow','hero_text','cta','intro_title','intro_text','heritage_title','heritage_text','why_title','why_items','why_texts','venues_title','faq_title','faqs','contact_title','contact_text','seo_title','seo_description','sections','media','translated_content','terms','privacy','consent','navigation','newsletter_title','newsletter_text'];
    public static function consentVersion(): string
    {
        return DB::query("SELECT id FROM manor_content_versions WHERE status='published' ORDER BY approved_at DESC,id DESC LIMIT 1")[0]['id']??'draft';
    }
    public static function draft(): array
    {
        $row=DB::query("SELECT * FROM manor_content_versions WHERE status='draft' ORDER BY created_at DESC,id DESC LIMIT 1")[0]??null;
        return ['id'=>$row['id']??null,'data'=>$row?array_replace(Settings::get(),json_decode($row['data'],true)):Settings::get()];
    }
    public static function publicSettings(bool $preview=false): array
    {
        $settings=Settings::get();
        $row=$preview?self::draft():null;
        if($preview)$settings=array_replace($settings,$row['data']);
        else{
            $published=DB::query("SELECT data FROM manor_content_versions WHERE status='published' ORDER BY approved_at DESC,id DESC LIMIT 1")[0]??null;
            if($published)$settings=array_replace($settings,json_decode($published['data'],true));
            $settings['publication_draft']=!$published;
        }
        foreach(['logo_url','favicon_url'] as $key)if(preg_match('#^/manor/media/([a-f0-9]{48})\.[a-z0-9]+$#D',$settings[$key]??'',$m)&&!DB::query("SELECT id FROM manor_media WHERE id=? AND deleted_at IS NULL".($preview?'':" AND status='published'"),[$m[1]]))$settings[$key]='';
        $settings['preview']=$preview;
        $settings['media']=array_values(array_filter($settings['media'],static function($m)use($preview){
            if(preg_match('#^/manor/media/([a-f0-9]{48})\.[a-z0-9]+$#D',$m['url']??'',$match)){
                return (bool)DB::query('SELECT id FROM manor_media WHERE id=? AND deleted_at IS NULL'.($preview?'':" AND status='published'"),[$match[1]]);
            }
            // External media is explicit approved content, never silently published from an upload.
            return $preview||!empty($m['rights_approved']);
        }));
        $settings['translated_content']=array_replace(Settings::defaults()['translated_content'],$settings['translated_content']??[]);
        $locale=\App\Engine\Locale::getLocale();
        if($locale!=='en')$settings=array_replace($settings,array_intersect_key($settings['translated_content'][$locale]??[],array_flip(array_diff(self::KEYS,['media','logo_url','favicon_url','sections']))));
        $settings['navigation']=Settings::navigation($settings['navigation']);
        return $settings;
    }
    public static function saveDraft(array $data): string
    {
        if(isset($data['navigation']))$data['navigation']=Settings::navigation($data['navigation']);
        $content=array_intersect_key($data,array_flip(self::KEYS));
        // Validate through the same settings schema, then restore live values; draft never changes publication.
        $live=Settings::get();Settings::validate(array_replace($live,$content));
        $id=Ulid::generate();DB::execute("INSERT INTO manor_content_versions(id,data,status,actor) VALUES(?,?,'draft',?)",[$id,json_encode($content,JSON_THROW_ON_ERROR),Auth::user()['id']??'system']);
        Service::audit(null,'content_draft_saved',['version'=>$id]);return $id;
    }
    public static function publish(string $id,bool $approved): void
    {
        if(!$approved)throw new \DomainException('Record content and publication-rights approval before publishing.');
        Inventory::run(function()use($id){
            $row=DB::query("SELECT * FROM manor_content_versions WHERE id=? AND status='draft' FOR UPDATE",[$id])[0]??null;
            if(!$row)throw new \DomainException('Draft version not found.');$data=json_decode($row['data'],true);
            foreach($data['media']??[] as $media){
                if(preg_match('#^/manor/media/([a-f0-9]{48})\.[a-z0-9]+$#D',$media['url']??'',$match)){
                    $asset=DB::query("SELECT * FROM manor_media WHERE id=? AND deleted_at IS NULL AND status IN('approved','published')",[$match[1]])[0]??null;
                    if(!$asset||!trim($asset['rights']))throw new \DomainException('Every selected media asset requires recorded rights and approval.');
                    DB::execute("UPDATE manor_media SET status='published' WHERE id=?",[$match[1]]);
                }elseif(empty($media['rights_approved'])||empty($media['rights']))throw new \DomainException('External media requires recorded publication rights.');
            }
            DB::execute("UPDATE manor_content_versions SET status='archived' WHERE status='published'");
            DB::execute("UPDATE manor_content_versions SET status='published',approved_at=UTC_TIMESTAMP(),actor=? WHERE id=?",[Auth::user()['id']??'system',$id]);
            $s=Settings::get();$s['content_confirmed']=true;Settings::save($s);Service::audit(null,'content_published',['version'=>$id]);
        });
    }
    public static function mediaReview(string $id,string $alt,string $rights,bool $approve): void
    {
        if($approve&&trim($rights)==='')throw new \DomainException('Record the source and publication rights for this asset.');
        DB::execute('UPDATE manor_media SET alt=?,rights=?,status=?,approved_by=?,approved_at=? WHERE id=? AND deleted_at IS NULL',[mb_substr($alt,0,2000),mb_substr($rights,0,5000),$approve?'approved':'draft',$approve?(Auth::user()['id']??'system'):null,$approve?gmdate('Y-m-d H:i:s'):null,$id]);
        $draft=self::draft()['data'];foreach($draft['media'] as &$entry)if(str_starts_with($entry['url']??'','/manor/media/'.$id.'.'))$entry['alt']=mb_substr($alt,0,2000);unset($entry);self::saveDraft($draft);
        Service::audit(null,'media_rights_reviewed',['media_id'=>$id,'approved'=>$approve]);
    }
    public static function removeMedia(string $id): void
    {
        DB::execute("UPDATE manor_media SET status='removed',deleted_at=UTC_TIMESTAMP() WHERE id=?",[$id]);
        $draft=self::draft()['data'];$draft['media']=array_values(array_filter($draft['media'],fn($m)=>!str_starts_with($m['url']??'','/manor/media/'.$id.'.')));
        foreach(['logo_url','favicon_url'] as $key)if(str_starts_with($draft[$key]??'','/manor/media/'.$id.'.'))$draft[$key]='';self::saveDraft($draft);
        Service::audit(null,'media_removed',['media_id'=>$id]);
    }
    public static function page(array $input): void
    {
        $id=$input['page_id']??'';if(!$id)$id=Ulid::generate();$slug=trim($input['slug']??'');$locale=$input['locale']??'en';
        if(!preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/D',$slug)||!in_array($locale,['en','ar'],true)||!trim($input['title']??''))throw new \DomainException('Enter a page title, language and valid URL slug.');
        if(!empty($input['enabled'])&&empty($input['approved']))throw new \DomainException('Approve page content before publication.');
        DB::execute('INSERT INTO manor_pages(id,slug,locale,title,body,seo_description,enabled) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),body=VALUES(body),seo_description=VALUES(seo_description),enabled=VALUES(enabled)',[$id,$slug,$locale,mb_substr($input['title'],0,255),mb_substr($input['body']??'',0,100000),mb_substr($input['seo_description']??'',0,1000),!empty($input['enabled'])?1:0]);
        Service::audit(null,'page_saved',['page_id'=>$id,'published'=>!empty($input['enabled'])]);
    }
}
