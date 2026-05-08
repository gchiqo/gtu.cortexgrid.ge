<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/http.php';

$code = trim((string)($_GET['code'] ?? ''));
if ($code === '') json_error('missing code');

$pdo = db();

$stmt = $pdo->prepare(
    'SELECT
         l.weekday, l.start_slot, l.end_slot, l.start_time, l.end_time,
         l.lesson_type, l.room, l.group_code, l.last_seen_at,
         s.name AS subject_name, s.code AS subject_code,
         t.id   AS teacher_id, t.name AS teacher_name,
         src.url           AS source_url,
         src.fetched_at    AS source_fetched_at,
         src.section_title AS source_section,
         src.display_label AS source_label
     FROM lecture l
     LEFT JOIN subject s   ON s.id   = l.subject_id
     LEFT JOIN teacher t   ON t.id   = l.teacher_id
     LEFT JOIN source  src ON src.id = l.source_id
     WHERE l.group_code = ?
     ORDER BY src.id, l.weekday, l.start_slot'
);
$stmt->execute([$code]);
$lectures = $stmt->fetchAll();

if (!$lectures) json_error('group not found', 404);

// Aggregates for the header summary.
$teachers = array_values(array_unique(array_filter(array_column($lectures, 'teacher_name'))));
$subjects = array_values(array_unique(array_filter(array_column($lectures, 'subject_name'))));
sort($teachers);
sort($subjects);

json_response([
    'code'      => $code,
    'lectures'  => $lectures,
    'teachers'  => $teachers,
    'subjects'  => $subjects,
]);
