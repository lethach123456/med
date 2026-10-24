<?php
declare(strict_types=1);

function medical_review_seo_required_columns(): array
{
    return ['status', 'slug', 'title', 'rating', 'author_text', 'method_text', 'condition_text', 'excerpt', 'story_json'];
}

/** Shared standalone-review eligibility for sitemap, robots and related links. */
function medical_review_seo_indexable_where(string $prefix = ''): string
{
    if ($prefix !== '' && !preg_match('/^[A-Za-z_][A-Za-z0-9_]*\.?$/D', $prefix)) {
        throw new InvalidArgumentException('Invalid review table prefix.');
    }
    $prefix = $prefix === '' ? '' : rtrim($prefix, '.') . '.';
    $text = static fn(string $column): string => "TRIM(COALESCE({$prefix}{$column},''))";
    return "{$prefix}status='published' AND " . $text('slug') . "<>'' AND " . $text('title') . "<>''"
        . " AND COALESCE({$prefix}rating,0)>0 AND COALESCE({$prefix}rating,0)<=5"
        . " AND " . $text('author_text') . "<>'Ban biên tập MedReview'"
        . " AND " . $text('method_text') . "<>'Thông tin tổng hợp'"
        . " AND " . $text('condition_text') . "<>'Nội dung biên tập, không phải đánh giá khách hàng'"
        // LENGTH intentionally keeps the existing sitemap's UTF-8 byte thresholds.
        . " AND (LENGTH(" . $text('excerpt') . ")>=80 OR LENGTH(" . $text('story_json') . ")>=120)";
}

/** Accept a raw medical_reviews row; missing data fails closed for indexing. */
function medical_review_seo_indexable(array $row): bool
{
    if (array_diff(medical_review_seo_required_columns(), array_keys($row)) !== []) return false;
    // Match SQL TRIM's removal of ordinary spaces, including UTF-8 byte lengths.
    $text = static fn(string $column): string => is_scalar($row[$column] ?? null) ? trim((string) $row[$column], ' ') : '';
    $rating = is_numeric($row['rating'] ?? null) ? (float) $row['rating'] : 0.0;
    return ($row['status'] ?? '') === 'published' && $text('slug') !== '' && $text('title') !== ''
        && is_finite($rating) && $rating > 0 && $rating <= 5
        && $text('author_text') !== 'Ban biên tập MedReview'
        && $text('method_text') !== 'Thông tin tổng hợp'
        && $text('condition_text') !== 'Nội dung biên tập, không phải đánh giá khách hàng'
        && (strlen($text('excerpt')) >= 80 || strlen($text('story_json')) >= 120);
}
