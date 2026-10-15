<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/admin/_cam_nang_import.php';
$bootstrap=file_get_contents(dirname(__DIR__).'/admin/_bootstrap.php');
$start=strpos($bootstrap,'function slugify(');$end=strpos($bootstrap,'function flash_toast_set(');
eval(substr($bootstrap,$start,$end-$start));
$checks=0;
$assert=static function(bool $ok,string $label)use(&$checks):void{++$checks;if(!$ok)throw new RuntimeException($label);};
$reject=static function(callable $fn,string $label)use($assert):void{try{$fn();}catch(InvalidArgumentException $e){$assert(true,$label);return;}throw new RuntimeException($label);};
$fixture=static function(bool $template=true):PDO{
    $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $pdo->exec('CREATE TABLE categories(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT,slug TEXT UNIQUE,language TEXT)');
    $pdo->exec('CREATE TABLE posts(id INTEGER PRIMARY KEY AUTOINCREMENT,category_id INTEGER,title TEXT,slug TEXT UNIQUE,language TEXT,status TEXT,content TEXT,excerpt TEXT'.($template?',template INTEGER DEFAULT 1':'').')');
    return$pdo;
};
$token=str_repeat('x',64);
$post=static fn($items,$language='vi')=>['action'=>'import_titles','csrf_token'=>$token,'default_language'=>$language,'titles_json'=>json_encode($items,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)];
$parsed=admin_handbook_import_titles('["  Chăm sóc   răng  ",{"title":"Dental care","language":"en"}]','vi');
$assert($parsed[0]['title']==='Chăm sóc răng'&&$parsed[1]['language']==='en','Normalizes titles and supports explicit per-item language');
$assert(admin_handbook_import_titles('["Dental care"]','en')[0]['language']==='en','String titles inherit selected language');
foreach(['','{}','[]','null','[42]','[["nested"]]','[{"title":true}]','[{"title":"x","language":"fr"}]','[{"title":"x","status":"published"}]','["<b>Title</b>"]',json_encode([str_repeat('a',161)]),json_encode(array_fill(0,101,'Title')),str_repeat(' ',131073)]as$json){
    $reject(fn()=>admin_handbook_import_titles($json,'vi'),'Rejects malformed, oversized or unsupported payload');
}
$reject(fn()=>admin_handbook_import_titles('["Title"]','fr'),'Rejects default language outside VI/EN');
$pdo=$fixture();
$bad=$post(['First title']);$bad['csrf_token']='invalid';
$reject(fn()=>admin_handbook_import_request($pdo,$bad,$token),'CSRF checked before writing');
$assert((int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn()===0,'Invalid CSRF does not seed categories');
$bad=$post(['Valid', ['title'=>'']]);
$reject(fn()=>admin_handbook_import_request($pdo,$bad,$token),'Whole batch validates before transaction');
$assert((int)$pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn()===0,'Invalid batch never partially imports');
$batch=$post(['Chăm sóc răng', ['title'=>'Dental care','language'=>'en'], '  CHĂM SÓC   RĂNG ']);
$result=admin_handbook_import_request($pdo,$batch,$token);
$assert(count($result['created'])===2&&count($result['skipped'])===1,'Mixed-language batch creates drafts and skips normalized duplicate');
$assert($result['skipped'][0]['id']===$result['created'][0]['id'],'Skipped row links to its existing article');
$rows=$pdo->query('SELECT p.*,c.slug AS category_slug FROM posts p JOIN categories c ON c.id=p.category_id ORDER BY p.id')->fetchAll();
$assert($rows[0]['category_slug']==='blog'&&$rows[1]['category_slug']==='blog-en','Each article uses its correct handbook category');
foreach($rows as$row)$assert($row['status']==='draft'&&$row['content']===null&&(int)$row['template']===0,'Import is empty draft only, ready for AI');
$again=admin_handbook_import_request($pdo,$batch,$token);
$assert($again['created']===[]&&count($again['skipped'])===3,'Resubmitting identical batch does not create duplicate articles');
$pdo->exec("UPDATE posts SET content='<p>Existing content</p>',status='published' WHERE id=1");
admin_handbook_import_request($pdo,$batch,$token);
$existing=$pdo->query('SELECT * FROM posts WHERE id=1')->fetch();
$assert($existing['status']==='published'&&$existing['content']==='<p>Existing content</p>','Import never overwrites existing content/status');
$same=admin_handbook_import_request($pdo,$post([['title'=>'Chăm sóc răng','language'=>'en']]),$token);
$assert(count($same['created'])===1,'Identical title in different language remains eligible');
$assert($pdo->query('SELECT slug FROM posts WHERE id=3')->fetchColumn()==='cham-soc-rang-2','Slug collisions receive a unique suffix');
$pdo->exec("INSERT INTO categories(id,name,slug,language) VALUES(3,'Other','services','vi')");
$pdo->exec("INSERT INTO posts(category_id,title,slug,language,status) VALUES(3,'Other topic','other-topic','vi','published')");
$other=admin_handbook_import_request($pdo,$post(['Other topic']),$token);
$assert(count($other['created'])===1,'An unrelated CMS page is not treated as an existing handbook article');
$minimal=$fixture(false);
$assert(count(admin_handbook_import_request($minimal,$post(['Topic']),$token,['has_template'=>false])['created'])===1,'Supports CMS without optional template column');
$rollback=$fixture();
$rollback->exec("CREATE TRIGGER fail_import BEFORE INSERT ON posts WHEN NEW.title='Fail title' BEGIN SELECT RAISE(ABORT,'Fixture failure'); END");
try{admin_handbook_import_request($rollback,$post(['First title','Fail title']),$token);throw new RuntimeException('Expected DB failure');}catch(PDOException $error){}
$assert((int)$rollback->query('SELECT COUNT(*) FROM posts')->fetchColumn()===0&&(int)$rollback->query('SELECT COUNT(*) FROM categories')->fetchColumn()===0,'Database failure rolls back all articles and category creation');
$assert(!$rollback->inTransaction(),'Failed import releases transaction');
echo "Handbook import: {$checks} checks passed; isolated SQLite only, no real database writes.\n";
