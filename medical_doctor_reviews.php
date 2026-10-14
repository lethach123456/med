<?php
declare(strict_types=1);

/** Imported public feedback belongs to the doctor, not medical_reviews facilities. */
function medical_doctor_review_text(mixed $value, string $path, int $max, bool $required = false): ?string
{
    if ($value === null || $value === '') {
        if ($required) throw new InvalidArgumentException("{$path} cần chuỗi có nội dung từ nguồn công khai.");
        return null;
    }
    if (!is_string($value) || mb_strlen($value, 'UTF-8') > $max || str_contains($value, "\0")) {
        throw new InvalidArgumentException("{$path} phải là chuỗi tối đa {$max} ký tự.");
    }
    $text = trim(strip_tags($value));
    if ($required && $text === '') throw new InvalidArgumentException("{$path} cần nội dung thực, không phải HTML rỗng.");
    return $text === '' ? null : $text;
}

/** Pure validation; source URLs are evidence, never automatically fetched. */
function medical_doctor_normalize_review_data(array &$fields, array $item, ?array $reviewIdentity = null): void
{
    // An EN editor can validate copied evidence against its DB-linked VI
    // source identity. The API never accepts an identity override from JSON.
    $identity = $reviewIdentity ?? $item;
    foreach (['aggregate_ratings_json', 'reviews_json'] as $field) {
        if (!array_key_exists($field, $fields)) continue;
        $rows = medical_doctor_decode_array($fields[$field], $field);
        $limit = $field === 'aggregate_ratings_json' ? 20 : 50;
        if (count($rows) > $limit) throw new InvalidArgumentException("{$field} tối đa {$limit} mục.");
        if ($rows !== [] && ($item['insufficient_data'] ?? false) !== false) {
            throw new InvalidArgumentException('Chưa xác định đủ hồ sơ bác sĩ thì không nhập điểm/đánh giá.');
        }
        $normalized = []; $seen = [];
        foreach ($rows as $index => $row) {
            $path = "{$field}[{$index}]";
            if (!is_array($row) || array_is_list($row)) throw new InvalidArgumentException("{$path} cần object đánh giá.");
            if (($row['entity_type'] ?? '') !== 'doctor' || !is_int($row['doctor_id'] ?? null)
                || $row['doctor_id'] <= 0 || $row['doctor_id'] !== ($identity['id'] ?? null)) {
                throw new InvalidArgumentException("{$path} cần entity_type=doctor và doctor_id đúng ID bác sĩ; không nhập điểm cơ sở.");
            }
            $name = medical_doctor_review_text($row['doctor_name'] ?? $identity['name'] ?? null, "{$path}.doctor_name", 190, true);
            if (!is_string($identity['name'] ?? null) || $name !== $identity['name']) {
                throw new InvalidArgumentException("{$path}.doctor_name phải giữ nguyên tên bác sĩ từ API.");
            }
            $url = medical_doctor_http_url($row['url'] ?? $row['source_url'] ?? null, "{$path}.url");
            if ($url === null) throw new InvalidArgumentException("{$path}.url cần URL nguồn đã đọc.");
            $source = medical_doctor_review_text($row['source'] ?? $row['source_text'] ?? null, "{$path}.source", 190, true);
            $evidence = medical_doctor_review_text($row['evidence_text'] ?? null, "{$path}.evidence_text", 2000, true);
            $entry = ['entity_type' => 'doctor', 'doctor_id' => $row['doctor_id'], 'doctor_name' => $name,
                'url' => $url, 'evidence_text' => $evidence];
            if ($field === 'aggregate_ratings_json') {
                $score = $row['score'] ?? null; $count = $row['count'] ?? null;
                if ((!is_int($score) && !is_float($score)) || !is_finite((float) $score) || $score < 1 || $score > 5
                    || !is_int($count) || $count < 1 || $count > 1000000000) {
                    throw new InvalidArgumentException("{$path} cần score là số từ 1 đến 5 và count nguyên dương tối đa 1.000.000.000.");
                }
                $entry += ['source' => $source, 'score' => $score, 'count' => $count,
                    'checked_at' => medical_doctor_legal_date($row['checked_at'] ?? null, "{$path}.checked_at")];
            } else {
                $score = $row['rating'] ?? null;
                if ((!is_int($score) && !is_float($score)) || !is_finite((float) $score) || $score < 1 || $score > 5) {
                    throw new InvalidArgumentException("{$path}.rating cần số từ 1 đến 5, không suy đoán điểm từ lời nhận xét.");
                }
                $entry += ['source_text' => $source, 'rating' => $score,
                    'author_text' => medical_doctor_review_text($row['author_text'] ?? $row['author'] ?? null, "{$path}.author_text", 190),
                    'excerpt' => medical_doctor_review_text($row['excerpt'] ?? $row['content'] ?? null, "{$path}.excerpt", 4000, true),
                    'review_date_text' => medical_doctor_review_text($row['review_date_text'] ?? $row['date'] ?? null, "{$path}.review_date_text", 120),
                    // Source evidence does not equal an independently verified MedReview patient.
                    'verified' => false];
            }
            $key = rtrim(preg_replace('/#.*$/', '', $url) ?? $url, '/');
            if ($field === 'reviews_json') {
                // A source page may contain several distinct genuine reviews;
                // repeated samples must not inflate the fallback count.
                $key = 'review:' . hash('sha256', medical_directory_json_encode([
                    $entry['author_text'], $entry['excerpt'], $entry['review_date_text']]));
            }
            if (isset($seen[$key])) {
                $previous = $normalized[$seen[$key]];
                $conflict = $field === 'reviews_json' ? $previous['rating'] !== $entry['rating'] : $previous !== $entry;
                if ($conflict) throw new InvalidArgumentException("{$path}.url trùng nguồn/nhận xét nhưng dữ liệu mâu thuẫn.");
                continue;
            }
            $seen[$key] = count($normalized);
            $normalized[] = $entry;
        }
        $fields[$field] = medical_directory_json_encode($normalized);
    }
}

/** Mirrors facility weighting, without adding sampled reviews to aggregate counts. */
function medical_doctor_review_rating_summary(array $fields): ?array
{
    $weighted = 0.0; $count = 0;
    foreach (medical_doctor_decode_array($fields['aggregate_ratings_json'] ?? null, 'aggregate_ratings_json') as $row) {
        $weighted += $row['score'] * $row['count'];
        $count += $row['count'];
    }
    if ($count > 0) {
        if ($count > 2147483647) throw new InvalidArgumentException('Tổng lượt đánh giá vượt giới hạn lưu trữ.');
        return ['rating' => round($weighted / $count, 1), 'reviews_count' => $count, 'basis' => 'external_aggregates'];
    }
    $reviews = medical_doctor_decode_array($fields['reviews_json'] ?? null, 'reviews_json');
    if ($reviews === []) return null;
    return ['rating' => round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1),
        'reviews_count' => count($reviews), 'basis' => 'imported_review_sample'];
}
