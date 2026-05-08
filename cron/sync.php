<?php
declare(strict_types=1);

/**
 * Crawls the leqtori.gtu.ge homepage and ingests every linked HTML/PDF schedule
 * (excluding Groups, Rooms, and the appeal-restore PDF), tagging each with the
 * section heading it sits under. Run via cron every 5 hours.
 *
 * CLI:   php cron/sync.php
 * HTTP:  /cron/sync.php?key=<SYNC_KEY>   (only if SYNC_KEY env is set)
 */

require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/parser.php';
require __DIR__ . '/../lib/pdf.php';
require __DIR__ . '/../lib/faculty.php';
require __DIR__ . '/../lib/additional_parser.php';
require_once __DIR__ . '/../lib/translit.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    $expected = getenv('SYNC_KEY') ?: '';
    if ($expected === '' || ($_GET['key'] ?? '') !== $expected) {
        http_response_code(403);
        echo "forbidden\n";
        exit;
    }
}

$start = microtime(true);
$logf = function (string $msg) use ($isCli) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($isCli) {
        fwrite(STDOUT, $line . "\n");
    } else {
        echo $line . "\n";
        @ob_flush(); @flush();
    }
};

try {
    run_sync($logf);
    $logf(sprintf('done in %.2fs', microtime(true) - $start));
} catch (Throwable $e) {
    $logf('ERROR: ' . $e->getMessage());
    if (!$isCli) http_response_code(500);
    exit(1);
}

function run_sync(callable $log): void {
    $log('fetching homepage');
    $homepage = http_get('https://leqtori.gtu.ge/');
    if ($homepage['status'] !== 200) {
        throw new RuntimeException('homepage HTTP ' . $homepage['status']);
    }

    $links = discover_homepage_links($homepage['body']);
    $log(sprintf('discovered %d links across %d sections',
        count($links),
        count(array_unique(array_column($links, 'section_title')))));

    // Group by kind so we can handle teachers HTML before clearing the lecture
    // table; PDFs get their own pass.
    $byKind = [];
    foreach ($links as $l) {
        $byKind[$l['kind']][] = $l;
    }

    $teachersLinks = $byKind['teachers_html'] ?? [];
    $additional    = $byKind['additional_pdf'] ?? [];
    $midterm       = $byKind['midterm_pdf'] ?? [];
    $unknown       = array_merge($byKind['unknown_pdf'] ?? [], $byKind['unknown_html'] ?? []);

    sync_teachers_html($teachersLinks, $log);
    sync_pdf_section($additional, 'additional_pdf', $log);
    sync_pdf_section($midterm,    'midterm_pdf',    $log);

    if ($unknown) {
        $log('skipped ' . count($unknown) . ' unclassified link(s):');
        foreach ($unknown as $u) $log('  ! ' . $u['anchor_text'] . ' -> ' . $u['url']);
    }
}

function sync_teachers_html(array $links, callable $log): void {
    if (!$links) { $log('teachers_html: none found on homepage'); return; }

    // Deduplicate by URL (prof_teachers.html appears under multiple sections).
    $seen = [];
    $unique = [];
    foreach ($links as $l) {
        if (isset($seen[$l['url']])) continue;
        $seen[$l['url']] = true;
        $unique[] = $l;
    }
    $log(sprintf('teachers_html: %d unique source(s)', count($unique)));

    $pdo = db();
    $now = time();

    // Wipe lecture and re-populate from current scrape (each row tagged with
    // its source so the same lecture in multiple HTMLs is preserved separately).
    $pdo->exec('DELETE FROM lecture');

    foreach ($unique as $link) {
        try {
            $url = $link['url'];
            $log("  [$url] fetching");
            $resp = http_get(encode_url_path($url));
            if ($resp['status'] !== 200) {
                $log('  HTTP ' . $resp['status'] . ', skipping');
                continue;
            }
            $bytes = strlen($resp['body']);
            $log(sprintf('  fetched %d bytes', $bytes));

            $records = parse_teachers_html($resp['body']);
            $log(sprintf('  parsed %d lectures from %s', count($records), $link['section_title']));

            $displayLabel = teachers_display_label($link, $url);
            $sourceId = upsert_source(
                $pdo, $url, 'teachers_html', $resp['status'], $bytes,
                $link['section_title'], $displayLabel, $link['anchor_text']
            );

            $pdo->beginTransaction();
            try {
                $ins = $pdo->prepare(
                    'INSERT IGNORE INTO lecture(source_id, teacher_id, subject_id, group_code, lesson_type, room,
                                         weekday, start_slot, end_slot, start_time, end_time, raw_cell,
                                         first_seen_at, last_seen_at)
                     VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $inserted = 0;
                foreach ($records as $r) {
                    $teacherId = upsert_teacher($pdo, $r['teacher_name']);
                    $subjectId = $r['subject_name'] !== ''
                        ? upsert_subject($pdo, $r['subject_code'], $r['subject_name'])
                        : null;
                    $ins->execute([
                        $sourceId, $teacherId, $subjectId,
                        $r['group_code'], $r['lesson_type'], $r['room'],
                        $r['weekday'], $r['start_slot'], $r['end_slot'],
                        $r['start_time'], $r['end_time'], $r['raw_cell'],
                        $now, $now,
                    ]);
                    if ($ins->rowCount() > 0) $inserted++;
                }
                $pdo->commit();
                $log(sprintf('  stored %d (deduped %d) into lecture', $inserted, count($records) - $inserted));
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        } catch (Throwable $e) {
            $log('  FAILED: ' . $e->getMessage());
        }
    }

    $log('teachers totals: '
        . (int)$pdo->query('SELECT COUNT(*) FROM teacher')->fetchColumn() . ' teachers, '
        . (int)$pdo->query('SELECT COUNT(*) FROM subject')->fetchColumn() . ' subjects, '
        . (int)$pdo->query('SELECT COUNT(*) FROM lecture')->fetchColumn() . ' lectures');
}

function teachers_display_label(array $link, string $url): string {
    $base = basename(parse_url($url, PHP_URL_PATH) ?: $url);
    $kind = '';
    if (str_contains($base, 'prof_teachers')) {
        $kind = 'პროფ. პედაგოგები';
    } elseif (preg_match('/_2025_2026_2_8_\.html$/', $base)) {
        $kind = 'სრული ცხრილი';        // weekly full timetable
    } else {
        $kind = 'პედაგოგები';
    }
    return $link['section_title'] . ' — ' . $kind;
}

function sync_pdf_section(array $links, string $kind, callable $log): void {
    if (!$links) { $log("$kind: no PDFs"); return; }

    $log(sprintf('%s: %d PDF(s) to fetch', $kind, count($links)));

    $faculties = faculties();
    $pdo = db();
    $now = time();
    $stored = 0;
    $failed = [];

    foreach ($links as $link) {
        $url = $link['url'];
        $anchor = $link['anchor_text'];

        // Map anchor → faculty slug. For midterm PDFs we use the same slug
        // namespace as additional PDFs; (kind, slug) is the unique key in pdf_doc.
        $slug = classify_faculty($anchor);
        if ($slug === null) {
            $log("  [unmapped] $anchor — skipping");
            continue;
        }

        try {
            $log("  [$kind/$slug] fetching " . basename(rawurldecode($url)));
            $resp = http_get(encode_url_path($url));
            if ($resp['status'] !== 200) throw new RuntimeException('HTTP ' . $resp['status']);
            $bytes = strlen($resp['body']);
            $log(sprintf('  [%s/%s] %d bytes, parsing PDF', $kind, $slug, $bytes));

            $extracted = pdf_extract_text($resp['body']);
            $log(sprintf('  [%s/%s] %d pages, %d text chars',
                $kind, $slug, $extracted['page_count'], strlen($extracted['text'])));

            $displayLabel = ($faculties[$slug]['name_ka'] ?? $anchor)
                . ' — ' . pdf_kind_label($kind);

            $sourceId = upsert_source(
                $pdo, $url, $kind, $resp['status'], $bytes,
                $link['section_title'], $displayLabel, $anchor
            );

            $stmt = $pdo->prepare(
                'INSERT INTO pdf_doc(faculty_slug, faculty_name, source_id, kind, page_count, raw_text, fetched_at)
                 VALUES(?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    id           = LAST_INSERT_ID(id),
                    faculty_name = VALUES(faculty_name),
                    source_id    = VALUES(source_id),
                    page_count   = VALUES(page_count),
                    raw_text     = VALUES(raw_text),
                    fetched_at   = VALUES(fetched_at)'
            );
            $stmt->execute([
                $slug,
                $faculties[$slug]['name_ka'] ?? $anchor,
                $sourceId,
                $kind,
                $extracted['page_count'],
                $extracted['text'],
                $now,
            ]);
            $pdfDocId = (int)$pdo->lastInsertId();
            $stored++;

            // Structured parse only attempted for additional_pdf rows where the
            // faculty layout is known. Midterm PDFs are catalog-only for now.
            if ($kind === 'additional_pdf' && in_array($slug, SUPPORTED_FACULTIES, true)) {
                $logicalRows  = pdf_extract_logical_rows($resp['body']);
                $structured   = parse_additional_rows($slug, $logicalRows);
                $insertedRows = upsert_additional_lectures($pdo, $pdfDocId, $slug, $structured, $now);
                $log(sprintf('  [%s/%s] structured: %d logical rows -> %d lectures',
                    $kind, $slug, count($logicalRows), $insertedRows));
            } else {
                $log("  [$kind/$slug] raw text only");
            }
        } catch (Throwable $e) {
            $failed[$slug] = $e->getMessage();
            $log("  [$kind/$slug] FAILED: " . $e->getMessage());
        }
    }
    $log(sprintf('%s: stored %d, failed %d', $kind, $stored, count($failed)));
}

function pdf_kind_label(string $kind): string {
    return match ($kind) {
        'additional_pdf' => 'დამატ. კურსები',
        'midterm_pdf'    => 'შუალედური გამოცდები',
        default          => $kind,
    };
}

function upsert_additional_lectures(PDO $pdo, int $pdfDocId, string $slug, array $rows, int $now): int {
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM additional_lecture WHERE pdf_doc_id = ?')->execute([$pdfDocId]);
        if (!$rows) { $pdo->commit(); return 0; }

        $ins = $pdo->prepare(
            'INSERT INTO additional_lecture
                (pdf_doc_id, faculty_slug, page_num, row_num,
                 teacher_name, teacher_searchable,
                 subject_name, subject_searchable,
                 lesson_type, weekday, day_label, times_csv, rooms_csv, raw_row,
                 parse_quality, last_seen_at)
             VALUES(?, ?, ?, ?,  ?, ?,  ?, ?,  ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $count = 0;
        foreach ($rows as $r) {
            $ins->execute([
                $pdfDocId, $slug, $r['page'], $r['row_num'],
                $r['teacher'], searchable_text($r['teacher']),
                $r['subject'], searchable_text($r['subject']),
                $r['lesson_type'],
                $r['weekday'], $r['day_label'],
                $r['times'] ? implode(',', $r['times']) : null,
                $r['rooms'] ? implode(',', $r['rooms']) : null,
                $r['raw'], $r['parse_quality'], $now,
            ]);
            $count++;
        }
        $pdo->commit();
        return $count;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** RFC 3986 path-encode while leaving "/" intact. */
function encode_url_path(string $url): string {
    $parts = parse_url($url);
    if (!$parts || empty($parts['path'])) return $url;
    $path = implode('/', array_map('rawurlencode', explode('/', $parts['path'])));
    $rebuilt = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '');
    if (!empty($parts['port'])) $rebuilt .= ':' . $parts['port'];
    $rebuilt .= $path;
    if (!empty($parts['query']))    $rebuilt .= '?' . $parts['query'];
    if (!empty($parts['fragment'])) $rebuilt .= '#' . $parts['fragment'];
    return $rebuilt;
}

function http_get(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT      => 'gtu-schedule-bot/0.1 (+https://gtu.cortexgrid.ge)',
        CURLOPT_ENCODING       => '',
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        throw new RuntimeException('curl: ' . curl_error($ch));
    }
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return ['status' => $status, 'body' => (string)$body];
}
