<?php
declare(strict_types=1);

/** Shared validation and delivery for the VI / EN contact forms. */
function medical_contact_validate(array $input): array
{
    $values = [];
    foreach (['name', 'email', 'phone', 'topic', 'url', 'message'] as $key) {
        $values[$key] = isset($input[$key]) && is_string($input[$key]) ? trim($input[$key]) : '';
    }
    $values['topic'] = $values['topic'] !== '' ? $values['topic'] : 'feedback';
    $errors = [];
    if ($values['name'] === '' || mb_strlen($values['name']) > 100 || preg_match('/[\x00-\x1F\x7F]/', $values['name'])) $errors['name'] = 'name';
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 254) $errors['email'] = 'email';
    if ($values['phone'] !== '' && (strlen($values['phone']) > 30 || !preg_match('/^[+0-9() .-]{6,30}$/', $values['phone']))) $errors['phone'] = 'phone';
    if (!in_array($values['topic'], ['feedback', 'provider', 'partnership'], true)) $errors['topic'] = 'topic';
    if ($values['url'] !== '' && (strlen($values['url']) > 1000 || !filter_var($values['url'], FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($values['url'], PHP_URL_SCHEME)), ['http', 'https'], true))) $errors['url'] = 'url';
    if ($values['message'] === '' || mb_strlen($values['message']) > 4000 || str_contains($values['message'], "\0")) $errors['message'] = 'message';
    return ['values' => $values, 'errors' => $errors];
}

/** A true result means the mail transport accepted the message, not inbox delivery. */
function medical_contact_send(array $values, string $recipient, ?callable $transport = null): bool
{
    $validated = medical_contact_validate($values);
    $from = trim((string) (getenv('CONTACT_MAIL_FROM') ?: $recipient));
    if ($validated['errors'] || !filter_var($recipient, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) return false;
    $values = $validated['values'];
    $topics = ['feedback' => 'Góp ý & hỗ trợ', 'provider' => 'Cập nhật hồ sơ cơ sở y tế', 'partnership' => 'Hợp tác & kết nối'];
    $subject = '=?UTF-8?B?' . base64_encode('[MedReview] ' . $topics[$values['topic']]) . '?=';
    $body = implode("\r\n", [
        'Lời nhắn từ trang liên hệ MedReview', '',
        'Chủ đề: ' . $topics[$values['topic']], 'Họ tên: ' . $values['name'],
        'Email: ' . $values['email'], 'Điện thoại: ' . ($values['phone'] ?: 'Không cung cấp'),
        'Đường dẫn tham khảo: ' . ($values['url'] ?: 'Không cung cấp'), '',
        'Nội dung:', str_replace(["\r\n", "\r"], "\n", $values['message']),
    ]);
    $body = quoted_printable_encode(str_replace(["\r\n", "\r"], "\n", $body));
    $headers = ['From' => 'MedReview <' . $from . '>', 'Reply-To' => $values['email'], 'MIME-Version' => '1.0', 'Content-Type' => 'text/plain; charset=UTF-8', 'Content-Transfer-Encoding' => 'quoted-printable'];
    try {
        if ($transport !== null) return (bool) $transport($recipient, $subject, $body, $headers);
        return function_exists('mail') && @mail($recipient, $subject, $body, $headers);
    } catch (Throwable $error) {
        return false;
    }
}
