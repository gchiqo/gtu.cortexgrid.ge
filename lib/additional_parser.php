<?php
declare(strict_types=1);

/**
 * Universal "additional courses" PDF row → structured-lecture parser.
 *
 * Strategy: instead of fragile per-faculty column index mappings, identify each
 * field by content-type. Day, time, and room have stable patterns (Georgian and
 * English day names; HH:MM times; ##-### room codes). What's left after these
 * are extracted is teacher + subject, which we split by heuristic.
 *
 * `parse_additional_rows($facultySlug, $logicalRows)` returns:
 *   [ ['row_num' => int, 'page' => int,
 *      'teacher' => ?string, 'subject' => ?string, 'lesson_type' => ?string,
 *      'weekday' => ?int (1..7), 'day_label' => ?string,
 *      'times' => [string], 'rooms' => [string],
 *      'raw' => string,
 *      'parse_quality' => int 0..100], ... ]
 *
 * `SUPPORTED_FACULTIES` lists the slugs we currently structure. Others are still
 * stored as raw text via pdf_doc — see project memory for context.
 */

const SUPPORTED_FACULTIES = ['ims', 'construction', 'agrarian', 'mining', 'architecture', 'business'];

const WEEKDAY_PATTERNS = [
    1 => ['Monday',    'ორშაბათი',   'ორშაბათ'],
    2 => ['Tuesday',   'სამშაბათი',  'სამშაბათ'],
    3 => ['Wednesday', 'ოთხშაბათი',  'ოთხშაბათ'],
    4 => ['Thursday',  'ხუთშაბათი',  'ხუთშაბათ'],
    5 => ['Friday',    'პარასკევი',  'პარასკევ'],
    6 => ['Saturday',  'შაბათი',     'შაბათ'],
    7 => ['Sunday',    'კვირა'],
];

const LESSON_TYPE_PATTERNS = [
    // Georgian full words
    'ლექცია', 'პრაქტიკული', 'ლაბორატორიული', 'სემინარი', 'საკურსო',
    // Georgian abbreviations seen in IMS / business / agrarian PDFs
    'პრაქტ', 'ლაბ.', 'ლაბ',
    // English/abbreviations
    'Lect.', 'Lect', 'Pract.', 'Pract', 'Lab.', 'Lab', 'lect', 'pract',
];

function parse_additional_rows(string $facultySlug, array $logicalRows): array {
    if (!in_array($facultySlug, SUPPORTED_FACULTIES, true)) return [];

    $results = [];
    foreach ($logicalRows as $r) {
        $cells = $r['cells'] ?? [];
        if (count($cells) < 2) continue;

        // The first cell is always the row number; drop it.
        $cells = array_slice($cells, 1);

        // Skip leading metadata columns (e.g. mining's subject_code looks like a
        // room and would confuse content detection).
        $skip = column_skip_for($facultySlug);
        $skippedCells = array_slice($cells, 0, $skip);
        $remaining    = array_slice($cells, $skip);

        // Classify each cell by content. "name" cells are the leftovers — they
        // go to the per-faculty teacher/subject splitter.
        $nameCells   = [];
        $formParts   = [];
        $weekday     = null;
        $dayLabel    = null;
        $times       = [];
        $rooms       = [];

        foreach ($remaining as $cell) {
            $t = $cell['text'];

            // Day check first — a cell whose text is a day name (or contains one).
            if ($weekday === null) {
                $w = detect_weekday($t);
                if ($w !== null) {
                    $weekday  = $w;
                    $dayLabel = day_label_for($w);
                    continue;
                }
            }

            // Time-only cell (e.g. "13:00" or "13:00, 14:00")
            $cellTimes = detect_times($t);
            if ($cellTimes && preg_match('/^[\d:,\s\-\x{2013}\x{2014}სთ]+$/u', $t)) {
                foreach ($cellTimes as $tm) if (!in_array($tm, $times, true)) $times[] = $tm;
                continue;
            }

            // Room-only cell
            $cellRooms = detect_rooms($t);
            if ($cellRooms && count($cellRooms) === 1
                && preg_match('/^\s*\d{2,4}[\-\x{2013}\d\s]*[ა-ჰa-zA-Z\.]{0,4}\s*$/u', $t)) {
                foreach ($cellRooms as $r2) if (!in_array($r2, $rooms, true)) $rooms[] = $r2;
                continue;
            }

            // Form cell — short, matches a lesson-type keyword
            if (mb_strlen($t) <= 25 && detect_lesson_type($t) !== null) {
                $formParts[] = $t;
                continue;
            }

            // Pure punctuation between form parts
            if (preg_match('/^[\s,\.\-\x{2013}\x{2014}]+$/u', $t) && $formParts) {
                $formParts[] = $t;
                continue;
            }

            // Otherwise, candidate for teacher/subject. Drop cells that are too
            // short to be a meaningful name fragment (e.g. an orphaned room
            // suffix like "ბ" from "06-505ბ" that got rendered as a separate
            // fragment — those would otherwise create a huge X-gap that
            // hijacks the largest-gap split).
            if (mb_strlen($t) < 2) continue;
            $nameCells[] = $cell;
        }

        // Salvage pass: bare 3-4 digit cells (e.g. construction's "521", "527"
        // rooms with no hyphen) end up in nameCells after the main classifier.
        // Move them to rooms so the largest-gap split isn't hijacked.
        $reallyNameCells = [];
        foreach ($nameCells as $cell) {
            $t = $cell['text'];
            if (preg_match('/^\d{3,4}$/u', $t) && !in_array($t, $rooms, true)) {
                $rooms[] = $t;
                continue;
            }
            $reallyNameCells[] = $cell;
        }
        $nameCells = $reallyNameCells;

        // Fallback: if any of those got missed, scan the joined text for them.
        $joined = implode(' ', array_map(fn($c) => $c['text'], array_merge($skippedCells, $remaining)));
        if ($weekday === null) {
            $weekday  = detect_weekday($joined);
            $dayLabel = $weekday !== null ? day_label_for($weekday) : null;
        }
        if (!$times) $times = detect_times($joined);
        if (!$rooms) $rooms = detect_rooms($joined);
        $type = $formParts ? trim(implode(' ', $formParts)) : detect_lesson_type($joined);

        // Per-faculty: turn the residual "name cells" into teacher + subject.
        [$teacher, $subject] = split_names_block($facultySlug, $nameCells);
        if (!teacher_name_looks_valid($teacher)) $teacher = null;
        if ($subject !== null && !teacher_name_looks_valid($subject)) $subject = null;

        $quality = score_quality($weekday, $times, $rooms, $teacher, $subject);
        if ($quality < 30) continue;

        $results[] = [
            'page'          => $r['page'],
            'row_num'       => $r['row_num'],
            'teacher'       => $teacher,
            'subject'       => $subject,
            'lesson_type'   => $type,
            'weekday'       => $weekday,
            'day_label'     => $dayLabel,
            'times'         => $times,
            'rooms'         => $rooms,
            'raw'           => implode(' | ', array_map(fn($c) => $c['text'], $cells)),
            'parse_quality' => $quality,
        ];
    }
    return $results;
}

/**
 * Per-faculty residual name-block parser.
 *
 * After day/time/room/form are stripped out, the remaining cells contain
 * teacher + subject (and sometimes program/load/subject_code metadata). The
 * faculty-specific config below handles the variations.
 */
function split_names_block(string $faculty, array $cells): array {
    if (!$cells) return [null, null];

    static $cfg = [
        // 'subject_first' = subject appears in the LEFT group, teacher in the right
        // 'leading_skip'  = drop N cells from the front (program, load, subject_code, etc.)
        'ims'          => ['subject_first' => false, 'leading_skip' => 0],
        'construction' => ['subject_first' => true,  'leading_skip' => 2], // program + load
        'agrarian'     => ['subject_first' => true,  'leading_skip' => 0],
        'mining'       => ['subject_first' => true,  'leading_skip' => 0], // subject_code already skipped via column_skip_for
        'architecture' => ['subject_first' => false, 'leading_skip' => 1], // [teacher][program][subject] — teacher is FIRST cell, then program (skip), then subject
        'business'     => ['subject_first' => false, 'leading_skip' => 0],
    ];
    $c = $cfg[$faculty] ?? ['subject_first' => false, 'leading_skip' => 0];

    if ($c['leading_skip'] > 0) {
        $cells = array_slice($cells, $c['leading_skip']);
    }
    if (!$cells) return [null, null];
    if (count($cells) === 1) {
        $only = trim($cells[0]['text']);
        return $c['subject_first'] ? [null, $only] : [$only, null];
    }

    // Find the largest X gap between consecutive cells; split there.
    $maxGap = -INF;
    $splitAt = 1;
    for ($i = 1; $i < count($cells); $i++) {
        $gap = $cells[$i]['x'] - $cells[$i - 1]['x'];
        if ($gap > $maxGap) {
            $maxGap = $gap;
            $splitAt = $i;
        }
    }
    $left  = array_slice($cells, 0, $splitAt);
    $right = array_slice($cells, $splitAt);

    $leftText  = clean_field(implode(' ', array_map(fn($c) => $c['text'], $left)));
    $rightText = clean_field(implode(' ', array_map(fn($c) => $c['text'], $right)));

    return $c['subject_first'] ? [$rightText, $leftText] : [$leftText, $rightText];
}

function detect_weekday(string $text): ?int {
    foreach (WEEKDAY_PATTERNS as $n => $words) {
        foreach ($words as $w) {
            if (mb_stripos($text, $w) !== false) return $n;
        }
    }
    return null;
}

function day_label_for(int $weekday): ?string {
    return WEEKDAY_PATTERNS[$weekday][1] ?? WEEKDAY_PATTERNS[$weekday][0] ?? null;
}

/** Returns ['HH:MM', ...] in source order, deduped. */
function detect_times(string $text): array {
    $out = [];

    // HH:MM
    if (preg_match_all('/\b(\d{1,2}):(\d{2})\b/u', $text, $m, PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            $h = (int)$hit[1]; $mn = (int)$hit[2];
            if ($h < 0 || $h > 23 || $mn > 59) continue;
            $t = sprintf('%02d:%02d', $h, $mn);
            if (!in_array($t, $out, true)) $out[] = $t;
        }
    }

    // Bare hours next to "სთ" (the Georgian abbreviation for hour) — e.g.
    // "10-11 -12 სთ" or "11 -12 სთ" or "11 სთ". Only match when "სთ" is nearby
    // so that "10-11" isn't misread as time when it's a date or score.
    if (preg_match_all('/(\d{1,2})\s*[-\x{2013}]?\s*(?:\d{1,2}\s*[-\x{2013}]?\s*)?\d{0,2}\s*სთ\b/u', $text, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as $hit) {
            $fragment = $hit[0];
            // Pull individual hour digits out of the fragment.
            if (preg_match_all('/\d{1,2}/', $fragment, $hours)) {
                foreach ($hours[0] as $h) {
                    $h = (int)$h;
                    if ($h < 0 || $h > 23) continue;
                    $t = sprintf('%02d:00', $h);
                    if (!in_array($t, $out, true)) $out[] = $t;
                }
            }
        }
    }

    sort($out);
    return $out;
}

/** Returns room codes. */
function detect_rooms(string $text): array {
    $rooms = [];

    // ##-###[suffix] pattern (e.g. 01-449, 09-304, 06-803ა, 03–334)
    if (preg_match_all('/\b(\d{2}[\-\x{2013}]\d{3,4}[ა-ჰa-zA-Z\.]{0,3})\b/u', $text, $m)) {
        foreach ($m[1] as $r) if (!in_array($r, $rooms, true)) $rooms[] = $r;
    }
    // ###[ბაც]-style without dash (e.g. 901ბ, 619ა, 104ა, 8778ც)
    if (preg_match_all('/\b(\d{3,4}[ა-ჰ]{1,2})(?!\d)/u', $text, $m)) {
        foreach ($m[1] as $r) if (!in_array($r, $rooms, true)) $rooms[] = $r;
    }
    return $rooms;
}

function detect_lesson_type(string $text): ?string {
    foreach (LESSON_TYPE_PATTERNS as $kw) {
        if (mb_stripos($text, $kw) !== false) return $kw;
    }
    return null;
}

function clean_field(string $s): string {
    // Strip any trailing detected room/time bits that bled into the column.
    $s = preg_replace('/\b\d{1,2}:\d{2}\b/u', '', $s) ?? $s;
    $s = preg_replace('/\b\d{2}[\-\x{2013}]\d{3,4}[ა-ჰa-zA-Z\.]{0,3}\b/u', '', $s) ?? $s;
    foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday',
              'ორშაბათი','სამშაბათი','ოთხშაბათი','ხუთშაბათი','პარასკევი','შაბათი','კვირა'] as $w) {
        $s = preg_replace('/' . preg_quote($w, '/') . '/u', '', $s) ?? $s;
    }
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    // Multi-byte safe trim: strip leading/trailing whitespace and punctuation,
    // including unicode dashes (U+2013, U+2014). Plain trim() with these chars
    // in the trim list would chop multi-byte sequences in half.
    $s = preg_replace('/^[\s\.,\-\x{2013}\x{2014}]+|[\s\.,\-\x{2013}\x{2014}]+$/u', '', $s) ?? $s;
    return $s;
}

function column_skip_for(string $faculty): int {
    static $cfg = [
        'mining' => 1,  // skip the subject-code column ("0305დ" etc.) from detection
    ];
    return $cfg[$faculty] ?? 0;
}

/**
 * Reject parser output where the "teacher" column ended up containing junk
 * (numbers, punctuation, empty strings) instead of a real name. Also rejects
 * very short fragments. Keeps Georgian, Latin, Cyrillic — anything with at
 * least 2 consecutive letter characters of any script.
 */
function teacher_name_looks_valid(?string $name): bool {
    if ($name === null) return false;
    $name = trim($name);
    if (mb_strlen($name) < 3) return false;
    return (bool)preg_match('/\p{L}{2,}/u', $name);
}

function score_quality(?int $weekday, array $times, array $rooms, ?string $teacher, ?string $subject): int {
    $score = 0;
    if ($weekday !== null) $score += 25;
    if ($times)  $score += 20;
    if ($rooms)  $score += 20;
    if ($teacher !== null && $teacher !== '') $score += 20;
    if ($subject !== null && $subject !== '') $score += 15;
    return $score;
}
