<?php
declare(strict_types=1);
require __DIR__ . '/lib/db.php';

$pdo = db();
$stats = [
    'teachers' => (int)$pdo->query('SELECT COUNT(*) FROM teacher')->fetchColumn(),
    'subjects' => (int)$pdo->query('SELECT COUNT(*) FROM subject')->fetchColumn(),
    'lectures' => (int)$pdo->query('SELECT COUNT(*) FROM lecture')->fetchColumn(),
];
$lastSource = $pdo->query('SELECT url, fetched_at FROM source ORDER BY fetched_at DESC LIMIT 1')->fetch();

$assetVersion = max(
    (int)@filemtime(__DIR__ . '/assets/app.js'),
    (int)@filemtime(__DIR__ . '/assets/style.css')
);
?>
<!DOCTYPE html>
<html lang="ka">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GTU ცხრილი — ძიება</title>
<link rel="stylesheet" href="assets/style.css?v=<?= $assetVersion ?>">
</head>
<body>
<header>
    <h1>GTU ცხრილი</h1>
    <p class="muted">
        <?= $stats['teachers'] ?> პედაგოგი ·
        <?= $stats['subjects'] ?> საგანი ·
        <?= $stats['lectures'] ?> ლექცია
        <?php if ($lastSource): ?>
            · განახლდა <?= htmlspecialchars(date('Y-m-d H:i', (int)$lastSource['fetched_at'])) ?>
            (<a href="<?= htmlspecialchars($lastSource['url']) ?>" target="_blank" rel="noopener">წყარო</a>)
        <?php endif; ?>
    </p>
</header>

<main>
    <section class="search">
        <input id="q" type="search" placeholder="შეიყვანე პედაგოგის გვარი ან საგანი…" autocomplete="off" autofocus>
        <ul id="results" class="results"></ul>
    </section>

    <section id="groupSection" class="group-section">
        <h2>ჯგუფის ცხრილი</h2>
        <p class="muted">შეიყვანე შენი ჯგუფის კოდი — მაგ. <code>052510-შ</code> — და ნახე მთელი კვირის ცხრილი.</p>
        <input id="groupQ" type="search" placeholder="ჯგუფის კოდი…" autocomplete="off">
        <ul id="groupResults" class="results"></ul>
        <article id="groupView" class="hidden">
            <h3 id="groupName"></h3>
            <p class="muted" id="groupMeta"></p>
            <div id="groupSchedule"></div>
        </article>
    </section>

    <section id="teacherView" class="hidden">
        <h2 id="teacherName"></h2>
        <p class="muted" id="teacherMeta"></p>
        <div id="schedule"></div>
    </section>

    <section id="pdfTeacherView" class="hidden">
        <h2 id="pdfTeacherName"></h2>
        <p class="muted" id="pdfTeacherMeta"></p>
        <table id="pdfTeacherTable" class="structured-table"></table>
    </section>

    <section id="subjectView" class="hidden">
        <h2 id="subjectName"></h2>
        <p class="muted" id="subjectMeta"></p>
        <table id="subjectTable" class="structured-table"></table>
    </section>

    <section id="facultiesSection">
        <h2>დამატებითი სასწავლო კურსები</h2>
        <p class="muted">
            ფაკულტეტების მიხედვით. PDF-ის ტექსტი ამოღებულია ბრაუზერში სასაძიებლად
            (Ctrl+F). ცხრილის სტრუქტურის გამო რთული სტრუქტურის ფაკულტეტებს
            ჯერ ვიხილავთ მხოლოდ raw სახით.
        </p>
        <ul id="facultyList" class="faculty-list"></ul>

        <article id="facultyView" class="hidden">
            <header class="faculty-header">
                <h3 id="facultyName"></h3>
                <p class="muted" id="facultyMeta"></p>
            </header>
            <div class="faculty-tabs">
                <button id="tabStructured" class="tab active" type="button">სტრუქტურირებული</button>
                <button id="tabRaw"        class="tab"         type="button">ტექსტი (PDF-ის ნახვა)</button>
            </div>
            <input id="facultyFilter" type="search" placeholder="ფილტრი ამ ფაკულტეტში… (პედაგოგი, საგანი, აუდიტორია)">
            <div id="structuredView">
                <table id="structuredTable" class="structured-table"></table>
                <p id="structuredEmpty" class="muted hidden">ამ ფაკულტეტისთვის სტრუქტურირებული მონაცემები ჯერ არ გვაქვს — გადართე "ტექსტი"-ზე.</p>
            </div>
            <pre id="facultyText" class="faculty-text hidden"></pre>
        </article>
    </section>
</main>

<footer>
    <p class="muted">
        მონაცემები: <a href="https://leqtori.gtu.ge/" target="_blank" rel="noopener">leqtori.gtu.ge</a>.
        ეს არ არის უნივერსიტეტის ოფიციალური საიტი — გადაამოწმე კრიტიკული ცვლილებები.
    </p>
</footer>

<script src="assets/app.js?v=<?= $assetVersion ?>"></script>
</body>
</html>
