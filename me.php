<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/match.php';
require_once __DIR__ . '/lib/faculty.php';

/**
 * Personal weekly schedule page. Reads a base64-encoded payload from ?d= that
 * the Chrome extension built from the user's vici.gtu.ge /student/card
 * response, then per-course finds matching lectures + exam PDFs from the
 * locally-scraped data.
 *
 * The payload only ever lives in the URL — nothing is stored server-side.
 */

$payload = null;
$decodeError = null;

// Three ways to receive the payload, in order of preference:
//  1. GET['c'] — array of "Subject|Teacher" strings. Used by /builder.php
//     so the URL stays human-readable and shareable.
//     Example: /me.php?c[]=ხელოვნური ინტელექტი|ცხომელიძე&c[]=...
//  2. GET['d'] — base64-of-JSON. Used by the Chrome extension because it
//     opens this page via window.open() and sends the full vici card.
//  3. POST['payload'] — raw JSON. Internal escape hatch for long payloads.
//  4. Nothing — render the empty state.
$encoded      = (string)($_GET['d'] ?? '');
$pairsParam   = $_GET['c'] ?? null;
$postPayload  = isset($_POST['payload']) ? (string)$_POST['payload'] : '';

if (is_array($pairsParam) && $pairsParam) {
    $courses = [];
    foreach ($pairsParam as $cs) {
        $parts = explode('|', (string)$cs, 2);
        $subject = trim($parts[0] ?? '');
        $teacher = trim($parts[1] ?? '');
        if ($subject !== '' && $teacher !== '') {
            $courses[] = [
                'subject'   => $subject,
                'subjectEn' => '',
                'teacher'   => $teacher,
                'teacherEn' => '',
            ];
        }
    }
    if ($courses) {
        $payload = ['name' => '', 'school' => '', 'courses' => $courses];
    } else {
        $decodeError = 'empty courses';
    }
} elseif ($encoded !== '') {
    $raw = base64_decode($encoded, true);
    if ($raw === false) {
        $decodeError = 'invalid base64';
    } else {
        $payload = json_decode($raw, true);
        if (!is_array($payload) || !isset($payload['courses']) || !is_array($payload['courses'])) {
            $decodeError = 'malformed payload';
            $payload = null;
        }
    }
} elseif ($postPayload !== '') {
    $payload = json_decode($postPayload, true);
    if (!is_array($payload) || !isset($payload['courses']) || !is_array($payload['courses'])) {
        $decodeError = 'malformed payload';
        $payload = null;
    }
}

$pdo = db();

$studentFacultySlug = null;
if ($payload && !empty($payload['school'])) {
    $studentFacultySlug = classify_faculty($payload['school']);
}

$enrichedCourses = [];
if ($payload && $payload['courses']) {
    $facultySlugs = $studentFacultySlug ? [$studentFacultySlug] : [];
    foreach ($payload['courses'] as $c) {
        $subjects = array_values(array_filter([
            (string)($c['subject']   ?? ''),
            (string)($c['subjectEn'] ?? ''),
        ]));
        $teachers = array_values(array_filter([
            (string)($c['teacher']   ?? ''),
            (string)($c['teacherEn'] ?? ''),
        ]));

        $enrichedCourses[] = [
            'meta'       => $c,
            'lectures'   => $subjects && $teachers ? match_lectures_for_course($pdo, $subjects, $teachers) : [],
            'additional' => $subjects && $teachers ? match_additional_for_course($pdo, $subjects, $teachers) : [],
            'midterm'    => $subjects ? match_midterm_pdfs_for_course($pdo, $subjects, $facultySlugs) : [],
        ];
    }
}

$assetVersion = max(
    (int)@filemtime(__DIR__ . '/assets/app.js'),
    (int)@filemtime(__DIR__ . '/assets/style.css'),
    (int)@filemtime(__DIR__ . '/assets/me.css'),
    (int)@filemtime(__DIR__ . '/assets/i18n.js'),
    (int)@filemtime(__DIR__ . '/assets/me.js'),
    (int)@filemtime(__FILE__)
);

$DAY_KA = ['', 'ორშ.', 'სამშ.', 'ოთხშ.', 'ხუთშ.', 'პარ.', 'შაბ.', 'კვ.'];

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }

/** Render a day cell that's hot-swappable to English by JS via data-day. */
function day_cell(?int $weekday, ?string $fallback = null, array $DAY = []): string {
    if ($weekday !== null && $weekday >= 1 && $weekday <= 7) {
        $label = $DAY[$weekday] ?? '?';
        return '<span data-day="' . $weekday . '">' . h($label) . '</span>';
    }
    return h($fallback ?? '—');
}

/**
 * Build a 12-slot × 6-day grid (Mon–Sat) from the user's combined regular +
 * additional lectures. Returns [grid, skip] where grid[s][d] is an array of
 * lessons (deduped by subject+start_time so the same lecture present in two
 * teachers HTMLs collapses to one cell). skip[s][d] = true means a previous
 * row's rowspan covers this cell.
 */
function build_personal_week_grid(array $allLectures): array {
    $grid = array_fill(0, 12, array_fill(0, 6, []));
    $skip = array_fill(0, 12, array_fill(0, 6, false));
    $seen = array_fill(0, 12, array_fill(0, 6, []));

    foreach ($allLectures as $l) {
        $weekday = $l['weekday'] ?? null;
        if (!$weekday || $weekday < 1 || $weekday > 6) continue;
        $col = (int)$weekday - 1;

        if ($l['kind'] === 'regular') {
            $start = max(0, (int)$l['start_slot'] - 1);
            $end   = min(11, (int)($l['end_slot'] ?? $l['start_slot']) - 1);
            $key = ($l['course_name'] ?? '') . '|' . ($l['start_time'] ?? '') . '|' . ($l['teacher'] ?? '');
            if (in_array($key, $seen[$start][$col], true)) continue;
            $seen[$start][$col][] = $key;

            $grid[$start][$col][] = [
                'kind'        => 'regular',
                'span'        => max(1, $end - $start + 1),
                'subject'     => $l['course_name'],
                'teacher'     => $l['teacher'] ?? '',
                'room'        => $l['room'] ?? '',
                'lesson_type' => $l['lesson_type'] ?? '',
                'start_time'  => $l['start_time'] ?? '',
                'end_time'    => $l['end_time'] ?? '',
                'course_idx'  => $l['course_idx'] ?? null,
            ];
            for ($s = $start + 1; $s <= $end; $s++) $skip[$s][$col] = true;
        } else { // additional: each entry in `times[]` is a single hour slot
            foreach (($l['times'] ?? []) as $tm) {
                if (!preg_match('/^(\d{1,2}):(\d{2})$/', $tm, $m)) continue;
                $hour = (int)$m[1];
                $idx = $hour - 9;
                if ($idx < 0 || $idx > 11) continue;
                $key = ($l['course_name'] ?? '') . '|' . $tm . '|' . ($l['teacher'] ?? '');
                if (in_array($key, $seen[$idx][$col], true)) continue;
                $seen[$idx][$col][] = $key;

                $rooms = $l['rooms'] ?? [];
                $grid[$idx][$col][] = [
                    'kind'        => 'additional',
                    'span'        => 1,
                    'subject'     => $l['course_name'],
                    'teacher'     => $l['teacher'] ?? '',
                    'room'        => $rooms ? implode(', ', $rooms) : '',
                    'lesson_type' => $l['lesson_type'] ?? '',
                    'start_time'  => $tm,
                    'end_time'    => sprintf('%02d:00', $hour + 1),
                    'course_idx'  => $l['course_idx'] ?? null,
                ];
            }
        }
    }
    return [$grid, $skip];
}

/**
 * Aggregate every midterm exam across all enriched courses into a single flat
 * list, sorted by date+time. Each entry carries which subject and faculty PDF
 * it came from so the table is self-explanatory.
 */
function aggregate_midterm_exams(array $enrichedCourses): array {
    $flat = [];
    $seen = [];
    foreach ($enrichedCourses as $c) {
        $subjectKa = $c['meta']['subject']   ?? '';
        $subjectEn = $c['meta']['subjectEn'] ?? '';
        foreach ($c['midterm'] ?? [] as $mid) {
            foreach ($mid['exams'] ?? [] as $ex) {
                $key = ($ex['date'] ?? '') . '|' . ($ex['time'] ?? '') . '|' . ($ex['room'] ?? '') . '|' . $subjectKa;
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $flat[] = [
                    'date'        => $ex['date']    ?? null,
                    'time'        => $ex['time']    ?? null,
                    'room'        => $ex['room']    ?? null,
                    'snippet'     => $ex['snippet'] ?? '',
                    'subject_ka'  => $subjectKa,
                    'subject_en'  => $subjectEn,
                    'faculty'     => $mid['faculty_name']  ?? '',
                    'pdf_url'     => $mid['source_url']    ?? '',
                ];
            }
        }
    }
    usort($flat, function ($a, $b) {
        // Order by parseable date, then time. Unparseable dates (null) go last.
        $da = exam_date_sort_key($a['date']);
        $db = exam_date_sort_key($b['date']);
        if ($da !== $db) return $da <=> $db;
        return strcmp($a['time'] ?? '99:99', $b['time'] ?? '99:99');
    });
    return $flat;
}
function exam_date_sort_key(?string $d): string {
    if (!$d || !preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $d, $m)) return '9999';
    return $m[3] . $m[2] . $m[1];
}

/**
 * For multi-teacher courses (e.g. "ავთანდილი ბიჩნიგაური,ლილი პეტრიაშვილი"),
 * group lecture rows by their actual teacher_name so we can render one table
 * per teacher inside the course card. Returns:
 *   [ ['teacher' => '<name>', 'lectures' => [...]], ... ]
 *
 * If only one teacher is involved, returns a single entry with the original
 * meta teacher.
 */
function split_lectures_by_teacher(string $metaTeacher, array $rows, string $rowField = 'teacher_name'): array {
    $teachers = preg_split('/\s*,\s*/u', trim($metaTeacher));
    $teachers = array_values(array_filter($teachers));

    if (count($teachers) <= 1 || !$rows) {
        return [['teacher' => $metaTeacher, 'lectures' => $rows]];
    }

    $groups = [];
    $unmatched = [];
    foreach ($rows as $r) {
        $name = (string)($r[$rowField] ?? '');
        $hit = null;
        foreach ($teachers as $t) {
            // Match by surname/word-overlap so romanised vs Georgian rows still bucket together.
            foreach (preg_split('/\s+/u', mb_strtolower($t, 'UTF-8')) as $word) {
                if (mb_strlen($word, 'UTF-8') >= 4 && mb_stripos($name, $word) !== false) {
                    $hit = $t; break 2;
                }
            }
        }
        if ($hit === null) $unmatched[] = $r;
        else $groups[$hit][] = $r;
    }

    $out = [];
    foreach ($teachers as $t) {
        if (!empty($groups[$t])) $out[] = ['teacher' => $t, 'lectures' => $groups[$t]];
    }
    if ($unmatched) $out[] = ['teacher' => $metaTeacher, 'lectures' => $unmatched];
    return $out;
}

$allLectures = [];
foreach ($enrichedCourses as $i => $c) {
    foreach ($c['lectures'] as $l) {
        $allLectures[] = [
            'kind' => 'regular', 'course_idx' => $i, 'course_name' => $c['meta']['subject'],
            'weekday' => (int)$l['weekday'],
            'start_time' => $l['start_time'], 'end_time' => $l['end_time'],
            // Include the slot indices so build_personal_week_grid puts the
            // lesson in the correct row (was bucketing everything into row 0
            // because these keys were missing).
            'start_slot' => (int)$l['start_slot'],
            'end_slot'   => (int)$l['end_slot'],
            'room' => $l['room'], 'lesson_type' => $l['lesson_type'], 'group_code' => $l['group_code'],
            'teacher' => $l['teacher_name'], 'source_label' => $l['source_label'], 'source_url' => $l['source_url'],
        ];
    }
    foreach ($c['additional'] as $l) {
        $allLectures[] = [
            'kind' => 'additional', 'course_idx' => $i, 'course_name' => $c['meta']['subject'],
            'weekday' => $l['weekday'] ? (int)$l['weekday'] : null, 'day_label' => $l['day_label'] ?? null,
            'times' => $l['times'] ?? [], 'rooms' => $l['rooms'] ?? [], 'lesson_type' => $l['lesson_type'],
            'teacher' => $l['teacher_name'], 'source_label' => $l['source_label'], 'source_url' => $l['source_url'],
        ];
    }
}
usort($allLectures, function ($a, $b) {
    $wa = $a['weekday'] ?? 99; $wb = $b['weekday'] ?? 99;
    if ($wa !== $wb) return $wa <=> $wb;
    $ta = $a['start_time'] ?? ($a['times'][0] ?? '99:99');
    $tb = $b['start_time'] ?? ($b['times'][0] ?? '99:99');
    return strcmp($ta, $tb);
});

[$weeklyGrid, $weeklySkip] = build_personal_week_grid($allLectures);
$weeklyHasAny = false;
foreach ($weeklyGrid as $row) foreach ($row as $cell) if ($cell) { $weeklyHasAny = true; break 2; }

$midtermAgg = aggregate_midterm_exams($enrichedCourses);

/* ─── Page meta ───
 * - Pages opened via the Chrome extension carry a real student name in the
 *   payload. Those stay `noindex,nofollow` — they are personal.
 * - Pages opened via /builder.php (or any `?c[]=` URL) carry only public
 *   subject/teacher strings, which is the same data leqtori.gtu.ge already
 *   publishes. We let robots index those so students searching for "GTU
 *   ცხრილი ხელოვნური ინტელექტი" can land here.                            */
$hasPersonal = $payload && !empty($payload['name']);
$courseSubjects = [];
if ($payload && !empty($payload['courses'])) {
    foreach ($payload['courses'] as $c) {
        $s = trim((string)($c['subject'] ?? ''));
        if ($s !== '') $courseSubjects[] = $s;
    }
}
$subjectPreview = implode(', ', array_slice($courseSubjects, 0, 3));
if (count($courseSubjects) > 3) $subjectPreview .= '…';

if ($hasPersonal) {
    $pageTitle = trim($payload['name']) . ' — ჩემი ცხრილი | GTU ცხრილი';
    $pageDesc  = '';
    $robots    = 'noindex,nofollow';
} elseif ($courseSubjects) {
    $pageTitle = ($subjectPreview ? $subjectPreview . ' — ' : '') . 'სასწავლო ცხრილი | GTU ცხრილი';
    $descSubjects = implode(', ', array_slice($courseSubjects, 0, 6));
    $pageDesc  = 'GTU სასწავლო ცხრილი: ' . $descSubjects
               . ' — ლექციები, საათები და აუდიტორიები. GTU class schedule for: '
               . $descSubjects . ' — lectures, times and rooms.';
    $robots    = 'index,follow,max-image-preview:large';
} else {
    $pageTitle = 'ჩემი ცხრილი | GTU ცხრილი';
    $pageDesc  = 'GTU პერსონალური სასწავლო ცხრილი — ააწყვე საგანი + პედაგოგი წყვილების მიხედვით. Build a personal GTU class schedule from subject + teacher pairs.';
    $robots    = 'noindex,nofollow';
}

$canonicalUrl = 'https://gtu.cortexgrid.ge' . ($_SERVER['REQUEST_URI'] ?? '/me.php');
?>
<!DOCTYPE html>
<html lang="ka">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle) ?></title>
<?php if ($pageDesc): ?>
<meta name="description" content="<?= h($pageDesc) ?>">
<?php endif; ?>
<meta name="robots" content="<?= h($robots) ?>">
<link rel="canonical" href="<?= h($canonicalUrl) ?>">
<?php if (!$hasPersonal && $courseSubjects): /* Open Graph only for indexable pages */ ?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="GTU ცხრილი">
<meta property="og:title" content="<?= h($pageTitle) ?>">
<meta property="og:description" content="<?= h($pageDesc) ?>">
<meta property="og:url" content="<?= h($canonicalUrl) ?>">
<meta property="og:image" content="https://gtu.cortexgrid.ge/assets/og-image.png">
<meta property="og:locale" content="ka_GE">
<meta name="twitter:card" content="summary_large_image">
<?php endif; ?>
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32.png">
<link rel="icon" type="image/png" sizes="128x128" href="/assets/favicon-128.png">
<link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">
<link rel="stylesheet" href="assets/style.css?v=<?= $assetVersion ?>">
<link rel="stylesheet" href="assets/me.css?v=<?= $assetVersion ?>">
</head>
<body class="me-page">

<header class="me-header">
    <div class="container">
        <div class="lang-switcher" role="group" aria-label="Language">
            <button data-lang="ka" type="button" data-i18n="lang.toggle.ka">ქარ</button>
            <button data-lang="en" type="button" data-i18n="lang.toggle.en">ENG</button>
        </div>
        <a href="/" class="back-link" data-i18n="me.back">← gtu.cortexgrid.ge — მთავარი ძიება</a>
        <?php if ($payload): ?>
            <h1><?= h($payload['name'] ?: 'სტუდენტი') ?></h1>
            <p class="muted">
                <?= h($payload['school']) ?>
                <?php if (!empty($payload['special'])): ?> · <?= h($payload['special']) ?><?php endif; ?>
            </p>
            <p class="muted">
                <span data-i18n="me.summary.semester" data-arg-n="<?= h((string)$payload['semester']) ?>">
                    <?= h((string)$payload['semester']) ?>-ე სემესტრი
                </span>
                <?php if (!empty($payload['year'])): ?> · <?= h($payload['year']) ?><?php endif; ?>
                <?php if ($payload['gpa'] !== null): ?>
                    <?php if (!empty($payload['avgResult'])): ?>
                        · <span data-i18n="me.summary.gpa"
                                data-arg-gpa="<?= h((string)$payload['gpa']) ?>"
                                data-arg-grade="<?= h($payload['avgResult']) ?>">
                            GPA <?= h((string)$payload['gpa']) ?> (<?= h($payload['avgResult']) ?>)
                          </span>
                    <?php else: ?>
                        · <span data-i18n="me.summary.gpa_no_grade" data-arg-gpa="<?= h((string)$payload['gpa']) ?>">
                            GPA <?= h((string)$payload['gpa']) ?>
                          </span>
                    <?php endif; ?>
                <?php endif; ?>
                · <span data-i18n="me.summary.courses" data-arg-n="<?= count($enrichedCourses) ?>">
                    <?= count($enrichedCourses) ?> ამჟამინდელი საგანი
                  </span>
            </p>
        <?php else: ?>
            <h1 data-i18n="me.title">ჩემი ცხრილი</h1>
            <p class="muted">პირადი ცხრილი ნაჩვენებია მხოლოდ მას შემდეგ რაც vici.gtu.ge-ზე შეხვალ extension-ით.</p>
        <?php endif; ?>
    </div>
</header>

<main class="container">
<?php if ($payload && $weeklyHasAny): ?>
    <section class="week-grid">
        <h2 data-i18n="me.weekgrid.heading">🗓️ კვირის ცხრილი</h2>
        <p class="muted small" data-i18n="me.weekgrid.help">ჩემი ყველა საგნის ლექცია კვირის ცხრილზე.</p>
        <table class="schedule-table">
            <thead><tr>
                <th></th>
                <?php for ($d = 1; $d <= 6; $d++): ?>
                    <th><span data-day="<?= $d ?>"><?= h($DAY_KA[$d]) ?></span></th>
                <?php endfor; ?>
            </tr></thead>
            <tbody>
            <?php for ($s = 0; $s < 12; $s++): ?>
                <tr>
                    <th class="slot-label"><?= ($s + 1) ?>—<?= sprintf('%02d', $s + 9) ?>:00</th>
                    <?php for ($d = 0; $d < 6; $d++):
                        if ($weeklySkip[$s][$d]) continue;
                        $cell = $weeklyGrid[$s][$d]; ?>
                        <td<?= ($cell && $cell[0]['span'] > 1) ? ' rowspan="' . $cell[0]['span'] . '"' : '' ?>
                            class="<?= $cell ? 'lesson' . ($cell[0]['kind'] === 'additional' ? ' lesson-add' : '') : 'free' ?>">
                            <?php foreach ($cell as $lesson): ?>
                                <div class="grid-lesson" data-teacher="<?= h($lesson['teacher']) ?>">
                                    <a class="subject" href="#course-<?= (int)$lesson['course_idx'] ?>">
                                        <?= h($lesson['subject']) ?>
                                    </a>
                                    <div class="meta">
                                        <?= h($lesson['teacher']) ?>
                                        <?php if (!empty($lesson['lesson_type'])): ?>
                                            · <?= h($lesson['lesson_type']) ?>
                                        <?php endif; ?>
                                        · <?= h($lesson['start_time']) ?>–<?= h($lesson['end_time']) ?>
                                    </div>
                                    <?php if (!empty($lesson['room'])): ?>
                                        <div class="room"><?= h($lesson['room']) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </td>
                    <?php endfor; ?>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>

<?php if ($payload && $midtermAgg): ?>
    <section class="midterm-aggregate">
        <h2 data-i18n="me.midterm.agg.heading" data-arg-n="<?= count($midtermAgg) ?>">
            📝 ყველა შუალედური გამოცდა (<?= count($midtermAgg) ?>)
        </h2>
        <p class="muted small" data-i18n="me.midterm.agg.help">ყველა საგნის შუალედური გამოცდები ერთ ცხრილში — დღის მიხედვით.</p>
        <table class="lecture-list">
            <thead><tr>
                <th data-i18n="me.midterm.agg.col.date">თარიღი</th>
                <th data-i18n="me.midterm.agg.col.time">დრო</th>
                <th data-i18n="me.midterm.agg.col.room">აუდიტორია</th>
                <th data-i18n="me.midterm.agg.col.subject">საგანი</th>
                <th data-i18n="me.midterm.agg.col.faculty">ფაკულტეტი</th>
                <th data-i18n="me.allLec.col.source">წყარო</th>
            </tr></thead>
            <tbody>
            <?php foreach ($midtermAgg as $ex): ?>
                <tr>
                    <td class="ex-date"><?= h($ex['date'] ?? '—') ?></td>
                    <td class="ex-time"><?= h($ex['time'] ?? '—') ?></td>
                    <td class="ex-room"><?= h($ex['room'] ?? '—') ?></td>
                    <td><?= h($ex['subject_ka']) ?></td>
                    <td class="muted"><?= h($ex['faculty']) ?></td>
                    <td class="src">
                        <?php if (!empty($ex['pdf_url'])): ?>
                            <a href="<?= h($ex['pdf_url']) ?>" target="_blank" rel="noopener"
                               data-i18n="me.add.source_link">PDF წყარო</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>

<?php if ($payload && $allLectures): ?>
    <section class="all-lectures">
        <h2 data-i18n="me.allLec.heading" data-arg-n="<?= count($allLectures) ?>">
            📋 ყველა ლექცია (<?= count($allLectures) ?>)
        </h2>
        <p class="muted small" data-i18n="me.allLec.help">ყველა ამჟამინდელი საგნის ლექცია ერთ ცხრილში, დღის მიხედვით.</p>
        <table class="lecture-list big">
            <thead><tr>
                <th data-i18n="me.allLec.col.day">დღე</th>
                <th data-i18n="me.allLec.col.times">დრო</th>
                <th data-i18n="me.allLec.col.subject">საგანი</th>
                <th data-i18n="me.allLec.col.teacher">პედაგოგი</th>
                <th data-i18n="me.allLec.col.room">აუდიტორია</th>
                <th data-i18n="me.allLec.col.type">ფორმა</th>
                <th data-i18n="me.allLec.col.group">ჯგუფი</th>
                <th data-i18n="me.allLec.col.source">წყარო</th>
            </tr></thead>
            <tbody>
            <?php foreach ($allLectures as $l):
                $timeStr  = isset($l['start_time'])
                              ? $l['start_time'] . '–' . $l['end_time']
                              : implode(', ', $l['times'] ?? []);
                $roomStr  = $l['room'] ?? implode(', ', $l['rooms'] ?? []);
                ?>
                <tr class="kind-<?= h($l['kind']) ?>" data-teacher="<?= h($l['teacher'] ?? '') ?>">
                    <td><?= day_cell($l['weekday'] ?? null, $l['day_label'] ?? '—', $DAY_KA) ?></td>
                    <td><?= h($timeStr) ?></td>
                    <td>
                        <a href="#course-<?= (int)$l['course_idx'] ?>"><?= h($l['course_name']) ?></a>
                        <?php if ($l['kind'] === 'additional'): ?>
                            <span class="badge-add" data-i18n="me.allLec.add_badge">დამატ.</span>
                        <?php endif; ?>
                    </td>
                    <td><?= h($l['teacher']) ?></td>
                    <td><?= h($roomStr) ?></td>
                    <td><?= h($l['lesson_type']) ?></td>
                    <td><?= h($l['group_code'] ?? '—') ?></td>
                    <td class="src">
                        <?php if ($l['source_url']): ?>
                            <a href="<?= h($l['source_url']) ?>" target="_blank" rel="noopener"
                               title="<?= h($l['source_label'] ?? '') ?>"
                               data-i18n="me.allLec.source_link">წყარო</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>

<?php if (!$payload): ?>
    <section class="empty-state">
        <h2 data-i18n="me.empty.heading">ცარიელი მდგომარეობა</h2>
        <?php if ($decodeError): ?>
            <p class="error" data-i18n="me.empty.error" data-arg-msg="<?= h($decodeError) ?>">payload error: <?= h($decodeError) ?></p>
        <?php endif; ?>
        <p data-i18n-html="me.empty.html">ამ გვერდს ექსტენშენი ხსნის — დააინსტალირე
           <code>extension/</code> ფოლდერი როგორც unpacked extension Chrome-ში,
           შევიდე vici.gtu.ge-ზე, შემდეგ დააჭირე
           <strong>📅 ჩემი ცხრილი</strong> ღილაკს.</p>
    </section>
<?php elseif (!$enrichedCourses): ?>
    <section class="empty-state">
        <h2 data-i18n="me.no_courses.heading">ამჟამინდელი საგნები ვერ ვიპოვე</h2>
        <p data-i18n="me.no_courses.body">vici-ის card-ში book.semester === view.semester
           ფილტრმა ვერაფერი დააბრუნა. შესაძლოა card-ი ჯერ არ არის სრულად შევსებული.</p>
    </section>
<?php else: ?>
    <?php foreach ($enrichedCourses as $i => $c):
        $m = $c['meta']; ?>
    <article class="course-card" id="course-<?= $i ?>">
        <header class="course-head">
            <h2><?= h($m['subject']) ?></h2>
            <?php if (!empty($m['subjectEn']) && $m['subjectEn'] !== $m['subject']): ?>
                <p class="muted"><?= h($m['subjectEn']) ?></p>
            <?php endif; ?>
            <?php
            // Multi-teacher courses (e.g. "ბიჩნიგაური,პეტრიაშვილი") get a chip
            // per teacher in the header AND lectures grouped per-teacher below.
            $teacherList = array_values(array_filter(array_map('trim',
                preg_split('/\s*,\s*/u', (string)($m['teacher'] ?? '')))));
            $multiTeacher = count($teacherList) > 1;
            ?>
            <p class="course-meta">
                <?php if ($multiTeacher): ?>
                    <span class="teacher-chips">
                    <?php foreach ($teacherList as $tname): ?>
                        <button type="button" class="chip teacher-chip"
                                data-teacher-toggle="<?= h($tname) ?>"
                                title="ეს პედაგოგი არ მასწავლის — დააჭირე რომ დამალო მისი ლექციები / Click to hide this teacher's lectures"><?= h($tname) ?></button>
                    <?php endforeach; ?>
                    </span>
                <?php else: ?>
                    <strong><?= h($m['teacher']) ?></strong>
                <?php endif; ?>
                <?php if (!empty($m['teacherMail'])): ?>
                    · <a href="mailto:<?= h($m['teacherMail']) ?>"><?= h($m['teacherMail']) ?></a>
                <?php endif; ?>
                <?php if (!empty($m['credit'])): ?>
                    · <span data-i18n="me.course.credits" data-arg-n="<?= h((string)$m['credit']) ?>">
                        <?= h((string)$m['credit']) ?> კრედიტი
                      </span>
                <?php endif; ?>
                <?php if (!empty($m['result'])): ?>
                    · <span class="grade grade-<?= h(strtolower($m['result'])) ?>"><?= h($m['result']) ?></span>
                    (<?= h((string)$m['score']) ?>)
                <?php endif; ?>
            </p>
        </header>

        <?php $hasMidterm = !empty($c['midterm']); ?>

        <?php if ($c['lectures']): ?>
            <h3 class="block-h" data-i18n="me.lec.heading" data-arg-n="<?= count($c['lectures']) ?>">
                📅 ლექციები (<?= count($c['lectures']) ?>)
            </h3>
            <?php
            $lectureGroups = $multiTeacher
                ? split_lectures_by_teacher($m['teacher'], $c['lectures'], 'teacher_name')
                : [['teacher' => $m['teacher'], 'lectures' => $c['lectures']]];
            ?>
            <?php foreach ($lectureGroups as $grp): ?>
                <div class="teacher-group" data-teacher="<?= h($grp['teacher']) ?>">
                <?php if ($multiTeacher): ?>
                    <h4 class="teacher-subheading">
                        <span data-i18n="me.course.lectures_for"
                              data-arg-teacher="<?= h($grp['teacher']) ?>">
                            ლექციები <?= h($grp['teacher']) ?>-ისგან
                        </span>
                        <span class="muted">(<?= count($grp['lectures']) ?>)</span>
                    </h4>
                <?php endif; ?>
                <table class="lecture-list">
                    <thead><tr>
                        <th data-i18n="me.lec.col.day">დღე</th>
                        <th data-i18n="me.lec.col.time">დრო</th>
                        <th data-i18n="me.lec.col.room">აუდიტორია</th>
                        <th data-i18n="me.lec.col.type">ფორმა</th>
                        <th data-i18n="me.lec.col.group">ჯგუფი</th>
                        <th data-i18n="me.lec.col.source">წყარო</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($grp['lectures'] as $l): ?>
                        <tr data-teacher="<?= h($l['teacher_name'] ?? '') ?>">
                            <td><?= day_cell((int)$l['weekday'], '?', $DAY_KA) ?></td>
                            <td><?= h($l['start_time']) ?>–<?= h($l['end_time']) ?></td>
                            <td><?= h($l['room']) ?></td>
                            <td><?= h($l['lesson_type']) ?></td>
                            <td><?= h($l['group_code']) ?></td>
                            <td class="src">
                                <?php if ($l['source_url']): ?>
                                    <a href="<?= h($l['source_url']) ?>" target="_blank" rel="noopener" title="<?= h($l['source_section']) ?>">
                                        <?= h($l['source_section'] ?: 'leqtori') ?>
                                    </a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($c['additional']): ?>
            <h3 class="block-h" data-i18n="me.add.heading" data-arg-n="<?= count($c['additional']) ?>">
                📚 დამატებითი კურსების ცხრილი (<?= count($c['additional']) ?>)
            </h3>
            <table class="lecture-list">
                <thead><tr>
                    <th data-i18n="me.add.col.day">დღე</th>
                    <th data-i18n="me.add.col.time">დრო</th>
                    <th data-i18n="me.add.col.room">აუდიტორია</th>
                    <th data-i18n="me.add.col.type">ფორმა</th>
                    <th data-i18n="me.add.col.faculty">ფაკულტეტი</th>
                    <th data-i18n="me.add.col.source">წყარო</th>
                </tr></thead>
                <tbody>
                <?php foreach ($c['additional'] as $l):
                    $lowQ = isset($l['parse_quality']) && $l['parse_quality'] !== null && $l['parse_quality'] < 70; ?>
                    <tr data-teacher="<?= h($l['teacher_name'] ?? '') ?>"<?= $lowQ ? ' class="low-q"' : '' ?>>
                        <td><?= day_cell($l['weekday'] ? (int)$l['weekday'] : null, $l['day_label'] ?: '?', $DAY_KA) ?></td>
                        <td><?= h(implode(', ', $l['times'])) ?></td>
                        <td><?= h(implode(', ', $l['rooms'])) ?></td>
                        <td><?= h($l['lesson_type']) ?></td>
                        <td><?= h($l['faculty_name']) ?></td>
                        <td class="src">
                            <a href="<?= h($l['source_url']) ?>" target="_blank" rel="noopener"
                               data-i18n="me.add.source_link">PDF წყარო</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($hasMidterm): ?>
            <h3 class="block-h" data-i18n="me.midterm.heading">📝 შუალედური გამოცდები</h3>
            <?php foreach ($c['midterm'] as $mid): ?>
                <div class="midterm-block">
                    <div class="midterm-head">
                        <a href="<?= h($mid['source_url']) ?>" target="_blank" rel="noopener">
                            <?= h($mid['faculty_name']) ?>
                        </a>
                        <span class="muted"> · <?= h($mid['source_section']) ?></span>
                    </div>
                    <?php if (!empty($mid['exams'])): ?>
                        <table class="exams-list">
                            <thead><tr>
                                <th data-i18n="me.midterm.col.date">თარიღი</th>
                                <th data-i18n="me.midterm.col.time">დრო</th>
                                <th data-i18n="me.midterm.col.room">აუდიტორია</th>
                                <th data-i18n="me.midterm.col.snippet">ნაწყვეტი PDF-დან</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($mid['exams'] as $ex): ?>
                                <tr>
                                    <td class="ex-date"><?= h($ex['date'] ?? '—') ?></td>
                                    <td class="ex-time"><?= h($ex['time'] ?? '—') ?></td>
                                    <td class="ex-room"><?= h($ex['room'] ?? '—') ?></td>
                                    <td class="ex-snip"><?= h($ex['snippet']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="muted small" data-i18n="me.midterm.no_match">საგანი ნახსენებია PDF-ში, მაგრამ ზუსტი დღე/დრო/აუდიტორიის
                           ამოცნობა ვერ მოხერხდა — გახსენი PDF.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!$c['lectures'] && !$c['additional'] && !$hasMidterm): ?>
            <p class="muted no-data" data-i18n="me.course.no_data">ვერ ვიპოვე ლექციები ან გამოცდის ჩანაწერი ჩვენს სკანირებულ მონაცემებში.</p>
        <?php endif; ?>
    </article>
    <?php endforeach; ?>
<?php endif; ?>
</main>

<footer class="me-footer container">
    <p class="muted" data-i18n="me.footer">
        მონაცემები მოდის leqtori.gtu.ge-ის ჩვენ მიერ სკანირებულ ვერსიიდან.
        პერსონალური ინფო (შენი card) მხოლოდ ბრაუზერში რჩება — სერვერზე არაფერი არ ინახება.
    </p>
    <p class="muted" style="margin-top: 8px;">
        <a href="/privacy.php">Privacy</a> ·
        <a href="/terms.php">Terms</a> ·
        <a href="mailto:voidpoko@gmail.com">voidpoko@gmail.com</a>
    </p>
</footer>

<script src="assets/i18n.js?v=<?= $assetVersion ?>" defer></script>
<script src="assets/me.js?v=<?= $assetVersion ?>" defer></script>
</body>
</html>
