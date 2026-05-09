<?php
declare(strict_types=1);

$assetVersion = max(
    (int)@filemtime(__DIR__ . '/assets/style.css'),
    (int)@filemtime(__DIR__ . '/assets/i18n.js'),
    (int)@filemtime(__FILE__)
);
$updatedDate = '2026-05-08';
?>
<!DOCTYPE html>
<html lang="ka">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Privacy Policy / კონფიდენციალურობის პოლიტიკა — GTU ცხრილი</title>
<meta name="description" content="GTU ცხრილის კონფიდენციალურობის პოლიტიკა. Privacy policy for the GTU ცხრილი website and Chrome extension.">
<meta name="robots" content="noindex,follow">
<link rel="canonical" href="https://gtu.cortexgrid.ge/privacy.php">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32.png">
<link rel="icon" type="image/png" sizes="128x128" href="/assets/favicon-128.png">
<link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">
<link rel="stylesheet" href="assets/style.css?v=<?= $assetVersion ?>">
<link rel="stylesheet" href="assets/legal.css?v=<?= $assetVersion ?>">
</head>
<body class="legal-page">

<header>
    <div class="lang-switcher" role="group" aria-label="Language">
        <button data-lang="ka" type="button" data-i18n="lang.toggle.ka">ქარ</button>
        <button data-lang="en" type="button" data-i18n="lang.toggle.en">ENG</button>
    </div>
    <h1 data-i18n="site.title">GTU ცხრილი</h1>
    <p class="muted">
        <a href="/" class="back-link" data-i18n="me.back">← gtu.cortexgrid.ge — მთავარი ძიება</a>
    </p>
</header>

<main class="legal container">

<section data-i18n-lang="ka">
    <h2>კონფიდენციალურობის პოლიტიკა</h2>
    <p class="muted">ბოლო განახლება: <?= htmlspecialchars($updatedDate) ?></p>

    <h3>1. ვინ არის ამის პასუხისმგებელი</h3>
    <p>ეს საიტი (gtu.cortexgrid.ge) და Chrome-ის ექსტენშენი <strong>GTU ცხრილი</strong>
    შექმნილია ერთი დეველოპერის მიერ (კონტაქტი ქვემოთ). ეს არის
    <strong>არაოფიციალური</strong> სერვისი — საქართველოს ტექნიკურ
    უნივერსიტეტთან ის არ არის ოფიციალურად დაკავშირებული. პროექტი
    უფასოა, ღია, და სტუდენტებისთვის სტუდენტის მიერ შექმნილი.</p>

    <h3>2. რა მონაცემებთან გვაქვს კავშირი</h3>
    <p><strong>საჯარო მონაცემები:</strong> leqtori.gtu.ge-ის ცხრილების HTML
    გვერდები და PDF-ები. მათ ვამუშავებთ ყოველ 5 საათში და ვინახავთ ჩვენს
    ბაზაში. ეს მონაცემები ისედაც საჯაროა — ყველას შეუძლია იხილოს leqtori.gtu.ge.</p>

    <p><strong>პირადი მონაცემები</strong> (მხოლოდ ექსტენშენის გამოყენებისას):</p>
    <ul>
        <li>თქვენი <code>Student-Token</code> JWT-ი, რომელიც vici.gtu.ge-მ
            თქვენი ნორმალური ავტორიზაციის შემდეგ ბრაუზერის localStorage-ში
            შეინახა. ექსტენშენი ამ ტოკენით ავსრულებს მოწოდებას
            <code>vici.gtu.ge/student/card</code>-ზე
            <strong>თქვენი სახელით</strong>. ტოკენი ჩვენთან არასოდეს მოდის —
            ის რჩება თქვენს ბრაუზერში.</li>
        <li><code>/student/card</code>-ის პასუხი: სახელი, ფაკულტეტი,
            სემესტრი, GPA, მიმდინარე საგნების სია (საგანი + პედაგოგი).</li>
    </ul>

    <h3>3. რას გადავცემთ / ვინახავთ</h3>
    <p>როდესაც დააჭერ "📅 ჩემი ცხრილი"-ს, ექსტენშენი card-ის მცირე
    ნაწილს გადასცემს gtu.cortexgrid.ge-ს:</p>
    <ul>
        <li><code>POST /api/me.php</code> — საგნებისა და პედაგოგების სია
            (გამოიყენება მხოლოდ leqtori-ის ჩვენ მიერ სკანირებულ ბაზაში
            შესაბამისი ლექციების მოსაძებნად). მომენტში წაიშლება პასუხის
            გენერაციის შემდეგ.</li>
        <li><code>GET /me.php?d=...</code> — იგივე ინფო + სახელი + ფაკულტეტი,
            base64-ით URL-ში დაკოდილი, რომ სერვერმა შეძლოს თქვენი პერსონალური
            გვერდის რენდერი. <strong>არ ლოგდება, არ ინახება, არ ანალიზდება.</strong></li>
    </ul>

    <p>თქვენი <strong>პაროლი არასოდეს იკითხება</strong>, არასოდეს გადაიცემა,
    არასოდეს ინახება. ჩვენ <strong>არ გვაქვს</strong> analytics, cookie-ები,
    third-party tracker-ები.</p>

    <h3>4. რამდენ ხანს ვინახავთ</h3>
    <ul>
        <li><strong>პირადი მონაცემები:</strong> არ ვინახავთ. <code>/api/me.php</code>
            და <code>/me.php</code> ენდპოინტები მუშავდება და მონაცემი
            მაშინვე უარიყოფა. სერვერის access log-ები (nginx default)
            ინახება ≤7 დღე debugging-ისთვის და ბრუნავს.</li>
        <li><strong>საჯარო leqtori მონაცემები:</strong> განახლდება ყოველ
            5 საათში, ინახება სანამ პროექტი არსებობს.</li>
        <li><strong>თქვენს მხარეს:</strong> <code>chrome.storage.local</code>
            ინახავს ბოლო card-ს და ენის არჩევანს თქვენ საკუთარ კომპიუტერზე.
            გარემოვა შეიძლება ნებისმიერ დროს Chrome ექსტენშენის გვერდიდან,
            ან დეინსტალაციით.</li>
    </ul>

    <h3>5. ვინ სხვა ხედავს თქვენს მონაცემებს</h3>
    <p>ვერავინ. ჩვენ <strong>არ ვყიდით, არ გვიზიარებთ, არ გადავცემთ</strong>
    პირად მონაცემებს არავის. ლექციების მონახავი სერვისი მუშაობს მხოლოდ
    იმავე დეველოპერის სერვერზე.</p>

    <h3>6. თქვენი უფლებები</h3>
    <ul>
        <li>გაგრძელება ნებისმიერ მომენტში დაატოვოთ — დეინსტალირეთ
            ექსტენშენი და ჩვენ მხარეს არაფერი დარჩება, რადგან card
            მონაცემებს არ ვინახავთ.</li>
        <li>ემაილი <a href="mailto:voidpoko@gmail.com">voidpoko@gmail.com</a>
            თუ გაქვთ კითხვა ან გნებავთ რომ რაიმე წავშალოთ საჯარო
            სკანირებული ცხრილიდან.</li>
    </ul>

    <h3>7. ცვლილებები</h3>
    <p>თუ რამე მნიშვნელოვანი შეიცვლება, "ბოლო განახლება" თარიღს
    შევცვლით. მიმდინარე ვერსია ყოველთვის
    <a href="https://gtu.cortexgrid.ge/privacy.php">https://gtu.cortexgrid.ge/privacy.php</a>-ზე.</p>

    <p class="contact-block">
        კონტაქტი: <a href="mailto:voidpoko@gmail.com">voidpoko@gmail.com</a>
    </p>
</section>

<section data-i18n-lang="en">
    <h2>Privacy Policy</h2>
    <p class="muted">Last updated: <?= htmlspecialchars($updatedDate) ?></p>

    <h3>1. Who runs this</h3>
    <p>This site (gtu.cortexgrid.ge) and the <strong>GTU ცხრილი</strong>
    Chrome extension are run by a single developer (contact below). It is
    <strong>not</strong> an official Georgian Technical University service
    and is not affiliated with, endorsed by, or operated on behalf of GTU.
    The project is free, open, and built by a student for fellow students.</p>

    <h3>2. What data we touch</h3>
    <p><strong>Public data:</strong> the schedule HTML pages and PDFs hosted
    on leqtori.gtu.ge. We scrape these every 5 hours and store the results
    in our database. Nothing here is private — anyone can view leqtori.gtu.ge.</p>

    <p><strong>Personal data</strong> (only when you use the extension):</p>
    <ul>
        <li>Your <code>Student-Token</code> JWT, which vici.gtu.ge already
            stored in your browser's localStorage when you logged in normally.
            The extension reads this token and uses it to call
            <code>vici.gtu.ge/student/card</code> on your behalf. The token
            is <strong>never sent to us</strong> — it stays in your browser
            and is only used in calls to vici.gtu.ge itself.</li>
        <li>Your <code>/student/card</code> response: name, faculty, semester,
            GPA, current course list (subject + teacher).</li>
    </ul>

    <h3>3. What data we transmit / store</h3>
    <p>When you click "📅 My schedule", the extension forwards a small
    subset of your card to gtu.cortexgrid.ge:</p>
    <ul>
        <li><code>POST /api/me.php</code> — your subject + teacher list,
            used only to look up the matching lecture rows in our scraped
            leqtori database. Discarded the moment the response is generated.</li>
        <li><code>GET /me.php?d=...</code> — same data plus your name and
            faculty, base64-encoded in the URL, so the server can render
            your personal schedule page. <strong>Not logged, not stored,
            not analyzed.</strong></li>
    </ul>

    <p>Your password is <strong>never read</strong>, never transmitted, never
    stored. We have <strong>no analytics, no cookies, no third-party trackers</strong>.</p>

    <h3>4. How long we keep things</h3>
    <ul>
        <li><strong>Personal data:</strong> not kept. The
            <code>/api/me.php</code> and <code>/me.php</code> endpoints
            process your request and immediately discard the payload.
            Server access logs (nginx default) are kept for ≤7 days for
            debugging and are rotated.</li>
        <li><strong>Public scraped data (leqtori):</strong> refreshed every
            5 hours, kept for as long as the project exists.</li>
        <li><strong>On your side:</strong> <code>chrome.storage.local</code>
            on your own machine caches your last card and language preference.
            Clear it anytime from the Chrome extensions page or by
            uninstalling.</li>
    </ul>

    <h3>5. Who else sees your data</h3>
    <p>No one. We don't <strong>sell, share, transfer, or rent</strong>
    personal data to anyone. The matching service runs entirely on the
    same developer's server.</p>

    <h3>6. Your rights</h3>
    <ul>
        <li>Stop using the extension at any time — uninstall and there's
            nothing left on our side, since we don't store your card data.</li>
        <li>Email <a href="mailto:voidpoko@gmail.com">voidpoko@gmail.com</a>
            if you have questions or want anything deleted from the public
            scraped data.</li>
    </ul>

    <h3>7. Changes</h3>
    <p>We'll update the "Last updated" date if anything material changes.
    The current version always lives at
    <a href="https://gtu.cortexgrid.ge/privacy.php">https://gtu.cortexgrid.ge/privacy.php</a>.</p>

    <p class="contact-block">
        Contact: <a href="mailto:voidpoko@gmail.com">voidpoko@gmail.com</a>
    </p>
</section>

</main>

<footer>
    <p class="muted">
        <a href="/privacy.php">Privacy</a> ·
        <a href="/terms.php">Terms</a> ·
        <a href="mailto:voidpoko@gmail.com">voidpoko@gmail.com</a>
    </p>
</footer>

<script src="assets/i18n.js?v=<?= $assetVersion ?>"></script>
</body>
</html>
