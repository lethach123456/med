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
require_once dirname(__DIR__) . '/medical_directory.php';
require_once dirname(__DIR__) . '/toplist_directory.php';

final class MaintenanceStatement extends PDOStatement
{
    public function __construct() {}
}
final class MaintenancePDO extends PDO
{
    public array $queries = [];
    public array $writes = [];
    public bool $missingColumns = false;
    public function __construct() {} // No connection, and no real database writes.
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $this->queries[] = $query;
        if (!preg_match('/^SELECT\b/i', trim($query))) throw new RuntimeException('Only SELECT is allowed.');
        if ($this->missingColumns) throw new RuntimeException('Simulated missing column.');
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
$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
};

if (PHP_SAPI !== 'cli') {
    $assert(!medreview_schema_migration_allowed(), 'ordinary HTTP cannot migrate');
    $spy = new MaintenancePDO();
    foreach (['medical_directory_ensure_tables', 'medical_directory_ensure_facility_ai_image_column',
        'medical_directory_ensure_facility_content_columns', 'medical_directory_ensure_ai_writer_claim_columns',
        'medical_directory_ensure_ai_image_prompt', 'medical_directory_ensure_doctor_content_columns',
        'toplist_directory_ensure_tables'] as $helper) {
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
    try { medical_directory_run_schema_migrations($spy); $failed = false; }
    catch (RuntimeException) { $failed = true; }
    $assert($failed && count($spy->writes) === 1 && str_starts_with($spy->writes[0], 'CREATE TABLE'), 'explicit maintenance invokes migration');
    $assert(!medreview_schema_migration_allowed(), 'maintenance flag reset after failure');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'checks' => $checks]);
    exit;
}

$assert(medreview_schema_migration_allowed(), 'CLI maintenance allowed');
foreach (['medical_facilities', 'medical_facility_edit', 'medical_doctors', 'medical_doctor_edit', 'medical_reviews', 'medical_review_edit'] as $page) {
    $source = file_get_contents(dirname(__DIR__) . '/admin/' . $page . '.php');
    $assert(!str_contains($source, 'medical_directory_seed_defaults(') && !str_contains($source, 'medical_directory_ensure_tables('), $page . ' no runtime setup');
}
$seedSource = file_get_contents(dirname(__DIR__) . '/scripts/seeds/seed_medical_directory.php');
$assert(str_contains($seedSource, "in_array('--demo', \$argv, true)") && !str_contains($seedSource, 'TRUNCATE'), 'demo opt-in, no reset');
$adminMigration = file_get_contents(dirname(__DIR__) . '/admin/api/migrate.php');
$assert(str_contains($adminMigration, 'medical_directory_run_schema_migrations($pdo)') && !str_contains($adminMigration, 'medical_directory_seed_defaults('), 'admin maintenance does not seed');
$seedFunction = new ReflectionFunction('medical_directory_seed_defaults');
$functionLines = array_slice(file($seedFunction->getFileName()), $seedFunction->getStartLine() - 1, $seedFunction->getEndLine() - $seedFunction->getStartLine() + 1);
$assert(!str_contains(implode('', $functionLines), 'SELECT slug FROM medical_facilities'), 'seed no full-directory aggregate loop');

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
    foreach (['/tests/medical_maintenance_test.php', '/scripts/migrate_medical_directory.php',
        '/scripts/seeds/seed_medical_directory.php', '/scripts/seeds/seed_verified_facilities.php',
        '/scripts/seeds/seed_editorial_facility_profiles.php'] as $path) {
        [$status] = $request($path);
        $assert($status === 404, $path . ' blocked over HTTP');
    }
} finally {
    proc_terminate($process);
    foreach ($pipes as $pipe) fclose($pipe);
    proc_close($process);
}
$seedProcess = proc_open([PHP_BINARY, dirname(__DIR__) . '/scripts/seeds/seed_medical_directory.php'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $seedPipes);
fclose($seedPipes[0]);
$out = stream_get_contents($seedPipes[1]); $err = stream_get_contents($seedPipes[2]);
fclose($seedPipes[1]); fclose($seedPipes[2]);
$assert(proc_close($seedProcess) === 1 && $out === '' && str_contains($err, '--demo'), 'seed without opt-in exits before DB access');
echo "Medical maintenance: {$checks} checks passed. No database connection or data changes.\n";
