<?php
declare(strict_types=1);
require_once __DIR__ . '/../../medical_review_article_ai.php';
medical_review_article_ai_bootstrap(['GET', 'POST']);
try {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        $pdo = medical_review_article_ai_database();
        $resolver = static function (PDO $pdo, array $source): array {
            return medical_directory_resolve_ai_prompt($pdo, 'review_article', (string) ($source['facility']['category'] ?? ''), (string) $source['language_code']);
        };
        json_response(medical_review_article_ai_queue($pdo, $_GET, $resolver));
    }
    if ($method === 'POST') {
        $body = medical_review_article_ai_read_body(65536);
        json_response(medical_review_article_ai_lease(medical_review_article_ai_database(), $body));
    }
    header('Allow: GET, POST, OPTIONS');
    throw new MedicalReviewArticleAiException('Chỉ hỗ trợ GET, POST và OPTIONS.', 405, 'method_not_allowed');
} catch (Throwable $error) { medical_review_article_ai_error($error); }
