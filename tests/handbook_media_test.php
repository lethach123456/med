<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_handbook_media.php';
if (!extension_loaded('gd') || !class_exists('DOMDocument')) throw new RuntimeException('Media tests require GD + DOM.');
$checks=0;
$assert=static function(bool $ok,string $label)use(&$checks):void{$checks++;if(!$ok)throw new RuntimeException($label);};
$reject=static function(callable $fn)use($assert):void{
    try{$fn();$assert(false,'Invalid media accepted');}catch(InvalidArgumentException){$assert(true,'Invalid media rejected');}
};
$make=static function(int $width,int $height,int $color):string{
    $image=imagecreatetruecolor($width,$height);
    imagefilledrectangle($image,0,0,$width,$height,$color);
    imageellipse($image,(int)($width/2),(int)($height/2),(int)($width/2),(int)($height/2),0xffffff);
    ob_start();imagepng($image);$bytes=(string)ob_get_clean();imagedestroy($image);return$bytes;
};
$png=$make(1600,900,0x3b77cc);
$uri='data:image/png;base64,'.base64_encode($png);
$other='data:image/png;base64,'.base64_encode($make(640,480,0x29a28e));
$root=sys_get_temp_dir().'/medreview-handbook-test-'.bin2hex(random_bytes(8));
if(!mkdir($root,0700))throw new RuntimeException('Cannot create test root');
$root=(string)realpath($root);
$remove=static function(string $path)use(&$remove):void{
    if(is_link($path)||is_file($path)){unlink($path);return;}
    if(!is_dir($path))return;
    foreach(new DirectoryIterator($path)as$item){if(!$item->isDot())$remove($item->getPathname());}
    rmdir($path);
};
try{
    $html='<h1>Chủ đề</h1><h2>Kiến thức hữu ích</h2><p style="display:none" onclick="bad()">'.str_repeat('Thông tin tham khảo có ích. ',15).'</p>'
        .'<script>throw new Error("bad")</script><iframe src="https://example.org/tracker">Track</iframe><svg><image href="bad"/></svg>'
        .'<figure><img src="'.$uri.'" alt="Minh hoạ chăm sóc" srcset="https://example.org/track 2x" onerror="bad()"><figcaption>Minh hoạ chăm sóc (ảnh tạo bằng AI).</figcaption></figure>'
        .'<a href="javascript:alert(1)" onclick="x()">Không an toàn</a><a href="https://www.who.int/" target="_blank">WHO</a>'
        .'<table><tbody><tr><th scope="col">Mục</th><td colspan="2" style="color:red">Nội dung</td></tr></tbody></table>';
    $result=handbook_ai_prepare_article_html($html,17,$root);
    $assert(count($result['images'])===1&&count($result['created_files'])===1,'One real raster imported');
    $meta=$result['images'][0];$file=$result['created_files'][0];
    $assert(is_file($file)&&!is_link($file)&&str_starts_with($file,$root.'/uploads/handbook/17/'),'File confined to server-generated post folder');
    $assert($meta['width']<=1280&&$meta['height']<=1280&&$meta['size']<102400&&filesize($file)===$meta['size'],'Image bounded and compressed below 100KiB');
    $assert(getimagesize($file)[2]===IMAGETYPE_JPEG,'Input PNG re-encoded to JPEG');
    $assert($meta['alt']==='Minh hoạ chăm sóc'&&$meta['caption']==='Minh hoạ chăm sóc (ảnh tạo bằng AI).'&&$meta['ai_generated']===true,'Alt/caption and AI provenance preserved');
    foreach(['<script','<iframe','<svg','style=','onclick','onerror','srcset','javascript:','data:image','base64,','throw new Error','Track']as$bad){
        $assert(!str_contains($result['content'],$bad),'Unsafe markup not persisted: '.$bad);
    }
    foreach(['<h2>','<table>','scope="col"','colspan="2"','rel="noopener noreferrer"','width="1280"','loading="lazy"',$meta['url']]as$safe){
        $assert(str_contains($result['content'],$safe),'Semantic output preserved: '.$safe);
    }
    $assert(!str_contains(json_encode($result['images']),'base64'),'No raw bytes in image metadata');
    $second=handbook_ai_prepare_article_html('<p>Second import</p><figure><img src="'.$uri.'" alt="Second"></figure>',17,$root);
    $assert($second['created_files'][0]!==$file&&is_file($file),'Second operation never overwrites first image');
    $duplicate=handbook_ai_prepare_article_html('<p>Duplicate data</p><img src="'.$uri.'"><img src="'.$uri.'">',18,$root);
    $assert(count($duplicate['images'])===1&&substr_count($duplicate['content'],$duplicate['images'][0]['url'])===2,'Same bytes deduplicated within one article');
    $multiple=handbook_ai_prepare_article_html('<p>Multiple images</p><img src="'.$uri.'"><img src="'.$other.'">',19,$root);
    $assert(count($multiple['images'])===2,'Distinct illustrations imported');
    foreach(['https://example.org/image.png','//example.org/image.png','file:///etc/passwd','blob:https://example.org/a',
        'data:image/svg+xml;base64,'.base64_encode('<svg/>'),'data:image/gif;base64,R0lGODlhAQABAAAAACw=',
        'data:image/jpeg;base64,'.base64_encode($png),'data:image/png;base64,...',str_replace('base64,','base64,\n',$uri),
        'data:image/png;base64,'.base64_encode('not an image')]as$bad){
        $reject(static fn()=>handbook_ai_prepare_article_html('<p>Invalid</p><img src="'.htmlspecialchars($bad,ENT_QUOTES).'">',20,$root));
    }
    $reject(static fn()=>handbook_ai_prepare_article_html('<p>No image</p>',20,$root));
    $reject(static fn()=>handbook_ai_prepare_article_html('<img src="'.$uri.'">',20,$root));
    $reject(static fn()=>handbook_ai_prepare_article_html('<!DOCTYPE foo [<!ENTITY x SYSTEM "file:///etc/passwd">]><p>XXE</p><img src="'.$uri.'">',20,$root));
    $reject(static fn()=>handbook_ai_prepare_article_html('<p>Many</p>'.str_repeat('<img src="'.$other.'">',5),20,$root));
    $reject(static fn()=>handbook_ai_prepare_article_html('<p>Deep</p>'.str_repeat('<div>',90).'<img src="'.$uri.'">'.str_repeat('</div>',90),20,$root));
    $reject(static fn()=>handbook_ai_prepare_article_html('<p>Failure after valid image</p><img src="'.$uri.'"><img src="invalid">',21,$root));
    $assert(!is_dir($root.'/uploads/handbook/21'),'All images validate before any write');
    $huge=substr_replace($png,pack('N',9000),16,4);
    $reject(static fn()=>handbook_ai_media_decode('data:image/png;base64,'.base64_encode($huge)));
    $tooManyPixels=substr_replace(substr_replace($png,pack('N',4000),16,4),pack('N',4000),20,4);
    $reject(static fn()=>handbook_ai_media_decode('data:image/png;base64,'.base64_encode($tooManyPixels)));
    $reject(static fn()=>handbook_ai_media_decode('data:image/png;base64,'.str_repeat('A',7*1024*1024)));
    $reject(static fn()=>handbook_ai_prepare_article_html(str_repeat('x',24*1024*1024+1),20,$root));
    $reject(static fn()=>handbook_ai_prepare_article_html(str_repeat('<br>',4001),20,$root));
    $padded=$png.str_repeat('x',1024*1024);
    $paddedResult=handbook_ai_prepare_article_html('<p>Large valid BASE64</p><img src="data:image/png;base64,'.base64_encode($padded).'">',23,$root);
    $assert($paddedResult['images'][0]['size']<102400,'Large canonical BASE64 validates and trailing original data is discarded by re-encode');
    handbook_ai_cleanup_created_files($paddedResult['created_files'],$root);
    $testDom=new DOMDocument();$testImg=$testDom->createElement('img');$testImg->setAttribute('src','data:image/png;base64,'.base64_encode($padded));
    $out=new DOMDocument();$parent=$out->createElement('div');$out->appendChild($parent);$images=[];$nodes=[];$total=12*1024*1024;
    $reject(static function()use($testImg,$parent,$out,&$images,&$nodes,&$total):void{handbook_ai_media_copy_node($testImg,$parent,$out,$images,$nodes,$total);});
    $assert($images===[],'Aggregate decoded image budget enforced before compression or writes');
    $sentinel=$root.'/uploads/handbook/17/ai-'.str_repeat('a',32).'.jpg';file_put_contents($sentinel,'preexisting');
    handbook_ai_cleanup_created_files([$sentinel,$root.'/../outside.jpg',$root],$root);
    $assert(is_file($sentinel),'Rollback refuses unregistered existing or broad paths');
    handbook_ai_cleanup_created_files($result['created_files'],$root);
    $assert(!is_file($file)&&is_file($second['created_files'][0])&&is_file($sentinel),'Rollback removes only exact files created by current operation');
    handbook_ai_cleanup_created_files($result['created_files'],$root);
    $assert(is_file($sentinel),'Repeated cleanup is harmless');
    mkdir($root.'/external',0700);symlink($root.'/external',$root.'/uploads/handbook/22');
    try{handbook_ai_prepare_article_html('<p>Symlink not allowed</p><img src="'.$uri.'">',22,$root);$assert(false,'Symlink storage accepted');}
    catch(RuntimeException){$assert(true,'Symlink upload tree refused');}
    $assert(count(scandir($root.'/external'))===2,'No file written outside upload tree');
    handbook_ai_cleanup_created_files(array_merge($second['created_files'],$duplicate['created_files'],$multiple['created_files']),$root);
    echo "Handbook media: {$checks} checks passed; generated fixtures in isolated temporary directory only.\n";
}finally{$remove($root);}
