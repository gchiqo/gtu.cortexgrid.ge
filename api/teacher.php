<?php
declare(strict_types=1);

require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/http.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) json_error('missing id');

$pdo = db();

$teacher = $pdo->prepare('SELECT id, name FROM teacher WHERE id = ?');
$teacher->execute([$id]);
$teacher = $teacher->fetch();
if (!$teacher) json_error('teacher not found', 404);

$stmt = $pdo->prepare(
    'SELECT
         l.weekday, l.start_slot, l.end_slot, l.start_time, l.end_time,
         l.group_code, l.lesson_type, l.room, l.last_seen_at,
         s.name AS subject_name, s.code AS subject_code,
         src.url           AS source_url,
         src.fetched_at    AS source_fetched_at,
         src.section_title AS source_section,
         src.display_label AS source_label
     FROM lecture l
     LEFT JOIN subject s   ON s.id   = l.subject_id
     LEFT JOIN source  src ON src.id = l.source_id
     WHERE l.teacher_id = ?
     ORDER BY src.id, l.weekday, l.start_slot'
);
$stmt->execute([$id]);
$lectures = $stmt->fetchAll();

json_response([
    'teacher'  => $teacher,
    'lectures' => $lectures,
]);
