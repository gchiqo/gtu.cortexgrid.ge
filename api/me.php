<?php
declare(strict_types=1);

/**
 * JSON twin of /me.php for the Chrome extension.
 *
 * Accepts:
 *   POST /api/me.php   Content-Type: application/json
 *     {
 *       "school":  "<student's school name, optional — used to scope midterms>",
 *       "courses": [
 *         {"subject": "<KA>", "subjectEn": "<EN>",
 *          "teacher": "<KA>", "teacherEn": "<EN>"},
 *         …
 *       ]
 *     }
 *
 * Returns:
 *   {
 *     "courses": [
 *       {"subject": "<KA>", "teacher": "<KA>",
 *        "lectures":   [...],   // matched rows from `lecture` (teachers HTML)
 *        "additional": [...],   // matched rows from `additional_lecture` (PDFs)
 *        "midterm":    [...]    // midterm PDFs that name this subject + parsed exams
 *       }, …
 *     ],
 *     "totals": {"lectures": N, "additional": N, "midterm_exams": N}
 *   }
 */

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/http.php';
require_once __DIR__ . '/../lib/match.php';
require_once __DIR__ . '/../lib/faculty.php';

// CORS — host_permissions in the extension already grants access, but a
// permissive CORS header makes this endpoint usable from a future fetch from
// our own pages too without surprise.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Accept JSON body OR ?d=<base64-of-json> (mirrors me.php for parity).
$body = file_get_contents('php://input');
$payload = null;
if ($body !== '' && $body !== false) {
    $payload = json_decode($body, true);
}
if (!$payload && isset($_GET['d'])) {
    $raw = base64_decode((string)$_GET['d'], true);
    if ($raw !== false) $payload = json_decode($raw, true);
}
if (!is_array($payload) || empty($payload['courses']) || !is_array($payload['courses'])) {
    json_error('expected JSON with non-empty `courses` array', 400);
}

$pdo = db();

$studentFacultySlug = null;
if (!empty($payload['school'])) {
    $studentFacultySlug = classify_faculty((string)$payload['school']);
}
$facultySlugs = $studentFacultySlug ? [$studentFacultySlug] : [];

$totals = ['lectures' => 0, 'additional' => 0, 'midterm_exams' => 0];
$out = [];

foreach ($payload['courses'] as $c) {
    $subjects = array_values(array_filter([
        (string)($c['subject']   ?? ''),
        (string)($c['subjectEn'] ?? ''),
    ]));
    $teachers = array_values(array_filter([
        (string)($c['teacher']   ?? ''),
        (string)($c['teacherEn'] ?? ''),
    ]));

    $lectures   = $subjects && $teachers ? match_lectures_for_course($pdo, $subjects, $teachers)   : [];
    $additional = $subjects && $teachers ? match_additional_for_course($pdo, $subjects, $teachers) : [];
    $midterm    = $subjects ? match_midterm_pdfs_for_course($pdo, $subjects, $facultySlugs) : [];

    $totals['lectures']   += count($lectures);
    $totals['additional'] += count($additional);
    foreach ($midterm as $mid) {
        $totals['midterm_exams'] += count($mid['exams'] ?? []);
    }

    $out[] = [
        'subject'    => (string)($c['subject']   ?? ''),
        'subjectEn'  => (string)($c['subjectEn'] ?? ''),
        'teacher'    => (string)($c['teacher']   ?? ''),
        'teacherEn'  => (string)($c['teacherEn'] ?? ''),
        'lectures'   => $lectures,
        'additional' => $additional,
        'midterm'    => $midterm,
    ];
}

json_response([
    'courses' => $out,
    'totals'  => $totals,
]);
