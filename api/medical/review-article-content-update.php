<?php
declare(strict_types=1);
require_once __DIR__ . '/../../medical_review_article_ai.php';
medical_review_article_ai_bootstrap(['POST']);
try {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
        header('Allow: POST, OPTIONS');
        throw new MedicalReviewArticleAiException('Chỉ hỗ trợ POST và OPTIONS.', 405, 'method_not_allowed');
    }
    $body = medical_review_article_ai_read_body();
    json_response(medical_review_article_ai_save(medical_review_article_ai_database(), $body));
} catch (Throwable $error) { medical_review_article_ai_error($error); }
