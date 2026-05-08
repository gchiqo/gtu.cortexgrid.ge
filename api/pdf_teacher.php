<?php
declare(strict_types=1);

require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/http.php';

$name = trim((string)($_GET['name'] ?? ''));
if ($name === '') json_error('missing name');

$pdo = db();

$stmt = $pdo->prepare(
    'SELECT al.id, al.faculty_slug, al.row_num, al.page_num,
            al.teacher_name, al.subject_name, al.lesson_type,
            al.weekday, al.day_label, al.times_csv, al.rooms_csv,
            al.parse_quality, al.raw_row,
            p.faculty_name, p.kind AS pdf_kind,
            s.url AS source_url, p.fetched_at,
            s.section_title AS source_section, s.display_label AS source_label
     FROM additional_lecture al
     JOIN pdf_doc p ON p.id = al.pdf_doc_id
     JOIN source  s ON s.id = p.source_id
     WHERE al.teacher_name = ?
     ORDER BY al.weekday IS NULL, al.weekday, al.times_csv, al.row_num'
);
$stmt->execute([$name]);
$lectures = $stmt->fetchAll();

if (!$lectures) json_error('teacher not found in PDFs', 404);

foreach ($lectures as &$l) {
    $l['times'] = $l['times_csv'] ? explode(',', $l['times_csv']) : [];
    $l['rooms'] = $l['rooms_csv'] ? explode(',', $l['rooms_csv']) : [];
    unset($l['times_csv'], $l['rooms_csv']);
}
unset($l);

// Faculties this teacher appears in (deduped).
$faculties = [];
foreach ($lectures as $l) {
    $faculties[$l['faculty_slug']] = $l['faculty_name'];
}
$facultyList = [];
foreach ($faculties as $slug => $kaName) {
    $facultyList[] = ['slug' => $slug, 'name' => $kaName];
}

json_response([
    'name'      => $name,
    'lectures'  => $lectures,
    'faculties' => $facultyList,
]);
