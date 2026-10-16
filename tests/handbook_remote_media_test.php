<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_handbook_remote_media.php';
if (!extension_loaded('gd')) throw new RuntimeException('Remote media tests require GD.');
$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void { $checks++; if (!$ok) throw new RuntimeException($label); };
$reject = static function (callable $fn, string $label) use ($assert): void {
    try { $fn(); $assert(false, 'Accepted invalid input: ' . $label); }
    catch (InvalidArgumentException) { $assert(true, $label); }
};
$image = imagecreatetruecolor(48,32);
imagefilledrectangle($image,0,0,48,32,0x48a0cc);
$fixtures = [];
foreach (['png','jpeg','webp'] as $format) {
    ob_start(); ('image' . $format)($image); $fixtures['image/' . $format] = (string) ob_get_clean();
}
imagedestroy($image);
$public = '93.184.216.34';
$resolver = static fn(string $host): array => [$public];
$png = $fixtures['image/png'];
$response = static fn(string $body, string $mime = 'image/png', int $status = 200, array $extra = []): array =>
    ['status'=>$status,'headers'=>array_replace(['content-type'=>[$mime]], $extra),'body'=>$body];
$download = static fn(string $url, array $reply, ?callable $dns = null, ?float $deadline = null): array =>
    handbook_ai_remote_image_download($url,$dns ?? $resolver,static fn(array $target,float $end):array=>$reply,$deadline);

foreach ($fixtures as $mime => $bytes) {
    $result = $download('https://images.example.org/photo', $response($bytes,$mime));
    $assert($result === ['bytes'=>$bytes,'mime'=>$mime], 'Real ' . $mime . ' bytes accepted without filename suffix');
    $assert($download('http://images.example.org/photo?w=1280&format=auto',$response($bytes,'application/octet-stream'))['mime'] === $mime,'Octet stream detected from actual bytes');
}
$assert($download('https://images.example.org/x',$response($png,''))['bytes'] === $png,'Missing content-type still requires genuine raster');
foreach (['http://127.0.0.1/x','https://10.0.0.1/x','http://169.254.169.254/latest/meta-data/','http://100.100.100.200/x',
    'http://172.16.0.1/x','http://192.168.1.1/x','http://0.0.0.0/x','http://192.0.2.2/x','http://198.18.0.1/x','http://203.0.113.3/x',
    'http://224.0.0.1/x','http://240.0.0.1/x','http://localhost/x','http://metadata.google.internal/x','http://server.local/x',
    'http://instance-data/x','http://a.home/x','http://a.lan/x','http://a.test/x','http://a.invalid/x','http://images.example.org./x',
    'http://2130706433/x','http://0177.0.0.1/x','http://0x7f000001/x','http://127.1/x','http://0x08.0x08.0x08.0x08/x',
    'http://0x7f.0x0.0x0.0x1/x','http://127.0.0.0x1/x','http://0177.0.0.0x1/x',
    'http://[::1]/x','http://[::ffff:127.0.0.1]/x','http://[::ffff:8.8.8.8]/x','http://[64:ff9b::808:808]/x','http://[fc00::1]/x',
    'http://[fe80::1%25en0]/x','http://[2001:db8::1]/x','http://[2002:0808:0808::1]/x','http://[3fff::1]/x',
    'https://user:password@images.example.org/x','ftp://images.example.org/x','file:///etc/passwd','//images.example.org/x',
    'data:image/png;base64,aaa','blob:https://images.example.org/x','https://images.example.org:444/x','http://images.example.org:443/x',
    'https://images.example.org/x#fragment','https://images.example.org/x?token=secret','https://images.example.org/x?api_key=secret',
    'https://images.example.org/x?%61uth=secret','https://images.example.org/x?a=1;access-token=secret','https://images.example.org/x?signature=secret',
    "https://images.example.org/x\n",'https://images.example.org\\@127.0.0.1/x',"https://images.example.org/x\u{00a0}",
    'https://images.example.org/' . str_repeat('x',8000)] as $bad) {
    $reject(static fn()=>$download($bad,$response($png)),'Unsafe URL rejected: '.$bad);
}
$numericResolvers=0;$numericTransfers=0;
foreach(['http://0x7f.0x0.0x0.0x1/x','http://127.0.0.0x1/x']as$numericUrl){
    $reject(static function()use($numericUrl,&$numericResolvers,&$numericTransfers,$public,$response,$png):void{
        handbook_ai_remote_image_download($numericUrl,
            static function()use(&$numericResolvers,$public):array{$numericResolvers++;return[$public];},
            static function()use(&$numericTransfers,$response,$png):array{$numericTransfers++;return$response($png);});
    },
        'Hex numeric targets rejected before any resolver or transport');
}
$assert($numericResolvers===0&&$numericTransfers===0,'Numeric spelling cannot bypass pinning through cURL IP interpretation');
foreach (['127.0.0.1','10.0.0.1','169.254.169.254','100.64.1.1','192.0.0.8','198.51.100.1','::1','::ffff:8.8.8.8','fd00::1','2001:db8::2','not-an-ip'] as $badIp) {
    $reject(static fn()=>$download('https://images.example.org/x',$response($png),static fn()=>[$public,$badIp]),'Mixed DNS answers rejected before connection');
}
$reject(static fn()=>$download('https://images.example.org/x',$response($png),static fn()=>[]),'Empty DNS rejected');
$reject(static fn()=>$download('https://images.example.org/x',$response($png),static fn()=>array_fill(0,65,$public)),'Excessive DNS answers rejected');
$targets = []; $dnsCalls = 0;
$result = handbook_ai_remote_image_download('https://Images.Example.org:443/photo?w=1280',
    static function(string $host)use(&$dnsCalls,$public):array{$dnsCalls++;return $dnsCalls===1?[$public,'2606:4700:4700::1111']:['127.0.0.1'];},
    static function(array $target,float $deadline)use(&$targets,$response,$png,$public):array{$targets[]=$target;return $response($png)+['primary_ip'=>$public];});
$assert($dnsCalls===1&&count($targets)===1,'One DNS resolution per actual transfer, never re-resolve inside transport');
$assert($targets[0]['ip']===$public&&$targets[0]['resolve']===['images.example.org:443:'.$public],'Validated numeric address pinned for exact hostname/port');
$assert($targets[0]['url']==='https://images.example.org/photo?w=1280','Normalized host/default port preserves image query');
$ipv6Target = handbook_ai_remote_target('https://images.example.org/x',static fn()=>['2606:4700:4700::1111'],microtime(true)+2);
$assert($ipv6Target['resolve']===['images.example.org:443:[2606:4700:4700::1111]'],'IPv6 pin uses required cURL bracket syntax');
$literal = handbook_ai_remote_target('https://[2606:4700:4700::1111]/x',static fn()=>['127.0.0.1'],microtime(true)+2);
$assert($literal['literal']&&$literal['resolve']===[]&&$literal['ip']==='2606:4700:4700::1111','Literal globally routed IPv6 cannot be re-resolved');
$assert($download('https://8.8.8.8/x',$response($png))['bytes']===$png,'Canonical globally public literal IPv4 supported');
$reject(static fn()=>$download('https://images.example.org/x',$response($png)+['primary_ip'=>'127.0.0.1']),'Unexpected connection IP rejected');
$reject(static fn()=>$download('https://images.example.org/x',$response($png)+['primary_ip'=>'8.8.8.8']),'Even another public unpinned connection IP rejected');

$hops = [];
$result = handbook_ai_remote_image_download('https://images.example.org/old/a', $resolver,
    static function(array $target,float $deadline)use(&$hops,$response,$png):array{
        $hops[]=$target;
        return count($hops)===1?$response('','text/html',302,['location'=>['../photo?width=640']]):$response($png);
    });
$assert($result['bytes']===$png&&count($hops)===2&&$hops[1]['url']==='https://images.example.org/photo?width=640','Relative redirect manually resolved and repinned');
foreach (['http://127.0.0.1/a','//10.0.0.1/a','http://metadata.google.internal/a','file:///etc/passwd','https://user@images.example.org/a',
    '/x#bad',"/x\n",'/x?token=secret'] as $location) {
    $connections = 0;
    $reject(static function()use($location,$resolver,$response,&$connections):void{
        handbook_ai_remote_image_download('https://images.example.org/old',$resolver,
            static function(array $target,float $end)use($location,$response,&$connections):array{$connections++;return $response('','text/html',302,['location'=>[$location]]);});
    },'Redirect target validated before subsequent connection');
    $assert($connections===1,'Unsafe redirect never connected');
}
$dnsCalls=0;$connections=0;
$reject(static function()use(&$dnsCalls,&$connections,$public,$response):void{
    handbook_ai_remote_image_download('https://images.example.org/a',static function()use(&$dnsCalls,$public):array{return ++$dnsCalls===1?[$public]:['127.0.0.1'];},
        static function()use(&$connections,$response):array{$connections++;return $response('','text/html',302,['location'=>['/b']]);});
},'DNS rebinding at next redirect rejected');
$assert($connections===1,'Redirect rebinding rejected before second transfer');
$reject(static fn()=>handbook_ai_remote_image_download('https://images.example.org/x',$resolver,
    static fn()=>$response('','text/html',302,['location'=>['/x']])),'Redirect loop rejected');
$count=0;
$reject(static function()use($resolver,&$count,$response):void{
    handbook_ai_remote_image_download('https://images.example.org/x',$resolver,
        static function()use(&$count,$response):array{return $response('','text/html',302,['location'=>['/hop-'.++$count]]);});
},'More than three redirects rejected');
$assert($count===4,'At most initial request plus three redirect transfers');

foreach ([$response('<html>not an image</html>','image/png'),$response($png,'text/html'),$response('<svg/>','image/svg+xml'),
    $response(base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=='),'image/gif'),
    $response(substr($png,0,-12),'image/png'),$response(substr($fixtures['image/jpeg'],0,-2),'image/jpeg'),
    $response(substr($fixtures['image/webp'],0,-1),'image/webp'),$response('','image/png'),$response($png,'image/jpeg'),
    $response($png,'image/png',404),$response($png,'image/png',206),$response($png,'image/png',500),
    $response(str_repeat('x',5*1024*1024+1)),$response($png,'image/png',200,['content-length'=>['999']]),
    $response($png,'image/png',200,['content-length'=>['6000000']]),$response($png,'image/png',200,['content-encoding'=>['gzip']]),
    $response($png,'image/png',200,['content-type'=>['image/png','text/html']]),
    ['status'=>200,'headers'=>['content-type'=>['image/png'],'X-Test'=>[str_repeat('x',33000)]],'body'=>$png],
    ['status'=>200,'headers'=>['X-Test'=>["evil\r\nLocation: http://127.0.0.1/"]],'body'=>$png]] as $invalid) {
    $reject(static fn()=>$download('https://images.example.org/x',$invalid),'Invalid response/image rejected');
}
$reject(static fn()=>$download('https://images.example.org/x',$response($png),null,microtime(true)-1),'Expired shared deadline rejected before DNS');
$reject(static fn()=>$download('https://images.example.org/x',$response($png),static function()use($public):array{usleep(15000);return[$public];},microtime(true)+0.005),'DNS consuming shared deadline prevents request');
$reject(static fn()=>handbook_ai_remote_image_download('https://images.example.org/x',$resolver,
    static function()use($response,$png):array{usleep(15000);return$response($png);},microtime(true)+0.005),'Late transfer cannot return accepted bytes');

handbook_ai_remote_decode_budget(1280,720,'128M',4*1024*1024);
$assert(true,'Normal image fits explicit decoding budget');
handbook_ai_remote_decode_budget(4000,3000,'-1',0);
$assert(true,'Unlimited memory remains subject to separate dimensions gate');
foreach([['128M',110*1024*1024],['16M',4*1024*1024],['invalid',0]]as[$memoryLimit,$usage]){
    $refused=false;
    try{handbook_ai_remote_decode_budget(4000,3000,$memoryLimit,$usage);}
    catch(RuntimeException){$refused=true;}
    $assert($refused,'Insufficient/unknown memory rejects before full pixel allocation');
}

$sink=handbook_ai_remote_http_sink(microtime(true)+2);
$assert(($sink['header'])(null,"HTTP/1.1 200 OK\r\n")>0&&($sink['header'])(null,"Content-Type: image/png\r\n")>0,'Streaming header collector accepts final headers');
$assert(($sink['write'])(null,$png)===strlen($png)&&($sink['response'])(200,$public)['body']===$png,'Streaming bytes preserved');
$sink=handbook_ai_remote_http_sink(microtime(true)+2);
$assert(($sink['header'])(null,"Content-Length: 6000000\r\n")===0,'Oversize Content-Length aborts before body');
$reject(static fn()=>($sink['response'])(200,$public),'Streaming abort surfaced as validation error');
$sink=handbook_ai_remote_http_sink(microtime(true)+2);
$assert(($sink['write'])(null,str_repeat('x',5*1024*1024))===5*1024*1024&&($sink['write'])(null,'x')===0,'Chunked body bounded while streaming, not only after download');
$reject(static fn()=>($sink['response'])(200,$public),'Chunked oversize cannot return partial data');
$sink=handbook_ai_remote_http_sink(microtime(true)+2);
$assert(($sink['header'])(null,'X-Test: '.str_repeat('x',8192)."\r\n")===0,'Single huge header aborts');
$reject(static fn()=>($sink['response'])(200,$public),'Huge header surfaced');
$sink=handbook_ai_remote_http_sink(microtime(true)+2);
for($i=0;$i<6;$i++)$last=($sink['header'])(null,'X-Test: '.str_repeat('x',6000)."\r\n");
$assert($last===0,'Aggregate header bytes bounded');
$sink=handbook_ai_remote_http_sink(microtime(true)-1);
$assert(($sink['write'])(null,'a')===0,'Streaming callback enforces shared deadline');

if(extension_loaded('curl')) {
    $options=handbook_ai_remote_curl_options($targets[0],microtime(true)+2,handbook_ai_remote_http_sink(microtime(true)+2));
    $assert($options[CURLOPT_RESOLVE]===['images.example.org:443:'.$public]&&$options[CURLOPT_IPRESOLVE]===CURL_IPRESOLVE_V4,'Production transport uses only pinned numeric family/address');
    $assert($options[CURLOPT_FOLLOWLOCATION]===false&&$options[CURLOPT_MAXREDIRS]===0,'cURL never follows unvalidated redirect automatically');
    $assert($options[CURLOPT_PROXY]===''&&$options[CURLOPT_NOPROXY]==='*','Environment proxies disabled');
    $assert($options[CURLOPT_SSL_VERIFYPEER]===true&&$options[CURLOPT_SSL_VERIFYHOST]===2,'TLS verifies certificate and original hostname');
    $assert($options[CURLOPT_NETRC]===CURL_NETRC_IGNORED&&$options[CURLOPT_HTTPAUTH]===CURLAUTH_NONE&&$options[CURLOPT_AUTOREFERER]===false,'No ambient auth/netrc/referrer');
    $assert(count($options[CURLOPT_HTTPHEADER])===2&&!str_contains(strtolower(implode('\n',$options[CURLOPT_HTTPHEADER])),'key'),'No API credentials/cookies passed to remote host');
    $assert($options[CURLOPT_TIMEOUT_MS]<=2000&&$options[CURLOPT_CONNECTTIMEOUT_MS]<=2000,'Remaining shared deadline passed to cURL');
    $assert($options[CURLOPT_PROTOCOLS]===(CURLPROTO_HTTP|CURLPROTO_HTTPS),'cURL protocol whitelist HTTP/HTTPS only');
}
echo "Handbook remote media: {$checks} checks passed; DNS/HTTP mocked, no external requests or DB writes.\n";
