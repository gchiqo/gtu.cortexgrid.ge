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
<title>Terms of Use / გამოყენების წესები — GTU ცხრილი</title>
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
    <h2>გამოყენების წესები</h2>
    <p class="muted">ბოლო განახლება: <?= htmlspecialchars($updatedDate) ?></p>

    <h3>1. ეს არ არის GTU-ის ოფიციალური სერვისი</h3>
    <p>ეს საიტი (gtu.cortexgrid.ge) და GTU ცხრილი ექსტენშენი არის
    დამოუკიდებელი პროექტი — სტუდენტის ნამოქმედარი სტუდენტებისთვის. ეს
    <strong>არ არის</strong> საქართველოს ტექნიკური უნივერსიტეტის
    ოფიციალური საიტი/აპლიკაცია. ოფიციალური წყაროებია
    <a href="https://leqtori.gtu.ge/" target="_blank" rel="noopener">leqtori.gtu.ge</a>,
    <a href="https://vici.gtu.ge/" target="_blank" rel="noopener">vici.gtu.ge</a> და
    <a href="https://gtu.ge/" target="_blank" rel="noopener">gtu.ge</a>.
    კრიტიკული ინფორმაცია (გამოცდის თარიღი/აუდიტორია, განრიგის
    ცვლილებები) ყოველთვის გადაამოწმე ოფიციალურ წყაროზე, სანამ რამეს
    გადაწყვეტ.</p>

    <h3>2. მონაცემები არის "best-effort"</h3>
    <p>ჩვენ ცხრილებს ვიღებთ leqtori.gtu.ge-დან ყოველ 5 საათში. ორ scrape-ს
    შორის მონაცემი შეიძლება იყოს მოძველებული; PDF-ის parser-მა შეიძლება
    არასწორად წაიკითხოს ცხრილების ცალკეული ველი. ჩვენ ვეცდებით ყველაფერი
    სწორად მოვიხდინოთ, მაგრამ <strong>გარანტიას არ ვიძლევთ</strong>
    სიზუსტის, სრულობის, ან რაიმე კონკრეტული მიზნებისთვის ვარგისიანობის
    შესახებ. ეს საიტი <strong>ერთადერთი</strong> წყარო არ უნდა იყოს
    არცერთი მნიშვნელოვანი გადაწყვეტილებისთვის.</p>

    <h3>3. გამოყენებით თქვენ თანხმდებით:</h3>
    <ul>
        <li>არ ვაბუზებ სისტემას (ბევრი ერთდროული მოთხოვნა, API-ს scraping
            პერსონალური მოხმარების გარდა, სხვა მომხმარებლების
            დე-ანონიმიზაციის მცდელობა).</li>
        <li>საჯარო ცხრილის მონაცემებს არ გამოვიყენებ კომერციული
            მიზნებისთვის (თუ არა მხოლოდ ჩემი სასწავლო დაგეგმვისთვის).</li>
        <li>ჩემი vici.gtu.ge-ის credential-ების უსაფრთხოებაზე მე ვარ
            პასუხისმგებელი. ექსტენშენი მხოლოდ იმ token-ს კითხულობს, რომელიც
            ბრაუზერს უკვე აქვს — თუ ჩემი account-ი კომპრომეტირებულია,
            ეს ცალკე საკითხია, რომელიც GTU-ის უნდა მივმართო.</li>
    </ul>

    <h3>4. დევერი არ აგებს პასუხს</h3>
    <p>ჩვენ <strong>არ ვაგებთ პასუხს</strong> გაცდენილ გამოცდებზე,
    არასწორ აუდიტორიებზე, ცხრილის კონფლიქტებზე, ან სხვა რაიმე ზიანზე,
    რომელიც ამ საიტის მოძველებული ან არასწორი მონაცემების გამო შეიძლება
    მოხდეს.</p>

    <h3>5. კონტაქტი / მონაცემის წაშლა</h3>
    <p>თუ თქვენ ასწავლით ან მუშაობთ GTU-ში და ჩვენთან რაიმე არასწორად წერია
    თქვენზე, ემაილი
    <a href="mailto:voidpoko@gmail.com">voidpoko@gmail.com</a>-ზე გაგზავნეთ
    და ჩვენ გავასწორებთ ან წავშლით.</p>

    <p class="contact-block">
        კონტაქტი: <a href="mailto:voidpoko@gmail.com">voidpoko@gmail.com</a>
    </p>
</section>

<section data-i18n-lang="en">
    <h2>Terms of Use</h2>
    <p class="muted">Last updated: <?= htmlspecialchars($updatedDate) ?></p>

    <h3>1. Not an official GTU service</h3>
    <p>This site (gtu.cortexgrid.ge) and the GTU ცხრილი extension are an
    independent project — built by a student, for fellow students. They are
    <strong>not</strong> operated, endorsed, or audited by Georgian Technical
    University. The official sources are
    <a href="https://leqtori.gtu.ge/" target="_blank" rel="noopener">leqtori.gtu.ge</a>,
    <a href="https://vici.gtu.ge/" target="_blank" rel="noopener">vici.gtu.ge</a>, and
    <a href="https://gtu.ge/" target="_blank" rel="noopener">gtu.ge</a>.
    Always confirm critical information (exam dates, exam rooms, schedule
    changes) at the official source before acting on it.</p>

    <h3>2. Data is best-effort</h3>
    <p>We pull schedule data from leqtori.gtu.ge every 5 hours. Between
    scrapes, the data may be stale; the parsers may also misread fields,
    especially in PDF tables. We do our best, but provide
    <strong>no warranty</strong> of accuracy, completeness, or fitness for
    any purpose. Don't use this site as your <strong>only</strong> source
    for anything important.</p>

    <h3>3. By using the site or extension you agree:</h3>
    <ul>
        <li>You will not abuse the system (spamming requests, scraping the
            API beyond personal use, attempting to deanonymize other users).</li>
        <li>You won't try to use the public schedule data for commercial
            purposes beyond personal study planning.</li>
        <li>You're responsible for keeping your own vici.gtu.ge credentials
            safe. The extension only reads the token your browser already has —
            if your account is compromised, that's a separate matter to take
            up with GTU directly.</li>
    </ul>

    <h3>4. No liability</h3>
    <p>We're <strong>not liable</strong> for missed exams, wrong rooms,
    schedule conflicts, or any other harm that happens because of stale or
    wrong data on this site.</p>

    <h3>5. Contact / take-down</h3>
    <p>If you teach or work at GTU and something here is wrong about you,
    email <a href="mailto:voidpoko@gmail.com">voidpoko@gmail.com</a> and
    we'll fix or remove it.</p>

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
