# GTU ცხრილი — Personal Schedule

A bilingual (Georgian / English) class-schedule tool for **საქართველოს ტექნიკური უნივერსიტეტი** (Georgian Technical University / GTU) students. It scrapes the public schedule pages on `leqtori.gtu.ge` every 5 hours into a local MySQL database, exposes search by teacher / subject / group, and ships a companion Chrome extension that bridges each student's authenticated `vici.gtu.ge` course list with that public data so they get one personal weekly schedule for free.

Live: <https://gtu.cortexgrid.ge>

---

## What it does

| Component | What it gives the user |
|---|---|
| **Website** (`gtu.cortexgrid.ge`) | Search any teacher, subject or group → see their full week as a 6-day × 12-slot grid. Faculty-by-faculty browser for the additional-courses and midterm-exam PDFs. |
| **Chrome extension** ("GTU ცხრილი") | When the student opens `vici.gtu.ge` and hits the toolbar icon, the popup shows their **personal** weekly schedule by joining their enrolled course list with the scraped lecture data. Also a "ჩემი ცხრილი" button that opens the full schedule page on the website. |
| **Cron sync** | `cron/sync.php` runs every 5 h, re-fetches the leqtori homepage, walks every linked HTML/PDF, parses, and upserts into MySQL. Provenance (`source.url`, `source.fetched_at`) is preserved on every row so the UI can say "this came from VIII კვირის ცხრილი, updated at 09:00". |

---

## Architecture

```
                      ┌───────────────────────────┐
                      │ leqtori.gtu.ge            │
                      │  • teachers.html × 2      │
                      │  • additional courses PDF │
                      │  • midterm exams PDF      │
                      └──────────────┬────────────┘
                                     │ every 5 h
                                     ▼
   ┌──────────┐   /api/search.php    ┌────────────────────┐
   │ Browser  ├─────────────────────►│ PHP 8.3 + MySQL    │
   │ (search) │                      │  ├─ teacher        │
   └──────────┘                      │  ├─ subject        │
                                     │  ├─ lecture        │
                                     │  ├─ additional_…   │
                                     │  ├─ pdf_doc        │
                                     │  └─ source         │
                                     └─────────▲──────────┘
                                               │ /api/me.php
   ┌────────────┐    JWT (Student-Token)       │
   │ vici.gtu.ge│───────────────┐              │
   └────────────┘               │              │
                                ▼              │
                         ┌──────────────┐      │
                         │ Chrome ext.  │──────┘
                         │ content.js + │   POSTs courses
                         │ popup        │   + name + school
                         └──────────────┘
```

The extension never asks the student to log in again — it reads the JWT that `vici.gtu.ge`'s own Angular app put in `localStorage` during normal login, calls vici's `/student/card` for the course list, and then hands that list (name + school + courses) to `gtu.cortexgrid.ge/api/me.php` for matching. Nothing is persisted server-side; the matched payload only ever lives in the URL of the personal-schedule page.

---

## Tech stack

- **Backend:** PHP 8.3, MySQL (utf8mb4_unicode_ci), [smalot/pdfparser](https://github.com/smalot/pdfparser) (pure-PHP, no system deps)
- **Frontend:** vanilla JS, server-rendered i18n via `data-i18n` / `data-arg-*` attributes
- **Extension:** Chrome Manifest V3, content script + popup, `chrome.storage.local` for caching
- **Hosting:** CloudPanel + nginx + Cloudflare in front
- **Cron:** `/etc/cron.d/gtu-sync` runs every 5 h as the site user

---

## Data sources

Everything is scraped from the public site `https://leqtori.gtu.ge`. The homepage links to:

- HTML teacher tables (two of them — sometimes the same teacher appears in both with slightly different schedules; both are kept and rendered as separate "source" sections)
- Additional-courses PDFs (one per faculty)
- Midterm-exam PDFs (one per faculty)

Excluded by design (lower signal, would clutter search): rooms list, groups list, "appeals" pages.

---

## File layout

```
.
├── index.php              search landing page (teacher / subject / group)
├── me.php                 personal schedule, takes ?d=<base64-encoded payload>
├── privacy.php            bilingual privacy policy (KA + EN)
├── terms.php              bilingual terms of use
├── robots.txt + sitemap.xml
│
├── api/
│   ├── search.php         autocomplete teacher/subject across HTML + PDF
│   ├── teacher.php        full schedule for a single teacher (HTML)
│   ├── pdf_teacher.php    full table for a teacher reconstructed from PDFs
│   ├── subject.php        every lecture for a subject across all sources
│   ├── group.php          full week for a single group code
│   ├── groups.php         autocomplete group codes
│   ├── faculty.php        structured + raw text of one faculty PDF
│   ├── faculties.php      list of cached faculty PDFs
│   └── me.php             accepts {courses, name, school} → enriched schedule
│
├── lib/
│   ├── config.php         DB creds (gitignored in production)
│   ├── db.php             PDO factory
│   ├── http.php           curl wrapper
│   ├── parser.php         HTML teacher-table parser
│   ├── pdf.php            PDF text/coordinate extraction (smalot/pdfparser)
│   ├── additional_parser.php   position-aware parser for additional-courses PDFs
│   ├── faculty.php        faculty-name → slug classifier
│   ├── translit.php       Georgian ↔ Latin (ISO 9984-ish) for cross-script search
│   └── match.php          3-step staircase matching: strict → loose → fuzzy
│
├── cron/sync.php          full re-scrape, runs every 5 h
├── data/sync.log          rolling cron log
│
├── assets/
│   ├── style.css, app.js  search + result rendering
│   ├── i18n.js            KA/EN dictionary + langchange dispatcher
│   ├── me.css, me.js      personal-schedule page (teacher exclusions, etc.)
│   ├── legal.css          privacy/terms styling
│   └── og-image.png       social-card image
│
├── extension/
│   ├── manifest.json      MV3, host: vici.gtu.ge + gtu.cortexgrid.ge
│   ├── content.js         runs on vici.gtu.ge, reads JWT, calls /student/card
│   ├── popup/             popup HTML/CSS/JS + i18n
│   ├── icons/             16/32/48/128/512 PNGs
│   └── store_assets/      promo tiles + screenshots for the Web Store
│
├── composer.json + vendor/   smalot/pdfparser
└── PROMPTS.md             every prompt I sent Claude Code while building this
```

---

## Setup

1. **Clone + composer install**
   ```bash
   composer install --no-dev
   ```
2. **Create the DB and import the schema**
   ```bash
   mysql -u root -p -e "CREATE DATABASE gtu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p -e "CREATE USER 'gtu'@'127.0.0.1' IDENTIFIED BY '<pw>'; GRANT ALL ON gtu.* TO 'gtu'@'127.0.0.1';"
   ```
   Tables (`teacher`, `subject`, `lecture`, `additional_lecture`, `pdf_doc`, `source`) are created on first sync run.
3. **Copy `lib/config.php.example` → `lib/config.php`** and put the DB credentials in.
4. **First sync** (one-shot)
   ```bash
   php cron/sync.php
   ```
   Watch `data/sync.log` to confirm rows landed.
5. **Install the cron drop-in**
   ```bash
   sudo cp deploy/cron.d-gtu-sync /etc/cron.d/gtu-sync
   ```
   Or via CloudPanel's *Cron Jobs* UI — but only **one** of the two, not both.
6. **Point the web root** at this directory. nginx + PHP-FPM 8.3 is the tested combo.

The Chrome extension is loaded separately as an unpacked extension during dev, or installed from the Chrome Web Store (review pending).

---

## Sync schedule

```
0 */5 * * *  cortexgrid-gtu  /usr/bin/php /…/cron/sync.php >> /…/data/sync.log 2>&1
```

That's 00:00, 05:00, 10:00, 15:00, 20:00 UTC. A full run touches ~30 sources, parses ~10 PDFs, and finishes in roughly 30–60 s.

---

## Privacy + scope

- The scraped data is **public** information published by GTU. The site re-presents it with attribution back to the source URL on every row.
- **No analytics, no cookies, no trackers.** The student's name and course list, when sent through `/api/me.php`, are used only to compute the response and are not stored.
- The Chrome extension keeps the JWT inside the browser. It is never sent to `gtu.cortexgrid.ge` — only the resulting course list is.
- Full disclosures: [`/privacy.php`](privacy.php), [`/terms.php`](terms.php).
- This site is **not** affiliated with or endorsed by GTU.

---

## Acknowledgements

The idea for this project was suggested by **ლუკა ცხომელიძე (Luka Tskhomelidze)**, lecturer of *ხელოვნური ინტელექტის საფუძვლები / Fundamentals of Artificial Intelligence* at GTU — who encouraged building something practical like this. Thank you.

---

## Built with

This whole project — backend, frontend, extension, scraper, store listing, SEO, legal pages — was built collaboratively with **Claude Code** (Opus 4.7, 1M context). Every prompt I used is in [`PROMPTS.md`](PROMPTS.md).

---

## Contact

- Email: `voidpoko@gmail.com`
- Issues / take-down requests: same address
