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
    (int)@filemtime(__DIR__ . '/assets/style.css'),
    (int)@filemtime(__DIR__ . '/assets/i18n.js')
);
?>
<!DOCTYPE html>
<html lang="ka">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GTU ცხრილი — ლექციების ცხრილი | საქართველოს ტექნიკური უნივერსიტეტი (GTU Schedule)</title>
<meta name="description" content="GTU ცხრილი — ეძებე საქართველოს ტექნიკური უნივერსიტეტის (სტუ) პედაგოგების, საგნებისა და ჯგუფების ცხრილი ერთ ადგილას. Search the Georgian Technical University (GTU) class schedule by teacher, subject, or group code.">
<meta name="keywords" content="gtu, gtu cxrili, gtu ცხრილი, სტუ, სტუ ცხრილი, ცხრილი, ლექციების ცხრილი, ტექნიკური უნივერსიტეტი, ტექნიკური უნივერსიტეტი ცხრილი, საქართველოს ტექნიკური უნივერსიტეტი, georgian technical university, georgian technical university schedule, gtu schedule, gtu ge, leqtori, leqtori.gtu.ge, ჯგუფის ცხრილი, პედაგოგის ცხრილი">
<meta name="author" content="gtu.cortexgrid.ge">
<meta name="robots" content="index,follow,max-image-preview:large">
<link rel="canonical" href="https://gtu.cortexgrid.ge/">
<link rel="alternate" hreflang="ka" href="https://gtu.cortexgrid.ge/">
<link rel="alternate" hreflang="en" href="https://gtu.cortexgrid.ge/?lang=en">
<link rel="alternate" hreflang="x-default" href="https://gtu.cortexgrid.ge/">
<link rel="icon" type="image/png" sizes="32x32" href="extension/icons/icon-32.png">
<link rel="icon" type="image/png" sizes="128x128" href="extension/icons/icon-128.png">
<link rel="apple-touch-icon" sizes="128x128" href="extension/icons/icon-128.png">

<meta property="og:type" content="website">
<meta property="og:site_name" content="GTU ცხრილი">
<meta property="og:title" content="GTU ცხრილი — საქართველოს ტექნიკური უნივერსიტეტის ლექციების ცხრილი">
<meta property="og:description" content="ეძებე GTU პედაგოგების, საგნებისა და ჯგუფების ცხრილი. Search Georgian Technical University class schedule by teacher, subject, or group.">
<meta property="og:url" content="https://gtu.cortexgrid.ge/">
<meta property="og:image" content="https://gtu.cortexgrid.ge/assets/og-image.png">
<meta property="og:image:width" content="1400">
<meta property="og:image:height" content="560">
<meta property="og:locale" content="ka_GE">
<meta property="og:locale:alternate" content="en_US">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="GTU ცხრილი — Georgian Technical University Schedule">
<meta name="twitter:description" content="ეძებე GTU ლექციების ცხრილი. Search GTU class schedule by teacher, subject, or group code.">
<meta name="twitter:image" content="https://gtu.cortexgrid.ge/assets/og-image.png">

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "WebSite",
      "@id": "https://gtu.cortexgrid.ge/#website",
      "url": "https://gtu.cortexgrid.ge/",
      "name": "GTU ცხრილი",
      "alternateName": ["GTU Schedule", "GTU cxrili", "სტუ ცხრილი", "Georgian Technical University Schedule"],
      "description": "საქართველოს ტექნიკური უნივერსიტეტის ლექციების ცხრილის ძიება. Search the Georgian Technical University class schedule.",
      "inLanguage": ["ka", "en"],
      "potentialAction": {
        "@type": "SearchAction",
        "target": {
          "@type": "EntryPoint",
          "urlTemplate": "https://gtu.cortexgrid.ge/?q={search_term_string}"
        },
        "query-input": "required name=search_term_string"
      }
    },
    {
      "@type": "WebApplication",
      "name": "GTU ცხრილი",
      "url": "https://gtu.cortexgrid.ge/",
      "applicationCategory": "EducationalApplication",
      "operatingSystem": "Any (web browser)",
      "browserRequirements": "Requires JavaScript",
      "inLanguage": ["ka", "en"],
      "offers": { "@type": "Offer", "price": "0", "priceCurrency": "GEL" },
      "about": {
        "@type": "CollegeOrUniversity",
        "name": "საქართველოს ტექნიკური უნივერსიტეტი",
        "alternateName": ["Georgian Technical University", "GTU", "სტუ"],
        "url": "https://gtu.ge/"
      }
    }
  ]
}
</script>

<link rel="stylesheet" href="assets/style.css?v=<?= $assetVersion ?>">
</head>
<body>
<header>
    <div class="lang-switcher" role="group" aria-label="Language">
        <button data-lang="ka" type="button" data-i18n="lang.toggle.ka">ქარ</button>
        <button data-lang="en" type="button" data-i18n="lang.toggle.en">ENG</button>
    </div>
    <h1 data-i18n="site.title">GTU ცხრილი</h1>
    <p class="muted">
        <span data-i18n="stats.teachers" data-arg-n="<?= $stats['teachers'] ?>"><?= $stats['teachers'] ?> პედაგოგი</span> ·
        <span data-i18n="stats.subjects" data-arg-n="<?= $stats['subjects'] ?>"><?= $stats['subjects'] ?> საგანი</span> ·
        <span data-i18n="stats.lectures" data-arg-n="<?= $stats['lectures'] ?>"><?= $stats['lectures'] ?> ლექცია</span>
        <?php if ($lastSource): ?>
            · <span data-i18n="stats.updated" data-arg-when="<?= htmlspecialchars(date('Y-m-d H:i', (int)$lastSource['fetched_at'])) ?>">განახლდა <?= htmlspecialchars(date('Y-m-d H:i', (int)$lastSource['fetched_at'])) ?></span>
            (<a href="<?= htmlspecialchars($lastSource['url']) ?>" target="_blank" rel="noopener" data-i18n="stats.source">წყარო</a>)
        <?php endif; ?>
    </p>
</header>

<main>
    <section class="search">
        <input id="q" type="search" data-i18n-placeholder="search.placeholder"
               placeholder="შეიყვანე პედაგოგის გვარი ან საგანი…" autocomplete="off" autofocus>
        <ul id="results" class="results"></ul>
    </section>

    <section id="groupSection" class="group-section">
        <h2 data-i18n="group.heading">ჯგუფის ცხრილი</h2>
        <p class="muted" data-i18n-html="group.help.html">შეიყვანე შენი ჯგუფის კოდი — მაგ. <code>052510-შ</code> — და ნახე მთელი კვირის ცხრილი.</p>
        <input id="groupQ" type="search" data-i18n-placeholder="group.placeholder"
               placeholder="ჯგუფის კოდი…" autocomplete="off">
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
        <h2 data-i18n="faculties.heading">დამატებითი სასწავლო კურსები</h2>
        <p class="muted" data-i18n="faculties.help">
            ფაკულტეტების მიხედვით. PDF-ის ტექსტი ამოღებულია ბრაუზერში სასაძიებლად
            (Ctrl+F). რთული სტრუქტურის ფაკულტეტებს ჯერ მხოლოდ raw სახით ვინახავთ.
        </p>
        <ul id="facultyList" class="faculty-list"></ul>

        <article id="facultyView" class="hidden">
            <header class="faculty-header">
                <h3 id="facultyName"></h3>
                <p class="muted" id="facultyMeta"></p>
            </header>
            <div class="faculty-tabs">
                <button id="tabStructured" class="tab active" type="button" data-i18n="faculties.tab.structured">სტრუქტურირებული</button>
                <button id="tabRaw"        class="tab"         type="button" data-i18n="faculties.tab.raw">ტექსტი (PDF-ის ნახვა)</button>
            </div>
            <input id="facultyFilter" type="search"
                   data-i18n-placeholder="faculties.filter.placeholder"
                   placeholder="ფილტრი ამ ფაკულტეტში… (პედაგოგი, საგანი, აუდიტორია)">
            <div id="structuredView">
                <table id="structuredTable" class="structured-table"></table>
                <p id="structuredEmpty" class="muted hidden" data-i18n="faculties.no_structured">ამ ფაკულტეტისთვის სტრუქტურირებული მონაცემები ჯერ არ გვაქვს — გადართე "ტექსტი"-ზე.</p>
            </div>
            <pre id="facultyText" class="faculty-text hidden"></pre>
        </article>
    </section>
</main>

<footer>
    <p class="muted" data-i18n-html="footer.html">
        მონაცემები: <a href="https://leqtori.gtu.ge/" target="_blank" rel="noopener">leqtori.gtu.ge</a>.
        ეს არ არის უნივერსიტეტის ოფიციალური საიტი — გადაამოწმე კრიტიკული ცვლილებები.
        <a href="/privacy.php">კონფიდენციალურობა</a> ·
        <a href="/terms.php">წესები</a> ·
        <a href="mailto:voidpoko@gmail.com">voidpoko@gmail.com</a>
    </p>
</footer>

<script src="assets/i18n.js?v=<?= $assetVersion ?>"></script>
<script src="assets/app.js?v=<?= $assetVersion ?>"></script>
</body>
</html>
