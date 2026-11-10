<?php
declare(strict_types=1);
namespace App\Manor;
use App\Engine\{Request,Response,Auth,View,Database as DB,Locale};
final class PublicController
{
    public function preview(Request $r): Response
    {
        if(!Auth::isOperator())return Response::html(Language::error('Forbidden'),403);
        Language::apply();return View::response('manor.home',['settings'=>Content::publicSettings(true),'units'=>DB::query('SELECT * FROM manor_units WHERE enabled=1 ORDER BY name')])->header('Cache-Control','no-store')->header('X-Robots-Tag','noindex')->header('Content-Security-Policy',Controller::csp());
    }
    public function reference(Request $r): Response
    {
        if(!Auth::isOperator())return Response::html(Language::error('Not found'),404);
        $path=dirname(__DIR__,2).'/.local/design-reference/property.jpg';
        if(!is_file($path))return Response::html(Language::error('Not found'),404);
        return (new Response())->body(file_get_contents($path))->header('Content-Type','image/jpeg')->header('Cache-Control','no-store');
    }
    public function media(Request $r): Response
    {
        $id=$r->getAttribute('id');$ext=$r->getAttribute('ext');
        $row=DB::query('SELECT * FROM manor_media WHERE id=? AND deleted_at IS NULL',[$id])[0]??null;
        if(!$row||($row['status']!=='published'&&!Auth::isOperator()))return Response::html(Language::error('Not found'),404);
        $variant=$r->query('width')==='720'&&str_starts_with($row['mime'],'image/');
        $name=$variant?$id.'-720.webp':$row['storage_name'];$path=dirname(__DIR__,2).'/storage/media/manor/'.$name;
        if(!is_file($path)){$variant=false;$path=dirname(__DIR__,2).'/storage/media/manor/'.$row['storage_name'];}
        if(!is_file($path))return Response::html(Language::error('Not found'),404);
        // URL extension must match the recorded type; paths are never derived from user input.
        if($ext!==pathinfo($row['storage_name'],PATHINFO_EXTENSION))return Response::html(Language::error('Not found'),404);
        $size=filesize($path);$start=0;$end=$size-1;$status=200;
        $range=$r->header('Range');
        if($range!==null){
            if(!preg_match('/^bytes=(\d+)-(\d*)$/D',$range,$match))return (new Response())->status(416)->header('Content-Range','bytes */'.$size);
            $start=(int)$match[1];$end=$match[2]===''?$end:min($end,(int)$match[2]);
            if($start>$end||$start>=$size)return (new Response())->status(416)->header('Content-Range','bytes */'.$size);$status=206;
        }
        $stream=fopen($path,'rb');fseek($stream,$start);$body=stream_get_contents($stream,$end-$start+1);fclose($stream);
        $response=(new Response())->status($status)->body($body)->header('Content-Type',$variant?'image/webp':$row['mime'])->header('Accept-Ranges','bytes')->header('Content-Length',(string)strlen($body))->header('Cache-Control',$row['status']==='published'?'public, max-age=0, must-revalidate':'no-store')->header('X-Content-Type-Options','nosniff');
        if($status===206)$response->header('Content-Range',"bytes $start-$end/$size");return $response;
    }
    public function page(Request $r): Response
    {
        Language::apply();$locale=Locale::getLocale();
        $page=DB::query('SELECT * FROM manor_pages WHERE slug=? AND locale=? AND enabled=1',[$r->getAttribute('slug'),$locale])[0]??null;
        if(!$page)return Response::html(Language::error('Not found'),404);
        $s=Content::publicSettings();$s['seo_title']=$page['title'].' · '.$s['website_name'];$s['seo_description']=$page['seo_description'];
        return View::response('manor.page',['settings'=>$s,'page'=>$page]);
    }
    public function pagePreview(Request $r): Response
    {
        if(!Auth::isOperator())return Response::html(Language::error('Forbidden'),403);
        Language::apply();$page=Content::pageDraft($r->getAttribute('id'));if(!$page)return Response::html(Language::error('Page not found.'),404);
        $s=Content::publicSettings();$s['preview']=true;$s['seo_title']=$page['title'];$s['seo_description']=$page['seo_description'];
        return View::response('manor.page',['settings'=>$s,'page'=>$page])->header('Cache-Control','no-store')->header('X-Robots-Tag','noindex')->header('Referrer-Policy','no-referrer');
    }
    public function recovery(Request $r): Response
    {
        Language::apply();
        try{
            if($r->method()==='POST'){
                if($r->getAttribute('token'))return Response::redirect('/manor/request/'.Notifications::exchange($r->getAttribute('token')));
                Notifications::recover($r->string('email'),$r->string('reference'));Notifications::flush();
                return View::response('manor.error',['settings'=>Content::publicSettings(),'message'=>__('manor.recovery_sent')]);
            }
            return View::response('manor.recover',['settings'=>Content::publicSettings(),'exchange'=>(bool)$r->getAttribute('token')])->header('Cache-Control','no-store')->header('Referrer-Policy','no-referrer');
        }catch(\DomainException $e){return View::response('manor.error',['settings'=>Content::publicSettings(),'message'=>Language::error($e->getMessage())],422);}
    }
}
