<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' && !(PHP_SAPI === 'cli-server' && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true))) {
    http_response_code(404); exit;
}
require_once dirname(__DIR__) . '/db.php';

// Loopback-only fixture: this path never initializes an application database.
if (PHP_SAPI === 'cli-server') {
    $locale = site_normalize_locale(is_string($_GET['locale'] ?? null) ? $_GET['locale'] : 'vi');
    site_language_response_header($locale);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="'.$locale.'"><head>'.site_language_metadata_tags($locale).'</head><body>Fixture</body></html>';
    exit;
}

$checks=0;
$assert=static function(bool $ok,string $message)use(&$checks):void {
    $checks++; if(!$ok)throw new RuntimeException($message);
};
$assert(site_language_metadata_tags('vi') === '<meta property="og:locale" content="vi_VN">','Vietnamese OG locale');
$assert(site_language_metadata_tags('en') === '<meta property="og:locale" content="en_US">','English OG locale');
foreach(['vi-VN','VI_vn',' vi ']as$locale)$assert(str_contains(site_language_metadata_tags($locale),'content="vi_VN"'),'Vietnamese normalized '.$locale);
foreach(['en-US','EN_gb',' en ']as$locale)$assert(str_contains(site_language_metadata_tags($locale),'content="en_US"'),'English normalized '.$locale);
$tags=site_language_metadata_tags('vi',['vi','en','en-US','x-default','fr',null,[]]);
$assert(substr_count($tags,'property="og:locale"')===1 && substr_count($tags,'property="og:locale:alternate"')===1,'No duplicates/self/unsupported alternates');
$assert(str_contains($tags,'property="og:locale:alternate" content="en_US"'),'Only the real supplied counterpart is declared');
$assert(!str_contains(site_language_metadata_tags('en'),'alternate'),'No counterpart is fabricated for an unpaired article');
$assert(!str_contains(site_language_metadata_tags('vi', ['"><script>alert(1)</script>']),'script'),'Untrusted locale strings are never reflected');
$assert(!str_contains(strtolower($tags),'http-equiv'),'No obsolete content-language meta tag');

$_SERVER['REQUEST_URI']='/en/cam-nang/test';$_GET['lang']='vi';$GLOBALS['site_forced_locale']='vi';
$assert(site_language_metadata_tags('en')==='<meta property="og:locale" content="en_US">','Actual record language wins over route/query/global UI hint');
unset($GLOBALS['site_forced_locale']);
$postSource=(string)file_get_contents(dirname(__DIR__).'/post.php');
$start=strpos($postSource,'$postCategorySlug =');
$end=strpos($postSource,'if (!$post) {',$start);
$languageBlock=substr($postSource,$start,$end-$start);
$postLanguage=static function(array $post,string $path='',string $routeContext='',bool $isHandbookDetailRoute=false,string $handbookLocale='vi')use($languageBlock):string {
    $requestPath=$path;eval($languageBlock);return $htmlLang;
};
$assert($postLanguage(['language'=>'en','category_slug'=>'blog'],'/blog/article')==='en','English DB article cannot be mislabeled by Vietnamese category');
$assert($postLanguage(['language'=>'vi','category_slug'=>'blog-en'],'/news/article','news')==='vi','Vietnamese DB article cannot be mislabeled by English route/category');
$assert($postLanguage(['language'=>' EN ','category_slug'=>'blog'])==='en','Normalize authoritative stored language');
$assert($postLanguage(['language'=>null,'category_slug'=>'blog-en'])==='en','Legacy missing language retains the existing category fallback');
$assert($postLanguage(['language'=>'fr','category_slug'=>'blog'])==='vi','Unsupported legacy language uses the site fallback');
$assert($postLanguage(['language'=>'en','category_slug'=>'blog'],'','',true,'vi')==='vi','Handbook query-enforced locale remains authoritative');
unset($GLOBALS['site_forced_locale']);

$pages=[
    'index.php'=>'$locale','co-so-y-te.php'=>'$locale','bac-si.php'=>'$locale','toplist.php'=>'$locale',
    'cam-nang.php'=>'$locale','ve-chung-toi.php'=>'$locale','ho-so-da-xac-thuc.php'=>'$locale',
    'Tem/contact-medreview.php'=>'$contactLocale','co-so-y-te-chi-tiet.php'=>'$facilityLanguage',
    'bac-si-chi-tiet.php'=>'$doctorLanguage','toplist-chi-tiet-mau.php'=>'$toplistLanguage',
    'about-us.php'=>"'en'",'news.php'=>"'en'",'services-en.php'=>"'en'",'blog.php'=>"'vi'",
    'dich-vu.php'=>"'vi'",'danh-muc-y-te.php'=>"'vi'",'san-pham.php'=>"'vi'",'homepage-new.php'=>'$locale',
];
foreach($pages as$file=>$argument){
    $source=(string)file_get_contents(dirname(__DIR__).'/'.$file);
    $assert(substr_count($source,'site_language_metadata_tags(')===1,$file.' has exactly one metadata hook');
    $assert(str_contains($source,'site_language_response_header('.$argument.')'),$file.' header follows its actual language');
    $assert(strpos($source,'site_language_response_header(')<strrpos($source,'<!doctype html>'),$file.' sets HTTP header before output');
}
foreach(['Tem/handbook-article.php','Tem/toplist-doctor-detail.php','Tem/review-head.php']as$file){
    $source=(string)file_get_contents(dirname(__DIR__).'/'.$file);
    $assert(substr_count($source,'site_language_metadata_tags(')===1,$file.' partial has exactly one hook');
}
foreach(['review.php','review-chi-tiet.php']as$file){
    $source=(string)file_get_contents(dirname(__DIR__).'/'.$file);
    $assert(str_contains($source,'site_language_response_header($locale)')&&!str_contains($source,'site_language_metadata_tags('),$file.' shares one head hook and sets header before output');
}
$assert(str_contains((string)file_get_contents(dirname(__DIR__).'/Tem/footer.php'),"\$GLOBALS['site_forced_locale']"),'Footer honors record-owned locale like header');
$errorSource=(string)file_get_contents(dirname(__DIR__).'/Tem/public-404.php');
$assert(str_contains($errorSource,'site_language_response_header($notFoundLocale)')&&str_contains($errorSource,'site_language_metadata_tags($notFoundLocale)'), 'Error pages declare their localized language without invented translations');

if(in_array('--http',$argv,true)) {
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$message);
    $assert(is_resource($socket),'Reserve loopback fixture port');
    $address=stream_socket_get_name($socket,false);fclose($socket);
    $process=proc_open([PHP_BINARY,'-S',$address,'-t',__DIR__],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,__DIR__);
    $assert(is_resource($process),'Start isolated PHP HTTP fixture');
    try{
        $ready=false;
        for($i=0;$i<40;$i++){
            $probe=@stream_socket_client('tcp://'.$address,$errno,$message,0.1);
            if(is_resource($probe)){fclose($probe);$ready=true;break;}
            usleep(50000);
        }
        $assert($ready,'Loopback HTTP fixture ready');
        foreach(['vi'=>'vi_VN','en'=>'en_US']as$locale=>$og){
            $html=file_get_contents('http://'.$address.'/language_metadata_test.php?locale='.$locale);
            $headers=implode("\n",$http_response_header??[]);
            $assert((bool)preg_match('/^Content-Language: '.$locale.'\s*$/mi',$headers),'HTTP response sends Content-Language '.$locale);
            $assert(str_contains($html,'<html lang="'.$locale.'">')&&str_contains($html,'content="'.$og.'"'),'HTTP document/header/meta agree for '.$locale);
        }
    }finally{
        proc_terminate($process);foreach($pipes as$pipe)fclose($pipe);proc_close($process);
    }
}
echo "Language metadata: {$checks} checks passed; no DB connection or writes.\n";
