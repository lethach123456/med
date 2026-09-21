<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../toplist_directory.php';

medical_api_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

function medical_toplist_api_unique_facility_slug(PDO $pdo, string $value): string
{
    $base = slugify($value);
    if ($base === '') $base = bin2hex(random_bytes(6));
    $base = substr($base, 0, 180);
    $candidate = $base;
    $suffix = 2;
    $check = $pdo->prepare('SELECT 1 FROM medical_facilities WHERE slug = :slug LIMIT 1');
    while (true) {
        $check->execute([':slug' => $candidate]);
        if (!$check->fetchColumn()) return $candidate;
        $candidate = $base . '-' . $suffix++;
    }
}

function medical_toplist_api_items(array $body): array
{
    if (isset($body['items']) && is_array($body['items'])) return $body['items'];

    if (isset($body['content']) && is_string($body['content'])) {
        $decoded = json_decode($body['content'], true);
        if (is_array($decoded)) {
            if (isset($decoded['items']) && is_array($decoded['items'])) return $decoded['items'];
            return [$decoded];
        }
    }

    if ($body !== [] && array_keys($body) === range(0, count($body) - 1)) return $body;
    return [$body];
}

$body = read_json_body();
$items = medical_toplist_api_items($body);
$pdo = db();
medical_directory_ensure_tables($pdo);
toplist_directory_ensure_tables($pdo);

$updatedToplists = [];
$createdFacilities = [];
$errors = [];

try {
    $pdo->beginTransaction();
    $toplistLookup = $pdo->prepare('SELECT id FROM medical_toplists WHERE id = :id LIMIT 1');
    $facilityLookupById = $pdo->prepare('SELECT id FROM medical_facilities WHERE id = :id LIMIT 1');
    $facilityLookupByNameCity = $pdo->prepare('SELECT id FROM medical_facilities WHERE name = :name AND COALESCE(city, \'\') = :city ORDER BY id ASC LIMIT 1');
    $facilityLookupByNameAddress = $pdo->prepare('SELECT id FROM medical_facilities WHERE name = :name AND COALESCE(address_text, \'\') = :address LIMIT 1');
    $facilityLookupByNameWebsite = $pdo->prepare('SELECT id FROM medical_facilities WHERE name = :name AND COALESCE(website_url, \'\') = :website LIMIT 1');
    $facilityInsert = $pdo->prepare(
        "INSERT INTO medical_facilities
         (slug, name, category, city, address_text, phone_text, website_url, image_url, status, display_order)
         VALUES (:slug, :name, :category, :city, :address, :phone, :website, :image, 'published', :display_order)"
    );

    foreach ($items as $itemIndex => $item) {
        if (!is_array($item)) {
            $errors[] = 'Mục #' . ($itemIndex + 1) . ' không phải JSON object.';
            continue;
        }

        $toplistId = (int) ($item['toplist_id'] ?? $item['toplistId'] ?? $item['id'] ?? 0);
        if ($toplistId <= 0) {
            $errors[] = 'Mục #' . ($itemIndex + 1) . ' thiếu toplist_id.';
            continue;
        }
        $toplistLookup->execute([':id' => $toplistId]);
        if (!$toplistLookup->fetchColumn()) {
            $errors[] = 'Không tìm thấy bài Toplist ID ' . $toplistId . '.';
            continue;
        }

        $facilityItems = $item['facilities'] ?? $item['co_so'] ?? $item['co_so_y_te'] ?? [];
        if (!is_array($facilityItems) || $facilityItems === []) {
            $errors[] = 'Toplist ID ' . $toplistId . ' chưa có facilities hợp lệ.';
            continue;
        }

        $rankedFacilityIds = [];
        foreach ($facilityItems as $facilityIndex => $facility) {
            if (!is_array($facility)) continue;

            $facilityId = (int) ($facility['facility_id'] ?? $facility['id'] ?? 0);
            if ($facilityId > 0) {
                $facilityLookupById->execute([':id' => $facilityId]);
                if (!$facilityLookupById->fetchColumn()) {
                    $errors[] = 'Toplist ID ' . $toplistId . ': không tìm thấy cơ sở ID ' . $facilityId . '.';
                    continue;
                }
            } else {
                $name = trim((string) ($facility['name'] ?? $facility['ten'] ?? ''));
                $address = trim((string) ($facility['address'] ?? $facility['address_text'] ?? $facility['dia_chi'] ?? ''));
                $city = trim((string) ($facility['city'] ?? $facility['province'] ?? $facility['tinh_thanh'] ?? ''));
                $website = trim((string) ($facility['website'] ?? $facility['website_url'] ?? ''));
                if ($name === '') {
                    $errors[] = 'Toplist ID ' . $toplistId . ': một cơ sở thiếu name.';
                    continue;
                }

                $facilityId = 0;
                // Same name + city is treated as the same facility to avoid duplicate records
                // from AI payloads that omit or format the address differently.
                if ($city !== '') {
                    $facilityLookupByNameCity->execute([':name' => $name, ':city' => $city]);
                    $facilityId = (int) $facilityLookupByNameCity->fetchColumn();
                }
                if ($facilityId <= 0 && $address !== '') {
                    $facilityLookupByNameAddress->execute([':name' => $name, ':address' => $address]);
                    $facilityId = (int) $facilityLookupByNameAddress->fetchColumn();
                } elseif ($facilityId <= 0 && $website !== '') {
                    $facilityLookupByNameWebsite->execute([':name' => $name, ':website' => $website]);
                    $facilityId = (int) $facilityLookupByNameWebsite->fetchColumn();
                }

                if ($facilityId <= 0) {
                    $facilityInsert->execute([
                        ':slug' => medical_toplist_api_unique_facility_slug($pdo, $name . '-' . $address),
                        ':name' => $name,
                        ':category' => trim((string) ($facility['category'] ?? $facility['group'] ?? $facility['nhom'] ?? 'Cơ sở y tế')) ?: 'Cơ sở y tế',
                        ':city' => $city,
                        ':address' => $address,
                        ':phone' => trim((string) ($facility['phone'] ?? $facility['phone_text'] ?? '')),
                        ':website' => $website,
                        ':image' => trim((string) ($facility['image_url'] ?? $facility['image'] ?? '')),
                        ':display_order' => (int) ($facility['rank_order'] ?? $facilityIndex + 1),
                    ]);
                    $facilityId = (int) $pdo->lastInsertId();
                    $createdFacilities[] = $facilityId;
                }
            }

            $rankedFacilityIds[] = [
                'id' => $facilityId,
                'rank' => max(1, (int) ($facility['rank_order'] ?? $facility['rank'] ?? $facilityIndex + 1)),
                'index' => $facilityIndex,
            ];
        }

        if ($rankedFacilityIds === []) {
            $errors[] = 'Toplist ID ' . $toplistId . ' không có cơ sở nào để cập nhật.';
            continue;
        }

        usort($rankedFacilityIds, static function (array $a, array $b): int {
            return $a['rank'] === $b['rank'] ? $a['index'] <=> $b['index'] : $a['rank'] <=> $b['rank'];
        });
        toplist_directory_sync_facilities($pdo, $toplistId, array_column($rankedFacilityIds, 'id'));
        $updatedToplists[] = $toplistId;
    }
    $pdo->commit();
    // `toplist_directory_sync_facilities()` runs before the transaction is
    // committed. Mark it stale again after commit to close the tiny race in
    // which a cache rebuild could otherwise read the old relationships.
    if ($updatedToplists !== [] || $createdFacilities !== []) {
        medical_search_cache_invalidate();
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_response([
        'ok' => false,
        'message' => 'Cập nhật danh sách Toplist thất bại: ' . $e->getMessage(),
        'updated_toplist_ids' => $updatedToplists,
        'created_facility_ids' => $createdFacilities,
        'errors' => $errors,
    ], 500);
}

json_response([
    'ok' => true,
    'updated_toplist_ids' => $updatedToplists,
    'updated_count' => count($updatedToplists),
    'created_facility_ids' => $createdFacilities,
    'created_facility_count' => count($createdFacilities),
    'errors' => $errors,
]);
