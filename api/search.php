<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/translit.php';

$q = trim((string)($_GET['q'] ?? ''));
$limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));

// Build the LIKE haystack from the query: lowercased original. Latin queries
// hit the transliterated form of Georgian names; Georgian queries hit the
// original Georgian form. Both are stored together in the *_searchable cols.
$qSearch = mb_strtolower($q, 'UTF-8');

$pdo = db();

// Common WHERE-clause snippets to reject parser noise (rooms misclassified as
// teacher names, single-character cells, etc). Equivalent to the PHP-side
// teacher_name_looks_valid() — we redo it here so already-stored data stays clean.
$validName = "(CHAR_LENGTH(%1\$s) >= 3 AND %1\$s REGEXP '[[:alpha:]]{2,}')";

if ($q === '') {
    // Default browse: alphabetical, mixing teachers and subjects from both sources.
    // No transliteration is needed here since we're not matching against text.
    $sql = "
        SELECT u.* FROM (
            SELECT 'teacher' AS type, 'html' AS source, t.id AS ref, t.name AS name, NULL AS code,
                   COUNT(l.id) AS lecture_count, NULL AS faculties
            FROM teacher t
            LEFT JOIN lecture l ON l.teacher_id = t.id
            GROUP BY t.id, t.name
            HAVING lecture_count > 0

            UNION ALL

            SELECT 'teacher', 'pdf', NULL, al.teacher_name, NULL,
                   COUNT(*),
                   GROUP_CONCAT(DISTINCT al.faculty_slug ORDER BY al.faculty_slug)
            FROM additional_lecture al
            WHERE al.teacher_name IS NOT NULL AND al.teacher_name <> ''
              AND " . sprintf($validName, 'al.teacher_name') . "
            GROUP BY al.teacher_name

            UNION ALL

            SELECT 'subject', 'html', s.id, s.name, s.code,
                   COUNT(l.id), NULL
            FROM subject s
            LEFT JOIN lecture l ON l.subject_id = s.id
            GROUP BY s.id, s.name, s.code
            HAVING lecture_count > 0

            UNION ALL

            SELECT 'subject', 'pdf', NULL, al.subject_name, NULL,
                   COUNT(*),
                   GROUP_CONCAT(DISTINCT al.faculty_slug ORDER BY al.faculty_slug)
            FROM additional_lecture al
            WHERE al.subject_name IS NOT NULL AND al.subject_name <> ''
              AND " . sprintf($validName, 'al.subject_name') . "
            GROUP BY al.subject_name
        ) u
        ORDER BY CASE u.type WHEN 'teacher' THEN 0 ELSE 1 END, u.name
        LIMIT ?";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
} else {
    // Search both teachers and subjects, both sources. The `*_searchable` cols
    // hold "{lowered original} {latin transliteration variants...}" so a single
    // LIKE substring hits Georgian, Latin, and ambiguous-romanization queries
    // (e.g. "tskhom" → "ცხომელიძე", "fizika" → "ფიზიკა").
    $sql = "
        SELECT u.* FROM (
            SELECT 'teacher' AS type, 'html' AS source, t.id AS ref, t.name AS name, NULL AS code,
                   COUNT(l.id) AS lecture_count, NULL AS faculties
            FROM teacher t
            LEFT JOIN lecture l ON l.teacher_id = t.id
            WHERE COALESCE(t.searchable, LOWER(t.name)) LIKE ?
            GROUP BY t.id, t.name

            UNION ALL

            SELECT 'teacher', 'pdf', NULL, al.teacher_name, NULL,
                   COUNT(*),
                   GROUP_CONCAT(DISTINCT al.faculty_slug ORDER BY al.faculty_slug)
            FROM additional_lecture al
            WHERE al.teacher_name IS NOT NULL AND al.teacher_name <> ''
              AND " . sprintf($validName, 'al.teacher_name') . "
              AND COALESCE(al.teacher_searchable, LOWER(al.teacher_name)) LIKE ?
            GROUP BY al.teacher_name

            UNION ALL

            SELECT 'subject', 'html', s.id, s.name, s.code,
                   COUNT(l.id), NULL
            FROM subject s
            LEFT JOIN lecture l ON l.subject_id = s.id
            WHERE COALESCE(s.searchable, LOWER(s.name)) LIKE ? OR s.code LIKE ?
            GROUP BY s.id, s.name, s.code

            UNION ALL

            SELECT 'subject', 'pdf', NULL, al.subject_name, NULL,
                   COUNT(*),
                   GROUP_CONCAT(DISTINCT al.faculty_slug ORDER BY al.faculty_slug)
            FROM additional_lecture al
            WHERE al.subject_name IS NOT NULL AND al.subject_name <> ''
              AND " . sprintf($validName, 'al.subject_name') . "
              AND COALESCE(al.subject_searchable, LOWER(al.subject_name)) LIKE ?
            GROUP BY al.subject_name
        ) u
        ORDER BY (LOWER(u.name) LIKE ?) DESC,
                 CASE u.type WHEN 'teacher' THEN 0 ELSE 1 END,
                 u.name
        LIMIT ?";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, '%' . $qSearch . '%', PDO::PARAM_STR);  // teacher html
    $stmt->bindValue(2, '%' . $qSearch . '%', PDO::PARAM_STR);  // teacher pdf
    $stmt->bindValue(3, '%' . $qSearch . '%', PDO::PARAM_STR);  // subject html name
    $stmt->bindValue(4, $q . '%',             PDO::PARAM_STR);  // subject html code (prefix, raw)
    $stmt->bindValue(5, '%' . $qSearch . '%', PDO::PARAM_STR);  // subject pdf
    $stmt->bindValue(6, $qSearch . '%',       PDO::PARAM_STR);  // ranking: prefix bonus on lowered
    $stmt->bindValue(7, $limit,               PDO::PARAM_INT);
    $stmt->execute();
}

$rows = $stmt->fetchAll();
foreach ($rows as &$r) {
    $r['ref'] = $r['ref'] !== null ? (int)$r['ref'] : null;
    $r['lecture_count'] = (int)$r['lecture_count'];
    $r['faculties'] = $r['faculties'] ? explode(',', $r['faculties']) : [];
}

json_response(['results' => $rows]);
