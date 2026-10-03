<?php
declare(strict_types=1);

// HTTP fixture is available only to a local test server with a random CLI token.
if (PHP_SAPI !== 'cli') {
    $expected = (string) getenv('MEDREVIEW_MAINTENANCE_TEST_TOKEN');
    if (PHP_SAPI !== 'cli-server' || $expected === ''
        || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
        || !hash_equals($expected, (string) ($_SERVER['HTTP_X_TEST_TOKEN'] ?? ''))) {
        http_response_code(404); exit;
    }
}
require_once dirname(__DIR__) . '/admin/_bootstrap.php';
require_once dirname(__DIR__) . '/medical_directory.php';
require_once dirname(__DIR__) . '/toplist_directory.php';
require_once dirname(__DIR__) . '/medical_media_worker.php';
require_once dirname(__DIR__) . '/schema_migrations.php';

final class MaintenanceStatement extends PDOStatement
{
    public function __construct(private ?array $row = null) {}
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->row ?? false;
    }
}
final class MaintenancePDO extends PDO
{
    public array $queries = [];
    public array $writes = [];
    public bool $missingColumns = false;
    public bool $legacyQueue = false;
    public function __construct() {} // No connection, and no real database writes.
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $this->queries[] = $query;
        if (!preg_match('/^(SELECT|SHOW)\b/i', trim($query))) throw new RuntimeException('Only read-only SQL is allowed.');
        if ($this->missingColumns) throw new RuntimeException('Simulated missing column.');
        if (str_starts_with($query, 'SHOW COLUMNS')) {
            return new MaintenanceStatement(['Type' => $this->legacyQueue ? "enum('pending','processing','done','failed')" : "enum('pending','processing','downloaded','done','failed')"]);
        }
        return new MaintenanceStatement();
    }
    public function exec(string $statement): int|false
    {
        $this->writes[] = $statement;
        throw new RuntimeException('Simulated migration failure.');
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->writes[] = $query;
        throw new RuntimeException('Unexpected prepare during runtime migration.');
    }
}
final class TemplateReadStatement extends PDOStatement
{
    public function __construct(private string $content) {}
    public function execute(?array $params = null): bool { return true; }
    public function fetchColumn(int $column = 0): mixed { return $this->content; }
}
final class TemplateReadPDO extends PDO
{
    public array $reads = [];
    public function __construct(private string $content = '') {}
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (!preg_match('/^\s*SELECT\b/i', $query)) throw new RuntimeException('Template renderer attempted a write.');
        $this->reads[] = $query;
        return new TemplateReadStatement($this->content);
    }
}
$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
};
$runtimeHelpers = ['medical_directory_ensure_tables', 'medical_directory_ensure_facility_ai_image_column',
    'medical_directory_ensure_facility_content_columns', 'medical_directory_ensure_ai_writer_claim_columns',
    'medical_directory_ensure_ai_image_prompt', 'medical_directory_ensure_doctor_content_columns',
    'toplist_directory_ensure_tables', 'medical_media_jobs_ensure_table', 'ensure_front_editor_page_profiles_table',
    'front_editor_templates_ensure_table', 'ensure_content_language_columns', 'medreview_migrate_content_columns',
    'medreview_migrate_core_tables'];

if (PHP_SAPI !== 'cli') {
    $assert(!medreview_schema_migration_allowed(), 'ordinary HTTP cannot migrate');
    $spy = new MaintenancePDO();
    foreach ($runtimeHelpers as $helper) {
        $helper($spy);
        $assert($spy->queries === [] && $spy->writes === [], $helper . ' does zero SQL on HTTP');
    }
    try { medical_directory_seed_defaults($spy); $rejected = false; }
    catch (LogicException) { $rejected = true; }
    $assert($rejected && $spy->queries === [] && $spy->writes === [], 'HTTP seed rejected before SQL');
    $assert(medreview_ensure_translation_columns($spy, 'medical_facilities'), 'ready translation schema accepted');
    $assert(count($spy->queries) === 1 && $spy->writes === [], 'translation readiness is one read only');
    medreview_ensure_translation_columns($spy, 'medical_facilities');
    $assert(count($spy->queries) === 1, 'readiness memoized within request');
    $spy->missingColumns = true;
    $assert(!medreview_ensure_translation_columns($spy, 'medical_toplists') && $spy->writes === [], 'missing columns never auto-migrate');
    try { medical_doctor_require_schema($spy); $missing = false; }
    catch (RuntimeException) { $missing = true; }
    $assert($missing && $spy->writes === [], 'missing doctor schema reports error without migration');
    $spy->missingColumns = false;
    $before = count($spy->queries);
    medical_doctor_require_schema($spy);
    $assert(count($spy->queries) === $before + 2 && $spy->writes === [], 'doctor readiness is two reads only');
    $oldQueue = new MaintenancePDO();
    $oldQueue->legacyQueue = true;
    try { medical_media_jobs_require_schema($oldQueue); $needsUpgrade = false; }
    catch (RuntimeException) { $needsUpgrade = true; }
    $assert($needsUpgrade && $oldQueue->writes === [], 'old worker queue reports migration required without DDL');
    $oldQueue->legacyQueue = false;
    medical_media_jobs_require_schema($oldQueue);
    $queueReads = count($oldQueue->queries);
    medical_media_jobs_require_schema($oldQueue);
    $assert($queueReads === 4 && count($oldQueue->queries) === 4 && $oldQueue->writes === [], 'queue validation memoized only after success');
    try { medical_directory_run_schema_migrations($spy); $failed = false; }
    catch (RuntimeException) { $failed = true; }
    $assert($failed && count($spy->writes) === 1 && str_starts_with($spy->writes[0], 'CREATE TABLE'), 'explicit maintenance invokes migration');
    $assert(!medreview_schema_migration_allowed(), 'maintenance flag reset after failure');
    $allSpy = new MaintenancePDO();
    try { medreview_run_all_schema_migrations($allSpy); $failed = false; }
    catch (RuntimeException) { $failed = true; }
    $assert($failed && count($allSpy->writes) === 1 && !medreview_schema_migration_allowed(), 'full maintenance failure restores runtime permission');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'checks' => $checks]);
    exit;
}

$assert(!medreview_schema_migration_allowed(), 'ordinary CLI cron cannot migrate');
$cliSpy = new MaintenancePDO();
foreach ($runtimeHelpers as $helper) {
    $helper($cliSpy);
    $assert($cliSpy->queries === [] && $cliSpy->writes === [], $helper . ' does zero SQL on CLI runtime');
}
medreview_with_schema_migration(static function () use ($assert): void {
    $assert(medreview_schema_migration_allowed(), 'explicit maintenance allowed');
    medreview_with_schema_migration(static fn() => null);
    $assert(medreview_schema_migration_allowed(), 'nested migration restores outer permission');
});
$assert(!medreview_schema_migration_allowed(), 'maintenance restores CLI runtime permission');
$blank = ['id' => 123, 'slug' => 'maintenance-test-empty', 'template' => 1, 'content' => ''];
$blankReader = new TemplateReadPDO();
$fallback = post_template_resolve_content($blank, $blankReader);
$assert(str_contains($fallback['content'], 'template-blank-canvas') && count($blankReader->reads) > 0 && $blank['content'] === '', 'blank template display resolves without DB persistence');
$paired = post_template_resolve_content($blank, new TemplateReadPDO('<p>Existing paired template</p>'));
$assert($paired['content'] === '<p>Existing paired template</p>', 'paired template reused read-only');
$filled = array_replace($blank, ['content' => '<p>Already edited</p>']);
$noRead = new TemplateReadPDO();
$assert(post_template_resolve_content($filled, $noRead) === $filled && $noRead->reads === [], 'existing template returned without SQL');
$resolver = new ReflectionFunction('post_template_resolve_content');
$resolverLines = array_slice(file($resolver->getFileName()), $resolver->getStartLine() - 1, $resolver->getEndLine() - $resolver->getStartLine() + 1);
$assert(!preg_match('/\b(?:UPDATE|INSERT|DELETE)\b|->exec\(/', implode('', $resolverLines)), 'template resolver contains no mutation');
foreach (['medical_facilities', 'medical_facility_edit', 'medical_doctors', 'medical_doctor_edit', 'medical_reviews', 'medical_review_edit'] as $page) {
    $source = file_get_contents(dirname(__DIR__) . '/admin/' . $page . '.php');
    $assert(!str_contains($source, 'medical_directory_seed_defaults(') && !str_contains($source, 'medical_directory_ensure_tables('), $page . ' no runtime setup');
}
$seedSource = file_get_contents(dirname(__DIR__) . '/scripts/seeds/seed_medical_directory.php');
$assert(str_contains($seedSource, "in_array('--demo', \$argv, true)") && !str_contains($seedSource, 'TRUNCATE'), 'demo opt-in, no reset');
$adminMigration = file_get_contents(dirname(__DIR__) . '/admin/api/migrate.php');
$assert(str_contains($adminMigration, 'medreview_run_all_schema_migrations($pdo)') && !str_contains($adminMigration, '$seedHome') && !str_contains($adminMigration, 'medical_directory_seed_defaults('), 'admin maintenance does not seed any demos');
$seedFunction = new ReflectionFunction('medical_directory_seed_defaults');
$functionLines = array_slice(file($seedFunction->getFileName()), $seedFunction->getStartLine() - 1, $seedFunction->getEndLine() - $seedFunction->getStartLine() + 1);
$assert(!str_contains(implode('', $functionLines), 'SELECT slug FROM medical_facilities'), 'seed no full-directory aggregate loop');

// Whole-project audit: schema SQL stays inside guarded libraries, not entry points.
$ddlLibraries = ['db.php', 'medical_directory.php', 'medical_doctor_content.php', 'toplist_directory.php', 'medical_media_worker.php', 'schema_migrations.php'];
$root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$audited = 0;
foreach ($iterator as $file) {
    $path = substr($file->getPathname(), strlen($root) + 1);
    if ($file->getExtension() !== 'php' || preg_match('~^(?:\.git|vendor|backups|storage|uploads|tests|scripts)/~', $path)) continue;
    $source = file_get_contents($file->getPathname());
    if (preg_match('/\b(?:CREATE\s+TABLE|ALTER\s+TABLE|CREATE\s+(?:UNIQUE\s+)?INDEX|TRUNCATE\s+TABLE)\b/i', $source)) {
        $assert(in_array($path, $ddlLibraries, true), 'DDL restricted to maintenance libraries: ' . $path);
    }
    if (!in_array($path, $ddlLibraries, true) && $path !== 'admin/_bootstrap.php') {
        $tokens = token_get_all($source);
        foreach ($tokens as $i => $token) {
            if (!is_array($token) || $token[0] !== T_STRING || !in_array($token[1], array_merge($runtimeHelpers, ['medreview_ensure_translation_columns', 'medical_directory_seed_defaults']), true)) continue;
            $assert(false, 'runtime entry point still references migration/seed: ' . $path . ':' . $token[2]);
        }
    }
    $audited++;
}
$assert($audited > 100, 'whole-project runtime audit completed');

// Isolated PHP HTTP server: no real DB used by this fixture or blocked scripts.
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($socket === false) throw new RuntimeException($error);
$address = stream_socket_get_name($socket, false);
fclose($socket);
$token = bin2hex(random_bytes(24));
$process = proc_open([PHP_BINARY, '-S', $address, '-t', dirname(__DIR__)], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes,
    dirname(__DIR__), array_merge(getenv(), ['MEDREVIEW_MAINTENANCE_TEST_TOKEN' => $token]));
if (!is_resource($process)) throw new RuntimeException('Cannot start test server.');
try {
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $ready = @stream_socket_client('tcp://' . $address, $errno, $error, 0.05);
        if ($ready !== false) { fclose($ready); break; }
        usleep(100000);
    }
    $request = static function (string $path, bool $auth = false) use ($address, $token): array {
        $curl = curl_init('http://' . $address . $path);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => $auth ? ['X-Test-Token: ' . $token] : []]);
        $raw = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_HTTP_CODE); curl_close($curl);
        return [$status, is_string($raw) ? $raw : ''];
    };
    [$status, $raw] = $request('/tests/medical_maintenance_test.php', true);
    $result = json_decode($raw, true);
    $assert($status === 200 && ($result['ok'] ?? false), 'HTTP regression checks pass: ' . $raw);
    $checks += (int) $result['checks'];
    foreach (['/tests/medical_maintenance_test.php', '/scripts/migrate_medical_directory.php', '/scripts/migrate_all.php',
        '/scripts/seeds/seed_medical_directory.php', '/scripts/seeds/seed_verified_facilities.php',
        '/scripts/seeds/seed_editorial_facility_profiles.php', '/scripts/seeds/seed_legacy_content.php', '/scripts/seeds/seed_post_templates.php'] as $path) {
        [$status] = $request($path);
        $assert($status === 404, $path . ' blocked over HTTP');
    }
} finally {
    proc_terminate($process);
    foreach ($pipes as $pipe) fclose($pipe);
    proc_close($process);
}
foreach (['seed_medical_directory.php' => '--demo', 'seed_legacy_content.php' => '--demo', 'seed_post_templates.php' => '--initialize-empty'] as $script => $flag) {
    $seedProcess = proc_open([PHP_BINARY, dirname(__DIR__) . '/scripts/seeds/' . $script], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $seedPipes);
    fclose($seedPipes[0]);
    $out = stream_get_contents($seedPipes[1]); $err = stream_get_contents($seedPipes[2]);
    fclose($seedPipes[1]); fclose($seedPipes[2]);
    $assert(proc_close($seedProcess) === 1 && $out === '' && str_contains($err, $flag), $script . ' without opt-in exits before DB access');
}
echo "Medical/system maintenance: {$checks} checks passed; {$audited} runtime PHP files audited. No database connection or data changes.\n";
