<?php
declare(strict_types=1);
namespace App\Manor;
use App\Engine\{Request,Response,Auth,View,Database as DB};
use Dompdf\{Dompdf,Options};
final class QuoteController
{
    public static function input(Request $r): array
    {
        $all=$r->all();$items=[];
        foreach(($all['item_name']??[]) as $i=>$name){
            if(!trim($name))continue;
            $items[]=['name'=>$name,'description'=>$all['item_description'][$i]??'','amount'=>$all['item_amount'][$i]??'0.00','quantity'=>$all['item_quantity'][$i]??1,'tax_bps'=>$all['item_tax'][$i]??0,'mandatory'=>!empty($all['item_mandatory'][$i]),'refundable'=>!empty($all['item_refundable'][$i]),'base'=>!empty($all['item_base'][$i]),'visible'=>!empty($all['item_visible'][$i])];
        }
        foreach(($all['catalogue']??[]) as $serviceId){
            $service=DB::query('SELECT * FROM manor_services WHERE id=? AND enabled=1',[$serviceId])[0]??null;
            if(!$service)throw new \DomainException('A selected service is unavailable.');
            $items[]=['name'=>$service['name'],'description'=>$service['description'],'amount'=>$service['amount'],'quantity'=>1,'tax_bps'=>(int)$service['tax_bps'],'mandatory'=>false,'refundable'=>(bool)$service['refundable'],'base'=>false,'visible'=>true];
        }
        return ['items'=>$items,'expires'=>$r->string('expires'),'terms'=>$r->string('terms'),'payment_url'=>$r->string('payment_url')];
    }
    public function pdf(Request $r): Response
    {
        Language::apply();$quote=DB::query('SELECT * FROM manor_quotes WHERE id=?',[$r->getAttribute('id')])[0]??null;
        if(!$quote)return Response::html(Language::error('Not found'),404);
        $authorized=Auth::isOperator();
        if(!$authorized){try{$authorized=Service::capability($r->query('token'))['id']===$quote['request_id'];}catch(\DomainException){}}
        if(!$authorized)return Response::html(Language::error('Not found'),404);
        $record=Service::request($quote['request_id']);$q=json_decode($quote['accepted_data']?:$quote['data'],true);
        $html=View::render('manor.quote-pdf',['settings'=>Content::publicSettings(),'record'=>$record,'quote'=>$quote,'q'=>$q]);
        // Dompdf needs visual Arabic glyph joining; transform text nodes only,
        // keeping HTML structure, escaping and mixed Latin identifiers intact.
        if(\App\Engine\Locale::getLocale()==='ar'){
            $document=new \DOMDocument();$previous=libxml_use_internal_errors(true);
            $document->loadHTML('<?xml encoding="UTF-8">'.$html);libxml_clear_errors();libxml_use_internal_errors($previous);
            $arabic=new \ArPHP\I18N\Arabic();$xpath=new \DOMXPath($document);
            foreach($xpath->query('//body//text()[not(ancestor::style) and not(ancestor::script)]') as $node){
                if(preg_match('/(?=\p{Arabic})\p{L}/u',$node->nodeValue))$node->nodeValue=$arabic->utf8Glyphs($node->nodeValue,80,false);
            }
            foreach(iterator_to_array($document->childNodes) as $child)if($child->nodeType===XML_PI_NODE)$document->removeChild($child);
            $html=$document->saveHTML();
        }
        $cache=dirname(__DIR__,2).'/storage/cache/dompdf';if(!is_dir($cache))mkdir($cache,0700,true);
        $options=new Options();$options->setFontCache($cache);$options->setTempDir($cache);$options->setIsRemoteEnabled(false);$options->setIsPhpEnabled(false);$options->setDefaultFont('DejaVu Sans');$options->setChroot(dirname(__DIR__,2).'/public');
        $pdf=new Dompdf($options);$pdf->loadHtml($html,'UTF-8');$pdf->setPaper('A4');$pdf->render();
        return (new Response())->body($pdf->output())->header('Content-Type','application/pdf')->header('Content-Disposition','attachment; filename="quotation-'.$quote['id'].'.pdf"')->header('Cache-Control','no-store')->header('Referrer-Policy','no-referrer');
    }
}
