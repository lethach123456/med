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
    foreach(['http://127.0.0.1/image.png','//example.org/image.png','file:///etc/passwd','blob:https://example.org/a',
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

    // Public image transport is injected: these tests never perform DNS or network I/O.
    $remotePng=$make(720,540,0xe8a323);$requests=[];
    $download=static function(string $url,float $deadline)use(&$requests,$remotePng):array{
        $requests[]=['url'=>$url,'deadline'=>$deadline];
        return ['bytes'=>$remotePng,'mime'=>'image/png'];
    };
    $mixedHtml='<h2>Hai nguồn ảnh</h2><p>Nội dung Cẩm nang thử nghiệm.</p>'
        .'<figure><img src="'.$uri.'" alt="Ảnh BASE64"><figcaption>Minh hoạ AI bằng BASE64.</figcaption></figure>'
        .'<figure><img src="https://images.example.org/illustration?id=2&amp;width=720" alt="Ảnh từ link"><figcaption>Minh hoạ AI từ file công khai.</figcaption></figure>';
    $mixed=handbook_ai_prepare_article_html($mixedHtml,24,$root,$download);
    $assert(count($mixed['images'])===2&&count($mixed['created_files'])===2,'BASE64 and a public URL can be imported in one article');
    $assert(count($requests)===1&&$requests[0]['url']==='https://images.example.org/illustration?id=2&width=720','Remote URL is HTML-decoded exactly once before transport');
    $assert($requests[0]['deadline']>microtime(true)&&$requests[0]['deadline']<=microtime(true)+30,'Remote transfer receives the shared bounded article deadline');
    $assert(!str_contains($mixed['content'],'https://images.example.org')&&!str_contains($mixed['content'],'data:image'),'Every source becomes a local hosting URL');
    $assert($mixed['images'][1]['alt']==='Ảnh từ link'&&$mixed['images'][1]['caption']==='Minh hoạ AI từ file công khai.','Remote image alt and caption preserved');
    foreach($mixed['created_files']as$stored)$assert(is_file($stored)&&filesize($stored)<102400&&getimagesize($stored)[2]===IMAGETYPE_JPEG,'Mixed images actually stored as compressed JPEG files');
    $assert(!str_contains(json_encode($mixed['images']),'images.example.org')&&!str_contains(json_encode($mixed['images']),'base64,'),'Metadata contains only local image references, not remote URL query data');

    $requests=[];
    $remoteDuplicate=handbook_ai_prepare_article_html('<p>One public file used twice.</p><img src="https://images.example.org/a"><img src="https://images.example.org/a">',25,$root,$download);
    $assert(count($requests)===1&&count($remoteDuplicate['images'])===1&&count($remoteDuplicate['created_files'])===1,'Repeated remote source fetched once and stored once');
    $sameBytes=static fn(string $url,float $deadline):array=>['bytes'=>$png,'mime'=>'image/png'];
    $crossDuplicate=handbook_ai_prepare_article_html('<p>Same file in both formats.</p><img src="'.$uri.'"><img src="https://images.example.org/same">',26,$root,$sameBytes);
    $assert(count($crossDuplicate['images'])===1&&substr_count($crossDuplicate['content'],$crossDuplicate['images'][0]['url'])===2,'Bytes shared across BASE64 and URL are deduplicated');
    $badDownload=static fn(string $url,float $deadline):array=>['bytes'=>'<html>Not an image</html>','mime'=>'image/png'];
    $reject(static fn()=>handbook_ai_prepare_article_html('<p>Failure after valid BASE64.</p><img src="'.$uri.'"><img src="https://images.example.org/not-image">',27,$root,$badDownload));
    $assert(!is_dir($root.'/uploads/handbook/27'),'Invalid downloaded file causes no partial image write');
    $unavailable=static function(string $url,float $deadline):array{throw new InvalidArgumentException('HTTP 404');};
    $reject(static fn()=>handbook_ai_prepare_article_html('<p>Image unavailable.</p><img src="https://images.example.org/404">',28,$root,$unavailable));
    $assert(!is_dir($root.'/uploads/handbook/28'),'Unreachable remote image does not leave a partial article folder');
    $mimeMismatch=static fn(string $url,float $deadline):array=>['bytes'=>$png,'mime'=>'image/jpeg'];
    $reject(static fn()=>handbook_ai_prepare_article_html('<p>Mismatch</p><img src="https://images.example.org/wrong-mime">',29,$root,$mimeMismatch));
    $tooLargeRemote=static fn(string $url,float $deadline):array=>['bytes'=>str_repeat('a',5*1024*1024+1),'mime'=>'image/png'];
    $reject(static fn()=>handbook_ai_prepare_article_html('<p>Too large</p><img src="https://images.example.org/large">',30,$root,$tooLargeRemote));
    $remoteBudgetCalls=[];
    $sameDeadline=static function(string $url,float $deadline)use(&$remoteBudgetCalls,$png,$remotePng):array{
        $remoteBudgetCalls[]=$deadline;
        return['bytes'=>str_ends_with($url,'first')?$png:$remotePng,'mime'=>'image/png'];
    };
    $remotePair=handbook_ai_prepare_article_html('<p>Two public files</p><img src="https://images.example.org/first"><img src="https://images.example.org/second">',31,$root,$sameDeadline);
    $assert(count($remoteBudgetCalls)===2&&$remoteBudgetCalls[0]===$remoteBudgetCalls[1],'Remote images share one deadline rather than multiplying timeout per image');
    $testDom=new DOMDocument();$testImg=$testDom->createElement('img');$testImg->setAttribute('src','https://images.example.org/budget');
    $out=new DOMDocument();$parent=$out->createElement('div');$out->appendChild($parent);$images=[];$nodes=[];$total=12*1024*1024;
    $remoteState=['download'=>$download,'deadline'=>microtime(true)+30,'cache'=>[]];
    $reject(static function()use($testImg,$parent,$out,&$images,&$nodes,&$total,&$remoteState):void{handbook_ai_media_copy_node($testImg,$parent,$out,$images,$nodes,$total,0,$remoteState);});
    $assert($images===[],'Downloaded bytes count toward the same aggregate image budget');
    handbook_ai_cleanup_created_files(array_merge($mixed['created_files'],$remoteDuplicate['created_files'],$crossDuplicate['created_files'],$remotePair['created_files']),$root);

    // Small WebP is stored byte-for-byte only after complete raster validation.
    $webpSource=imagecreatefromstring($png);ob_start();imagewebp($webpSource,null,80);$webp=(string)ob_get_clean();imagedestroy($webpSource);
    $assert(strlen($webp)<102400,'WebP fixture is strictly below the storage threshold');
    $webpUri='data:image/webp;base64,'.base64_encode($webp);
    $smallWebp=handbook_ai_prepare_article_html('<p>Preserve real WebP.</p><figure><img src="'.$webpUri.'" alt="WebP gốc"><figcaption>Minh hoạ AI.</figcaption></figure>',40,$root);
    $webpFile=$smallWebp['created_files'][0];$webpMeta=$smallWebp['images'][0];
    $assert(str_ends_with($webpFile,'.webp')&&str_ends_with($webpMeta['url'],'.webp')&&$webpMeta['mime']==='image/webp','Preserved file extension and MIME match real WebP');
    $assert(hash_file('sha256',$webpFile)===hash('sha256',$webp)&&filesize($webpFile)===strlen($webp),'Small WebP bytes and file size are preserved exactly');
    $assert($webpMeta['width']===1600&&$webpMeta['height']===900,'Small WebP retains original safe dimensions without resizing');
    $assert($webpMeta['alt']==='WebP gốc'&&$webpMeta['caption']==='Minh hoạ AI.','Preservation retains alt and caption');
    $downloadWebp=static fn(string $url,float $deadline):array=>['bytes'=>$webp,'mime'=>'image/webp'];
    $remoteWebp=handbook_ai_prepare_article_html('<p>Remote WebP.</p><img src="https://images.example.org/small.webp">',41,$root,$downloadWebp);
    $assert(hash_file('sha256',$remoteWebp['created_files'][0])===hash('sha256',$webp)&&$remoteWebp['images'][0]['mime']==='image/webp','Remote WebP uses the same unchanged storage policy');
    $combinedWebp=handbook_ai_prepare_article_html('<p>Same WebP from both sources.</p><img src="'.$webpUri.'"><img src="https://images.example.org/same.webp">',42,$root,$downloadWebp);
    $assert(count($combinedWebp['images'])===1&&count($combinedWebp['created_files'])===1,'Preserved WebP deduplicates across BASE64 and URL inputs');
    $assert(substr_count($combinedWebp['content'],$combinedWebp['images'][0]['url'])===2,'Repeated WebP occurrences share one local URL');
    $brokenWebp='data:image/webp;base64,'.base64_encode(substr($webp,0,-2));
    $reject(static fn()=>handbook_ai_prepare_article_html('<p>Truncated WebP.</p><img src="'.$brokenWebp.'">',43,$root));
    $assert(!is_dir($root.'/uploads/handbook/43'),'Truncated small WebP cannot bypass validation or create files');

    // A legal unknown RIFF chunk gives deterministic exact-size threshold fixtures.
    $padWebp=static function(string $bytes,int $size):string{
        $padding=$size-strlen($bytes)-8;
        if($padding<0||$padding%2)throw new RuntimeException('Invalid RIFF padding fixture');
        return substr_replace($bytes,pack('V',$size-8),4,4).'JUNK'.pack('V',$padding).str_repeat("\0",$padding);
    };
    $thresholdFiles=[];
    foreach([102398,102400,102402]as$offset=>$size){
        $sized=$padWebp($webp,$size);
        $assert(handbook_ai_remote_image_mime($sized)==='image/webp','Threshold fixture remains a complete decodable WebP');
        $threshold=handbook_ai_prepare_article_html('<p>WebP size boundary.</p><img src="data:image/webp;base64,'.base64_encode($sized).'">',44+$offset,$root);
        $thresholdFiles=array_merge($thresholdFiles,$threshold['created_files']);$stored=$threshold['created_files'][0];
        if($size<102400){
            $assert(str_ends_with($stored,'.webp')&&hash_file('sha256',$stored)===hash('sha256',$sized),'WebP just below100KiB is not re-encoded');
        }else{
            $assert(str_ends_with($stored,'.jpg')&&getimagesize($stored)[2]===IMAGETYPE_JPEG&&filesize($stored)<102400,'WebP at or above100KiB follows existing JPEG encoder');
            $assert($threshold['images'][0]['width']<=1280&&$threshold['images'][0]['mime']==='image/jpeg','Re-encoded WebP keeps the existing resized JPEG contract');
        }
    }
    $keepWebp=$root.'/uploads/handbook/40/ai-'.str_repeat('b',32).'.webp';file_put_contents($keepWebp,$webp);
    handbook_ai_cleanup_created_files([$keepWebp],$root);
    $assert(is_file($keepWebp),'Cleanup never deletes an unregistered preexisting WebP file');
    handbook_ai_cleanup_created_files($smallWebp['created_files'],$root);
    $assert(!is_file($webpFile)&&is_file($keepWebp)&&is_file($remoteWebp['created_files'][0]),'Rollback handles exact registered WebP files without touching other uploads');
    handbook_ai_cleanup_created_files(array_merge($remoteWebp['created_files'],$combinedWebp['created_files'],$thresholdFiles),$root);
    echo "Handbook media: {$checks} checks passed; generated fixtures in isolated temporary directory only.\n";
}finally{$remove($root);}
