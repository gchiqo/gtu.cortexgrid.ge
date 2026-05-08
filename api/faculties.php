<?php
declare(strict_types=1);

require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/http.php';

$pdo = db();
$rows = $pdo->query(
    "SELECT p.id AS pdf_doc_id, p.faculty_slug, p.faculty_name, p.page_count, p.fetched_at,
            p.kind,
            s.url           AS source_url,
            s.section_title AS source_section,
            s.display_label AS source_label,
            COUNT(al.id) AS structured_rows
     FROM pdf_doc p
     JOIN source s ON s.id = p.source_id
     LEFT JOIN additional_lecture al ON al.pdf_doc_id = p.id
     GROUP BY p.id, p.faculty_slug, p.faculty_name, p.page_count, p.fetched_at, p.kind,
              s.url, s.section_title, s.display_label
     ORDER BY p.kind, structured_rows DESC, p.faculty_name"
)->fetchAll();

foreach ($rows as &$r) $r['structured_rows'] = (int)$r['structured_rows'];
unset($r);

json_response(['faculties' => $rows]);
