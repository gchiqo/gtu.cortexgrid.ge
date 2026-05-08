<?php
declare(strict_types=1);

require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/http.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$kind = trim((string)($_GET['kind'] ?? 'additional_pdf'));
if ($slug === '' || !preg_match('/^[a-z_0-9]+$/', $slug)) json_error('invalid slug');
if (!in_array($kind, ['additional_pdf', 'midterm_pdf'], true)) json_error('invalid kind');

$pdo = db();
$stmt = $pdo->prepare(
    "SELECT p.id AS pdf_doc_id, p.faculty_slug, p.faculty_name, p.kind,
            p.page_count, p.raw_text, p.fetched_at,
            s.url           AS source_url,
            s.section_title AS source_section,
            s.display_label AS source_label
     FROM pdf_doc p
     JOIN source s ON s.id = p.source_id
     WHERE p.faculty_slug = ? AND p.kind = ?"
);
$stmt->execute([$slug, $kind]);
$doc = $stmt->fetch();
if (!$doc) json_error('faculty not found for that kind', 404);

$stmt = $pdo->prepare(
    'SELECT page_num, row_num, teacher_name, subject_name, lesson_type,
            weekday, day_label, times_csv, rooms_csv, parse_quality, raw_row
     FROM additional_lecture
     WHERE pdf_doc_id = ?
     ORDER BY weekday IS NULL, weekday, times_csv, row_num'
);
$stmt->execute([(int)$doc['pdf_doc_id']]);
$lectures = $stmt->fetchAll();

foreach ($lectures as &$l) {
    $l['times'] = $l['times_csv'] !== null && $l['times_csv'] !== '' ? explode(',', $l['times_csv']) : [];
    $l['rooms'] = $l['rooms_csv'] !== null && $l['rooms_csv'] !== '' ? explode(',', $l['rooms_csv']) : [];
    unset($l['times_csv'], $l['rooms_csv']);
}
unset($l);

$doc['lectures'] = $lectures;
$doc['structured'] = !empty($lectures);
unset($doc['pdf_doc_id']);

json_response($doc);
