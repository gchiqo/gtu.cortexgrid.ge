<?php
declare(strict_types=1);

require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/http.php';

$name = trim((string)($_GET['name'] ?? ''));
if ($name === '') json_error('missing name');

$pdo = db();

// Combine matches from both sources. We match by exact subject name (the name
// the user clicked on the search-result list, which IS the canonical name as
// stored), so EN ↔ GE titles do NOT auto-merge.
$sql = "
    SELECT
        'html'                AS source,
        l.weekday             AS weekday,
        NULL                  AS day_label,
        l.start_time          AS time_one,
        l.end_time            AS end_time,
        l.start_slot          AS start_slot,
        l.end_slot            AS end_slot,
        l.room                AS room_one,
        NULL                  AS times_csv,
        NULL                  AS rooms_csv,
        l.lesson_type         AS lesson_type,
        l.group_code          AS group_code,
        t.name                AS teacher_name,
        s.name                AS subject_name,
        s.code                AS subject_code,
        NULL                  AS faculty_slug,
        NULL                  AS faculty_name,
        NULL                  AS parse_quality,
        src.section_title     AS source_section,
        src.display_label     AS source_label,
        src.url               AS source_url
    FROM lecture l
    JOIN teacher t   ON t.id = l.teacher_id
    JOIN subject s   ON s.id = l.subject_id
    JOIN source  src ON src.id = l.source_id
    WHERE s.name = ?

    UNION ALL

    SELECT
        'pdf',
        al.weekday,
        al.day_label,
        NULL, NULL, NULL, NULL, NULL,    -- time_one, end_time, start_slot, end_slot, room_one
        al.times_csv,
        al.rooms_csv,
        al.lesson_type,
        NULL,                            -- group_code
        al.teacher_name,
        al.subject_name,
        NULL,                            -- subject_code
        al.faculty_slug,
        p.faculty_name,
        al.parse_quality,
        src.section_title,
        src.display_label,
        src.url
    FROM additional_lecture al
    JOIN pdf_doc p   ON p.id = al.pdf_doc_id
    JOIN source  src ON src.id = p.source_id
    WHERE al.subject_name = ?

    ORDER BY weekday IS NULL, weekday, time_one, times_csv, teacher_name
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$name, $name]);
$rows = $stmt->fetchAll();

if (!$rows) json_error('subject not found', 404);

// Normalize the row shape: collapse the per-source quirks into a uniform schema.
foreach ($rows as &$r) {
    if ($r['source'] === 'html') {
        $r['times'] = [$r['time_one']];
        $r['rooms'] = $r['room_one'] ? [$r['room_one']] : [];
    } else {
        $r['times'] = $r['times_csv'] ? explode(',', $r['times_csv']) : [];
        $r['rooms'] = $r['rooms_csv'] ? explode(',', $r['rooms_csv']) : [];
    }
    unset($r['time_one'], $r['room_one'], $r['times_csv'], $r['rooms_csv'], $r['start_slot'], $r['end_slot'], $r['end_time']);
}
unset($r);

// Aggregate metadata.
$teachers  = array_unique(array_column($rows, 'teacher_name'));
$faculties = array_values(array_unique(array_filter(array_column($rows, 'faculty_slug'))));

json_response([
    'name'      => $name,
    'lectures'  => $rows,
    'teachers'  => array_values($teachers),
    'faculties' => $faculties,
]);
