<?php
use App\Engine\View;
use App\Middleware\CsrfMiddleware;
function me($v): string {return View::e($v);}
function money($v,$currency=null): string {return \App\Engine\Locale::currency((int)$v/100,$currency??\App\Manor\Settings::get()['currency']);}
function csrf(): string {return '<input type="hidden" name="_csrf_token" value="'.me(CsrfMiddleware::generateToken()).'">';}
function field($name,$label,$value='',$type='text',$required=true): string {return '<label>'.me($label).'<input type="'.me($type).'" name="'.me($name).'" value="'.me($value).'" '.($required?'required':'').'></label>';}
function media($settings,$section,$gallery=false): string {
 $items=array_values(array_filter($settings['media'],fn($m)=>($m['section']??'')===$section));
 if(!$items){if(!empty($settings['preview']))$items=[['url'=>'/admin/manor/reference-media','alt'=>'Draft reference photograph — publication rights not approved','kind'=>'image']];else return '<div class="media-placeholder" role="img" aria-label="'.me(__('manor.draft_media_placeholder')).'"><span>'.me($settings['website_name']).'</span><small>'.me(__('manor.draft_media_placeholder')).'</small></div>';}
 $html='';foreach($gallery?$items:array_slice($items,0,1) as $m){
  if(($m['kind']??'image')==='video')$html.='<video controls preload="metadata" src="'.me($m['url']).'"></video>';
  else $html.='<img '.(str_starts_with($m['url'],'/manor/media/')?'srcset="'.me($m['url']).'?width=720 720w, '.me($m['url']).' 2400w" sizes="(max-width: 720px) 100vw, 60vw" ':'').'src="'.me($m['url']).'" alt="'.me($m['alt']??'').'" loading="lazy">';
 }
 return $gallery&&count($items)>1?'<div class="media-gallery" aria-label="Venue gallery">'.$html.'</div>':$html;
}

function pdf_money(int $pence,string $currency): string {return me($currency.' '.number_format(intdiv($pence,100),0,'.',',').'.'.str_pad((string)($pence%100),2,'0',STR_PAD_LEFT));}
