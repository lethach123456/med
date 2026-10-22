<?php
declare(strict_types=1);
require_once __DIR__ . '/medical_review_articles.php';

/** Optimistic editor version also changes when an AI acquires the article. */
function medical_review_article_admin_version(array $row): string
{
    $fields = array_merge(MEDICAL_REVIEW_ARTICLE_CONTENT_FIELDS, ['id', 'status', 'updated_at', 'ai_writer_claim_json']);
    return hash('sha256', medical_review_articles_json_encode(array_intersect_key($row, array_flip($fields))));
}

/** Auth/CSRF belong to the admin controller; only article content can change here. */
function medical_review_article_admin_save(PDO $pdo, int $id, array $body, string $version): array
{
    if ($id < 1 || !preg_match('/^[a-f0-9]{64}$/D', $version)) throw new InvalidArgumentException('Phiên chỉnh sửa không hợp lệ. Tải lại bài trước khi lưu.');
    $status = $body['status'] ?? 'draft';
    if (!is_string($status) || !in_array($status, ['draft', 'published', 'archived'], true)) throw new InvalidArgumentException('Trạng thái bài review không hợp lệ.');
    unset($body['status']);
    $content = medical_review_articles_validate_article($body);
    medical_review_articles_require_schema($pdo);
    if ($pdo->inTransaction()) throw new RuntimeException('Không thể bắt đầu giao dịch lưu bài.');
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('SELECT * FROM medical_review_articles WHERE id=:id LIMIT 1' . ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''));
        $statement->execute([':id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new InvalidArgumentException('Không tìm thấy bài review.');
        if (!hash_equals(medical_review_article_admin_version($row), $version)) throw new RuntimeException('Bài đã thay đổi hoặc được AI nhận xử lý. Tải lại để đối chiếu trước khi lưu.');
        $claim = json_decode((string) ($row['ai_writer_claim_json'] ?? ''), true);
        if (is_array($claim) && (int) ($claim['expires_at'] ?? 0) > time()) throw new RuntimeException('AI đang viết bài này. Hoàn tất hoặc trả quyền giữ bài trước khi chỉnh sửa.');
        $values = [];
        foreach ($content as $field => $value) $values[$field] = str_ends_with($field, '_json') ? medical_review_articles_json_encode($value) : $value;
        $values += ['status' => $status, 'ai_content_json' => null, 'ai_writer_claim_json' => null,
            'published_at' => $status === 'published' ? ($row['published_at'] ?: gmdate('Y-m-d H:i:s')) : $row['published_at']];
        $set = []; $params = [':id' => $id];
        foreach ($values as $field => $value) { $set[] = $field . '=:' . $field; $params[':' . $field] = $value; }
        $pdo->prepare('UPDATE medical_review_articles SET ' . implode(',', $set) . ',updated_at=CURRENT_TIMESTAMP WHERE id=:id')->execute($params);
        $statement->execute([':id' => $id]); $saved = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($saved)) throw new RuntimeException('Không đọc lại được bài review sau khi lưu.');
        foreach ($values as $field => $value) if (($saved[$field] ?? null) !== $value) throw new RuntimeException('Chưa xác nhận lưu đúng nội dung bài review.');
        $pdo->commit();
        return ['id' => $id, 'status' => $status, 'saved' => true, 'storage_verified' => true];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
