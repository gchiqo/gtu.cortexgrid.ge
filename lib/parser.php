<?php
declare(strict_types=1);

/**
 * Parses a leqtori "teachers_*.html" page into a flat list of lecture records.
 * Each record:
 *   teacher_name, group_code, lesson_type, subject_name, subject_code, room,
 *   weekday (1=Mon..6=Sat), start_slot, end_slot, start_time, end_time, raw_cell
 */
function parse_teachers_html(string $html): array {
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    // Force UTF-8 interpretation. DOMDocument defaults to ISO-8859-1.
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);
    $tables = $xpath->query('//table[starts-with(@id, "table_")]');

    $records = [];
    foreach ($tables as $table) {
        /** @var DOMElement $table */
        $teacherName = extract_teacher_name($xpath, $table);
        if ($teacherName === '') continue;

        $rows = $xpath->query('.//tbody/tr', $table);
        $pending = array_fill(0, 6, 0); // remaining rowspan per weekday column
        $rowIdx = 0;

        foreach ($rows as $tr) {
            /** @var DOMElement $tr */
            [$slotNum, $startTime] = parse_slot_label($xpath, $tr);
            if ($slotNum === null) { $rowIdx++; continue; }

            $tds = $xpath->query('./td', $tr);
            $tdQueue = iterator_to_array($tds);
            $tdCursor = 0;

            for ($col = 0; $col < 6; $col++) {
                if ($pending[$col] > 0) {
                    $pending[$col]--;
                    continue;
                }
                if ($tdCursor >= count($tdQueue)) break;
                /** @var DOMElement $td */
                $td = $tdQueue[$tdCursor++];

                $rowspan = max(1, (int)($td->getAttribute('rowspan') ?: 1));
                $pending[$col] = $rowspan - 1;

                $cellText = trim(inner_text_with_breaks($td));
                if ($cellText === '' || $cellText === '---') continue;

                $parsed = parse_cell($cellText);
                if ($parsed === null) continue;

                $records[] = [
                    'teacher_name'  => $teacherName,
                    'group_code'    => $parsed['group_code'],
                    'lesson_type'   => $parsed['lesson_type'],
                    'subject_name'  => $parsed['subject_name'],
                    'subject_code'  => $parsed['subject_code'],
                    'room'          => $parsed['room'],
                    'weekday'       => $col + 1,
                    'start_slot'    => $slotNum,
                    'end_slot'      => $slotNum + $rowspan - 1,
                    'start_time'    => $startTime,
                    'end_time'      => slot_end_time($slotNum + $rowspan - 1),
                    'raw_cell'      => $cellText,
                ];
            }
            $rowIdx++;
        }
    }
    return $records;
}

function extract_teacher_name(DOMXPath $xpath, DOMElement $table): string {
    $th = $xpath->query('.//thead//th[@colspan]', $table);
    if ($th->length === 0) return '';
    return preg_replace('/\s+/u', ' ', trim($th->item(0)->textContent)) ?? '';
}

/** Returns [slotNumber, "HH:MM"] from a "<th class=yAxis>1-9:00</th>". */
function parse_slot_label(DOMXPath $xpath, DOMElement $tr): array {
    $th = $xpath->query('./th[contains(@class,"yAxis")]', $tr);
    if ($th->length === 0) return [null, null];
    $label = trim($th->item(0)->textContent);
    if (!preg_match('/^(\d+)-(\d{1,2}):(\d{2})/', $label, $m)) return [null, null];
    return [(int)$m[1], sprintf('%02d:%02d', (int)$m[2], (int)$m[3])];
}

/** Slot N starts at (N+8):00. So slot N ends at (N+9):00. */
function slot_end_time(int $slot): string {
    return sprintf('%02d:00', $slot + 9);
}

/** Replace <br> with newlines, then return the cell's plain text. */
function inner_text_with_breaks(DOMElement $node): string {
    $html = '';
    foreach ($node->childNodes as $child) {
        $html .= $node->ownerDocument->saveHTML($child);
    }
    $html = preg_replace('#<br\s*/?>#i', "\n", $html);
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // Normalize but preserve newlines.
    $text = preg_replace("/[ \t\xC2\xA0]+/u", ' ', $text);
    return trim($text);
}

/**
 * Parse a non-empty cell string of the form:
 *   <group>-<short_type>\n<subject> (<code>) <lesson_type>\n<room>
 * Returns null if the shape doesn't match at all.
 */
function parse_cell(string $cell): ?array {
    $lines = array_values(array_filter(array_map('trim', preg_split('/\R+/u', $cell)), fn($l) => $l !== ''));
    if (count($lines) === 0) return null;

    $groupCode = null;
    $subjectName = null;
    $subjectCode = null;
    $lessonType = null;
    $room = null;

    // First line: group identifier (e.g. "052510-შ", or sometimes multiple groups).
    $groupCode = $lines[0];

    // Second line: "<subject> (<code>) <type>"
    if (isset($lines[1])) {
        if (preg_match('/^(.+?)\s*\((\d+)\)\s*(.*)$/u', $lines[1], $m)) {
            $subjectName = trim($m[1]);
            $subjectCode = trim($m[2]);
            $lessonType  = trim($m[3]) !== '' ? trim($m[3]) : null;
        } else {
            $subjectName = $lines[1];
        }
    }

    // Third line: room
    if (isset($lines[2])) {
        $room = $lines[2];
    }

    if ($subjectName === null && $room === null) return null;

    return [
        'group_code'   => $groupCode,
        'subject_name' => $subjectName ?? '',
        'subject_code' => $subjectCode,
        'lesson_type'  => $lessonType,
        'room'         => $room,
    ];
}

/**
 * Discover ALL teachers HTML URLs from the homepage, with their section titles.
 * Multiple sections may each link a teachers HTML — we want all of them.
 *
 * Returns: [ ['url' => ..., 'anchor_text' => ..., 'section_title' => ...], ... ]
 */
function discover_teachers_urls(string $homepageHtml): array {
    $links = discover_homepage_links($homepageHtml);
    $out = [];
    $seen = [];
    foreach ($links as $l) {
        if ($l['kind'] !== 'teachers_html') continue;
        if (isset($seen[$l['url']])) continue;
        $seen[$l['url']] = true;
        $out[] = $l;
    }
    return $out;
}

/**
 * Discover EVERY downloadable link on the leqtori homepage, grouped by the
 * section heading it sits under. Returns:
 *
 *   [
 *     ['section_title' => '...', 'anchor_text' => '...', 'url' => '...',
 *      'kind' => 'teachers_html'|'additional_pdf'|'midterm_pdf'|'unknown_*'],
 *     ...
 *   ]
 *
 * Excluded by anchor-text keyword: groups (აკადემიური ჯგუფები), rooms
 * (აუდიტორიები), and the "appeal-restore" PDF. The exclusion list is keyword-
 * based so weekly file-name changes don't re-include them.
 */
function discover_homepage_links(string $homepageHtml): array {
    $excludeKeywords = [
        'აკადემიური ჯგუფები',     // Groups
        'Groups',                  // Groups (English)
        'აუდიტორიები',             // Rooms (KA)
        'Rooms',                   // Rooms (EN)
        'სწავლის შედეგების შეფასების გასაჩივრება', // appeal-restore PDF
    ];

    // Keywords that, when present in a <strong> heading's text, mark it as a
    // "real" section heading (not just inline bold or a stray date label).
    $headingKeywords = [
        'პროფესიული სწავლების ცხრილი',
        'კვირის სასწავლო ცხრილი',
        'შუალედური გამოცდების ცხრილი',
        'Midterm Exam Schedule',
        'დამატებითი სასწავლო კურსების ცხრილი',
    ];

    $current = null;
    $results = [];
    $pos = 0;
    $len = strlen($homepageHtml);

    while ($pos < $len) {
        // Find the next <strong>...</strong> or <a href="*.{pdf,html}">...</a>.
        $sP = stripos($homepageHtml, '<strong>', $pos);
        $aHit = preg_match(
            '#<a\s+[^>]*href="([^"]+\.(?:pdf|html))"[^>]*>(.*?)</a>#siu',
            $homepageHtml, $aM, PREG_OFFSET_CAPTURE, $pos
        );

        $strongPos = $sP !== false ? $sP : PHP_INT_MAX;
        $anchorPos = $aHit ? $aM[0][1] : PHP_INT_MAX;
        if ($strongPos === PHP_INT_MAX && $anchorPos === PHP_INT_MAX) break;

        if ($strongPos < $anchorPos) {
            $end = stripos($homepageHtml, '</strong>', $strongPos);
            if ($end === false) break;
            $rawInner = substr($homepageHtml, $strongPos + 8, $end - $strongPos - 8);
            $inner = html_entity_decode(trim(strip_tags($rawInner)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $inner = preg_replace('/\s+/u', ' ', $inner) ?? $inner;
            // Treat as section header only if it has no anchors inside (anchored
            // <strong> elements are link-text, not headers) and contains one of
            // the recognised heading keywords. Store the LITERAL text so a label
            // like "VIII კვირის სასწავლო ცხრილი" keeps its week-number prefix.
            if (stripos($rawInner, '<a ') === false && mb_strlen($inner) > 4) {
                foreach ($headingKeywords as $kw) {
                    if (mb_stripos($inner, $kw) !== false) {
                        // The page's "Midterm" section has a Georgian heading
                        // followed by a stray English-only <strong>. Prefer the
                        // Georgian when we recognise it; for everything else,
                        // store the literal heading text as it appears.
                        if (mb_stripos($inner, 'Midterm') !== false && mb_stripos($inner, 'შუალედური') === false) {
                            $current = 'შუალედური გამოცდების ცხრილი / ' . $inner;
                        } else {
                            $current = $inner;
                        }
                        break;
                    }
                }
            }
            $pos = $end + 9;
            continue;
        }

        $url       = html_entity_decode($aM[1][0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $anchorRaw = $aM[2][0];
        $anchor    = html_entity_decode(trim(strip_tags($anchorRaw)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $anchor    = preg_replace('/\s+/u', ' ', $anchor) ?? $anchor;
        $pos       = $aM[0][1] + strlen($aM[0][0]);
        if ($anchor === '') continue;

        // Apply exclusion list.
        $skip = false;
        foreach ($excludeKeywords as $kw) {
            if (mb_stripos($anchor, $kw) !== false) { $skip = true; break; }
        }
        if ($skip) continue;

        $kind = classify_link_kind($url, $anchor, $current);

        $results[] = [
            'section_title' => $current,
            'anchor_text'   => $anchor,
            'url'           => $url,
            'kind'          => $kind,
        ];
    }

    return $results;
}

function classify_link_kind(string $url, string $anchor, ?string $section): string {
    $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
    $section = $section ?? '';

    if ($ext === 'html' && (str_contains($url, 'teachers') || mb_stripos($anchor, 'პედაგოგ') !== false || mb_stripos($anchor, 'Teach') !== false)) {
        return 'teachers_html';
    }
    if ($ext === 'pdf' && mb_stripos($section, 'დამატებითი') !== false) {
        return 'additional_pdf';
    }
    if ($ext === 'pdf' && (mb_stripos($section, 'შუალედური') !== false || mb_stripos($section, 'Midterm') !== false)) {
        return 'midterm_pdf';
    }
    return $ext === 'pdf' ? 'unknown_pdf' : 'unknown_html';
}

/**
 * Backward-compatibility shim: callers that wanted ONLY the additional-courses
 * PDFs can still ask for them by slug. Internally uses discover_homepage_links.
 */
function discover_additional_pdfs(string $homepageHtml): array {
    require_once __DIR__ . '/faculty.php';
    $links = discover_homepage_links($homepageHtml);
    $out = [];
    foreach ($links as $l) {
        if ($l['kind'] !== 'additional_pdf') continue;
        $slug = classify_faculty($l['anchor_text']);
        if ($slug === null || isset($out[$slug])) continue;
        $out[$slug] = [
            'url'           => $l['url'],
            'anchor_text'   => $l['anchor_text'],
            'section_title' => $l['section_title'],
        ];
    }
    return $out;
}
