<?php
declare(strict_types=1);
namespace App\Manor;
use App\Engine\{Database as DB,Ulid};
final class Files
{
    public static function upload(string $requestId,string $type,array $file): void
    {
        Settings::flag('documents');
        $r=Service::request($requestId);
        if(in_array($r['status'],['cancelled','rejected','expired'],true)) throw new \DomainException('This request is closed.');
        $policy=null;
        foreach(Service::documentTypes($r) as $p) if($p['key']===$type) $policy=$p;
        if($type==='payment_evidence') $policy=['mimes'=>['application/pdf','image/jpeg','image/png'],'max_mb'=>5];
        if(!$policy) throw new \DomainException('Unknown document type.');
        [$mime,$bytes]=self::validate($file,$policy['mimes'],(int)$policy['max_mb']*1048576);
        $root=dirname(__DIR__,2).'/storage/private/manor';
        if(!is_dir($root)) mkdir($root,0700,true);
        $name=bin2hex(random_bytes(32)); $path=$root.'/'.$name;
        if(!move_uploaded_file($file['tmp_name'],$path)) throw new \RuntimeException('Document storage failed.');
        chmod($path,0600);
        try { DB::execute('INSERT INTO manor_documents(id,request_id,type,storage_name,original_name,mime,bytes) VALUES(?,?,?,?,?,?,?)',[Ulid::generate(),$requestId,$type,$name,mb_substr(basename($file['name']),0,255),$mime,$bytes]); Service::audit($requestId,'document_uploaded',['type'=>$type]); }
        catch(\Throwable $e){unlink($path);throw $e;}
    }
    public static function validate(array $file,array $mimes,int $max): array
    {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']??'')) throw new \DomainException('Choose a valid uploaded file.');
        $size=filesize($file['tmp_name']); $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if(!$size || $size>$max || !in_array($mime,$mimes,true)) throw new \DomainException('File format or size is not allowed.');
        if(str_starts_with($mime,'image/')) {
            $info=getimagesize($file['tmp_name']);
            if(!$info || $info[0]*$info[1]>40000000) throw new \DomainException('Image dimensions are not allowed.');
        }
        return [$mime,$size];
    }
    public static function media(array $file): string
    {
        [$mime]=self::validate($file,['image/jpeg','image/png','image/webp','video/mp4'],25*1048576);
        $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','video/mp4'=>'mp4'][$mime];
        $root=dirname(__DIR__,2).'/storage/media/manor'; if(!is_dir($root)) mkdir($root,0700,true);
        $id=bin2hex(random_bytes(24));$name=$id.'.'.$ext;$path=$root.'/'.$name;
        if(str_starts_with($mime,'image/')) {
            $image=@imagecreatefromstring(file_get_contents($file['tmp_name']));
            if(!$image)throw new \DomainException('Image cannot be decoded.');
            if(imagesx($image)>2400)$image=imagescale($image,2400);
            $written=match($mime){'image/jpeg'=>imagejpeg($image,$path,88),'image/png'=>imagepng($image,$path),'image/webp'=>imagewebp($image,$path,88)};
            if(!$written)throw new \RuntimeException('Media storage failed.');
            if(imagesx($image)>720){$small=imagescale($image,720);imagewebp($small,$root.'/'.$id.'-720.webp',85);imagedestroy($small);}
            imagedestroy($image);
        }elseif(!move_uploaded_file($file['tmp_name'],$path))throw new \RuntimeException('Media storage failed.');
        chmod($path,0600);
        DB::execute('INSERT INTO manor_media(id,storage_name,original_name,mime,bytes,alt,rights) VALUES(?,?,?,?,?,?,?)',[$id,$name,mb_substr(basename($file['name']),0,255),$mime,filesize($path),'','']);
        return '/manor/media/'.$name;
    }
}
