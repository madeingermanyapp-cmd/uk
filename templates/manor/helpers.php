<?php
use App\Engine\View;
use App\Middleware\CsrfMiddleware;
function me($v): string {return View::e($v);}
function money($v): string {return '£'.number_format((int)$v/100,2);}
function csrf(): string {return '<input type="hidden" name="_csrf_token" value="'.me(CsrfMiddleware::generateToken()).'">';}
function field($name,$label,$value='',$type='text',$required=true): string {return '<label>'.me($label).'<input type="'.me($type).'" name="'.me($name).'" value="'.me($value).'" '.($required?'required':'').'></label>';}
function media($settings,$section,$gallery=false): string {
 $items=array_values(array_filter($settings['media'],fn($m)=>($m['section']??'')===$section));
 if(!$items)$items=[['url'=>'/manor/property.jpg','alt'=>'Wonham Manor House exterior, supplied design reference photograph','kind'=>'image']];
 $html='';foreach($gallery?$items:array_slice($items,0,1) as $m){
  if(($m['kind']??'image')==='video')$html.='<video controls preload="metadata" src="'.me($m['url']).'"></video>';
  else $html.='<img src="'.me($m['url']).'" alt="'.me($m['alt']??'').'" loading="lazy">';
 }
 return $gallery&&count($items)>1?'<div class="media-gallery" aria-label="Venue gallery">'.$html.'</div>':$html;
}
