<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Extract clean per-page text from a PDF binary blob.
 *
 * Returns ['page_count' => int, 'text' => string, 'pages' => [pageText...]]
 * Pages are joined with a form-feed separator so the client UI can split if
 * desired without losing the page boundaries.
 */
function pdf_extract_text(string $pdfBytes): array {
    $parser = new \Smalot\PdfParser\Parser();
    $pdf = $parser->parseContent($pdfBytes);

    $pages = $pdf->getPages();
    $cleaned = [];
    foreach ($pages as $p) {
        $cleaned[] = pdf_clean_text($p->getText());
    }

    return [
        'page_count' => count($pages),
        'pages'      => $cleaned,
        'text'       => pdf_join_pages($cleaned),
    ];
}

function pdf_join_pages(array $pages): string {
    $out = '';
    foreach ($pages as $i => $t) {
        if ($i > 0) $out .= "\n\n";
        $out .= '——— page ' . ($i + 1) . " ———\n\n" . $t;
    }
    return $out;
}

/**
 * Extract "logical rows" from an Excel-generated PDF using positioned text data.
 *
 * Each entry in the source table starts with a row number (1, 2, 3, ...) — we use
 * those as anchors. Rows above/below an anchor (within the Y-midpoint to its
 * neighbors) get attributed to that entry — this captures multi-line cells that
 * Excel-PDF generators wrap across multiple text fragments.
 *
 * Within each entry, fragments are binned into columns by X position (page-global
 * X clustering, so a column missing from one anchor row is still detected from
 * the others).
 *
 * Returns: [ ['page' => int, 'row_num' => int, 'cells' => [['text' => string, 'x' => float], ...]], ... ]
 * Empty cells are dropped — the parser uses cell X positions to find logical
 * field boundaries (largest-gap split for teacher↔subject), so column
 * alignment across rows is no longer needed.
 */
function pdf_extract_logical_rows(string $pdfBytes): array {
    $parser = new \Smalot\PdfParser\Parser();
    $pdf    = $parser->parseContent($pdfBytes);
    $out    = [];

    foreach ($pdf->getPages() as $pageIdx => $page) {
        if (!method_exists($page, 'getDataTm')) continue;
        $dataTm = $page->getDataTm();
        if (!$dataTm) continue;

        // 1) collect non-blank fragments
        $frags = [];
        foreach ($dataTm as $entry) {
            [$tm, $text] = $entry;
            if (trim($text) === '') continue;
            $frags[] = ['x' => (float)$tm[4], 'y' => (float)$tm[5], 'text' => $text];
        }
        if (!$frags) continue;

        // 2) group by Y row (within ±2pt)
        $byY = [];
        foreach ($frags as $f) {
            $key = (int)round($f['y'] / 2) * 2;
            $byY[$key][] = $f;
        }
        krsort($byY); // page top first

        // 3) anchor rows: leftmost cell is "<int>" or "<int><word>" (digit glued to next text)
        $anchorYs = [];
        $anchorNum = [];
        foreach ($byY as $y => $cells) {
            usort($cells, fn($a, $b) => $a['x'] <=> $b['x']);
            $first = trim($cells[0]['text']);
            if (preg_match('/^(\d{1,3})\.?$/u', $first, $m)
                || preg_match('/^(\d{1,3})(?=[^\d])/u', $first, $m)) {
                $n = (int)$m[1];
                if ($n >= 1 && $n < 1000) {
                    $anchorYs[] = $y;
                    $anchorNum[$y] = $n;
                }
            }
        }
        if (!$anchorYs) continue;

        // 4) global column starts: cluster X positions across all anchor rows
        $allXs = [];
        foreach ($anchorYs as $y) {
            foreach ($byY[$y] as $c) $allXs[] = $c['x'];
        }
        sort($allXs);
        $colStarts = [];
        $last = -INF;
        foreach ($allXs as $x) {
            if ($x - $last > 6) $colStarts[] = $x;
            $last = $x;
        }
        if (!$colStarts) continue;

        // 5) for each anchor, build the entry's column array
        for ($i = 0; $i < count($anchorYs); $i++) {
            $y      = $anchorYs[$i];
            $prevY  = $i > 0 ? $anchorYs[$i - 1] : null;
            $nextY  = $i + 1 < count($anchorYs) ? $anchorYs[$i + 1] : null;
            $top    = $prevY !== null ? ($prevY + $y) / 2 : $y + 14;
            $bottom = $nextY !== null ? ($y + $nextY) / 2 : $y - 14;

            // gather fragments in this entry's Y window, sorted top->bottom, left->right
            $entryFrags = [];
            foreach ($byY as $rowY => $cells) {
                if ($rowY > $top || $rowY <= $bottom) continue;
                foreach ($cells as $c) $entryFrags[] = $c;
            }
            usort($entryFrags, function ($a, $b) {
                if (abs($a['y'] - $b['y']) > 2) return $b['y'] <=> $a['y'];
                return $a['x'] <=> $b['x'];
            });

            // bin into columns by closest col-start ≤ x; track the leftmost X of
            // each column so the parser can use real positions for field detection.
            $colTexts = array_fill(0, count($colStarts), '');
            $colMinX  = array_fill(0, count($colStarts), null);
            foreach ($entryFrags as $f) {
                $colIdx = 0;
                for ($k = 0; $k < count($colStarts); $k++) {
                    if ($f['x'] >= $colStarts[$k] - 1) $colIdx = $k;
                    else break;
                }
                $colTexts[$colIdx] .= $f['text'];
                if ($colMinX[$colIdx] === null || $f['x'] < $colMinX[$colIdx]) {
                    $colMinX[$colIdx] = $f['x'];
                }
            }

            // Build cells, dropping empties. Whitespace normalized.
            $cells = [];
            foreach ($colTexts as $k => $v) {
                $v = trim(preg_replace('/\s+/u', ' ', $v) ?? $v);
                if ($v === '') continue;
                $cells[] = ['text' => $v, 'x' => (float)$colMinX[$k]];
            }
            if (!$cells) continue;

            $out[] = [
                'page'    => $pageIdx + 1,
                'row_num' => $anchorNum[$y],
                'cells'   => $cells,
            ];
        }
    }

    return $out;
}

/**
 * smalot's text extraction often produces:
 *   - lines that wrap mid-cell
 *   - non-breaking spaces and tabs sprinkled in
 *   - trailing whitespace on most lines
 * Normalize whitespace without breaking the line structure.
 */
function pdf_clean_text(string $text): string {
    // Collapse non-breaking space + tabs into regular spaces.
    $text = str_replace(["\xC2\xA0", "\t"], ' ', $text);
    // Normalize CRLF / CR to LF.
    $text = preg_replace("/\r\n?/", "\n", $text);
    // Collapse runs of horizontal whitespace.
    $text = preg_replace('/[ ]{2,}/u', ' ', $text);
    // Trim each line.
    $lines = array_map('rtrim', explode("\n", $text));
    // Collapse 3+ consecutive blank lines into 1.
    $out = [];
    $blanks = 0;
    foreach ($lines as $ln) {
        if ($ln === '') {
            $blanks++;
            if ($blanks <= 1) $out[] = '';
        } else {
            $blanks = 0;
            $out[] = $ln;
        }
    }
    return trim(implode("\n", $out));
}
