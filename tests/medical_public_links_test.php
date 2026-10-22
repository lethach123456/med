<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_public_links.php';

$checks = 0;
$equal = static function (string $actual, string $expected, string $label) use (&$checks): void {
    ++$checks;
    if ($actual !== $expected) throw new RuntimeException($label . "\nExpected: " . $expected . "\nActual: " . $actual);
};
$old = '/co-so-y-te-chi-tiet.php?slug=';
$cases = [
    [$old . 'nha-khoa-my-smile-da-nang-da-nang-f2f306c9db783a8c', '/co-so-y-te/nha-khoa-my-smile-da-nang-da-nang-f2f306c9db783a8c'],
    ['co-so-y-te-chi-tiet.php?slug=phong-kham', '/co-so-y-te/phong-kham'],
    [$old . rawurlencode('nha-khoa-Đà-Nẵng'), '/co-so-y-te/' . rawurlencode('nha-khoa-Đà-Nẵng')],
    [$old . 'nha%20khoa', '/co-so-y-te/nha%20khoa'],
    [$old . 'nha%2Bkhoa', '/co-so-y-te/nha%2Bkhoa'],
    [$old . 'clinic&ref=top%20list&rank=2#gioi-thieu', '/co-so-y-te/clinic?ref=top%20list&rank=2#gioi-thieu'],
    ['/en/co-so-y-te-chi-tiet.php?slug=clinic', '/en/co-so-y-te/clinic'],
    [$old . 'clinic&lang=en&ref=x', '/en/co-so-y-te/clinic?ref=x'],
    ['/en/co-so-y-te-chi-tiet.php?lang=vi&slug=clinic', '/co-so-y-te/clinic'],
    [$old . 'clinic&lang=fr', '/co-so-y-te/clinic?lang=fr'],
    ['https://medreview.vn' . $old . 'clinic', 'https://medreview.vn/co-so-y-te/clinic'],
    ['http://www.medreview.vn' . $old . 'clinic', 'https://medreview.vn/co-so-y-te/clinic'],
    ['https://MEDREVIEW.VN:443' . $old . 'clinic', 'https://medreview.vn/co-so-y-te/clinic'],
    [$old . 'clinic&filter%5Bcity%5D=danang', '/co-so-y-te/clinic?filter%5Bcity%5D=danang'],
    [$old . 'clinic&foo=a%26b#anh', '/co-so-y-te/clinic?foo=a%26b#anh'],
    ['/co-so-y-te-chi-tiet.php?sl%75g=clinic&lang=%65n', '/en/co-so-y-te/clinic'],
];
foreach ($cases as [$url, $expected]) {
    $equal(medical_canonical_facility_url($url), $expected, 'Canonical URL: ' . $url);
    $equal(medical_canonical_facility_url($expected), $expected, 'Canonical URL is idempotent');
}
$unchanged = [
    '', '#gioi-thieu', '/co-so-y-te/clinic', '/bac-si-chi-tiet.php?slug=doctor',
    '/other/co-so-y-te-chi-tiet.php?slug=clinic', 'en/co-so-y-te-chi-tiet.php?slug=clinic',
    $old, $old . '  ', $old . '%00', $old . '%0Aabc', $old . '%FF',
    $old . '..', $old . '.', $old . '..%2Fprivate', $old . 'clinic%5Cprivate',
    $old . 'a%3Fb', $old . 'a%23b', $old . 'a&slug=b',
    '/co-so-y-te-chi-tiet.php?slug%5B%5D=clinic',
    '/co-so-y-te-chi-tiet.php?slug=clinic&lang%5B%5D=en',
    '/co-so-y-te-chi-tiet.php?slug=clinic&lang=en&lang=vi',
    '/co-so-y-te-chi-tiet.php?slug=clinic&x=%0D%0AHeader',
    'https://medreview.vn.evil.test' . $old . 'clinic',
    'https://notmedreview.vn' . $old . 'clinic',
    'https://medreview.vn@evil.test' . $old . 'clinic',
    'https://user@medreview.vn' . $old . 'clinic',
    'https://medreview.vn:8443' . $old . 'clinic',
    '//medreview.vn' . $old . 'clinic',
    'javascript:co-so-y-te-chi-tiet.php?slug=clinic',
    'data:text/html,co-so-y-te-chi-tiet.php?slug=clinic',
    'https:\\medreview.vn' . $old . 'clinic',
    'https://medreview.vn:wrong' . $old . 'clinic',
    ' /co-so-y-te-chi-tiet.php?slug=clinic',
    '/co-so-y-te-chi-tiet.php?+slug=clinic',
    $old . 'clinic&+slug=other',
    $old . '%ZZ', $old . '%C2%A0', $old . 'clinic#%00',
];
foreach ($unchanged as $url) $equal(medical_canonical_facility_url($url), $url, 'Unsafe or unrelated URL stays untouched');
$equal(medical_canonical_facility_url('http://127.0.0.1:8799' . $old . 'clinic', 'http://127.0.0.1:8799'), 'http://127.0.0.1:8799/co-so-y-te/clinic', 'Configured local origin allowed');
$equal(medical_canonical_facility_url('http://127.0.0.1:8800' . $old . 'clinic', 'http://127.0.0.1:8799'), 'http://127.0.0.1:8800' . $old . 'clinic', 'Other local ports untouched');
$equal(medical_canonical_facility_url('https://www.medreview.vn' . $old . 'clinic', 'http://127.0.0.1:8799'), 'https://medreview.vn/co-so-y-te/clinic', 'Production link never becomes local');
$equal(medical_canonical_facility_url('https://staging.test' . $old . 'clinic', 'https://staging.test'), 'https://staging.test/co-so-y-te/clinic', 'Configured staging origin allowed');
$equal(medical_canonical_facility_url('https://staging.test' . $old . 'clinic', 'https://staging.test/path'), 'https://staging.test' . $old . 'clinic', 'Origin with a path is not trusted');

$htmlCases = [
    ['<a href="' . $old . 'clinic">Nha khoa</a>', '<a href="/co-so-y-te/clinic">Nha khoa</a>'],
    ["<A  class='clinic' HREF = '" . $old . "clinic&lang=en&amp;ref=top' title='A > B'>X</A>", "<A  class='clinic' HREF = '/en/co-so-y-te/clinic?ref=top' title='A > B'>X</A>"],
    ['<a href="' . $old . 'clinic&amp;ref=top&amp;rank=2#anh">X</a>', '<a href="/co-so-y-te/clinic?ref=top&amp;rank=2#anh">X</a>'],
    ['<a href=' . $old . 'clinic class=x>X</a>', '<a href="/co-so-y-te/clinic" class=x>X</a>'],
    ['<a href="' . $old . 'clinic" data-href="' . $old . 'other" style="color:red">X</a>', '<a href="/co-so-y-te/clinic" data-href="' . $old . 'other" style="color:red">X</a>'],
    ['<a data-href="' . $old . 'clinic" src="' . $old . 'image">X</a>', '<a data-href="' . $old . 'clinic" src="' . $old . 'image">X</a>'],
    ['<img src="' . $old . 'clinic"><div href="' . $old . 'clinic">X</div>', '<img src="' . $old . 'clinic"><div href="' . $old . 'clinic">X</div>'],
    ['<a href="' . $old . 'clinic" href="' . $old . 'second">X</a>', '<a href="' . $old . 'clinic" href="' . $old . 'second">X</a>'],
    ['<a href href="' . $old . 'clinic">X</a>', '<a href href="' . $old . 'clinic">X</a>'],
    ['<a href="https://external.test' . $old . 'clinic">X</a>', '<a href="https://external.test' . $old . 'clinic">X</a>'],
    ['<!-- <a href="' . $old . 'clinic">X</a> --><a href="' . $old . 'real">Y</a>', '<!-- <a href="' . $old . 'clinic">X</a> --><a href="/co-so-y-te/real">Y</a>'],
    ['Text ' . $old . 'clinic &lt;a href="' . $old . 'clinic"&gt;', 'Text ' . $old . 'clinic &lt;a href="' . $old . 'clinic"&gt;'],
    ['<![CDATA[<a href="' . $old . 'clinic">X</a>]]>', '<![CDATA[<a href="' . $old . 'clinic">X</a>]]>'],
    ['<?php echo \'<a href="' . $old . 'clinic">X</a>\'; ?>', '<?php echo \'<a href="' . $old . 'clinic">X</a>\'; ?>'],
    ['<a title="Keep <a href=\'' . $old . 'fake\'>" href="' . $old . 'real">Y</a>', '<a title="Keep <a href=\'' . $old . 'fake\'>" href="/co-so-y-te/real">Y</a>'],
    ['<a href="' . $old . 'clinic%22%20onclick%3D%22evil">X</a>', '<a href="/co-so-y-te/clinic%22%20onclick%3D%22evil">X</a>'],
];
foreach (['script', 'style', 'textarea', 'title', 'code', 'pre', 'xmp', 'iframe', 'noembed', 'noframes'] as $rawTag) {
    $literal = '<' . $rawTag . ' data-x="a > b"><a href="' . $old . 'literal">X</a></' . $rawTag . '>';
    $htmlCases[] = [$literal . '<a href="' . $old . 'real">Y</a>', $literal . '<a href="/co-so-y-te/real">Y</a>'];
}
foreach ($htmlCases as [$html, $expected]) {
    $equal(medical_normalize_facility_links_html($html), $expected, 'HTML changes only the intended anchor href');
    $equal(medical_normalize_facility_links_html($expected), $expected, 'HTML normalization is idempotent');
}
foreach (['<!-- <a href="' . $old . 'x">', '<script><a href="' . $old . 'x">', '<a href="' . $old . 'x', '<plaintext><a href="' . $old . 'x">'] as $html) {
    $equal(medical_normalize_facility_links_html($html), $html, 'Unterminated/literal markup preserved');
}
echo "Medical public links: {$checks} checks passed. No DB or network access.\n";
