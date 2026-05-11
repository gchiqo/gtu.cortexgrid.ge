<?php
declare(strict_types=1);
require __DIR__ . '/lib/db.php';

$assetVersion = max(
    (int)@filemtime(__DIR__ . '/assets/style.css'),
    (int)@filemtime(__DIR__ . '/assets/builder.css'),
    (int)@filemtime(__DIR__ . '/assets/i18n.js'),
    (int)@filemtime(__DIR__ . '/assets/builder.js'),
    (int)@filemtime(__FILE__)
);
?>
<!DOCTYPE html>
<html lang="ka">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title data-i18n="builder.title">ააწყვე შენი ცხრილი — GTU ცხრილი</title>
<meta name="description" content="ააწყვე შენი პერსონალური სასწავლო ცხრილი GTU-ში — აირჩიე საგანი ან პედაგოგი, შემდეგ მისი წყვილი. Build your personal GTU class schedule by picking a subject or teacher, then their counterpart.">
<meta name="robots" content="index,follow">
<link rel="canonical" href="https://gtu.cortexgrid.ge/builder.php">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32.png">
<link rel="icon" type="image/png" sizes="128x128" href="/assets/favicon-128.png">
<link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">
<link rel="stylesheet" href="assets/style.css?v=<?= $assetVersion ?>">
<link rel="stylesheet" href="assets/builder.css?v=<?= $assetVersion ?>">
</head>
<body class="builder-page">

<header>
    <div class="lang-switcher" role="group" aria-label="Language">
        <button data-lang="ka" type="button" data-i18n="lang.toggle.ka">ქარ</button>
        <button data-lang="en" type="button" data-i18n="lang.toggle.en">ENG</button>
    </div>
    <h1 data-i18n="builder.heading">ააწყვე შენი ცხრილი</h1>
    <p class="muted">
        <a href="/" class="back-link" data-i18n="me.back">← gtu.cortexgrid.ge — მთავარი ძიება</a>
    </p>
    <p class="muted" data-i18n="builder.lead">აირჩიე საგანი ან პედაგოგი — შემდეგ მისი წყვილი. ცხრილს თვითონ ააწყობს.</p>
</header>

<main>
    <!-- Step 1: search either side -->
    <section class="builder-search">
        <label class="builder-label" for="anchorQ" data-i18n="builder.step1">ნაბიჯი 1 — აირჩიე საგანი ან პედაგოგი</label>
        <input id="anchorQ" type="search" autocomplete="off"
               data-i18n-placeholder="builder.placeholder.anchor"
               placeholder="საგანი ან პედაგოგი — KA ან EN">
        <ul id="anchorResults" class="results"></ul>
    </section>

    <!-- Step 2: pick the counterpart -->
    <section id="suggestSection" class="builder-suggest hidden">
        <header class="suggest-head">
            <div>
                <div class="suggest-anchor-kind" id="suggestKind"></div>
                <div class="suggest-anchor-name" id="suggestName"></div>
            </div>
            <button type="button" id="changeAnchorBtn" class="change-anchor-btn"
                    data-i18n="builder.change_anchor">← შეცვალე</button>
        </header>
        <p class="muted" id="suggestHint"></p>
        <ul id="suggestList" class="suggest-list"></ul>
        <p class="muted hidden" id="suggestEmpty" data-i18n="builder.suggest_empty">ვერ ვიპოვე წყვილი ამ შემთხვევაში.</p>
    </section>

    <!-- Step 3: build & view -->
    <section class="builder-list">
        <h2>
            <span data-i18n="builder.your_courses">შენი საგნები</span>
            <span class="builder-count" id="courseCount">(0)</span>
        </h2>
        <p class="muted" id="emptyHint" data-i18n="builder.empty_hint">ჯერ არ აირჩიე საგანი. ზემოთ მოძებნე — საგანი ან პედაგოგი → დაამატე.</p>
        <ul id="courseList" class="course-list"></ul>
        <div class="builder-actions">
            <button id="viewBtn" class="builder-view-btn" type="button" disabled
                    data-i18n="builder.view">📅 ცხრილის ნახვა →</button>
            <button id="clearBtn" class="builder-clear-btn" type="button"
                    data-i18n="builder.clear">გასუფთავება</button>
        </div>
    </section>
</main>

<footer>
    <p class="muted" data-i18n-html="footer.html">
        მონაცემები: <a href="https://leqtori.gtu.ge/" target="_blank" rel="noopener">leqtori.gtu.ge</a>.
        ეს არ არის უნივერსიტეტის ოფიციალური საიტი.
        <a href="/privacy.php">კონფიდენციალურობა</a> ·
        <a href="/terms.php">წესები</a> ·
        <a href="mailto:voidpoko@gmail.com">voidpoko@gmail.com</a>
    </p>
</footer>

<script src="assets/i18n.js?v=<?= $assetVersion ?>" defer></script>
<script src="assets/builder.js?v=<?= $assetVersion ?>" defer></script>
</body>
</html>
