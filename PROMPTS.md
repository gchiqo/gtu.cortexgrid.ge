# Prompts I used to build this project

Every prompt I sent to **Claude Code** (Opus 4.7, 1M context) while building [gtu.cortexgrid.ge](https://gtu.cortexgrid.ge) and the matching Chrome extension — in roughly the order I sent them. Sensitive bits (DB password, my own academic record, my vici credentials) are redacted; everything else is verbatim, including the typos.

The work spans roughly two days (2026-05-08 → 2026-05-09). Tool calls, edits, and Claude's responses aren't shown — just my side of the conversation.

---

## Phase 1 — Genesis: scrape leqtori.gtu.ge into a DB

### #1
```
so i want to make wegsite+chrome extension to . make  it easyer for me and  other students at GTU to manage their schedule.
so here is website where i can see my subjects and  teachers https://vici.gtu.ge/#/learningCard (it needs authorization (we don't need to think about it yet (only after we move to extension)))
and here https://leqtori.gtu.ge/ is links that can take us to when and wheere lecture is.  i want to make website that will do chack every 5 hours and gather data in its db (from witch link it got info, subject, teacher, place, time)
```

### #2
```
username: gtu
db: gtu
password: <redacted>
```

### #3 — list of faculties to ingest
```
i need this info to
დამატებითი სასწავლო კურსების ცხრილი

სამშენებლო ფაკულტეტი / Faculty of Construction
ენერგეტიკის ფაკულტეტი / Faculty of Power Engineering
სამთო გეოლოგიური და მთის მდგრადი განვითარების ფაკულტეტი / Faculty of Mining Geology and Sustainable Mountain Development
აგრარული მეცნიერებისა და ქიმიური ტექნოლოგიების ფაკულტეტი / Faculty of Agricultural Sciences and Chemical Technologies
სატრანსპორტო სისტემები და მექანიკის ინჟინერიის ფაკულტეტი / Transport Systems and Mechanical Engineering
არქიტექტურა, ურბანისტიკა და დიზაინის ფაკულტეტი / Faculty of Architecture, Urbanism and Design
სამართალი და საერთაშორისო ურთიერთობების ფაკულტეტი / Faculty of Law and International Relations
ინფორმატიკისა და მართვის სისტემების ფაკულტეტი / Faculty of Informatics and Control Systems
ბიზნესტექნოლოგიების ფაკულტეტი / Faculty of Business Technologies
სოციალურ მეცნიერებათა ფაკულტეტი / Faculty of Social Sciences
```

### #4
```
i want to have hat info in my db like i have პედაგოგები/Teachers
```

### #5
```
i want all of the lecturers (no mater if they are in "დამატებითი სასწავლო კურსების ცხრილი") (in pdf)
all of them should be searchable in main page
```

### #6 — first regression
```
when i click on something missing id
```

---

## Phase 2 — Bilingual / cross-script search

### #7
```
i want to be able to search with both with teacher name and with subject name
and  in both languages en/ge
```

### #8 — bug: surname split
```
ცხომელიძე

3 ლექცია · ფაკულტეტი: იმს · ლუკა
[…table showing teacher's first name "ლუკა" parsed as the teacher and subject name missing…]

ცხომელიძე ლუკა ლექტორია და ის ორ საგანს ასწავლის აქ კი არასწორად ჩანს და საგნის სახელი საერთოდ არ წერია
```

### #9 — bug: Latin-script search misses Georgian rows
```
[…showing search for "ლუკა ცხომელიძე" returns 3 results but "Tskhomelidze Luka" returns 1…]

tskhom ინგლისურად რო დავწერ მარტო ერთს აგდებს მინდა რომ ავტომატური (ლათინური -> ქართული გადაყვანა ჰქონდეს, რომ ყველა შესაძლო შედეგი ვნახო)
```

### #10
```
ჩამოთვალე ლინკები რომლებიდანაც ინფორმაცია მოგვაქვს
https://leqtori.gtu.ge
```

### #11 — exclusion list
```
აკადემიური ჯგუფები/Groups
აუდიტორიები/Rooms
სწავლის შედეგების შეფასების გასაჩივრება და საპატიო მიზეზით გაცდენილი შეფასების/გამოცდის აღდგენა
```

### #12 — full ingest spec, with provenance
```
პედაგოგების ორი ცხრილია ორივე უნდა წამოვიღოთ (შეიძლება ერთმანეთს ჰგავდეს მაგრამ სხვადასხვა ინფორმაციაა)

მოცემულ გვერდძე ყველა ლინკიდან (.html, .pdf) უნდა წამოვიღოთ ინფორმაცია გარდა
აკადემიური ჯგუფები/Groups
აუდიტორიები/Rooms
სწავლის შედეგების შეფასების გასაჩივრება და საპატიო მიზეზით გაცდენილი შეფასების/გამოცდის აღდგენა

და მნიშვნელოვანია რომ ძებნის დროს და შედეგების ჩვენებისას ეწეროს ზუსტი ინფორმაცია საიდან წამოვიღეთ ინფორმაცია (ზოგს ზედა სათაური აქვს (პროფესიული სწავლების ცხრილი (4.05.2026-9.05.2026)), VIII კვირის სასწავლო ცხრილი, შუალედური გამოცდების ცხრილი / Midterm Exam Schedule., დამატებითი სასწავლო კურსების ცხრილი, )
```

---

## Phase 3 — Polish, group search, cron, PDF parser

### #13
```
what improvements can we make
```

### #14
```
ok add another page or something and in it search with group and display all things at once
```

### #15
```
what cron command should i add or did you do it
```

### #16
```
what do we use composer for
```

---

## Phase 4 — Chrome extension foundation

### #17
```
ok now make extension  but before that can you connect with claudes extension to inspect web
```

### #18 — manifest fix + branding
```
Failed to load extension
File: ~/Downloads/archive/extension
Error: Default locale was specified, but _locales subtree is missing.
Could not load manifest.

and add icon purple GTU
```

### #19 / #20
> Pasted my full `vici.gtu.ge` `/student/card` JSON response (~24 KB) so Claude could see the real shape of the data the extension would have to handle. Redacted here — it contains my personal academic record.

### #21
> Pointed at `extension/content.js:100` (the `payload` line) to ask about base64 encoding of the URL payload.

### #22 — show midterms inline + flat lectures table
```
way do we need to encode payload?
can we show midterm data in site not only redirect to pdf
and section to show all lections as table
```

---

## Phase 5 — Bilingual UI + personal schedule page

### #23
```
can you add language switcher or something so foreign students can better understand some things that now are only in georgian
```

### #24 — big spec for `me.php` + extension popup
```
make separate midterms table
and in extension set language
and add fetch gtu.cortexgrid.ge api to it so it can directly fetch lecture date time based on both teacher and subject name
and add message if you can't finde here or want more ifo go to website

and in here https://gtu.cortexgrid.ge/me.php?d=e
add ← gtu.cortexgrid.ge main search page
on top add table of week showing exact tacher and subject
and in subject separate tables if there are two lecturers write lecturers names
```

### #25 — exclude teacher who isn't actually mine
```
პეტრიაშვილი ლილი Petreashvili Lili არ მასწავლის
ამიტომ 🗓️ კვირის ცხრილში არ აჩვენო ის ()

[…full screenshot of the weekly grid with both Petreashvili and Bichnigauri showing for the same Hadoop course…]
```

---

## Phase 6 — Matching bugs (the long debug session)

### #26 / #27
> Pasted the specific course entry from `/student/card` so Claude could see the exact shape (course code, multiple teachers, etc.). Redacted — same reason as #19/#20.

### #28
```
პეტრიაშვილი ლილი "დიდი მონაცემების შენახვა და დამუშავების სისტემა Hadoop"-ს არ ასწავლის მაგრამ ცხრილში ასე მიჩანს

[…full leqtori weekly table for Petreashvili showing she only teaches "Fundamentals of Database Systems", not Hadoop — the matcher was finding her by surname only and over-attaching her to a Hadoop course…]
```

### #29
```
[Request interrupted by user]
```

### #30 — same bug, restated
```
[…same Petreashvili weekly table again, with the explanation that the loose subject matcher was over-matching via the substring "data"…]

პეტრიაშვილი ლილი "დიდი მონაცემების შენახვა და დამუშავების სისტემა Hadoop"-ს არ ასწავლის მაგრამ ცხრილში მაინც მიჩანს როგორც მოცემული საგნის ერთერთი ლექტორი
```

### #31
```
show time and day in extension popup to
```

### #32 — opposite bug: real teacher missing
```
მთავარ ცხრილში

[…full leqtori weekly table for Bichnigauri Avtandil showing he DOES teach "Hadoop ეკოსისტემა" (note: "ეკოსისტემა" not "სისტემა" — one word different from card)…]

მაგრამ ჩვენს საიტზე მაჩვენებს მხოლოდ დამატებით ცხრილის ინფორმაციას
```
> This is what triggered the 3-step matching staircase: strict → loose → fuzzy with overlap threshold (≥3 matching ≥4-char words AND ≥50% of card-side words). Catches "სისტემა Hadoop" vs "ეკოსისტემა Hadoop" while still rejecting Lili's Database Systems.

---

## Phase 7 — Chrome Web Store listing

### #33 — store form pasted in
```
[…the entire blank Chrome Web Store store-listing form pasted in: Description, Category, Language, Graphic assets, Screenshots, Small/Marquee promo tiles, Homepage URL, Support URL, Mature content…]

now i want to upload extension

for each what should i wrte in
and an you make imagess for me to upload there
```

### #34 — second store-listing pass
```
[…blanks: Description, Category, Language…]
```

### #35 — Privacy section of the form
```
Single purpose / Permission justification / storage justification / Host permission justification / Remote code / Data usage [Personally identifiable information / Health / Financial / Authentication / …]
```

### #36 — privacy + terms pages
```
add privacy policy and terms of use  and contact voidpoko@gmail.com
on our website
```

### #37 — test instructions for Google reviewers
```
Test instructions
[…the form with my Username 49001014840, Password (redacted), and Additional instructions: https://vici.gtu.ge/#/login — this is my personal account…]
```

### #38 — final pass on the Privacy section, with the answers Claude had drafted
> A long paste-back of the filled-in Single-purpose, storage justification, host-permission justifications, and data-usage disclosures so Claude could sanity-check that they matched the code. Trimmed here for length.

---

## Phase 8 — SEO + UX additions

### #39
```
add seo things so site gets indexed when someone search for
gtu cxrili
ტექნიკური უნივერსიტეტი ცხრილი
technical university
and other things like that
```

### #40
```
in search add show all results
and show all results at once like all of them been clicked at once
```

---

## Phase 9 — Verification + meta

### #41
```
does cron you created work or should i add it in cloudpanel or anything else
```

### #42
```
how much time does extennsion review takes
```

### #43
```
add readme and prompts file that describes the project and how it works and what  prompts did i use to create this project
```

---

## What I learned from this

A few things worth recording:

- **My side stayed terse and Georgian-mixed.** I almost never explained *why*; I just pasted screenshots, table dumps, and the occasional "this is wrong, fix it." Claude was fine with that.
- **Pasting the actual broken output beats describing it.** Every matching-bug fix in Phase 6 came from me pasting the leqtori HTML and saying "but our site shows X" — Claude could see the discrepancy directly.
- **Big features came in one sentence.** The whole Chrome-extension scaffold started from #17 ("ok now make extension"). The whole `me.php` page came from a six-line spec in #24.
- **Two days of conversation, ~43 prompts, ~9 MB of transcript.** The working tree at the end: a PHP+MySQL site, an MV3 extension, two faculty-PDF parsers, a 3-step matcher, full bilingual UI, store assets, privacy/terms pages, SEO, and a cron drop-in.

— `voidpoko@gmail.com`, 2026-05-09
