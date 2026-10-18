<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/api/medical/_handbook.php';

$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException($label);
};
$fails = static function (callable $callback, int $status, string $reason = '') use ($assert): void {
    try { $callback(); $assert(false, 'Expected failure: ' . $reason); }
    catch (HandbookApiException $e) { $assert($e->status === $status && ($reason === '' || $e->reason === $reason), 'Expected API status/reason: ' . $reason); }
};
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY,slug TEXT,name TEXT)');
$pdo->exec("INSERT INTO categories VALUES (1,'blog','Cẩm nang'),(2,'blog-en','Guides'),(3,'services','Services')");
$pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY,category_id INTEGER,title TEXT,slug TEXT,language TEXT,status TEXT,content TEXT,
    excerpt TEXT,seo_title TEXT,seo_description TEXT,seo_keywords TEXT,featured_image_url TEXT,template INTEGER DEFAULT 0,
    created_at TEXT,updated_at TEXT,ai_writer_claim_json TEXT,ai_content_json TEXT)');
$pdo->exec('CREATE TABLE settings (key TEXT PRIMARY KEY,value TEXT)');
$insert = $pdo->prepare('INSERT INTO posts(id,category_id,title,slug,language,status,content,excerpt,seo_title,seo_description,seo_keywords,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
foreach ([
    [1,1,'Chăm sóc răng khỏe mạnh','cham-soc-rang','vi','draft',''],
    [2,2,'Healthy eyes','healthy-eyes','en','draft',''],
    [3,1,'Published without content','published-empty','vi','published',''],
    [4,1,'Already written','already-written','vi','draft','<p>Existing article.</p>'],
    [5,3,'Service page','service','vi','draft',''],
    [6,1,'Unsupported','unsupported','fr','draft',''],
    [7,1,'','no-title','vi','draft',''],
    [8,1,'No slug','','vi','draft',''],
    [9,2,'Legacy VI','legacy-vi',null,'draft',''],
    [10,1,'Save fixture','save-fixture','vi','draft',''],
    [11,1,'Rollback fixture','rollback-fixture','vi','draft',''],
    [12,1,'Editor changes','editor-changes','vi','draft',''],
] as $row) $insert->execute(array_merge($row, ['','','','','2026-10-15 00:00:00','2026-10-15 00:00:00']));
handbook_ai_require_schema($pdo);
$now = 1800000000;
$initial = handbook_api_list($pdo, [], $now);
$assert($initial['total'] === 6 && count($initial['items']) === 6, 'Queue includes only empty draft handbook articles in supported languages.');
$assert(array_column($initial['items'], 'id') === [1,2,9,10,11,12], 'Queue order and eligibility are deterministic.');
$assert($initial['claim_required'] && $initial['save_status'] === 'published', 'Generated medical guides require a lease and publish after successful receipt.');
$assert(!array_key_exists('claim_token', $initial['items'][0]) && !array_key_exists('ai_writer_claim_json', $initial['items'][0]), 'The queue never exposes a private claim token.');
$assert(handbook_api_list($pdo, ['language' => 'en'], $now)['total'] === 1, 'English queue isolates the correct article.');
$assert(handbook_api_list($pdo, ['ids' => '2,2,9'], $now)['checked_ids'] === [2,9], 'ID filters deduplicate exact positive IDs.');
$assert(handbook_api_list($pdo, ['limit' => '2', 'page' => '100'], $now)['page'] === 3, 'Pagination clamps safely to the final page.');
foreach ([['limit' => '26'], ['language' => []], ['language' => 'fr'], ['page' => '1e2'], ['ids' => '1,0'], ['ids' => ['1']], ['ids' => '']] as $query) {
    $fails(static fn () => handbook_api_queue_filters($query), 422);
}
$fails(static fn () => handbook_api_positive_id(true), 422);
$fails(static fn () => handbook_api_text([], 'name', 30), 422);
$assert(handbook_api_parse_body('{"id":1}')===['id'=>1],'Strict JSON body parser accepts a single object');
foreach(['[]','null','false','42','{bad}',"{\"text\":\"\xff\"}"]as$invalidJson)$fails(static fn()=>handbook_api_parse_body($invalidJson),400);
$fails(static fn()=>handbook_api_parse_body('{"long":"123456"}',4),413);
$fails(static fn()=>handbook_api_parse_body('{"list":['.implode(',',array_fill(0,10002,'0')).']}'),400);

$claimBody = ['id' => 1, 'action' => 'claim', 'request_id' => 'job-1',
    'source_revision' => handbook_ai_revision(handbook_api_row($pdo, 1)),
    'client' => ['instance_id' => 'machine-A', 'worker_id' => 'chrome-tab-1', 'provider' => 'gemini', 'model' => 'research']];
$claimFor = static fn(int $id, string $request): array => array_merge($claimBody,
    ['id'=>$id,'request_id'=>$request,'source_revision'=>handbook_ai_revision(handbook_api_row($pdo,$id))]);
$beforeRevision = handbook_ai_revision(handbook_api_row($pdo, 1));
$claim = handbook_api_lease($pdo, $claimBody, $now);
$assert(strlen($claim['claim_token']) === 64 && $claim['expires_at'] === $now + 600, 'Claims use random tokens and a bounded ten-minute lease.');
$assert($claim['source_revision'] === $beforeRevision, 'Claim writes preserve the editorial revision.');
$retry = handbook_api_lease($pdo, $claimBody, $now + 1);
$assert($retry['claim_token'] === $claim['claim_token'], 'Lost claim response retry returns the same token.');
$other = $claimBody; $other['client']['instance_id'] = 'machine-B';
$fails(static fn () => handbook_api_lease($pdo, $other, $now + 2), 409, 'already_claimed');
$assert(!in_array(1, array_column(handbook_api_list($pdo, [], $now + 2)['items'], 'id'), true), 'Active claims disappear from the queue for other clients.');
$status = handbook_api_lease($pdo, ['id' => 1, 'action' => 'status'], $now + 2);
$assert($status['claimed'] && !isset($status['writer']['claim_token'], $status['writer']['request_id']), 'Status exposes only public owner metadata.');
$heartbeat = handbook_api_lease($pdo, ['id' => 1, 'action' => 'heartbeat', 'claim_token' => $claim['claim_token']], $now + 10);
$assert($heartbeat['expires_at'] === $now + 610 && $heartbeat['heartbeat_interval_seconds'] === 45, 'Heartbeats renew only a live owned lease.');
$fails(static fn () => handbook_api_lease($pdo, ['id' => 1, 'action' => 'heartbeat', 'claim_token' => str_repeat('a', 64)], $now + 11), 409, 'lease_lost');
$fails(static fn () => handbook_api_lease($pdo, ['id' => 1, 'action' => 'heartbeat', 'claim_token' => $claim['claim_token']], $now + 611), 409, 'lease_lost');
$assert(in_array(1, array_column(handbook_api_list($pdo, [], $now + 611)['items'], 'id'), true), 'An expired abandoned job can be acquired again.');
$newClaim = handbook_api_lease($pdo, $other, $now + 612);
$assert($newClaim['claim_token'] !== $claim['claim_token'], 'Reassignment after expiry creates a new token.');
$fails(static fn () => handbook_api_lease($pdo, ['id' => 1, 'action' => 'release', 'claim_token' => $claim['claim_token']], $now + 613), 409, 'lease_lost');
$released = handbook_api_lease($pdo, ['id' => 1, 'action' => 'release', 'claim_token' => $newClaim['claim_token']], $now + 613);
$assert($released['released'] && !$pdo->inTransaction(), 'Release is atomic and returns the article to the queue.');
foreach ([3,4,7,8] as $notEligible) $fails(static fn () => handbook_api_lease($pdo, array_merge($claimBody, ['id' => $notEligible]), $now), 409, 'not_eligible');
foreach ([5,6,9999] as $notFound) $fails(static fn () => handbook_api_lease($pdo, array_merge($claimBody, ['id' => $notFound]), $now), 404, 'not_found');
$assert(!$pdo->inTransaction(), 'Rejected claims always roll back.');
$staleClaim=$claimFor(12,'stale-job');$staleClaim['source_revision']=str_repeat('0',64);
$fails(static fn()=>handbook_api_lease($pdo,$staleClaim,$now),409,'source_revision_changed');
$assert(handbook_api_row($pdo,12)['ai_writer_claim_json']===null,'Stale queue prompt cannot acquire a new lease');

$mediaCalls = 0; $cleanupCalls = [];
$prepare = static function (string $html, int $id) use (&$mediaCalls): array {
    $mediaCalls++;
    return ['content' => '<h2>Thông tin hữu ích</h2><p>' . str_repeat('Nội dung đã kiểm tra. ', 30) . '</p><figure><img src="/uploads/handbook/' . $id . '/test.webp" alt="Minh họa"></figure>',
        'images' => [['url' => '/uploads/handbook/' . $id . '/test.webp', 'alt' => 'Minh họa', 'width' => 640, 'height' => 480]],
        'created_files' => ['/private/tmp/handbook-fixture-file.webp']];
};
$cleanup = static function (array $files) use (&$cleanupCalls): void { $cleanupCalls[] = $files; };
$payload = static function (int $id, array $lease, string $request): array {
    return ['id' => $id, 'title' => 'Chăm sóc sức khỏe đúng cách', 'language' => 'vi', 'excerpt' => 'Những điều cần biết trước khi chăm sóc sức khỏe.',
        'content_html' => '<h2>Thông tin hữu ích</h2><p>' . str_repeat('Tham khảo thông tin và hỏi bác sĩ khi cần. ', 40) . '</p><figure><img src="data:image/png;base64,fixture" alt="Minh họa"></figure>',
        'seo_title' => 'Chăm sóc sức khỏe đúng cách', 'seo_description' => 'Thông tin tham khảo giúp bạn chủ động chăm sóc sức khỏe.',
        'sources_json' => [['url' => 'https://www.who.int/health-topics', 'title' => 'World Health Organization']],
        'claim_token' => $lease['claim_token'], 'source_revision' => $lease['source_revision'], 'request_id' => $request];
};
$saveClaim = handbook_api_lease($pdo, $claimFor(10, 'job-save'), $now);
$body = $payload(10, $saveClaim, 'job-save');
$saved = handbook_api_save($pdo, $body, $prepare, $cleanup, $now + 1);
$savedRow = handbook_api_row($pdo, 10);
$assert($saved['ok'] && $saved['status'] === 'published' && !$saved['idempotent'] && $mediaCalls === 1, 'Save processes media once and publishes a medical article.');
$assert($savedRow['status'] === 'published', 'Successful API receipt makes the article public.');
$assert($savedRow['featured_image_url'] === '/uploads/handbook/10/test.webp' && $savedRow['ai_writer_claim_json'] === null, 'Local optimized image is featured and the lease is cleared.');
$assert(!str_contains($savedRow['content'], 'base64,') && !str_contains($savedRow['ai_content_json'], 'base64,'), 'Neither article HTML nor metadata stores a base64 image.');
$metadata = json_decode($savedRow['ai_content_json'], true);
$assert(!str_contains($savedRow['ai_content_json'], $saveClaim['claim_token']) && $metadata['claim_token_sha256'] === hash('sha256', $saveClaim['claim_token']), 'Idempotency retains only the token hash.');
$duplicate = handbook_api_save($pdo, $body, $prepare, $cleanup, $now + 1000);
$assert($duplicate['idempotent'] && $mediaCalls === 1, 'A lost success response can be retried after claim expiry without saving duplicate files.');
$changed = $body; $changed['content_html'] .= '<p>Changed.</p>';
$fails(static fn () => handbook_api_save($pdo, $changed, $prepare, $cleanup, $now + 2), 409, 'idempotency_conflict');
$differentJob = $body; $differentJob['request_id'] = 'job-new';
$fails(static fn () => handbook_api_save($pdo, $differentJob, $prepare, $cleanup, $now + 2), 409, 'not_eligible');
$wrongToken = $body; $wrongToken['claim_token'] = str_repeat('f', 64);
$fails(static fn () => handbook_api_save($pdo, $wrongToken, $prepare, $cleanup, $now + 2), 409, 'idempotency_conflict');
$assert($mediaCalls === 1 && $cleanupCalls === [], 'Rejected duplicates do not create or delete media.');

$editorClaim = handbook_api_lease($pdo, $claimFor(12, 'job-editor'), $now);
$editorBody = $payload(12, $editorClaim, 'job-editor');
$pdo->exec("UPDATE posts SET title='Topic revised by editor' WHERE id=12");
$fails(static fn()=>handbook_api_lease($pdo,['id'=>12,'action'=>'heartbeat','claim_token'=>$editorClaim['claim_token']],$now+1),409,'source_revision_changed');
$fails(static fn () => handbook_api_save($pdo, $editorBody, $prepare, $cleanup, $now + 1), 409, 'source_revision_changed');
$refreshedEditorBody=$editorBody;$refreshedEditorBody['source_revision']=handbook_ai_revision(handbook_api_row($pdo,12));
$fails(static fn()=>handbook_api_save($pdo,$refreshedEditorBody,$prepare,$cleanup,$now+1),409,'source_revision_changed');
$fails(static fn()=>handbook_api_lease($pdo,$claimFor(12,'job-editor'),$now+1),409,'source_revision_changed');
$assert($mediaCalls === 1, 'Stale editorial data is rejected before writing image files.');
$rollbackClaim = handbook_api_lease($pdo, $claimFor(11, 'job-rollback'), $now);
$rollbackBody = $payload(11, $rollbackClaim, 'job-rollback');
$pdo->exec("CREATE TRIGGER fail_handbook_save BEFORE UPDATE OF content ON posts WHEN NEW.id=11 BEGIN SELECT RAISE(FAIL,'fixture write failure'); END");
try { handbook_api_save($pdo, $rollbackBody, $prepare, $cleanup, $now + 1); $assert(false, 'DB write must fail.'); }
catch (PDOException $e) { $assert(true, 'Injected DB failure propagates.'); }
$rolledBack = handbook_api_row($pdo, 11);
$assert($rolledBack['content'] === '' && $rolledBack['ai_content_json'] === null && $rolledBack['ai_writer_claim_json'] !== null, 'A failed DB save leaves the draft and its live lease untouched.');
$assert($cleanupCalls === [['/private/tmp/handbook-fixture-file.webp']] && !$pdo->inTransaction(), 'A failed DB save removes only files created by that operation.');
$expiredBody = $rollbackBody;
$fails(static fn () => handbook_api_save($pdo, $expiredBody, $prepare, $cleanup, $now + 1000), 409, 'lease_lost');

// Integrate actual raster decoding/compression with an isolated SQLite transaction.
require_once dirname(__DIR__).'/medical_handbook_media.php';
$mediaRoot=sys_get_temp_dir().'/medreview-handbook-api-test-'.bin2hex(random_bytes(8));
mkdir($mediaRoot,0700);$mediaRoot=(string)realpath($mediaRoot);
$realPrepare=static fn(string $html,int $id):array=>handbook_ai_prepare_article_html($html,$id,$mediaRoot);
$realCleanup=static fn(array $files)=>handbook_ai_cleanup_created_files($files,$mediaRoot);
$image=imagecreatetruecolor(640,480);imagefill($image,0,0,0x3377dd);ob_start();imagepng($image);$png=(string)ob_get_clean();imagedestroy($image);
$realClaim=handbook_api_lease($pdo,$claimFor(9,'real-image-job'),$now);
$realBody=$payload(9,$realClaim,'real-image-job');
$realBody['content_html']=str_replace('base64,fixture','base64,'.base64_encode($png),$realBody['content_html']);
$createdReal=[];
$trackingPrepare=static function(string $html,int $id)use($realPrepare,&$createdReal):array{$result=$realPrepare($html,$id);$createdReal=array_merge($createdReal,$result['created_files']);return$result;};
try{
    $realSaved=handbook_api_save($pdo,$realBody,$trackingPrepare,$realCleanup,$now+1);
    $realRow=handbook_api_row($pdo,9);
    $assert($realSaved['images_count']===1&&is_file($createdReal[0]),'Actual received BASE64 becomes an on-disk raster file');
    $assert(filesize($createdReal[0])<102400&&getimagesize($createdReal[0])[2]===IMAGETYPE_JPEG,'Actual receipt stores compressed JPEG below 100KiB');
    $assert(str_contains($realRow['content'],$realSaved['images'][0]['url'])&&!str_contains($realRow['content'],'data:'),'Actual receipt replaces BASE64 with hosting URL');
    handbook_api_save($pdo,$realBody,$trackingPrepare,$realCleanup,$now+2);
    $assert(count($createdReal)===1,'Retry of real image payload creates no duplicate file');
    $failedReal=$rollbackBody;$failedReal['content_html']=$realBody['content_html'];
    try{handbook_api_save($pdo,$failedReal,$trackingPrepare,$realCleanup,$now+1);$assert(false,'Injected DB failure expected');}
    catch(PDOException){$assert(count($createdReal)===2&&!is_file($createdReal[1])&&is_file($createdReal[0]),'DB rollback removes only fresh compressed image, preserves previous success');}

    // Simulate DB COMMIT succeeding but its acknowledgement being lost.
    $ambiguous=new class('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]) extends PDO {
        public bool $loseCommitAcknowledgement=false;
        public function commit():bool{
            $ok=parent::commit();
            if($this->loseCommitAcknowledgement){$this->loseCommitAcknowledgement=false;throw new PDOException('Fixture: commit response lost');}
            return$ok;
        }
    };
    foreach(['categories','posts']as$table){
        $schema=$pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='".$table."'")->fetchColumn();$ambiguous->exec($schema);
    }
    foreach($pdo->query('SELECT id,slug,name FROM categories')->fetchAll()as$category)$ambiguous->prepare('INSERT INTO categories(id,slug,name) VALUES (?,?,?)')->execute(array_values($category));
    $fixture=handbook_api_row($pdo,12);unset($fixture['category_slug']);
    $fixture=array_replace($fixture,['id'=>20,'slug'=>'commit-uncertainty','content'=>'','ai_writer_claim_json'=>null,'ai_content_json'=>null]);
    $ambiguous->prepare('INSERT INTO posts('.implode(',',array_keys($fixture)).') VALUES('.implode(',',array_fill(0,count($fixture),'?')).')')->execute(array_values($fixture));
    $ambiguityClaimBody=array_merge($claimBody,['id'=>20,'request_id'=>'uncertain-commit','source_revision'=>handbook_ai_revision(handbook_api_row($ambiguous,20))]);
    $ambiguityLease=handbook_api_lease($ambiguous,$ambiguityClaimBody,$now);
    $ambiguityBody=$payload(20,$ambiguityLease,'uncertain-commit');$ambiguityBody['content_html']=$realBody['content_html'];
    $ambiguous->loseCommitAcknowledgement=true;
    try{handbook_api_save($ambiguous,$ambiguityBody,$trackingPrepare,$realCleanup,$now+1);$assert(false,'Lost COMMIT reply expected');}
    catch(PDOException){$assert(count($createdReal)===3&&is_file($createdReal[2])&&handbook_api_row($ambiguous,20)['content']!=='','Uncertain COMMIT never deletes an image referenced by a saved article');}
    $reconciled=handbook_api_save($ambiguous,$ambiguityBody,$trackingPrepare,$realCleanup,$now+2);
    $assert($reconciled['idempotent']&&count($createdReal)===3,'Retry reconciles a lost COMMIT acknowledgement without duplicate images');

    // Mixed input formats use the same authenticated lease, atomic DB save and real encoder.
    $fixture=handbook_api_row($pdo,1);unset($fixture['category_slug']);
    foreach([30,31,32,33,40,41]as$id){
        $testRow=array_replace($fixture,['id'=>$id,'slug'=>'mixed-image-'.$id,'content'=>'','ai_writer_claim_json'=>null,'ai_content_json'=>null]);
        $pdo->prepare('INSERT INTO posts('.implode(',',array_keys($testRow)).') VALUES('.implode(',',array_fill(0,count($testRow),'?')).')')->execute(array_values($testRow));
    }
    $remoteImage=imagecreatetruecolor(800,600);imagefill($remoteImage,0,0,0x22aa77);ob_start();imagepng($remoteImage);$remotePng=(string)ob_get_clean();imagedestroy($remoteImage);
    $downloadCalls=0;
    $download=static function(string $url,float $deadline)use(&$downloadCalls,$remotePng):array{
        $downloadCalls++;
        if(str_ends_with($url,'missing'))throw new InvalidArgumentException('Ảnh từ link trả HTTP 404.');
        return['bytes'=>$remotePng,'mime'=>'image/png'];
    };
    $mixedPrepare=static function(string $html,int $id)use($mediaRoot,$download,&$createdReal):array{
        $result=handbook_ai_prepare_article_html($html,$id,$mediaRoot,$download);
        $createdReal=array_merge($createdReal,$result['created_files']);return$result;
    };
    $mixedClaim=handbook_api_lease($pdo,$claimFor(30,'mixed-image-job'),$now);
    $mixedBody=$payload(30,$mixedClaim,'mixed-image-job');
    $mixedBody['content_html']=$realBody['content_html'].'<figure><img src="https://images.example.org/illustration" alt="File công khai"><figcaption>Ảnh minh hoạ AI.</figcaption></figure>';
    $beforeCount=count($createdReal);
    $mixedSaved=handbook_api_save($pdo,$mixedBody,$mixedPrepare,$realCleanup,$now+1);
    $mixedRow=handbook_api_row($pdo,30);
    $assert($mixedSaved['ok']&&$mixedSaved['images_count']===2&&count($createdReal)===$beforeCount+2,'Mixed BASE64 + URL payload commits two real compressed images');
    $assert($mixedRow['status']==='published'&&$mixedRow['ai_writer_claim_json']===null,'Mixed image receipt publishes and clears only the successful lease');
    $assert(!str_contains($mixedRow['content'],'https://images.example.org')&&!str_contains($mixedRow['content'],'data:image'),'Saved HTML contains neither original remote URL nor BASE64');
    $assert($mixedRow['featured_image_url']===$mixedSaved['images'][0]['url']&&str_contains($mixedRow['content'],$mixedSaved['images'][1]['url']),'Featured and inline images reference hosting files');
    $assert(!str_contains($mixedRow['ai_content_json'],'https://images.example.org')&&!str_contains($mixedRow['ai_content_json'],'data:image'),'Result metadata retains only stored image URLs');
    $repeatMixed=handbook_api_save($pdo,$mixedBody,$mixedPrepare,$realCleanup,$now+1000);
    $assert($repeatMixed['idempotent']&&$downloadCalls===1&&count($createdReal)===$beforeCount+2,'Idempotent retry does not fetch remote images or create new files');

    $badClaim=handbook_api_lease($pdo,$claimFor(31,'missing-remote-job'),$now);
    $badMixed=$payload(31,$badClaim,'missing-remote-job');
    $badMixed['content_html']=$realBody['content_html'].'<img src="https://images.example.org/missing">';
    try{handbook_api_save($pdo,$badMixed,$mixedPrepare,$realCleanup,$now+1);$assert(false,'Unavailable remote image accepted');}
    catch(InvalidArgumentException){$assert(true,'Unavailable remote image rejected as invalid user input');}
    $badRow=handbook_api_row($pdo,31);
    $assert($badRow['content']===''&&$badRow['ai_content_json']===null&&$badRow['ai_writer_claim_json']!==null&&!$pdo->inTransaction(),'Remote image failure rolls back the article and preserves its lease');
    $assert(!is_dir($mediaRoot.'/uploads/handbook/31'),'Remote image failure writes no partial BASE64 image');

    $dbFailClaim=handbook_api_lease($pdo,$claimFor(32,'mixed-db-fail'),$now);
    $dbFailBody=$payload(32,$dbFailClaim,'mixed-db-fail');$dbFailBody['content_html']=$mixedBody['content_html'];
    $pdo->exec("CREATE TRIGGER fail_mixed_save BEFORE UPDATE OF content ON posts WHEN NEW.id=32 BEGIN SELECT RAISE(FAIL,'mixed fixture failure'); END");
    $beforeFailed=count($createdReal);
    try{handbook_api_save($pdo,$dbFailBody,$mixedPrepare,$realCleanup,$now+1);$assert(false,'DB write expected to fail');}
    catch(PDOException){$assert(count($createdReal)===$beforeFailed+2&&!is_file($createdReal[$beforeFailed])&&!is_file($createdReal[$beforeFailed+1]),'DB rollback deletes both mixed-input files created in this operation');}
    $assert(is_file($createdReal[$beforeCount])&&is_file($createdReal[$beforeCount+1]),'Mixed rollback never removes earlier successful article media');
    $noLease=handbook_api_lease($pdo,$claimFor(33,'denied-download'),$now);
    $unauthorized=$payload(33,$noLease,'denied-download');$unauthorized['content_html']=$mixedBody['content_html'];$unauthorized['claim_token']=str_repeat('f',64);
    $beforeDenied=$downloadCalls;
    $fails(static fn()=>handbook_api_save($pdo,$unauthorized,$mixedPrepare,$realCleanup,$now+1),409,'lease_lost');
    $assert($downloadCalls===$beforeDenied&&!is_dir($mediaRoot.'/uploads/handbook/33'),'Rejected lease cannot trigger any remote download or file creation');

    $webpSource=imagecreatefromstring($png);ob_start();imagewebp($webpSource,null,80);$webpBytes=(string)ob_get_clean();imagedestroy($webpSource);
    $webpClaim=handbook_api_lease($pdo,$claimFor(40,'preserve-webp-job'),$now);
    $webpBody=$payload(40,$webpClaim,'preserve-webp-job');
    $webpBody['content_html']=str_replace('data:image/png;base64,fixture','data:image/webp;base64,'.base64_encode($webpBytes),$webpBody['content_html'])
        .'<figure><img src="https://images.example.org/companion.png" alt="Companion PNG"></figure>';
    $beforeWebp=count($createdReal);$beforeWebpDownloads=$downloadCalls;
    $webpSaved=handbook_api_save($pdo,$webpBody,$mixedPrepare,$realCleanup,$now+1);
    $webpRow=handbook_api_row($pdo,40);$webpMetadata=json_decode($webpRow['ai_content_json'],true);
    $assert($webpSaved['ok']&&$webpSaved['images_count']===2&&count($createdReal)===$beforeWebp+2,'API commits unchanged WebP alongside re-encoded PNG');
    $assert(str_ends_with($createdReal[$beforeWebp],'.webp')&&hash_file('sha256',$createdReal[$beforeWebp])===hash('sha256',$webpBytes),'API preserves exact original WebP bytes below100KiB');
    $assert($webpRow['featured_image_url']===$webpSaved['images'][0]['url']&&str_ends_with($webpRow['featured_image_url'],'.webp'),'Featured image links directly to preserved WebP');
    $assert($webpMetadata['images'][0]['mime']==='image/webp'&&$webpMetadata['images'][1]['mime']==='image/jpeg','Result metadata records the actual stored formats');
    $webpRetry=handbook_api_save($pdo,$webpBody,$mixedPrepare,$realCleanup,$now+1000);
    $assert($webpRetry['idempotent']&&count($createdReal)===$beforeWebp+2&&$downloadCalls===$beforeWebpDownloads+1,'Retry preserves original WebP result without duplicate files or downloads');
    $webpFailClaim=handbook_api_lease($pdo,$claimFor(41,'preserve-webp-rollback'),$now);
    $webpFail=$payload(41,$webpFailClaim,'preserve-webp-rollback');$webpFail['content_html']=$webpBody['content_html'];
    $pdo->exec("CREATE TRIGGER fail_webp_save BEFORE UPDATE OF content ON posts WHEN NEW.id=41 BEGIN SELECT RAISE(FAIL,'webp fixture failure'); END");
    $beforeWebpFail=count($createdReal);
    try{handbook_api_save($pdo,$webpFail,$mixedPrepare,$realCleanup,$now+1);$assert(false,'WebP DB write expected to fail');}
    catch(PDOException){$assert(count($createdReal)===$beforeWebpFail+2&&!is_file($createdReal[$beforeWebpFail])&&!is_file($createdReal[$beforeWebpFail+1]),'DB rollback cleans both preserved WebP and compressed JPEG files');}
    $assert(is_file($createdReal[$beforeWebp])&&hash_file('sha256',$createdReal[$beforeWebp])===hash('sha256',$webpBytes),'WebP rollback leaves earlier successful media unchanged');
}finally{
    $realCleanup($createdReal);
    // Only empty test-owned directories remain after exact-file cleanup.
    foreach(glob($mediaRoot.'/uploads/handbook/*',GLOB_ONLYDIR)?:[]as$dir)rmdir($dir);
    if(is_dir($mediaRoot.'/uploads/handbook'))rmdir($mediaRoot.'/uploads/handbook');
    if(is_dir($mediaRoot.'/uploads'))rmdir($mediaRoot.'/uploads');
    rmdir($mediaRoot);
}

// Exercise the actual receipt/metadata path, not just the standalone matcher.
$pdo->exec('CREATE TABLE medical_facilities (
    id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE, language_code TEXT, name TEXT,
    address_text TEXT, city TEXT, category TEXT, phone_text TEXT, website_url TEXT,
    full_json TEXT, status TEXT, verified INTEGER, content TEXT)');
$pdo->exec('CREATE TABLE medical_doctors (
    id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE, language_code TEXT, name TEXT,
    specialty_text TEXT, facility_name TEXT, address_text TEXT, city TEXT, website_url TEXT,
    full_json TEXT, status TEXT, verified INTEGER, content TEXT)');
$pdo->exec("INSERT INTO medical_facilities(id,slug,language_code,name,address_text,city,website_url,phone_text,status,verified,content)
    VALUES (59,'dana-original','vi','Dana Dental - Nha Khoa Đà Nẵng','129 Nguyễn Hoàng, Hải Châu, Đà Nẵng','Đà Nẵng','https://nhakhoadana.com/','0788588588','published',1,'<p>Written profile</p>'),
    (58,'smile-original','vi','Nha Khoa My Smile','85 Điện Biên Phủ, Thanh Khê, Đà Nẵng','Đà Nẵng','https://mysmileclinic.vn/','0888884418','published',1,'<p>Written profile</p>'),
    (94,'smile-duplicate','vi','Nha Khoa My Smile','85 Điện Biên Phủ, Chính Gián, Thanh Khê, Đà Nẵng','Đà Nẵng','https://mysmileclinic.vn/','0888 884 418','published',0,'<p>Another written profile</p>')");
$pdo->exec("INSERT INTO medical_doctors(id,slug,language_code,name,specialty_text,facility_name,address_text,city,status,verified,content)
    VALUES (42,'doctor-original','vi','BS. Trần Minh An','Răng Hàm Mặt','Nha Khoa My Smile',
    '85 Điện Biên Phủ, Thanh Khê, Đà Nẵng','Đà Nẵng','published',0,'<p>Written doctor profile</p>')");
foreach ([501,502] as $testId) $insert->execute([$testId,1,'Suggested identities','suggestions-'.$testId,'vi','draft','',
    '','','','','2026-10-15 00:00:00','2026-10-15 00:00:00']);
$identityClaim = handbook_api_lease($pdo, $claimFor(501, 'identity-job'), $now);
$identityBody = $payload(501, $identityClaim, 'identity-job');
$identityBody['suggested_facilities_json'] = [
    ['name'=>'Dana Dental','address'=>'129 Nguyễn Hoàng, Phường Hải Châu, Đà Nẵng','city'=>'Đà Nẵng',
        'website'=>'https://nhakhoadana.com/','evidence_url'=>'https://nhakhoadana.com/gioi-thieu/'],
    ['name'=>'My Smile','address'=>'85 Điện Biên Phủ, Chính Gián, Đà Nẵng','city'=>'Đà Nẵng',
        'website'=>'https://mysmileclinic.vn/','evidence_url'=>'https://mysmileclinic.vn/gioi-thieu/'],
];
$identityBody['suggested_doctors_json'] = [['name'=>'BS.CKII. Trần Minh An','specialty'=>'Răng Hàm Mặt',
    'facility_name'=>'My Smile','address'=>'85 Điện Biên Phủ, Chính Gián, Đà Nẵng','city'=>'Đà Nẵng',
    'evidence_url'=>'https://mysmileclinic.vn/bac-si/']];
$identityResult = handbook_api_save($pdo, $identityBody, $prepare, $cleanup, $now + 1);
$identityMetadata = json_decode(handbook_api_row($pdo, 501)['ai_content_json'], true);
$assert($identityResult['status'] === 'published'
    && array_column($identityResult['suggested_facilities_json'], 'facility_id') === [59,58],
    'Article receipt returns existing canonical facility IDs, not new records');
$assert(array_column($identityMetadata['suggested_facilities_json'], 'facility_id') === [59,58]
    && array_column($identityMetadata['suggested_facilities_json'], 'resolution') === ['matched','matched'],
    'Stored article JSON keeps the canonical facility links for the public renderer');
$assert($identityResult['suggested_doctors_json'][0]['doctor_id'] === 42
    && $identityMetadata['suggested_doctors_json'][0]['doctor_id'] === 42,
    'Doctor title and workplace variants retain the existing doctor ID in response and article');
$assert((int) $pdo->query('SELECT COUNT(*) FROM medical_facilities')->fetchColumn() === 3
    && (int) $pdo->query('SELECT COUNT(*) FROM medical_doctors')->fetchColumn() === 1,
    'Successful article receipt inserts no duplicate suggested facilities or doctors');
$identityRetry = handbook_api_save($pdo, $identityBody, $prepare, $cleanup, $now + 1000);
$assert($identityRetry['idempotent'] && handbook_api_json($identityRetry['suggested_facilities_json']) === handbook_api_json($identityResult['suggested_facilities_json']),
    'Lost receipt acknowledgement returns the same canonical links on retry');

$rollbackIdentityClaim = handbook_api_lease($pdo, $claimFor(502, 'rollback-identity-job'), $now);
$rollbackIdentityBody = $payload(502, $rollbackIdentityClaim, 'rollback-identity-job');
$rollbackIdentityBody['suggested_facilities_json'] = [['name'=>'Nha khoa Mới','address'=>'25 Trần Phú, Đà Nẵng',
    'city'=>'Đà Nẵng','evidence_url'=>'https://newclinic.example.org/about']];
$pdo->exec("CREATE TRIGGER fail_identity_article BEFORE UPDATE OF content ON posts WHEN NEW.id=502 BEGIN SELECT RAISE(FAIL,'identity fixture rollback'); END");
try { handbook_api_save($pdo, $rollbackIdentityBody, $prepare, $cleanup, $now + 1); $assert(false, 'Article save should fail'); }
catch (PDOException) { $assert((int) $pdo->query('SELECT COUNT(*) FROM medical_facilities')->fetchColumn() === 3
    && handbook_api_row($pdo, 502)['status'] === 'draft', 'A failed article write rolls back suggested-profile creation too'); }

// Security contract checks for thin HTTP wrappers; no web server, DB credentials or live media needed.
$helperSource = (string) file_get_contents(dirname(__DIR__) . '/api/medical/_handbook.php');
$assert(str_contains($helperSource, "require_once __DIR__ . '/_auth.php'") && str_contains($helperSource, 'medical_api_auth();'), 'Both new endpoints use the existing API-key authentication.');
$assert(str_contains($helperSource, "stream_get_contents($" . 'stream, $maxBytes + 1)'), 'Request JSON is read with a real stream byte limit.');
$assert(str_contains($helperSource, "chrome-extension:") && str_contains($helperSource, 'Access-Control-Allow-Headers: X-Medical-Api-Key, Content-Type'), 'CORS supports authenticated extension requests.');
$assert(!preg_match('/\b(?:CREATE|ALTER)\s+TABLE\b/i', $helperSource), 'API helper never runs migrations.');
$assert(!str_contains($helperSource, 'file_get_contents($') && !str_contains($helperSource, 'curl_exec'), 'Endpoints delegate image fetching to the secure media importer rather than fetching URLs directly.');
echo "handbook_api_test: {$checks} checks passed\n";
