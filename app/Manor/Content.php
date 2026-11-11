<?php
declare(strict_types=1);
namespace App\Manor;
use App\Engine\{Database as DB,Ulid,Auth};
final class Content
{
    public const KEYS=['logo_url','favicon_url','hero_title','hero_eyebrow','hero_text','cta','intro_title','intro_text','heritage_title','heritage_text','why_title','why_items','why_texts','grounds_title','grounds_text','gallery_title','gallery_text','venues_title','faq_title','faqs','contact_title','contact_text','seo_title','seo_description','sections','media','translated_content','terms','privacy','consent','navigation','newsletter_title','newsletter_text'];
    public static function consentVersion(): string
    {
        return DB::query("SELECT id FROM manor_content_versions WHERE status='published' ORDER BY approved_at DESC,id DESC LIMIT 1")[0]['id']??'draft';
    }
    public static function draft(): array
    {
        $published=DB::query("SELECT id,data FROM manor_content_versions WHERE status='published' ORDER BY approved_at DESC,id DESC LIMIT 1")[0]??null;
        $row=DB::query("SELECT * FROM manor_content_versions WHERE status='draft' AND id>? ORDER BY created_at DESC,id DESC LIMIT 1",[$published['id']??''])[0]??null;
        $base=array_replace(Settings::get(),$published?json_decode($published['data'],true):[]);
        $data=$row?array_replace($base,json_decode($row['data'],true)):$base;
        $data['sections']=array_replace(Settings::defaults()['sections'],$data['sections']??[]);
        return ['id'=>$row['id']??null,'data'=>$data];
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
        $settings['sections']=array_replace(Settings::defaults()['sections'],$settings['sections']??[]);
        $settings['navigation']=Settings::navigation($settings['navigation']);
        $settings['published_pages']=DB::query('SELECT slug,title FROM manor_pages WHERE enabled=1 AND locale=? ORDER BY title',[$locale]);
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
            DB::execute("UPDATE manor_content_versions SET status='archived' WHERE status='draft' AND id<?",[$id]);
            DB::execute("UPDATE manor_content_versions SET status='published',approved_at=UTC_TIMESTAMP(),actor=? WHERE id=?",[Auth::user()['id']??'system',$id]);
            $s=Settings::get();$s['content_confirmed']=true;Settings::save($s);Service::audit(null,'content_published',['version'=>$id]);
        });
    }
    public static function mediaReview(string $id,string $alt,string $rights,bool $approve): void
    {
        Inventory::run(function()use($id,$alt,$rights,$approve){
        $asset=DB::query('SELECT status FROM manor_media WHERE id=? AND deleted_at IS NULL FOR UPDATE',[$id])[0]??null;
        if(!$asset)throw new \DomainException('Media asset not found.');
        if($approve&&trim($rights)==='')throw new \DomainException('Record the source and publication rights for this asset.');
        DB::execute('UPDATE manor_media SET alt=?,rights=?,status=?,approved_by=?,approved_at=? WHERE id=? AND deleted_at IS NULL',[mb_substr($alt,0,2000),mb_substr($rights,0,5000),$approve?($asset['status']==='published'?'published':'approved'):'draft',$approve?(Auth::user()['id']??'system'):null,$approve?gmdate('Y-m-d H:i:s'):null,$id]);
        $draft=self::draft()['data'];foreach($draft['media'] as &$entry)if(str_starts_with($entry['url']??'','/manor/media/'.$id.'.'))$entry['alt']=mb_substr($alt,0,2000);unset($entry);self::saveDraft($draft);
        Service::audit(null,'media_rights_reviewed',['media_id'=>$id,'approved'=>$approve]);
        });
    }
    public static function removeMedia(string $id): void
    {
        DB::execute("UPDATE manor_media SET status='removed',deleted_at=UTC_TIMESTAMP() WHERE id=?",[$id]);
        $draft=self::draft()['data'];$draft['media']=array_values(array_filter($draft['media'],fn($m)=>!str_starts_with($m['url']??'','/manor/media/'.$id.'.')));
        foreach(['logo_url','favicon_url'] as $key)if(str_starts_with($draft[$key]??'','/manor/media/'.$id.'.'))$draft[$key]='';self::saveDraft($draft);
        Service::audit(null,'media_removed',['media_id'=>$id]);
    }
    public static function pageHash(array $data): string
    {
        ksort($data);return hash('sha256',json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    }
    public static function pageDraft(string $id): ?array
    {
        $row=DB::query('SELECT p.*,d.data AS draft_data,d.approved_hash FROM manor_pages p LEFT JOIN manor_page_drafts d ON d.page_id=p.id WHERE p.id=?',[$id])[0]??null;
        if(!$row)return null;
        return !empty($row['draft_data'])?array_replace($row,json_decode($row['draft_data'],true)):$row;
    }
    public static function page(array $input): void
    {
        Inventory::run(function()use($input){
            $id=trim($input['page_id']??'');$row=$id?DB::query('SELECT * FROM manor_pages WHERE id=? FOR UPDATE',[$id])[0]??null:null;
            if($id&&!$row)throw new \DomainException('Page not found.');
            $action=$input['page_action']??(!empty($input['enabled'])?'publish':'draft');
            if(!in_array($action,['draft','publish','unpublish'],true))throw new \DomainException('Unknown action.');
            if($action==='unpublish'){
                if(!$row)throw new \DomainException('Page not found.');
                DB::execute('UPDATE manor_pages SET enabled=0 WHERE id=?',[$id]);Service::audit(null,'page_unpublished',['page_id'=>$id]);return;
            }
            $data=['slug'=>trim($input['slug']??''),'locale'=>$input['locale']??'en','title'=>mb_substr(trim($input['title']??''),0,255),'body'=>mb_substr($input['body']??'',0,100000),'seo_description'=>mb_substr($input['seo_description']??'',0,1000)];
            if(!preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/D',$data['slug'])||!in_array($data['locale'],['en','ar'],true)||!$data['title'])throw new \DomainException('Enter a page title, language and valid URL slug.');
            if(DB::query('SELECT id FROM manor_pages WHERE slug=? AND locale=? AND id<>? LIMIT 1',[$data['slug'],$data['locale'],$id]))throw new \DomainException('A page already uses this URL and language.');
            if($action==='publish'&&empty($input['approved']))throw new \DomainException('Approve page content before publication.');
            if(!$row){$id=Ulid::generate();DB::execute('INSERT INTO manor_pages(id,slug,locale,title,body,seo_description,enabled) VALUES(?,?,?,?,?,?,0)',[$id,$data['slug'],$data['locale'],$data['title'],'','']);}
            DB::execute('INSERT INTO manor_page_drafts(page_id,data) VALUES(?,?) ON DUPLICATE KEY UPDATE data=VALUES(data)',[$id,json_encode($data,JSON_THROW_ON_ERROR)]);
            if($action==='publish'){
                DB::execute('UPDATE manor_pages SET slug=?,locale=?,title=?,body=?,seo_description=?,enabled=1 WHERE id=?',[$data['slug'],$data['locale'],$data['title'],$data['body'],$data['seo_description'],$id]);
                $hash=self::pageHash($data);DB::execute('UPDATE manor_page_drafts SET approved_hash=?,approved_at=UTC_TIMESTAMP(),approved_by=? WHERE page_id=?',[$hash,Auth::user()['id']??'system',$id]);
                Service::audit(null,'page_published',['page_id'=>$id,'content_hash'=>$hash,'approved'=>true]);
            }else Service::audit(null,'page_draft_saved',['page_id'=>$id]);
        });
    }
}
