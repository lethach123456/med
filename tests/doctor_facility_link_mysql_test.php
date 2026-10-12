<?php
declare(strict_types=1);

/**
 * Real InnoDB integration test. It NEVER calls db(), loads runtime credentials,
 * copies production tables, or issues HTTP requests.
 *
 * Run only with --mysql-isolated and explicit DB_HOST=127.0.0.1, DB_NAME,
 * DB_USER and DB_PASS. DB_NAME must be a NEW medreview_test_* database.
 * The runner creates that database and always attempts to drop ONLY that same
 * database. Existing databases are refused, including other test databases.
 * DB_PORT is optional (3306). Supply credentials through your local environment,
 * not command-line arguments or committed source.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!in_array('--mysql-isolated', $argv, true)) {
    echo "Doctor facility MySQL test skipped; explicit --mysql-isolated and isolated local DB environment required.\n";
    exit(0);
}

final class DoctorFacilityMysqlTestFailure extends RuntimeException {}

function doctor_facility_mysql_fail(string $message): never
{
    throw new DoctorFacilityMysqlTestFailure($message);
}

function doctor_facility_mysql_settings(): array
{
    $host = getenv('DB_HOST');
    $name = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $password = getenv('DB_PASS');
    $port = getenv('DB_PORT');
    if ($host !== '127.0.0.1') doctor_facility_mysql_fail('DB_HOST must explicitly be 127.0.0.1.');
    if (!is_string($name) || !preg_match('/\Amedreview_test_[a-z0-9_]{6,40}\z/D', $name)) {
        doctor_facility_mysql_fail('DB_NAME must explicitly name a new medreview_test_* database (lowercase letters, digits and underscores).');
    }
    if (!is_string($user) || $user === '' || !is_string($password)) {
        doctor_facility_mysql_fail('DB_USER and DB_PASS must both be explicitly supplied.');
    }
    $port = $port === false || $port === '' ? '3306' : $port;
    if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
        doctor_facility_mysql_fail('DB_PORT must be a valid local TCP port.');
    }
    return ['host' => $host, 'name' => $name, 'user' => $user, 'password' => $password, 'port' => (int) $port];
}

function doctor_facility_mysql_connect(array $settings, bool $withDatabase = true): PDO
{
    $dsn = 'mysql:host=127.0.0.1;port=' . $settings['port'] . ';charset=utf8mb4';
    if ($withDatabase) $dsn .= ';dbname=' . $settings['name'];
    $pdo = new PDO($dsn, $settings['user'], $settings['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    if ($withDatabase) {
        if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== $settings['name']) {
            doctor_facility_mysql_fail('Connection did not select the isolated test database.');
        }
        $pdo->exec('SET SESSION innodb_lock_wait_timeout=5');
        $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    }
    return $pdo;
}

function doctor_facility_mysql_error(Throwable $error): string
{
    // Authentication/connect exceptions can contain usernames or connection
    // details. Never print their message, stack trace, DSN or environment.
    if ($error instanceof DoctorFacilityMysqlTestFailure) return $error->getMessage();
    if ($error instanceof PDOException) {
        return 'MySQL operation failed (SQLSTATE ' . (string) $error->getCode()
            . ', driver ' . (string) ($error->errorInfo[1] ?? 'unknown') . ').';
    }
    return 'Unexpected ' . get_class($error) . ' in the isolated test.';
}

function doctor_facility_mysql_location(string $name, string $address): array
{
    return ['facility_id' => null, 'facility_name' => $name, 'address_text' => $address,
        'is_primary' => true, 'phone_text' => '028 1234 5678',
        'website_url' => 'https://example.org/isolated-clinic',
        'source_ids' => ['s1']];
}

/** Exercise the same owner contract as admin/research: lock doctor, save all fields, commit together. */
function doctor_facility_mysql_save(PDO $pdo, int $doctorId, array $locations, bool $forceRollback = false): array
{
    $operation = static function () use ($pdo, $doctorId, $locations, $forceRollback): array {
        $pdo->beginTransaction();
        try {
            $select = $pdo->prepare('SELECT * FROM medical_doctors WHERE id=? FOR UPDATE');
            $select->execute([$doctorId]);
            $doctor = $select->fetch();
            if (!is_array($doctor)) doctor_facility_mysql_fail('Fixture doctor is absent.');
            $fields = ['locations_json' => medical_directory_json_encode($locations),
                'sources_json' => medical_directory_json_encode([['id' => 's1', 'url' => 'https://example.org/isolated-source']])];
            medical_doctor_link_workplaces($pdo, $doctorId, $fields, $doctor, (string) $doctor['language_code']);
            if ($forceRollback) doctor_facility_mysql_fail('Deliberate owner rollback.');
            $sets = []; $parameters = [':id' => $doctorId];
            foreach ($fields as $key => $value) {
                // Keep this fixture owner update from accidentally accepting a
                // future field that its minimal doctor table does not include.
                if (!in_array($key, ['locations_json', 'sources_json', 'facility_slug', 'facility_name'], true)) {
                    doctor_facility_mysql_fail('Unexpected helper field in fixture owner update.');
                }
                $sets[] = '`' . $key . '`=:' . $key;
                $parameters[':' . $key] = $value;
            }
            $pdo->prepare('UPDATE medical_doctors SET ' . implode(',', $sets) . ' WHERE id=:id')->execute($parameters);
            $pdo->commit();
            return $fields;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    };
    // The serialization helper must acquire its lock BEFORE the owner
    // transaction begins and release it after commit/rollback.
    if (!function_exists('medical_doctor_with_facility_lock')) {
        doctor_facility_mysql_fail('The production facility serialization helper is required by this integration test.');
    }
    return medical_doctor_with_facility_lock($pdo, $operation);
}

/** Retry the COMPLETE owner transaction only for genuine InnoDB contention. */
function doctor_facility_mysql_save_with_retry(PDO $pdo, int $doctorId, array $locations): array
{
    $retries = 0;
    while (true) {
        try {
            $fields = doctor_facility_mysql_save($pdo, $doctorId, $locations);
            return ['fields' => $fields, 'retries' => $retries];
        } catch (PDOException $error) {
            $driver = (int) ($error->errorInfo[1] ?? 0);
            if (!in_array($driver, [1205, 1213], true) || $retries >= 3) throw $error;
            if ($pdo->inTransaction()) $pdo->rollBack();
            $retries++;
            usleep(25000 * $retries);
        }
    }
}

function doctor_facility_mysql_worker(array $settings, int $doctorId): never
{
    $token = getenv('MEDREVIEW_MYSQL_TEST_RUN_TOKEN');
    if (!is_string($token) || !preg_match('/\A[a-f0-9]{48}\z/D', $token)) {
        doctor_facility_mysql_fail('Workers can only be started by the owning isolated runner.');
    }
    $pdo = doctor_facility_mysql_connect($settings);
    $storedToken = $pdo->query('SELECT run_token FROM doctor_facility_test_guard WHERE id=1')->fetchColumn();
    if (!is_string($storedToken) || !hash_equals($token, $storedToken)) {
        doctor_facility_mysql_fail('Worker database ownership guard did not match.');
    }
    require_once dirname(__DIR__) . '/medical_directory.php';
    echo "READY\n";
    fflush(STDOUT);
    if (trim((string) fgets(STDIN)) !== 'GO') doctor_facility_mysql_fail('Worker was not released by its parent.');
    $location = doctor_facility_mysql_location('Phòng khám Kiểm thử đồng thời', '501 Nguyễn Văn Kiểm Thử, Phường Bình An, Thành phố Hồ Chí Minh');
    $result = doctor_facility_mysql_save_with_retry($pdo, $doctorId, [$location]);
    $resolved = json_decode($result['fields']['locations_json'], true, 512, JSON_THROW_ON_ERROR);
    echo json_encode(['ok' => true, 'doctor_id' => $doctorId, 'facility_id' => $resolved[0]['facility_id'],
        'retries' => $result['retries']], JSON_THROW_ON_ERROR) . "\n";
    exit(0);
}

function doctor_facility_mysql_concurrency(array $settings, string $token): array
{
    if (!function_exists('proc_open')) doctor_facility_mysql_fail('proc_open is required for real independent MySQL connections.');
    $children = [];
    try {
        foreach ([11, 12] as $doctorId) {
            $environment = getenv();
            $environment['MEDREVIEW_MYSQL_TEST_RUN_TOKEN'] = $token;
            $pipes = [];
            $process = proc_open([PHP_BINARY, __FILE__, '--mysql-isolated', '--worker=' . $doctorId],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), $environment);
            if (!is_resource($process)) doctor_facility_mysql_fail('Could not start independent MySQL worker.');
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $children[] = ['process' => $process, 'pipes' => $pipes, 'stdout' => '', 'stderr' => '', 'ready' => false];
        }
        $deadline = microtime(true) + 15;
        while (!array_reduce($children, static fn(bool $all, array $child): bool => $all && $child['ready'], true)) {
            foreach ($children as &$child) {
                $child['stdout'] .= stream_get_contents($child['pipes'][1]);
                $child['stderr'] .= stream_get_contents($child['pipes'][2]);
                $child['ready'] = str_contains($child['stdout'], "READY\n");
            }
            unset($child);
            if (microtime(true) > $deadline) doctor_facility_mysql_fail('Independent workers did not reach the start barrier.');
            usleep(10000);
        }
        // Both processes are connected and waiting. Release them together so
        // the same absent clinic is created/reused under genuine contention.
        foreach ($children as $child) { fwrite($child['pipes'][0], "GO\n"); fflush($child['pipes'][0]); }
        $deadline = microtime(true) + 30;
        $results = [];
        while (count($results) < count($children)) {
            foreach ($children as $position => &$child) {
                if (isset($results[$position])) continue;
                $child['stdout'] .= stream_get_contents($child['pipes'][1]);
                $child['stderr'] .= stream_get_contents($child['pipes'][2]);
                $lines = array_filter(explode("\n", $child['stdout']), static fn(string $line): bool => str_starts_with($line, '{'));
                if ($lines !== []) {
                    $result = json_decode((string) end($lines), true, 512, JSON_THROW_ON_ERROR);
                    if (($result['ok'] ?? false) !== true) doctor_facility_mysql_fail('A concurrent owner transaction failed.');
                    $results[$position] = $result;
                } elseif (!proc_get_status($child['process'])['running']) {
                    doctor_facility_mysql_fail('A MySQL worker ended before completing its save.');
                }
            }
            unset($child);
            if (microtime(true) > $deadline) doctor_facility_mysql_fail('Concurrent MySQL saves exceeded their bounded wait.');
            usleep(10000);
        }
        ksort($results);
        return array_values($results);
    } finally {
        foreach ($children as $child) {
            if (proc_get_status($child['process'])['running']) proc_terminate($child['process']);
            foreach ($child['pipes'] as $pipe) if (is_resource($pipe)) fclose($pipe);
            proc_close($child['process']);
        }
    }
}

$settings = []; $server = null; $pdo = null; $created = false; $failure = null; $checks = 0; $retries = 0;
try {
    $settings = doctor_facility_mysql_settings();
    $workerArgument = array_values(array_filter($argv, static fn(string $argument): bool => str_starts_with($argument, '--worker=')));
    if ($workerArgument !== []) {
        $doctorId = substr($workerArgument[0], strlen('--worker='));
        if (!in_array($doctorId, ['11', '12'], true)) doctor_facility_mysql_fail('Unknown isolated worker doctor.');
        doctor_facility_mysql_worker($settings, (int) $doctorId);
    }
    $server = doctor_facility_mysql_connect($settings, false);
    $existing = $server->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');
    $existing->execute([$settings['name']]);
    if ($existing->fetchColumn() !== false) doctor_facility_mysql_fail('Refusing to use or remove an existing database. Choose a new DB_NAME.');
    // Identifier is strictly validated above. The created flag is set ONLY
    // after CREATE DATABASE succeeds; no existing schema is ever dropped.
    $server->exec('CREATE DATABASE `' . $settings['name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    $pdo = doctor_facility_mysql_connect($settings);
    $token = bin2hex(random_bytes(24));
    $pdo->exec('CREATE TABLE doctor_facility_test_guard (id INT PRIMARY KEY, run_token CHAR(48) NOT NULL) ENGINE=InnoDB');
    $pdo->prepare('INSERT INTO doctor_facility_test_guard VALUES (1,?)')->execute([$token]);

    // Minimal fixture definitions copied from the repository schema, not from
    // a production connection. Preserve unique/index/InnoDB semantics.
    $pdo->exec("CREATE TABLE medical_facilities (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT, slug VARCHAR(191) NOT NULL,
        language_code VARCHAR(5) NOT NULL DEFAULT 'vi', translation_of_id INT UNSIGNED NULL,
        name VARCHAR(160) NOT NULL, category VARCHAR(120) NOT NULL DEFAULT 'Cơ sở y tế',
        city VARCHAR(120) NOT NULL DEFAULT '', address_text VARCHAR(255) NULL,
        phone_text VARCHAR(80) NULL, website_url VARCHAR(255) NULL, booking_url TEXT NULL,
        content LONGTEXT NULL, full_json LONGTEXT NULL, sources_json MEDIUMTEXT NULL,
        status ENUM('draft','published') NOT NULL DEFAULT 'published',
        verified TINYINT(1) NOT NULL DEFAULT 1, rating DECIMAL(3,1) NOT NULL DEFAULT 0.0,
        reviews_count INT UNSIGNED NOT NULL DEFAULT 0, followers_count INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id), UNIQUE KEY uniq_medical_facilities_slug (slug),
        UNIQUE KEY idx_medical_facilities_translation_parent (translation_of_id),
        KEY idx_medical_facilities_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE medical_doctors (
        id INT UNSIGNED NOT NULL, name VARCHAR(160) NOT NULL, language_code VARCHAR(5) NOT NULL DEFAULT 'vi',
        city VARCHAR(120) NOT NULL DEFAULT '', address_text TEXT NULL,
        locations_json MEDIUMTEXT NULL, sources_json MEDIUMTEXT NULL,
        facility_slug VARCHAR(191) NOT NULL DEFAULT '', facility_name VARCHAR(160) NOT NULL DEFAULT '',
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE medical_doctor_facilities (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT, doctor_id INT UNSIGNED NOT NULL,
        facility_id INT UNSIGNED NULL, facility_name VARCHAR(160) NOT NULL DEFAULT '',
        role_text VARCHAR(190) NULL, department_text VARCHAR(160) NULL,
        address_text TEXT NULL, phone_text VARCHAR(80) NULL, website_url TEXT NULL, booking_url TEXT NULL,
        schedule_json MEDIUMTEXT NULL, fees_json MEDIUMTEXT NULL, source_ids_json MEDIUMTEXT NULL,
        is_primary TINYINT(1) NOT NULL DEFAULT 0, display_order INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id), KEY idx_doctor_locations (doctor_id, display_order),
        KEY idx_location_facility (facility_id), UNIQUE KEY uniq_doctor_facility (doctor_id, facility_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $seedDoctor = $pdo->prepare("INSERT INTO medical_doctors (id,name,city,locations_json) VALUES (?,?,'Thành phố Hồ Chí Minh','[]')");
    foreach ([1, 2, 3, 11, 12] as $id) $seedDoctor->execute([$id, 'Bác sĩ Kiểm thử ' . $id]);
    require_once dirname(__DIR__) . '/medical_directory.php';
    $assert = static function (bool $condition, string $label) use (&$checks): void {
        if (!$condition) doctor_facility_mysql_fail($label);
        $checks++;
    };
    $countFacilities = static fn(): int => (int) $pdo->query('SELECT COUNT(*) FROM medical_facilities')->fetchColumn();
    $readDoctor = static function (int $id) use ($pdo): array {
        $statement = $pdo->prepare('SELECT * FROM medical_doctors WHERE id=?');
        $statement->execute([$id]);
        return $statement->fetch();
    };
    $links = static function (int $id) use ($pdo): array {
        $statement = $pdo->prepare('SELECT * FROM medical_doctor_facilities WHERE doctor_id=? ORDER BY display_order');
        $statement->execute([$id]);
        return $statement->fetchAll();
    };
    $location = doctor_facility_mysql_location('Phòng khám Kiểm thử tích hợp', '101 Nguyễn Văn Kiểm Thử, Phường Bình An, Thành phố Hồ Chí Minh');

    // A helper may not create anything outside the caller's transaction.
    $fields = ['locations_json' => medical_directory_json_encode([$location])];
    $rejected = false;
    try { medical_doctor_link_workplaces($pdo, 1, $fields, $readDoctor(1)); }
    catch (LogicException) { $rejected = true; }
    $assert($rejected && $countFacilities() === 0 && $links(1) === [], 'Missing owner transaction must reject before creating a clinic/link.');

    $saved = doctor_facility_mysql_save($pdo, 1, [$location]);
    $resolved = json_decode($saved['locations_json'], true, 512, JSON_THROW_ON_ERROR)[0];
    $facility = $pdo->query('SELECT * FROM medical_facilities')->fetch();
    $assert($countFacilities() === 1 && (int) $facility['id'] === $resolved['facility_id'], 'A valid missing facility is created exactly once.');
    $assert($facility['name'] === $location['facility_name'] && $facility['address_text'] === $location['address_text'], 'New facility preserves name and complete address.');
    $assert($facility['city'] === 'Hồ Chí Minh' && $facility['phone_text'] === $location['phone_text']
        && $facility['website_url'] === $location['website_url'], 'New facility stores its address-derived city and workplace contact metadata.');
    $provenance = json_decode($facility['full_json'], true, 512, JSON_THROW_ON_ERROR);
    $assert($provenance['origin'] === 'doctor_workplace' && $provenance['doctor_id'] === 1
        && $provenance['source_ids'] === ['s1'] && $provenance['sources'][0]['id'] === 's1'
        && $provenance['sources'][0]['url'] === 'https://example.org/isolated-source', 'Seeded clinic provenance retains doctor origin and the selected source evidence.');
    $assert((int) $facility['verified'] === 0 && (float) $facility['rating'] === 0.0
        && (int) $facility['reviews_count'] === 0 && (int) $facility['followers_count'] === 0
        && $facility['content'] === null && $facility['status'] === 'published', 'New facility is unverified, unrated and content-empty.');
    $assert($saved['facility_slug'] === $facility['slug'] && $saved['facility_name'] === $facility['name'], 'Legacy primary fields resolve to the actual clinic.');
    $assert($resolved['source_ids'] === ['s1'] && json_decode($links(1)[0]['source_ids_json'], true) === ['s1'], 'JSON and relational links preserve source references.');
    $assert((int) $links(1)[0]['facility_id'] === (int) $facility['id'] && (int) $links(1)[0]['is_primary'] === 1, 'Committed relational primary link points to the created clinic.');
    $assert(json_decode($readDoctor(1)['locations_json'], true)[0]['facility_id'] === (int) $facility['id'], 'Doctor JSON and facility are committed in the same owner save.');

    $again = doctor_facility_mysql_save($pdo, 2, [$location]);
    $assert($countFacilities() === 1 && json_decode($again['locations_json'], true)[0]['facility_id'] === (int) $facility['id'], 'A second doctor reuses the existing identity.');
    $beforeDoctor = $readDoctor(1); $beforeLinks = $links(1);
    $rollbackLocation = doctor_facility_mysql_location('Phòng khám Kiểm thử rollback', '902 Đại lộ Kiểm thử, Phường Bình An, Thành phố Hồ Chí Minh');
    $rollbackObserved = false;
    try { doctor_facility_mysql_save($pdo, 1, [$rollbackLocation], true); }
    catch (DoctorFacilityMysqlTestFailure $error) { $rollbackObserved = $error->getMessage() === 'Deliberate owner rollback.'; }
    $assert($rollbackObserved && !$pdo->inTransaction(), 'An owner failure rolls back and leaves no open transaction.');
    $assert($countFacilities() === 1, 'A facility created before an owner failure is rolled back.');
    $assert($readDoctor(1) === $beforeDoctor && $links(1) === $beforeLinks, 'Owner rollback preserves the previous doctor JSON and relational links.');

    $invalid = array_replace($rollbackLocation, ['facility_id' => -1, 'is_primary' => false]);
    $invalidObserved = false;
    try { doctor_facility_mysql_save($pdo, 1, [$rollbackLocation, $invalid]); }
    catch (InvalidArgumentException) { $invalidObserved = true; }
    $assert($invalidObserved && !$pdo->inTransaction() && $countFacilities() === 1, 'Invalid later workplace cannot leave a partial seeded clinic.');
    $assert($readDoctor(1) === $beforeDoctor && $links(1) === $beforeLinks, 'Invalid payload preserves previous JSON and links.');

    $incomplete = array_replace($location, ['facility_name' => 'Phòng khám Kiểm thử chưa rõ', 'address_text' => null]);
    $unlinked = doctor_facility_mysql_save($pdo, 3, [$incomplete]);
    $assert($countFacilities() === 1 && json_decode($unlinked['locations_json'], true)[0]['facility_id'] === null
        && $links(3)[0]['facility_id'] === null, 'Missing address remains unlinked and does not seed a clinic.');

    // Empty candidate range is the important InnoDB race: two doctors both
    // observe an absent clinic and must finish with one clinic and two links.
    $pdo->exec('DELETE FROM medical_doctor_facilities');
    $pdo->exec('DELETE FROM medical_facilities');
    $results = doctor_facility_mysql_concurrency($settings, $token);
    $assert(count($results) === 2 && $results[0]['facility_id'] > 0
        && $results[0]['facility_id'] === $results[1]['facility_id'], 'Concurrent independent writers resolve the same created clinic ID.');
    $assert($countFacilities() === 1, 'Concurrent same-facility writers create exactly one clinic.');
    $assert(count($links(11)) === 1 && count($links(12)) === 1
        && (int) $links(11)[0]['facility_id'] === (int) $links(12)[0]['facility_id'], 'Concurrent writers commit one link for each distinct doctor.');
    foreach ([11, 12] as $id) {
        $assert(json_decode($readDoctor($id)['locations_json'], true)[0]['facility_id'] === (int) $links($id)[0]['facility_id'], 'Concurrent doctor JSON agrees with its relational link.');
    }
    $retries = array_sum(array_column($results, 'retries'));
    $assert(!$pdo->inTransaction(), 'Main test connection is transaction-free after concurrent saves.');
} catch (Throwable $error) {
    $failure = doctor_facility_mysql_error($error);
} finally {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    $pdo = null;
    if ($created && $server instanceof PDO) {
        try { $server->exec('DROP DATABASE `' . $settings['name'] . '`'); }
        catch (Throwable $error) { $failure = ($failure ? $failure . ' ' : '') . 'Cleanup of the newly created isolated database failed; remove only the explicit test DB_NAME manually.'; }
    }
}
if ($failure !== null) {
    fwrite(STDERR, 'Doctor facility MySQL test failed: ' . $failure . "\n");
    exit(1);
}
echo "Doctor facility MySQL test: {$checks} checks passed; real isolated local InnoDB, two concurrent processes, {$retries} contention retries; owned test database removed.\n";
