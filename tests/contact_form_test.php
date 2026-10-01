<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/contact_form.php';

$checks = 0;
$check = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException($label);
    $checks++;
};
$valid = ['name' => 'Người dùng thử', 'email' => 'reader@example.com', 'phone' => '', 'topic' => 'provider', 'url' => 'https://medreview.vn/co-so-y-te/example', 'message' => "Góp ý nội dung.\nDòng thứ hai."];
$check(medical_contact_validate($valid)['errors'] === [], 'Valid VI input, optional phone');
$check(isset(medical_contact_validate([])['errors']['name']), 'Required name');
$check(isset(medical_contact_validate($valid + [])['values']['message']), 'Message retained');
foreach ([
    ['name', str_repeat('a', 101)], ['name', "Name\r\nInjected"],
    ['email', "reader@example.com\r\nBcc: victim@example.com"], ['email', 'not-an-email'],
    ['phone', '<script>'], ['topic', 'unknown'], ['url', 'javascript:alert(1)'],
    ['message', str_repeat('a', 4001)], ['message', "Text\0value"],
] as [$field, $value]) {
    $input = $valid;
    $input[$field] = $value;
    $check(isset(medical_contact_validate($input)['errors'][$field]), 'Reject invalid ' . $field);
}
$captured = [];
$transport = static function ($recipient, $subject, $body, $headers) use (&$captured): bool {
    $captured = compact('recipient', 'subject', 'body', 'headers');
    return true;
};
$check(medical_contact_send($valid, 'team@example.com', $transport), 'Mock delivery success');
$check($captured['headers']['Reply-To'] === 'reader@example.com', 'Validated reply address');
$check(str_contains(quoted_printable_decode($captured['body']), $valid['message']), 'Message encoded without loss');
$check(str_starts_with($captured['subject'], '=?UTF-8?B?'), 'UTF-8 subject');
$check(!medical_contact_send($valid, 'team@example.com', static fn (): bool => false), 'Transport failure remains failure');
$check(!medical_contact_send($valid, 'bad-recipient', $transport), 'Reject invalid recipient');
$check(!medical_contact_send($valid, "team@example.com\r\nBcc: victim@example.com", $transport), 'Reject recipient header injection');
$check(!medical_contact_send(['email' => 'reader@example.com'], 'team@example.com', $transport), 'Never send invalid form');
$check(!medical_contact_send($valid, 'team@example.com', static function (): bool { throw new RuntimeException('Unavailable'); }), 'Transport exception remains failure');
echo "Contact form: {$checks} checks passed (mock transport only).\n";
