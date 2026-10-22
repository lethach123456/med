<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/medical_review_article_price_source.php';

$assertions = 0;
$check = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$parse = static function (string $html): DOMDocument {
    $document = new DOMDocument('1.0', 'UTF-8');
    $old = libxml_use_internal_errors(true);
    try { $document->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_NONET); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($old); }
    return $document;
};
foreach ([null, false, true, 123, [], new stdClass(), '', " \n\t", "\xC3\x28", "<p>bad\0text</p>", str_repeat('a', 250001)] as $bad) {
    $check(medical_review_article_price_source($bad) === '', 'Malformed, non-string or oversized source is omitted.');
}
if (!class_exists('DOMDocument')) {
    $check(medical_review_article_price_source('<table><tr><td>Khám</td><td>100.000 đ</td></tr></table>') === '', 'Missing DOM fails closed without flattening prices.');
    echo 'review_article_price_source_test: ' . $assertions . " assertions passed (DOM unavailable).\n";
    exit;
}

$raw = '<div class="price"><p>Bảng giá <strong>tham khảo</strong> &amp; đơn vị tính.</p><table id="prices" class="old">'
    . '<caption>Dịch vụ &amp; chi phí</caption><thead><tr><th>Dịch vụ</th><th>Giá (VNĐ)</th></tr></thead><tbody>'
    . '<tr><td>Khám tổng quát</td><td>100.000 – 200.000 đ / lượt</td></tr>'
    . '<tr><td>Niềng răng</td><td>20.000.000–35.000.000 ₫ / 2 hàm</td></tr>'
    . '</tbody><tfoot><tr><td colspan="2">* Chưa gồm phim chụp; xác nhận trực tiếp.</td></tr></tfoot></table>'
    . '<p><em>Lưu ý:</em> Mức phí phụ thuộc tình trạng thực tế.<br>Áp dụng theo thông báo của cơ sở.</p></div>';
$safe = medical_review_article_price_source($raw);
$document = $parse($safe);
$check(str_contains($safe, '<table>') && str_contains($safe, '<thead>') && str_contains($safe, '<tbody>') && str_contains($safe, '<tfoot>'), 'Table sections and complete row structure are retained.');
$check($document->getElementsByTagName('tr')->length === 4, 'Header, price rows and footnote row remain separate.');
$rows = $document->getElementsByTagName('tr');
$check($rows->item(1)->getElementsByTagName('td')->item(0)->textContent === 'Khám tổng quát', 'Service stays in its original row/cell.');
$check($rows->item(1)->getElementsByTagName('td')->item(1)->textContent === '100.000 – 200.000 đ / lượt', 'Price range and per-visit unit remain paired with service.');
$check($rows->item(2)->getElementsByTagName('td')->item(1)->textContent === '20.000.000–35.000.000 ₫ / 2 hàm', 'Different service amount/range/unit is not merged.');
$check(str_contains($document->textContent, 'Dịch vụ & chi phí') && str_contains($safe, '&amp;') && !str_contains($safe, '&amp;amp;'), 'Entities are decoded and safely serialized once.');
$check(str_contains($safe, '* Chưa gồm phim chụp; xác nhận trực tiếp.') && str_contains($safe, 'Mức phí phụ thuộc tình trạng thực tế.'), 'Source footnotes and qualification notes are preserved.');
$check(str_contains($safe, '<strong>tham khảo</strong>') && str_contains($safe, '<em>Lưu ý:</em>') && str_contains($safe, '<br>'), 'Minimal notes formatting is retained.');
$check(!str_contains($safe, 'class=') && !str_contains($safe, 'id='), 'Non-semantic source attributes are removed.');

$spans = medical_review_article_price_source('<table><thead><tr><th colspan="3" style="color:red">Implant</th></tr></thead><tbody>'
    . '<tr><td rowspan="2">Cấy ghép</td><td>Loại A</td><td>10.000.000 ₫ / răng</td></tr>'
    . '<tr><td>Loại B</td><td>15.000.000 ₫ / răng</td></tr></tbody></table>');
$check(str_contains($spans, 'colspan="3"') && str_contains($spans, 'rowspan="2"') && !str_contains($spans, 'style='), 'Bounded row/column spans survive with no style attributes.');
$spanDocument = $parse($spans);
$check($spanDocument->getElementsByTagName('tr')->item(2)->getElementsByTagName('td')->length === 2, 'Rowspan continuation does not invent or move a service cell.');
foreach (['0','101','1000','-1','2.5','2 onmouseover=x'] as $badSpan) {
    $badTable = '<p>Ghi chú nguồn.</p><table><tr><td colspan="' . htmlspecialchars($badSpan, ENT_QUOTES) . '">Dịch vụ</td><td>500.000 ₫</td></tr></table>';
    $result = medical_review_article_price_source($badTable);
    $check(!str_contains($result, '<table') && str_contains($result, 'Ghi chú nguồn.'), 'Unsupported spans omit the table instead of silently changing its relationships.');
}

$unsafe = '<style>STYLE_SECRET { background:url(http://127.0.0.1/private); }</style><script>SCRIPT_SECRET</script>'
    . '<template>TEMPLATE_SECRET</template><noscript>NOSCRIPT_SECRET</noscript><svg><text>SVG_SECRET</text></svg>'
    . '<iframe src="http://localhost/private">IFRAME_SECRET</iframe><object data="http://localhost/private">OBJECT_SECRET</object>'
    . '<form action="https://private.example"><p>FORM_SECRET</p><input value="TOKEN_SECRET"></form>'
    . '<table onclick="bad()" data-token="PRIVATE_ATTR"><tr><td onmouseover="bad()">Khám '
    . '<img src="http://127.0.0.1/private" onerror="bad()"><a href="http://localhost/private" target="_blank">cơ bản</a></td>'
    . '<td style="color:red">300.000 ₫ / lượt</td></tr></table><p hidden>HIDDEN_SECRET</p>'
    . '<p aria-hidden="true">ARIA_SECRET</p><p style="display: none !important;">CSS_SECRET</p><!-- COMMENT_SECRET -->';
$clean = medical_review_article_price_source($unsafe);
foreach (['STYLE_SECRET','SCRIPT_SECRET','TEMPLATE_SECRET','NOSCRIPT_SECRET','SVG_SECRET','IFRAME_SECRET','OBJECT_SECRET',
    'FORM_SECRET','TOKEN_SECRET','PRIVATE_ATTR','HIDDEN_SECRET','ARIA_SECRET','CSS_SECRET','COMMENT_SECRET','127.0.0.1','localhost','onerror','onclick','onmouseover','href=','src=','style=','data-token'] as $secret) {
    $check(!str_contains($clean, $secret), 'Active/hidden content or private URL attribute is absent: ' . $secret);
}
$check(str_contains($clean, 'Khám cơ bản') && str_contains($clean, '300.000 ₫ / lượt'), 'Visible safe service and price survive unsafe neighboring content.');
$hiddenCells = $parse(medical_review_article_price_source('<table><tr><td>Khám</td><td hidden>PRIVATE_CELL</td><td>400.000 ₫</td></tr>'
    . '<tr hidden><td rowspan="2">PRIVATE_SERVICE</td><td>PRIVATE_FEE</td><td>PRIVATE_NOTE</td></tr>'
    . '<tr><td>Ghi chú</td><td>Đơn vị</td></tr></table>'));
$check($hiddenCells->getElementsByTagName('tr')->item(0)->getElementsByTagName('td')->length === 3, 'Hidden cell keeps an empty structural position rather than shifting the price.');
$check(!str_contains($hiddenCells->textContent, 'PRIVATE_') && str_contains($hiddenCells->saveHTML(), 'rowspan="2"'), 'Hidden rows suppress data while retaining span geometry.');

$manyRows = '<p>Bảng giá tham khảo, không phải báo giá cuối cùng.</p><table><thead><tr><th>Dịch vụ</th><th>Giá</th></tr></thead><tbody>';
for ($index = 1; $index <= 700; $index++) $manyRows .= '<tr><td>Dịch vụ ' . str_pad((string) $index, 4, '0', STR_PAD_LEFT) . '</td><td>' . (100000 + $index) . ' ₫ / lượt</td></tr>';
$manyRows .= '</tbody></table><p>Ghi chú cuối nguồn.</p>';
$limited = medical_review_article_price_source($manyRows);
$check(mb_strlen($limited, 'UTF-8') <= 20000 && str_contains($limited, '[ĐÃ RÚT GỌN:'), 'Oversized output is bounded and explicitly marked as incomplete non-data.');
$limitedDocument = $parse($limited);
$limitedRows = $limitedDocument->getElementsByTagName('tr');
$check($limitedRows->length > 2 && $limitedRows->length < 701, 'Truncated table retains useful complete initial rows.');
for ($rowIndex = 1; $rowIndex < $limitedRows->length; $rowIndex++) {
    $cells = $limitedRows->item($rowIndex)->getElementsByTagName('td');
    $check($cells->length === 2 && $cells->item(0)->textContent === 'Dịch vụ ' . str_pad((string) $rowIndex, 4, '0', STR_PAD_LEFT)
        && $cells->item(1)->textContent === (100000 + $rowIndex) . ' ₫ / lượt', 'Every retained service/price pair is a complete original prefix row.');
}
$check(substr_count($limited, '<table>') === substr_count($limited, '</table>') && substr_count($limited, '<tr>') === substr_count($limited, '</tr>'), 'Truncation retains complete closing table/row elements.');
$giantRow = '<table><tr><td>Giữ dòng đầu</td><td>100.000 ₫</td></tr><tr><td>' . str_repeat('Khám ', 5000)
    . '</td><td>999.999 ₫</td></tr><tr><td>DÒNG SAU KHÔNG NỐI LẠI</td><td>888.888 ₫</td></tr></table>';
$limitedGiant = medical_review_article_price_source($giantRow);
$check(str_contains($limitedGiant, 'Giữ dòng đầu') && str_contains($limitedGiant, '100.000 ₫') && !str_contains($limitedGiant, '999.999')
    && !str_contains($limitedGiant, '888.888') && !str_contains($limitedGiant, 'DÒNG SAU KHÔNG NỐI LẠI'), 'An overlong row is omitted whole and later rows are not spliced back into the prefix.');
$check(medical_review_article_price_source('<p>' . str_repeat('x', 21000) . '</p>') === '', 'A sole oversized element is not cut into a misleading partial note or amount.');
$check(medical_review_article_price_source(str_repeat('<div>', 50) . '<p>Khám 100.000 ₫</p>' . str_repeat('</div>', 50)) === '', 'Pathologically deep source is bounded.');
$check(medical_review_article_price_source('<script>only secret</script><img src="https://example.com/x">') === '', 'Active-content-only input produces no context.');
$plaintext = medical_review_article_price_source("Ghi chú: chưa gồm phim.\nXác nhận mức phí với cơ sở.");
$check(str_contains($plaintext, "phim.\nXác nhận"), 'Plain block notes retain their line separation.');

$helper = (string) file_get_contents(dirname(__DIR__) . '/medical_review_article_price_source.php');
$check(!preg_match('/\b(?:db|fopen|file_get_contents|file_put_contents|curl_init|curl_exec|shell_exec|exec|include|require|require_once)\s*\(/i', $helper), 'Helper contains no DB, filesystem, process or HTTP I/O calls.');
$check(str_contains($helper, 'LIBXML_NONET') && !str_contains($helper, 'LIBXML_NOENT') && !str_contains($helper, 'LIBXML_DTDLOAD'), 'Parser never enables remote DTD or external entity expansion.');
echo 'review_article_price_source_test: ' . $assertions . " assertions passed.\n";
