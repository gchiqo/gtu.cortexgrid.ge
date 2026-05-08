<?php
declare(strict_types=1);

require_once __DIR__ . '/translit.php';

/**
 * Build a list of LIKE substrings for a name like "ლუკა ცხომელიძე" — gives us
 * the original lowered form plus its Latin transliterations, so a query
 * coming in as Georgian still matches DB rows stored in Latin (and vice
 * versa). Returns the list as a single LIKE pattern array (each prefixed/
 * suffixed with %).
 */
function name_like_patterns(?string $value): array {
    if ($value === null) return [];
    $value = trim($value);
    if ($value === '') return [];

    // The `prof` field on a vici card sometimes lists multiple co-teachers
    // separated by a comma — e.g. "ავთანდილი ბიჩნიგაური,ლილი პეტრიაშვილი".
    // Treat each as a separate name candidate so a row matching either
    // teacher is found.
    $candidates = preg_split('/\s*[,;]\s*/u', $value) ?: [$value];
    $forms = [];
    foreach ($candidates as $cand) {
        $cand = trim($cand);
        if ($cand === '') continue;
        $lower = mb_strtolower($cand, 'UTF-8');
        $forms[] = $lower;
        foreach (transliterate_variants($lower) as $v) {
            if ($v !== '' && !in_array($v, $forms, true)) $forms[] = $v;
        }
    }
    $forms = array_values(array_unique($forms));
    return array_map(fn($s) => '%' . $s . '%', $forms);
}

/**
 * Looser variant for cases where strict full-phrase matching produced no
 * hits. Adds per-word patterns (and their Latin transliterations) so a
 * "ცხომელიძე ლუკა" query can match a row whose searchable column reads
 * "Tskhomelidze Luka" — the surname alone is the bridge.
 */
function name_like_patterns_loose(?string $value): array {
    $base = name_like_patterns($value);
    if (!$base) return [];
    $lower = mb_strtolower(trim((string)$value), 'UTF-8');
    foreach (preg_split('/\s+/u', $lower) as $word) {
        if (mb_strlen($word, 'UTF-8') >= 4) {
            $word_t = transliterate_ka_to_latin($word);
            $base[] = '%' . $word . '%';
            if ($word_t !== $word) $base[] = '%' . $word_t . '%';
        }
    }
    return array_values(array_unique($base));
}

/**
 * For a given (subject, teacher) pair from a vici card, find every matching
 * row in the lecture table (HTML-source schedule). We require BOTH a subject
 * match AND a teacher match — otherwise unrelated subjects taught by the same
 * teacher would leak in.
 */
/**
 * Pull the meaningful words out of a subject title.
 *
 * Parenthesised course codes ("(ICT25408E1-LP)") get stripped because they
 * vary between vici and leqtori for the same subject. Sub-4-character words
 * are dropped (Georgian "და" / English "of" etc.) — they create too much
 * noise. Returns lowercased, deduplicated.
 */
function significant_words(string $s): array {
    $s = preg_replace('/\([^)]*\)/u', ' ', $s) ?? $s;
    $tokens = preg_split('/[\s,.\\\\\/\-—–:;]+/u', mb_strtolower($s, 'UTF-8')) ?: [];
    $out = [];
    foreach ($tokens as $w) {
        if ($w !== '' && mb_strlen($w, 'UTF-8') >= 4) $out[] = $w;
    }
    return array_values(array_unique($out));
}

/**
 * Looser-than-strict subject match: returns true if a row subject shares
 * enough significant words with any of the card's subject candidates that
 * we're confident it's the same course despite small wording differences
 * (e.g. "სისტემა Hadoop" in the card vs "ეკოსისტემა Hadoop" in leqtori).
 *
 * Uses substring tests rather than equality so a row's "ეკოსისტემა"
 * still satisfies the card's "სისტემა" (it embeds it). The threshold
 * combination — ≥3 matches AND ≥50% of card words — was chosen so:
 *   - Bichnigauri's true Hadoop row scores 5/6 → matches
 *   - Lili's "Fundamentals of Database Systems" scores 1/5 (just "data")
 *     → does NOT match
 *   - Bichnigauri's other course "Big Data Fundamentals" scores 1/5 → does
 *     NOT match (we don't pull her unrelated lectures into this course)
 */
function subject_overlaps_enough(string $rowSubject, array $cardSubjectCandidates): bool {
    $rowLower = mb_strtolower($rowSubject, 'UTF-8');
    foreach ($cardSubjectCandidates as $cs) {
        $cardWords = significant_words($cs);
        $n = count($cardWords);
        if ($n < 3) continue; // single-word subjects can't be fuzzy-matched safely
        $matched = 0;
        foreach ($cardWords as $w) {
            if (mb_stripos($rowLower, $w) !== false) $matched++;
        }
        if ($matched >= 3 && ($matched / $n) >= 0.5) return true;
    }
    return false;
}

/** Same SQL shape as lecture_query() but with subject filter dropped — we
 *  post-filter in PHP using subject_overlaps_enough() instead. Used as a
 *  fallback when both strict-subject passes return nothing. */
function fuzzy_lecture_query(PDO $pdo, array $subjectCandidates, array $teacherCandidates): array {
    $tchPats = merge_patterns($teacherCandidates, true);
    if (!$tchPats || !$subjectCandidates) return [];

    $tchOrs = implode(' OR ', array_fill(0, count($tchPats), 'COALESCE(t.searchable, LOWER(t.name)) LIKE ?'));
    $sql = "SELECT
                l.weekday, l.start_slot, l.end_slot, l.start_time, l.end_time,
                l.lesson_type, l.room, l.group_code,
                t.name AS teacher_name,
                s.name AS subject_name, s.code AS subject_code,
                src.url           AS source_url,
                src.section_title AS source_section,
                src.display_label AS source_label,
                src.fetched_at    AS source_fetched_at
            FROM lecture l
            JOIN teacher t ON t.id = l.teacher_id
            JOIN subject s ON s.id = l.subject_id
            JOIN source  src ON src.id = l.source_id
            WHERE ($tchOrs)
            ORDER BY src.id, l.weekday, l.start_slot";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($tchPats);
    $rows = $stmt->fetchAll();
    return array_values(array_filter($rows,
        fn($r) => subject_overlaps_enough((string)$r['subject_name'], $subjectCandidates)
    ));
}

function fuzzy_additional_query(PDO $pdo, array $subjectCandidates, array $teacherCandidates): array {
    $tchPats = merge_patterns($teacherCandidates, true);
    if (!$tchPats || !$subjectCandidates) return [];

    $tchOrs = implode(' OR ', array_fill(0, count($tchPats), 'al.teacher_searchable LIKE ?'));
    $sql = "SELECT
                al.weekday, al.day_label, al.times_csv, al.rooms_csv,
                al.lesson_type, al.parse_quality,
                al.teacher_name, al.subject_name, al.faculty_slug,
                p.faculty_name, p.kind AS pdf_kind,
                src.url           AS source_url,
                src.section_title AS source_section,
                src.display_label AS source_label,
                src.fetched_at    AS source_fetched_at
            FROM additional_lecture al
            JOIN pdf_doc p   ON p.id   = al.pdf_doc_id
            JOIN source  src ON src.id = p.source_id
            WHERE ($tchOrs)
            ORDER BY al.weekday IS NULL, al.weekday, al.times_csv";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($tchPats);
    $rows = $stmt->fetchAll();
    // Caller (match_additional_for_course) does the times_csv/rooms_csv split.
    return array_values(array_filter($rows,
        fn($r) => subject_overlaps_enough((string)$r['subject_name'], $subjectCandidates)
    ));
}

function match_lectures_for_course(PDO $pdo, array $subjectCandidates, array $teacherCandidates): array {
    // Subject must be a FULL-phrase match — never per-word loose. A subject
    // like "Big Data storage and processing system Hadoop" splits into words
    // that include "data" and "system" and "storage", which over-match against
    // unrelated subjects ("Fundamentals of *Data*base *System*s", "*Storage*
    // accounting", etc). Real-world false positive that surfaced when a
    // co-teacher on the vici card teaches a totally different course.
    //
    // Teacher CAN fall back to loose so a Georgian-stored "ცხომელიძე" still
    // matches a Latin-only "Tskhomelidze" row and surname differences like
    // "Avtandili" vs "Avtandil" don't drop legitimate hits.
    $subj = merge_patterns($subjectCandidates, false);

    $rows = lecture_query($pdo, $subj, merge_patterns($teacherCandidates, false));
    if (!$rows) {
        $rows = lecture_query($pdo, $subj, merge_patterns($teacherCandidates, true));
    }
    if (!$rows) {
        // Fuzzy: card and leqtori sometimes use slightly different wording for
        // the same subject (e.g. "სისტემა Hadoop" vs "ეკოსისტემა Hadoop").
        // Pull the teacher's rows and post-filter by significant-word overlap.
        $rows = fuzzy_lecture_query($pdo, $subjectCandidates, $teacherCandidates);
    }
    return $rows;
}

function merge_patterns(array $candidates, bool $loose): array {
    $out = [];
    foreach ($candidates as $c) {
        $pats = $loose ? name_like_patterns_loose($c) : name_like_patterns($c);
        foreach ($pats as $p) if (!in_array($p, $out, true)) $out[] = $p;
    }
    return $out;
}

function lecture_query(PDO $pdo, array $subjPats, array $tchPats): array {
    if (!$subjPats || !$tchPats) return [];

    $subjOrs = implode(' OR ', array_fill(0, count($subjPats), 'COALESCE(s.searchable, LOWER(s.name)) LIKE ?'));
    $tchOrs  = implode(' OR ', array_fill(0, count($tchPats),  'COALESCE(t.searchable, LOWER(t.name)) LIKE ?'));

    $sql = "SELECT
                l.weekday, l.start_slot, l.end_slot, l.start_time, l.end_time,
                l.lesson_type, l.room, l.group_code,
                t.name AS teacher_name,
                s.name AS subject_name, s.code AS subject_code,
                src.url           AS source_url,
                src.section_title AS source_section,
                src.display_label AS source_label,
                src.fetched_at    AS source_fetched_at
            FROM lecture l
            JOIN teacher t ON t.id = l.teacher_id
            JOIN subject s ON s.id = l.subject_id
            JOIN source  src ON src.id = l.source_id
            WHERE ($subjOrs) AND ($tchOrs)
            ORDER BY src.id, l.weekday, l.start_slot";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([...$subjPats, ...$tchPats]);
    return $stmt->fetchAll();
}

/**
 * Same idea for additional_lecture (PDF-derived rows). Returns rows with the
 * source's section heading attached so the UI can label each block.
 */
function match_additional_for_course(PDO $pdo, array $subjectCandidates, array $teacherCandidates): array {
    // Same rule as lectures: strict subject only; teacher can be loose.
    $subj = merge_patterns($subjectCandidates, false);

    $rows = additional_query($pdo, $subj, merge_patterns($teacherCandidates, false));
    if (!$rows) {
        $rows = additional_query($pdo, $subj, merge_patterns($teacherCandidates, true));
    }
    if (!$rows) {
        $rows = fuzzy_additional_query($pdo, $subjectCandidates, $teacherCandidates);
    }
    foreach ($rows as &$r) {
        $r['times'] = $r['times_csv'] ? explode(',', $r['times_csv']) : [];
        $r['rooms'] = $r['rooms_csv'] ? explode(',', $r['rooms_csv']) : [];
        unset($r['times_csv'], $r['rooms_csv']);
    }
    return $rows;
}

function additional_query(PDO $pdo, array $subjPats, array $tchPats): array {
    if (!$subjPats || !$tchPats) return [];

    $subjOrs = implode(' OR ', array_fill(0, count($subjPats), 'al.subject_searchable LIKE ?'));
    $tchOrs  = implode(' OR ', array_fill(0, count($tchPats),  'al.teacher_searchable LIKE ?'));

    $sql = "SELECT
                al.weekday, al.day_label, al.times_csv, al.rooms_csv,
                al.lesson_type, al.parse_quality,
                al.teacher_name, al.subject_name, al.faculty_slug,
                p.faculty_name, p.kind AS pdf_kind,
                src.url           AS source_url,
                src.section_title AS source_section,
                src.display_label AS source_label,
                src.fetched_at    AS source_fetched_at
            FROM additional_lecture al
            JOIN pdf_doc p   ON p.id   = al.pdf_doc_id
            JOIN source  src ON src.id = p.source_id
            WHERE ($subjOrs) AND ($tchOrs)
            ORDER BY al.weekday IS NULL, al.weekday, al.times_csv";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([...$subjPats, ...$tchPats]);
    return $stmt->fetchAll();
}

/**
 * Midterm PDFs are catalog-only (no structured rows). Best we can do is
 * search their raw_text for a mention of the subject, then surface the PDF
 * link so the student can open it themselves.
 */
/**
 * Midterm PDFs are catalog-only (no structured rows). We restrict to:
 *   - the student's own faculty (via $facultySlugs)
 *   - the two general exam centers (always relevant)
 * — otherwise a generic phrase like "ფიზიკა" matches every faculty's midterm
 * and produces noise.
 */
function match_midterm_pdfs_for_course(PDO $pdo, array $subjectCandidates, array $facultySlugs = []): array {
    $subjPats = merge_patterns($subjectCandidates, false);
    if (!$subjPats) return [];

    // Always include the two general exam centers if they exist in the DB.
    $slugs = array_values(array_unique(array_filter(array_merge(
        $facultySlugs,
        ['exam_center_1', 'exam_center_2']
    ))));

    $textOrs = implode(' OR ', array_fill(0, count($subjPats), 'LOWER(p.raw_text) LIKE ?'));
    $slugIn  = $slugs ? implode(',', array_fill(0, count($slugs), '?')) : "''";
    $sql = "SELECT
                p.id AS pdf_doc_id, p.faculty_slug, p.faculty_name, p.raw_text,
                src.url           AS source_url,
                src.section_title AS source_section,
                src.display_label AS source_label
            FROM pdf_doc p
            JOIN source src ON src.id = p.source_id
            WHERE p.kind = 'midterm_pdf'
              AND p.faculty_slug IN ($slugIn)
              AND ($textOrs)
            GROUP BY p.id, p.faculty_slug, p.faculty_name, p.raw_text, src.url, src.section_title, src.display_label
            ORDER BY p.faculty_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([...$slugs, ...$subjPats]);
    $rows = $stmt->fetchAll();

    // For each match, extract concrete exam (date/time/room) entries from
    // the raw_text — the IMS midterm PDF in particular has lines shaped like
    //   "<subject> <teacher> DD.MM.YYYY HH:MM <room>"
    // — regex-friendly even though the underlying table layout isn't.
    foreach ($rows as &$r) {
        $r['exams'] = extract_midterm_exams($r['raw_text'], $subjectCandidates);
        unset($r['raw_text']); // don't ship the whole PDF text to the page
    }
    return $rows;
}

/**
 * Pull concrete exam rows out of a midterm PDF's raw_text for a given subject.
 *
 * Looks for any of the candidate subject phrases inside raw_text, then for
 * each occurrence pulls a window of text (the line + ~200 chars of context)
 * and extracts the first DD.MM.YYYY date, the next HH:MM time after it, and
 * the next room-shaped token after that. Returns dedup'd entries keyed by
 * (date, time, room) so we don't show the same exam twice if its subject
 * line wraps across two physical lines in the PDF.
 */
function extract_midterm_exams(string $rawText, array $subjectCandidates): array {
    $exams = [];
    $seen  = [];

    // Search by each candidate (KA + EN). Phrase-only — no per-word splitting.
    foreach ($subjectCandidates as $cand) {
        $cand = trim($cand);
        if ($cand === '' || mb_strlen($cand, 'UTF-8') < 4) continue;

        $lower = mb_strtolower($rawText, 'UTF-8');
        $needle = mb_strtolower($cand, 'UTF-8');

        $offset = 0;
        while (($pos = mb_strpos($lower, $needle, $offset, 'UTF-8')) !== false) {
            // Window: the subject occurrence + 250 chars of following text.
            $window = mb_substr($rawText, $pos, mb_strlen($cand, 'UTF-8') + 250, 'UTF-8');

            // Pull date / time / room with regex.
            $exam = ['date' => null, 'time' => null, 'room' => null, 'snippet' => trim($window)];
            if (preg_match('/\b(\d{1,2})\.(\d{1,2})\.(\d{4})\b/u', $window, $dm)) {
                $exam['date'] = sprintf('%02d.%02d.%s', (int)$dm[1], (int)$dm[2], $dm[3]);
                // Time should appear AFTER the date.
                $afterDate = mb_substr($window, mb_strpos($window, $dm[0], 0, 'UTF-8') + mb_strlen($dm[0], 'UTF-8'), 80, 'UTF-8');
                if (preg_match('/\b(\d{1,2}):(\d{2})\b/u', $afterDate, $tm)) {
                    $h = (int)$tm[1]; $m = (int)$tm[2];
                    if ($h >= 0 && $h < 24 && $m < 60) {
                        $exam['time'] = sprintf('%02d:%02d', $h, $m);
                    }
                }
                // Room should appear AFTER the time, anywhere in remaining window.
                $afterTime = $exam['time']
                    ? mb_substr($afterDate, mb_strpos($afterDate, $exam['time'], 0, 'UTF-8') + 5, 80, 'UTF-8')
                    : $afterDate;
                if (preg_match('/\b(\d{2}-\d{3}[ა-ჰa-zA-Z]?)\b/u', $afterTime, $rm)) {
                    $exam['room'] = $rm[1];
                }
            }

            // Trim a useful preview of the line (just the subject + teacher + first time/room).
            $singleLine = preg_replace('/\s+/u', ' ', $exam['snippet']);
            $exam['snippet'] = mb_substr($singleLine, 0, 220, 'UTF-8');

            $key = ($exam['date'] ?? '?') . '|' . ($exam['time'] ?? '?') . '|' . ($exam['room'] ?? '?');
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $exams[] = $exam;
            }

            $offset = $pos + mb_strlen($needle, 'UTF-8');
        }
    }
    return $exams;
}
