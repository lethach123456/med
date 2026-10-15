<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/db.php';
require_once dirname(__DIR__).'/medical_handbook_translations.php';
require_once dirname(__DIR__).'/admin/_cam_nang.php';
$checks=0;$assert=static function(bool$ok,string$label)use(&$checks):void{$checks++;if(!$ok)throw new RuntimeException($label);};
$reject=static function(callable$fn)use($assert):void{try{$fn();$assert(false,'Invalid pair accepted');}catch(InvalidArgumentException){$assert(true,'Invalid pair refused');}};
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec('PRAGMA foreign_keys=ON');
$pdo->exec('CREATE TABLE categories(id INTEGER PRIMARY KEY,name TEXT,slug TEXT UNIQUE,language TEXT)');
$pdo->exec("INSERT INTO categories VALUES(1,'Cẩm nang','blog','vi'),(2,'Guides','blog-en','en'),(3,'Services','services','vi')");
$pdo->exec('CREATE TABLE posts(id INTEGER PRIMARY KEY,category_id INTEGER,title TEXT,slug TEXT UNIQUE,language TEXT,status TEXT,content TEXT,updated_at TEXT,excerpt TEXT,seo_title TEXT,seo_description TEXT,seo_keywords TEXT,featured_image_url TEXT,template INTEGER)');
$insert=$pdo->prepare('INSERT INTO posts(id,category_id,title,slug,language,status,content,updated_at) VALUES(?,?,?,?,?,?,?,?)');
foreach([[1,1,'Việt A','viet-a','vi','published','<p>A</p>'],[2,2,'English A','english-a','en','published','<p>A EN</p>'],
    [3,1,'Việt B','viet-b','vi','published','<p>B</p>'],[4,2,'English B','english-b','en','draft','<p>Draft</p>'],
    [5,3,'Service EN','service-en','en','published','<p>Wrong category</p>'],[6,2,'French','french','fr','published','<p>Unsupported</p>'],
    [7,1,'Legacy VI','legacy-vi',null,'published','<p>Legacy</p>'],[8,1,'Legacy EN','legacy-en',' EN ','published','<p>EN legacy</p>'],
    [9,2,'English blank','blank-en','en','published','']]as$row)$insert->execute(array_merge($row,['2026-10-15 00:00:00']));
$assert(!handbook_translations_ready($pdo)&&handbook_translation_current($pdo,1)===null,'Missing schema read gracefully without creating table');
$assert(handbook_translation_candidates($pdo,'en')===[],'Candidates do not seed missing schema');
$reject(static fn()=>handbook_translation_set($pdo,1,2));
$pdo->exec('CREATE TABLE handbook_translations(vi_post_id INTEGER PRIMARY KEY REFERENCES posts(id) ON DELETE CASCADE,en_post_id INTEGER NOT NULL UNIQUE REFERENCES posts(id) ON DELETE CASCADE,updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$assert(handbook_translations_ready($pdo),'Explicit schema is ready');
handbook_translation_set($pdo,1,2);
$assert(handbook_translation_current($pdo,1)['id']===2&&handbook_translation_current($pdo,2)['id']===1,'Explicit pair resolves reciprocally');
$assert(handbook_translation_current($pdo,1,true)['slug']==='english-a','Published counterpart uses its actual translated slug');
$postSource=(string)file_get_contents(dirname(__DIR__).'/post.php');
$blockStart=strpos($postSource,"    require_once __DIR__ . '/medical_handbook_translations.php';");
$blockEnd=strpos($postSource,"\n}\n\$homePath =",$blockStart);
$switchBlock=substr($postSource,$blockStart,$blockEnd-$blockStart);
$switchBlock=str_replace('__DIR__',var_export(dirname(__DIR__),true),$switchBlock);
$runSwitch=static function(int$id,string$locale)use($pdo,$switchBlock):array{
    $post=handbook_translation_rows($pdo,[$id])[$id];$handbookLocale=$locale;
    eval($switchBlock);return['links'=>$GLOBALS['site_language_links'],'alternates'=>$handbookArticleAlternates];
};
$viSwitch=$runSwitch(1,'vi');$enSwitch=$runSwitch(2,'en');
$assert($viSwitch['links']['en']==='/en/cam-nang/english-a'&&$enSwitch['links']['vi']==='/cam-nang/viet-a','Actual reader language switches open reciprocal article slugs');
$assert($viSwitch['alternates']===['vi'=>'/cam-nang/viet-a','en'=>'/en/cam-nang/english-a','x-default'=>'/cam-nang/viet-a'],'Actual reader exposes matching reciprocal hreflang');
handbook_translation_set($pdo,3,4);
$assert(handbook_translation_current($pdo,3)['id']===4&&handbook_translation_current($pdo,3,true)===null,'Admin sees draft counterpart; public never links a draft');
$assert(handbook_translation_current($pdo,4,true)===null,'Draft source also prevents public alternates');
$draftSwitch=$runSwitch(3,'vi');
$assert($draftSwitch['links']['en']==='/en/cam-nang'&&$draftSwitch['alternates']===[],'Actual reader falls back to language index without leaking draft slug/hreflang');
$reject(static fn()=>handbook_translation_set($pdo,3,2));
$assert(handbook_translation_current($pdo,1)['id']===2&&handbook_translation_current($pdo,3)['id']===4,'Conflicting relink cannot steal another pair or discard current pair');
foreach([[1,1],[1,3],[1,5],[1,6],[1,999],[999,2]]as[$source,$target])$reject(static fn()=>handbook_translation_set($pdo,$source,$target));
handbook_translation_set($pdo,7,8);
$assert(handbook_translation_current($pdo,7,true)['language']==='en','Legacy authoritative languages normalized independently of categories');
handbook_translation_set($pdo,1,null);
$assert(handbook_translation_current($pdo,1)===null&&handbook_translation_current($pdo,2)===null,'Detach is reciprocal');
handbook_translation_set($pdo,1,9);
$assert(handbook_translation_current($pdo,1,true)===null,'Contentless published counterpart never gets public hreflang');
handbook_translation_set($pdo,1,2);
$assert(handbook_translation_current($pdo,9)===null,'Explicit source relink detaches only its previous counterpart');
$pdo->beginTransaction();handbook_translation_set($pdo,1,null);
$assert($pdo->inTransaction()&&handbook_translation_current($pdo,1)===null,'Pair helper participates without committing caller transaction');
$pdo->rollBack();$assert(handbook_translation_current($pdo,1)['id']===2,'Caller rollback restores pairing');
$candidates=handbook_translation_candidates($pdo,'en',3);$indexed=array_column($candidates,null,'id');
$assert((int)$indexed[4]['is_available']===1&&(int)$indexed[2]['is_available']===0,'Current counterpart selectable, paired targets unavailable');
$pdo->exec('DELETE FROM posts WHERE id=8');
$assert(handbook_translation_current($pdo,7)===null,'Deleting a translation cascades its pair only');
$values=['title'=>'New Việt','slug'=>'new-viet','language'=>'vi','excerpt'=>'','content'=>'','seo_title'=>'','seo_description'=>'','seo_keywords'=>'','status'=>'draft','featured_image_url'=>''];
$before=(int)$pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn();
$reject(static fn()=>admin_handbook_save($pdo,$values,0,['update_link'=>true,'linked_post_id'=>2]));
$assert((int)$pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn()===$before,'Invalid pairing rolls back newly created article');
$pdo->exec('DELETE FROM categories WHERE id=1'); // Fixture rows can be orphaned; scope validation must reject them.
$assert(handbook_translation_current($pdo,2)===null,'Orphan/unrelated article cannot remain a public counterpart');
$categoriesBefore=(int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
$reject(static fn()=>admin_handbook_save($pdo,array_replace($values,['slug'=>'category-rollback']),0,['update_link'=>true,'linked_post_id'=>2]));
$assert((int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn()===$categoriesBefore,'Failed pair validation also rolls back the missing category created by explicit save');
try{handbook_translations_migrate($pdo);$assert(false,'Runtime DDL accepted');}catch(LogicException){$assert(true,'Runtime cannot run translation DDL');}
$post=(string)file_get_contents(dirname(__DIR__).'/post.php');$template=(string)file_get_contents(dirname(__DIR__).'/Tem/handbook-article.php');
$assert(str_contains($post,'handbook_translation_current($pdo,(int)$post[\'id\'],true)')&&str_contains($post,'$handbookOtherPath ??'), 'Reader uses public-only explicit counterpart and fallback');
$assert(str_contains($template,'hreflang=')&&str_contains($template,'$handbookArticleAlternates ?? []'),'Only real published pairs produce article hreflang');
echo"Handbook translations: {$checks} checks passed; SQLite only, no real DB writes.\n";
