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

$encoded = (string)($_GET['d'] ?? '');
$payload = null;
$decodeError = null;

if ($encoded !== '') {
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
}

$pdo = db();

// Per-course lookups happen up-front so the page is server-rendered (cleaner
// than client-rendered for this view; bookmarkable URL becomes a useful
// shareable link to your own schedule).
// Map the student's faculty (free-text Georgian name in the card) to our
// internal slug — used to scope midterm PDF lookups to their own faculty.
$studentFacultySlug = null;
if ($payload && !empty($payload['school'])) {
    $studentFacultySlug = classify_faculty($payload['school']);
}

$enrichedCourses = [];
if ($payload && $payload['courses']) {
    $facultySlugs = $studentFacultySlug ? [$studentFacultySlug] : [];
    foreach ($payload['courses'] as $c) {
        // Use BOTH the Georgian and English names from the card. Some rows are
        // stored only in one language in our DB (e.g. the IMS additional PDF
        // has English subjects + Latin-romanised teachers, while the regular
        // teachers HTML uses Georgian throughout).
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
    (int)@filemtime(__FILE__)
);

$DAY_KA = ['', 'ორშ.', 'სამშ.', 'ოთხშ.', 'ხუთშ.', 'პარ.', 'შაბ.', 'კვ.'];

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }

/**
 * Flatten every lecture from every course into a single chronological list,
 * tagging each row with which course it belongs to so the user can scan
 * "what's my whole week look like" in one place.
 */
$allLectures = [];
foreach ($enrichedCourses as $i => $c) {
    foreach ($c['lectures'] as $l) {
        $allLectures[] = [
            'kind'         => 'regular',
            'course_idx'   => $i,
            'course_name'  => $c['meta']['subject'],
            'weekday'      => (int)$l['weekday'],
            'start_time'   => $l['start_time'],
            'end_time'     => $l['end_time'],
            'room'         => $l['room'],
            'lesson_type'  => $l['lesson_type'],
            'group_code'   => $l['group_code'],
            'teacher'      => $l['teacher_name'],
            'source_label' => $l['source_label'],
            'source_url'   => $l['source_url'],
        ];
    }
    foreach ($c['additional'] as $l) {
        $allLectures[] = [
            'kind'         => 'additional',
            'course_idx'   => $i,
            'course_name'  => $c['meta']['subject'],
            'weekday'      => $l['weekday'] ? (int)$l['weekday'] : null,
            'day_label'    => $l['day_label'] ?? null,
            'times'        => $l['times'] ?? [],
            'rooms'        => $l['rooms'] ?? [],
            'lesson_type'  => $l['lesson_type'],
            'teacher'      => $l['teacher_name'],
            'source_label' => $l['source_label'],
            'source_url'   => $l['source_url'],
        ];
    }
}
usort($allLectures, function ($a, $b) {
    // sort by weekday (NULL last), then start time
    $wa = $a['weekday'] ?? 99;
    $wb = $b['weekday'] ?? 99;
    if ($wa !== $wb) return $wa <=> $wb;
    $ta = $a['start_time'] ?? ($a['times'][0] ?? '99:99');
    $tb = $b['start_time'] ?? ($b['times'][0] ?? '99:99');
    return strcmp($ta, $tb);
});
?>
<!DOCTYPE html>
<html lang="ka">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $payload['name'] ? h($payload['name']) . ' — ' : '' ?>ჩემი ცხრილი | GTU</title>
<link rel="stylesheet" href="assets/style.css?v=<?= $assetVersion ?>">
<link rel="stylesheet" href="assets/me.css?v=<?= $assetVersion ?>">
</head>
<body class="me-page">

<header class="me-header">
    <div class="container">
        <a href="/" class="back-link">← gtu.cortexgrid.ge</a>
        <?php if ($payload): ?>
            <h1><?= h($payload['name'] ?: 'სტუდენტი') ?></h1>
            <p class="muted">
                <?= h($payload['school']) ?>
                <?php if (!empty($payload['special'])): ?> · <?= h($payload['special']) ?><?php endif; ?>
            </p>
            <p class="muted">
                <?= h((string)$payload['semester']) ?>-ე სემესტრი
                <?php if (!empty($payload['year'])): ?> · <?= h($payload['year']) ?><?php endif; ?>
                <?php if ($payload['gpa'] !== null): ?>
                    · GPA <?= h((string)$payload['gpa']) ?>
                    <?php if (!empty($payload['avgResult'])): ?> (<?= h($payload['avgResult']) ?>)<?php endif; ?>
                <?php endif; ?>
                · <?= count($enrichedCourses) ?> ამჟამინდელი საგანი
            </p>
        <?php else: ?>
            <h1>ჩემი ცხრილი</h1>
            <p class="muted">პირადი ცხრილი ნაჩვენებია მხოლოდ მას შემდეგ რაც vici.gtu.ge-ზე შეხვალ extension-ით.</p>
        <?php endif; ?>
    </div>
</header>

<main class="container">
<?php if ($payload && $allLectures): ?>
    <section class="all-lectures">
        <h2>📋 ყველა ლექცია (<?= count($allLectures) ?>)</h2>
        <p class="muted small">ყველა ამჟამინდელი საგნის ლექცია ერთ ცხრილში, დღის მიხედვით.</p>
        <table class="lecture-list big">
            <thead><tr>
                <th>დღე</th><th>დრო</th><th>საგანი</th><th>პედაგოგი</th>
                <th>აუდიტორია</th><th>ფორმა</th><th>ჯგუფი</th><th>წყარო</th>
            </tr></thead>
            <tbody>
            <?php foreach ($allLectures as $l): ?>
                <?php
                $dayLabel = $l['weekday'] ? ($DAY_KA[$l['weekday']] ?? '?') : ($l['day_label'] ?? '—');
                $timeStr  = isset($l['start_time'])
                              ? $l['start_time'] . '–' . $l['end_time']
                              : implode(', ', $l['times'] ?? []);
                $roomStr  = $l['room'] ?? implode(', ', $l['rooms'] ?? []);
                ?>
                <tr class="kind-<?= h($l['kind']) ?>">
                    <td><?= h($dayLabel) ?></td>
                    <td><?= h($timeStr) ?></td>
                    <td>
                        <a href="#course-<?= (int)$l['course_idx'] ?>"><?= h($l['course_name']) ?></a>
                        <?php if ($l['kind'] === 'additional'): ?>
                            <span class="badge-add">დამატ.</span>
                        <?php endif; ?>
                    </td>
                    <td><?= h($l['teacher']) ?></td>
                    <td><?= h($roomStr) ?></td>
                    <td><?= h($l['lesson_type']) ?></td>
                    <td><?= h($l['group_code'] ?? '—') ?></td>
                    <td class="src">
                        <?php if ($l['source_url']): ?>
                            <a href="<?= h($l['source_url']) ?>" target="_blank" rel="noopener"
                               title="<?= h($l['source_label'] ?? '') ?>">წყარო</a>
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
        <h2>ცარიელი მდგომარეობა</h2>
        <?php if ($decodeError): ?>
            <p class="error">payload error: <?= h($decodeError) ?></p>
        <?php endif; ?>
        <p>ამ გვერდს ექსტენშენი ხსნის — დააინსტალირე
           <code>extension/</code> ფოლდერი როგორც unpacked extension Chrome-ში,
           შევიდე vici.gtu.ge-ზე, შემდეგ დააჭირე
           <strong>📅 ჩემი ცხრილი</strong> ღილაკს.</p>
        <p><a href="/">←  გვერდი</a></p>
    </section>
<?php elseif (!$enrichedCourses): ?>
    <section class="empty-state">
        <h2>ამჟამინდელი საგნები ვერ ვიპოვე</h2>
        <p>vici-ის card-ში <code>book.semester === view.semester</code>
           ფილტრმა ვერაფერი დააბრუნა. შესაძლოა card-ი ჯერ არ არის
           სრულად შევსებული.</p>
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
            <p class="course-meta">
                <strong><?= h($m['teacher']) ?></strong>
                <?php if (!empty($m['teacherMail'])): ?>
                    · <a href="mailto:<?= h($m['teacherMail']) ?>"><?= h($m['teacherMail']) ?></a>
                <?php endif; ?>
                <?php if (!empty($m['credit'])): ?> · <?= h((string)$m['credit']) ?> კრედიტი<?php endif; ?>
                <?php if (!empty($m['result'])): ?>
                    · <span class="grade grade-<?= h(strtolower($m['result'])) ?>"><?= h($m['result']) ?></span>
                    (<?= h((string)$m['score']) ?>)
                <?php endif; ?>
            </p>
        </header>

        <?php
        $totalLecs = count($c['lectures']) + count($c['additional']);
        $hasMidterm = !empty($c['midterm']);
        ?>

        <?php if ($c['lectures']): ?>
            <h3 class="block-h">📅 ლექციები (<?= count($c['lectures']) ?>)</h3>
            <table class="lecture-list">
                <thead><tr>
                    <th>დღე</th><th>დრო</th><th>აუდიტორია</th><th>ფორმა</th><th>ჯგუფი</th><th>წყარო</th>
                </tr></thead>
                <tbody>
                <?php foreach ($c['lectures'] as $l): ?>
                    <tr>
                        <td><?= h($DAY_KA[$l['weekday']] ?? '?') ?></td>
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
        <?php endif; ?>

        <?php if ($c['additional']): ?>
            <h3 class="block-h">📚 დამატებითი კურსების ცხრილი (<?= count($c['additional']) ?>)</h3>
            <table class="lecture-list">
                <thead><tr>
                    <th>დღე</th><th>დრო</th><th>აუდიტორია</th><th>ფორმა</th><th>ფაკულტეტი</th><th>წყარო</th>
                </tr></thead>
                <tbody>
                <?php foreach ($c['additional'] as $l): ?>
                    <?php $lowQ = isset($l['parse_quality']) && $l['parse_quality'] !== null && $l['parse_quality'] < 70; ?>
                    <tr<?= $lowQ ? ' class="low-q"' : '' ?>>
                        <td><?= h($l['weekday'] ? ($DAY_KA[$l['weekday']] ?? $l['day_label']) : ($l['day_label'] ?: '?')) ?></td>
                        <td><?= h(implode(', ', $l['times'])) ?></td>
                        <td><?= h(implode(', ', $l['rooms'])) ?></td>
                        <td><?= h($l['lesson_type']) ?></td>
                        <td><?= h($l['faculty_name']) ?></td>
                        <td class="src">
                            <a href="<?= h($l['source_url']) ?>" target="_blank" rel="noopener">
                                PDF წყარო
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($hasMidterm): ?>
            <h3 class="block-h">📝 შუალედური გამოცდები</h3>
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
                                <th>თარიღი</th><th>დრო</th><th>აუდიტორია</th><th>ნაწყვეტი PDF-დან</th>
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
                        <p class="muted small">საგანი ნახსენებია PDF-ში, მაგრამ ზუსტი დღე/დრო/აუდიტორიის
                           ამოცნობა ვერ მოხერხდა — გახსენი PDF.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!$c['lectures'] && !$c['additional'] && !$hasMidterm): ?>
            <p class="muted no-data">ვერ ვიპოვე ლექციები ან გამოცდის ჩანაწერი ჩვენს სკანირებულ მონაცემებში.
               შესაძლოა შენი ფაკულტეტისთვის (<?= h($payload['school']) ?>) midterm PDF-ი ჯერ არ არის
               სტრუქტურირებულად დამუშავებული, ან leqtori.gtu.ge-ის ცხრილში ეს საგანი
               განცალკევებულ სექციაშია.</p>
        <?php endif; ?>
    </article>
    <?php endforeach; ?>
<?php endif; ?>
</main>

<footer class="me-footer container">
    <p class="muted">
        მონაცემები მოდის leqtori.gtu.ge-ის ჯერ ჩვენ მიერ სკანირებულ ვერსიიდან.
        პერსონალური ინფო (შენი card) მხოლოდ ბრაუზერში რჩება — სერვერზე არაფერი არ ინახება.
    </p>
</footer>
</body>
</html>
