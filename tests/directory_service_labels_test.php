<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_facility_directory_view.php';
require_once dirname(__DIR__) . '/medical_doctor_directory_view.php';
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) throw new RuntimeException($message);
    $checks++;
};
foreach (['facility', 'doctor'] as $type) {
    $render = $type === 'doctor' ? 'doctor_directory_card' : 'facility_directory_card';
    // Explicit URL keeps the presentation test independent of DB bootstrap.
    $item = ['url' => '/profile/test', 'name' => 'Test', 'services' => ['Niềng răng thẩm mỹ']];
    foreach (['vi', 'en'] as $locale) {
        $html = $render($item, $locale);
        $assert(str_contains($html, 'data-extra-count="0"') && str_contains($html, 'aria-expanded="false"'), "$type medium-length label has a mobile disclosure");
        $assert(str_contains($html, $locale === 'en' ? 'Details' : 'Xem đủ'), "$type localized full-value control");
        $assert(str_contains($html, 'Niềng răng thẩm mỹ'), "$type original service value preserved");
    }
    $page = file_get_contents(dirname(__DIR__) . ($type === 'doctor' ? '/bac-si.php' : '/co-so-y-te.php'));
    $assert(str_contains($page, '/assets/css/core/directory-service-labels.css?v='), "$type loads shared cache-busted styling");
    $js = file_get_contents(dirname(__DIR__) . '/assets/js/' . ($type === 'doctor' ? 'doctor' : 'facility') . '-directory.js');
    $assert(str_contains($js, 'length > 14'), "$type SSR/AJAX disclosure threshold agrees");
}
$css = file_get_contents(dirname(__DIR__) . '/assets/css/core/directory-service-labels.css');
foreach (['min-height:44px', 'min-width:44px', 'text-overflow:ellipsis', 'white-space:normal', 'prefers-reduced-motion:reduce'] as $rule) {
    $assert(str_contains($css, $rule), 'Shared compact chips preserve ' . $rule);
}
echo "Directory service labels: {$checks} checks passed. No DB or network used.\n";
