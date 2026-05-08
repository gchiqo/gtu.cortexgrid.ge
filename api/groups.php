<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/http.php';

$q = trim((string)($_GET['q'] ?? ''));
$limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));

$pdo = db();

if ($q === '') {
    $stmt = $pdo->prepare(
        "SELECT group_code AS code, COUNT(*) AS lecture_count
         FROM lecture
         WHERE group_code IS NOT NULL AND group_code <> ''
         GROUP BY group_code
         ORDER BY group_code
         LIMIT ?"
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
} else {
    // Substring match. Prefix matches rank above mid-string matches.
    $stmt = $pdo->prepare(
        "SELECT group_code AS code, COUNT(*) AS lecture_count
         FROM lecture
         WHERE group_code IS NOT NULL AND group_code <> ''
           AND group_code LIKE ?
         GROUP BY group_code
         ORDER BY (group_code LIKE ?) DESC, group_code
         LIMIT ?"
    );
    $stmt->bindValue(1, '%' . $q . '%', PDO::PARAM_STR);
    $stmt->bindValue(2, $q . '%',       PDO::PARAM_STR);
    $stmt->bindValue(3, $limit,         PDO::PARAM_INT);
    $stmt->execute();
}

$rows = $stmt->fetchAll();
foreach ($rows as &$r) $r['lecture_count'] = (int)$r['lecture_count'];

json_response(['results' => $rows]);
