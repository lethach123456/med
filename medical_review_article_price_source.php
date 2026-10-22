<?php
declare(strict_types=1);

/**
 * Read-only AI context, not a price calculator or a public HTML sanitizer.
 * Keep complete table rows so a service is never paired with another row's fee.
 * No DOM fallback deliberately returns nothing rather than flattening a table.
 */
function medical_review_article_price_source(mixed $raw): string
{
    if (!is_string($raw) || strlen($raw) > 250000 || trim($raw) === ''
        || preg_match('//u', $raw) !== 1 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $raw)
        || !class_exists('DOMDocument')) return '';

    $length = static fn(string $value): int => function_exists('mb_strlen')
        ? mb_strlen($value, 'UTF-8') : (int) preg_match_all('/./us', $value);
    $input = new DOMDocument('1.0', 'UTF-8');
    $oldErrors = libxml_use_internal_errors(true);
    try {
        // No DTD loading or entity-substitution option; remote resources are never read.
        $loaded = $input->loadHTML('<?xml encoding="UTF-8"><html><head><meta charset="UTF-8"></head><body>' . $raw . '</body></html>', LIBXML_NONET);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($oldErrors);
    }
    if (!$loaded) return '';
    $body = $input->getElementsByTagName('body')->item(0);
    if (!$body instanceof DOMElement) return '';

    $output = new DOMDocument('1.0', 'UTF-8');
    $root = $output->createElement('div');
    $output->appendChild($root);
    $allowed = array_fill_keys(['table','thead','tbody','tfoot','tr','th','td','caption','p','br','strong','em','ul','ol','li'], true);
    $blocked = array_fill_keys(['script','style','noscript','template','img','picture','source','svg','math','canvas',
        'iframe','frame','frameset','object','embed','video','audio','track','form','input','button','select','option',
        'textarea','link','meta','base','head'], true);
    $tableTags = array_fill_keys(['table','thead','tbody','tfoot','tr','th','td','caption'], true);
    $layoutTags = array_fill_keys(['thead','tbody','tfoot','tr','th','td'], true);
    $visited = 0;

    $hidden = static function (DOMElement $node): bool {
        return $node->hasAttribute('hidden') || strtolower(trim($node->getAttribute('aria-hidden'))) === 'true'
            || preg_match('/(?:^|;)\s*(?:display\s*:\s*none|visibility\s*:\s*(?:hidden|collapse))(?:\s*!important)?\s*(?:;|$)/i', $node->getAttribute('style')) === 1;
    };
    $invalidSpans = static function (DOMElement $table): bool {
        // Do not silently change a malformed table's column/row relationships.
        foreach (['td','th'] as $tag) foreach ($table->getElementsByTagName($tag) as $cell) {
            foreach (['colspan','rowspan'] as $attribute) {
                if (!$cell->hasAttribute($attribute)) continue;
                $value = trim($cell->getAttribute($attribute));
                if (!preg_match('/^[1-9][0-9]{0,2}$/D', $value) || (int) $value > 100) return true;
            }
        }
        return false;
    };

    $copy = static function (DOMNode $node, DOMNode $parent, int $depth = 0, bool $blank = false) use (
        &$copy, &$visited, $output, $allowed, $blocked, $tableTags, $layoutTags, $hidden, $invalidSpans
    ): void {
        if (++$visited > 20000 || $depth > 40) throw new LengthException('Price context structure is too complex.');
        if ($node instanceof DOMText) {
            if ($blank) return;
            $text = str_replace(["\r\n", "\r"], "\n", $node->nodeValue ?? '');
            if ($parent instanceof DOMElement && in_array($parent->tagName, ['table','thead','tbody','tfoot','tr'], true)) return;
            if ($parent->nodeName === 'div' && trim($text) === '') return;
            $parent->appendChild($output->createTextNode($text));
            return;
        }
        if (!$node instanceof DOMElement) return;
        $tag = strtolower($node->tagName);
        if (isset($blocked[$tag])) return;
        $isHidden = $hidden($node);
        // Hidden structural cells remain empty, preserving other columns/spans.
        if ($isHidden && !isset($layoutTags[$tag])) return;
        $blank = $blank || $isHidden;
        if ($tag === 'table' && $invalidSpans($node)) return;
        if (isset($tableTags[$tag])) {
            $parentTag = strtolower($parent->nodeName);
            $validParent = match ($tag) {
                'thead','tbody','tfoot','caption' => $parentTag === 'table',
                'tr' => in_array($parentTag, ['table','thead','tbody','tfoot'], true),
                'td','th' => $parentTag === 'tr',
                default => true,
            };
            if (!$validParent) return;
        }
        if (!isset($allowed[$tag])) {
            foreach ($node->childNodes as $child) $copy($child, $parent, $depth + 1, $blank);
            return;
        }
        $clean = $output->createElement($tag);
        if ($tag === 'td' || $tag === 'th') foreach (['colspan','rowspan'] as $attribute) {
            if ($node->hasAttribute($attribute)) $clean->setAttribute($attribute, (string) (int) $node->getAttribute($attribute));
        }
        $parent->appendChild($clean);
        foreach ($node->childNodes as $child) $copy($child, $clean, $depth + 1, $blank);
        // Do not leave meaningless or malformed empty table structures.
        if (($tag === 'table' && $clean->getElementsByTagName('tr')->length === 0)
            || ($tag === 'tr' && $clean->getElementsByTagName('td')->length + $clean->getElementsByTagName('th')->length === 0)) {
            $parent->removeChild($clean);
        }
    };
    try {
        foreach ($body->childNodes as $node) $copy($node, $root);
    } catch (LengthException) { return ''; }

    $serialize = static function () use ($output, $root): string {
        $html = '';
        foreach ($root->childNodes as $node) $html .= $output->saveHTML($node);
        return trim($html);
    };
    $html = $serialize();
    if ($html === '' || trim($root->textContent ?? '') === '') return '';
    if ($length($html) <= 20000) return $html;

    $marker = '<p>[ĐÃ RÚT GỌN: chỉ giữ các dòng hoàn chỉnh; phần còn lại chưa được đưa vào dữ liệu này.]</p>';
    $budget = 20000 - $length($marker);
    $remaining = $length($html);
    $containers = array_fill_keys(['table','thead','tbody','tfoot','ul','ol'], true);
    $prune = static function (DOMNode $container) use (&$prune, $output, $length, $containers): int {
        $last = $container->lastChild;
        if (!$last) return 0;
        if ($last instanceof DOMElement && isset($containers[$last->tagName]) && $last->hasChildNodes()) {
            $removed = $prune($last);
            if (!$last->hasChildNodes()) {
                $removed += $length((string) $output->saveHTML($last));
                $container->removeChild($last);
            }
            return $removed;
        }
        // In particular, remove an entire tr, never an individual service/price cell.
        $removed = $length((string) $output->saveHTML($last));
        $container->removeChild($last);
        return $removed;
    };
    while ($remaining > $budget && $root->hasChildNodes()) {
        $removed = $prune($root);
        if ($removed <= 0) return '';
        $remaining -= $removed;
    }
    $html = $serialize();
    if ($html === '' || trim($root->textContent ?? '') === '') return '';
    // A second check accounts for serializer/whitespace edge cases conservatively.
    return $length($html . $marker) <= 20000 ? $html . $marker : '';
}
