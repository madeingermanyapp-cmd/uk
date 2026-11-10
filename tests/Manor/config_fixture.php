<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
use App\Engine\Database as DB;
if(($_ENV['APP_ENV']??'')!=='local')throw new RuntimeException('Configuration fixture requires APP_ENV=local.');
if(($argv[1]??'')==='identity'){echo $_ENV['APP_URL']??'';exit;}
$dir=dirname(__DIR__,2).'/.local';if(!is_dir($dir))mkdir($dir,0700,true);
$file=$dir.'/configuration-check-snapshot.json';
$tables=['manor_config','manor_content_versions','manor_media','manor_pages','manor_page_drafts'];
if(($argv[1]??'')==='save'){
 if(is_file($file))throw new RuntimeException('A configuration verification snapshot already exists; restore it before another run.');
 $data=[];foreach($tables as $table)$data[$table]=DB::query('SELECT * FROM '.$table);
 file_put_contents($file,json_encode($data,JSON_THROW_ON_ERROR));chmod($file,0600);
}else{
 if(!is_file($file))throw new RuntimeException('No configuration verification snapshot exists.');
 DB::transaction(function()use($file,$tables){
  $data=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
  foreach($tables as $table){DB::execute('DELETE FROM '.$table);foreach($data[$table] as $row){$columns=array_keys($row);DB::execute('INSERT INTO '.$table.' (`'.implode('`,`',$columns).'`) VALUES('.implode(',',array_fill(0,count($columns),'?')).')',array_values($row));}}
 });unlink($file);
}
