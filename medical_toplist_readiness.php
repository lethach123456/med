<?php
declare(strict_types=1);
require_once __DIR__ . '/medical_search_cache.php';

/** Inspect every raw relation, not the filtered public view of the ranking. */
function medical_toplist_readiness(array $article, array $members): array
{
    $reasons = [];
    $type = $article['entity_type'] ?? '';
    $locale = $article['language_code'] ?? '';
    if (!in_array($type, ['facility', 'doctor', 'mixed'], true)) $reasons[] = 'invalid_type';
    if (!in_array($locale, ['vi', 'en'], true)) $reasons[] = 'invalid_language';
    if (trim((string) ($article['title'] ?? '')) === '' || trim((string) ($article['slug'] ?? '')) === '') $reasons[] = 'missing_identity';
    if ($members === []) $reasons[] = 'empty_list';
    $ready = 0;
    $blocked = [];
    $seen = [];
    foreach ($members as $member) {
        $why = [];
        $memberType = $member['member_type'] ?? '';
        if (!in_array($memberType, ['facility', 'doctor'], true) || ($type !== 'mixed' && $memberType !== $type)) $why[] = 'wrong_type';
        $key = $memberType . ':' . (int) ($member['member_id'] ?? 0);
        if (isset($seen[$key])) $why[] = 'duplicate_member';
        $seen[$key] = true;
        if (empty($member['id'])) $why[] = 'missing_profile';
        else {
            if (($member['status'] ?? '') !== 'published') $why[] = 'profile_not_published';
            if (($member['language_code'] ?? '') !== $locale) $why[] = 'wrong_language';
            if (!medical_search_cache_has_written_content((string) ($member['content'] ?? ''))) $why[] = 'missing_content';
        }
        if ($why === []) $ready++;
        else $blocked[] = ['member' => $key, 'reasons' => $why];
    }
    // A partial Top 5 with only four linked profiles must not pass the all-ready check.
    if (preg_match('/\btop\s*(\d{1,3})\b|^\s*(\d{1,3})\s+/iu', (string) ($article['title'] ?? ''), $match)) {
        $expected = (int) ($match[1] !== '' ? $match[1] : ($match[2] ?? 0));
        if ($expected > count($members)) $reasons[] = 'fewer_members_than_title';
    }
    return ['ready' => $reasons === [] && $blocked === [], 'total' => count($members),
        'ready_count' => $ready, 'reasons' => $reasons, 'blocked' => $blocked];
}
