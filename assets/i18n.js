'use strict';

/**
 * Tiny i18n layer.
 *
 *   - Dictionary keyed by short identifier (e.g. "search.placeholder").
 *   - Default render language is Georgian (matches what server-side templates
 *     output literally), so KA users see no flash on load.
 *   - On boot, JS swaps everything to English if the user has previously chosen
 *     EN OR if their browser language starts with "en" and they've never picked.
 *   - Plain attributes for translation:
 *       <span    data-i18n="key">გფ</span>
 *       <input   data-i18n-placeholder="key">
 *       <element data-i18n-title="key">
 *       <element data-i18n-html="key"> ← when the translation contains markup
 *
 *   - Programmatic: t("key") or t("key", {n: 5}) for {n} interpolation.
 *   - Day names: DAYS() returns the localised short-day list.
 *
 * Translations are deliberately not too literal — they prioritise being clear
 * to a foreign student over translating word-for-word.
 */

const I18N_DICT = {
    ka: {
        // Brand / chrome
        'site.title':            'GTU ცხრილი',
        'site.subtitle.search':  'GTU ცხრილი — ძიება',
        'lang.toggle.ka':        'ქარ',
        'lang.toggle.en':        'ENG',

        // Stats line on homepage
        'stats.teachers':        '{n} პედაგოგი',
        'stats.subjects':        '{n} საგანი',
        'stats.lectures':        '{n} ლექცია',
        'stats.updated':         'განახლდა {when}',
        'stats.source':          'წყარო',

        // Main search
        'search.placeholder':    'შეიყვანე პედაგოგის გვარი ან საგანი…',
        'search.error':          'შეცდომა ძიებისას',
        'search.lectures.count': '{n} ლექცია',
        'search.show_all':       'ყველა შედეგის ნახვა ({n})',
        'search.show_all.loading': 'იტვირთება ყველა შედეგი…',
        'search.show_all.empty': 'შედეგი არ მოიძებნა',
        'search.show_all.hide':  'დამალვა',

        // Builder page
        'builder.title':                   'ააწყვე შენი ცხრილი — GTU ცხრილი',
        'builder.heading':                 'ააწყვე შენი ცხრილი',
        'builder.lead':                    'აირჩიე საგანი ან პედაგოგი — შემდეგ მისი წყვილი. ცხრილს თვითონ ააწყობს.',
        'builder.step1':                   'ნაბიჯი 1 — აირჩიე საგანი ან პედაგოგი',
        'builder.placeholder.anchor':      'საგანი ან პედაგოგი — KA ან EN',
        'builder.suggest_for_teacher':     'პედაგოგი — აირჩიე საგანი',
        'builder.suggest_for_subject':     'საგანი — აირჩიე პედაგოგი',
        'builder.suggest_hint.teacher':    'ეს პედაგოგი ასწავლის ({n}) საგანს. დააჭირე "+ დაამატე" შენი არჩევანის გვერდით.',
        'builder.suggest_hint.subject':    'ამ საგანს ასწავლის ({n}) პედაგოგი. დააჭირე "+ დაამატე" შენი ლექტორის გვერდით.',
        'builder.suggest_empty':           'ვერ ვიპოვე წყვილი ამ შემთხვევაში.',
        'builder.change_anchor':           '← შეცვალე',
        'builder.add':                     '+ დაამატე',
        'builder.added':                   '✓ დამატებულია',
        'builder.your_courses':            'შენი საგნები',
        'builder.empty_hint':              'ჯერ არ აირჩიე საგანი. ზემოთ მოძებნე — საგანი ან პედაგოგი → დაამატე.',
        'builder.view':                    '📅 ცხრილის ნახვა →',
        'builder.clear':                   'გასუფთავება',
        'builder.clear.confirm':           'წავშალოთ ყველა?',
        'builder.cta':                     'ააწყვე ცხრილი',
        'badge.html':            'ცხრილი',
        'badge.pdf':             'დამატ.',
        'badge.subject':         'საგანი',
        'badge.midterm':         'შუალედ.',

        // Group section
        'group.heading':         'ჯგუფის ცხრილი',
        'group.help.html':       'შეიყვანე შენი ჯგუფის კოდი — მაგ. <code>052510-შ</code> — და ნახე მთელი კვირის ცხრილი.',
        'group.placeholder':     'ჯგუფის კოდი…',
        'group.error':           'შეცდომა მონაცემების ჩატვირთვისას',
        'group.summary':         '{n} ლექცია · {teachers} პედაგოგი · {subjects} საგანი · {sources} წყარო',
        'group.title':           'ჯგუფი {code}',

        // Teacher / subject views
        'teacher.error':         'შეცდომა მონაცემების ჩატვირთვისას',
        'teacher.summary':       '{n} ლექცია · {sources} წყარო',
        'teacher.unknown':       '(უცნობი წყარო)',
        'subject.col.source':    'წყარო',
        'subject.col.teacher':   'პედაგოგი',
        'subject.col.day':       'დღე',
        'subject.col.times':     'საათი',
        'subject.col.room':      'აუდიტორია',
        'subject.col.type':      'ფორმა',
        'subject.col.faculty':   'ფაკულტეტი / განყოფილება',
        'pdfteacher.col.faculty': 'ფაკულტეტი',
        'pdfteacher.col.subject': 'საგანი',
        'pdfteacher.col.type':    'ფორმა',
        'pdfteacher.col.day':     'დღე',
        'pdfteacher.col.times':   'საათი',
        'pdfteacher.col.room':    'აუდიტორია',

        // Faculties (additional courses) section
        'faculties.heading':       'დამატებითი სასწავლო კურსები',
        'faculties.help':          'ფაკულტეტების მიხედვით. PDF-ის ტექსტი ამოღებულია ბრაუზერში სასაძიებლად (Ctrl+F). რთული სტრუქტურის ფაკულტეტებს ჯერ მხოლოდ raw სახით ვინახავთ.',
        'faculties.empty':         'არ არის ჩატვირთული. გაუშვი sync.',
        'faculties.kind.add':      'დამატებითი სასწავლო კურსები',
        'faculties.kind.midterm':  'შუალედური გამოცდები',
        'faculties.tab.structured': 'სტრუქტურირებული',
        'faculties.tab.raw':        'ტექსტი (PDF-ის ნახვა)',
        'faculties.filter.placeholder': 'ფილტრი ამ ფაკულტეტში… (პედაგოგი, საგანი, აუდიტორია)',
        'faculties.no_structured':  'ამ ფაკულტეტისთვის სტრუქტურირებული მონაცემები ჯერ არ გვაქვს — გადართე "ტექსტი"-ზე.',
        'faculties.cell.lectures_struct': '{n} ლექცია სტრუქტ.',
        'faculties.cell.text_only':       'მხოლოდ ტექსტი',
        'faculties.cell.detail':          '{lectures} · {pages} გვერდი · {date}',
        'faculties.subjlabel.pages':      '{n} გვერდი',
        'faculties.col.q':                'q',
        'faculties.col.num':              '#',
        'faculties.col.teacher':          'პედაგოგი',
        'faculties.col.subject':          'საგანი',
        'faculties.col.type':             'ფორმა',
        'faculties.col.day':              'დღე',
        'faculties.col.times':            'საათი',
        'faculties.col.room':             'აუდიტორია',
        'faculties.source':               'PDF წყარო',

        // me.php
        'me.title':                   'ჩემი ცხრილი',
        'me.back':                    '← gtu.cortexgrid.ge — მთავარი ძიება',
        'me.weekgrid.heading':        '🗓️ კვირის ცხრილი',
        'me.weekgrid.help':           'ჩემი ყველა საგნის ლექცია კვირის ცხრილზე.',
        'me.weekgrid.empty':          'სამშაბათი ცარიელია — არც ერთი ლექცია არ მოხვდა ცხრილზე.',
        'me.midterm.agg.heading':     '📝 ყველა შუალედური გამოცდა ({n})',
        'me.midterm.agg.help':        'ყველა საგნის შუალედური გამოცდები ერთ ცხრილში — დღის მიხედვით.',
        'me.midterm.agg.col.date':    'თარიღი',
        'me.midterm.agg.col.time':    'დრო',
        'me.midterm.agg.col.room':    'აუდიტორია',
        'me.midterm.agg.col.subject': 'საგანი',
        'me.midterm.agg.col.faculty': 'ფაკულტეტი',
        'me.course.teacher_n':        'პედაგოგი {n}',
        'me.course.lectures_for':     'ლექციები {teacher}-ისგან',
        'me.empty.heading':           'ცარიელი მდგომარეობა',
        'me.empty.html':              'ამ გვერდს ექსტენშენი ხსნის — დააინსტალირე <code>extension/</code> ფოლდერი როგორც unpacked extension Chrome-ში, შევიდე vici.gtu.ge-ზე, შემდეგ დააჭირე <strong>📅 ჩემი ცხრილი</strong> ღილაკს.',
        'me.empty.error':             'payload error: {msg}',
        'me.no_courses.heading':      'ამჟამინდელი საგნები ვერ ვიპოვე',
        'me.no_courses.body':         'vici-ის card-ში book.semester === view.semester ფილტრმა ვერაფერი დააბრუნა. შესაძლოა card-ი ჯერ არ არის სრულად შევსებული.',
        'me.summary.semester':        '{n}-ე სემესტრი',
        'me.summary.gpa':             'GPA {gpa} ({grade})',
        'me.summary.gpa_no_grade':    'GPA {gpa}',
        'me.summary.courses':         '{n} ამჟამინდელი საგანი',
        'me.allLec.heading':          '📋 ყველა ლექცია ({n})',
        'me.allLec.help':             'ყველა ამჟამინდელი საგნის ლექცია ერთ ცხრილში, დღის მიხედვით.',
        'me.allLec.col.day':          'დღე',
        'me.allLec.col.times':        'დრო',
        'me.allLec.col.subject':      'საგანი',
        'me.allLec.col.teacher':      'პედაგოგი',
        'me.allLec.col.room':         'აუდიტორია',
        'me.allLec.col.type':         'ფორმა',
        'me.allLec.col.group':        'ჯგუფი',
        'me.allLec.col.source':       'წყარო',
        'me.allLec.add_badge':        'დამატ.',
        'me.allLec.source_link':      'წყარო',
        'me.lec.heading':             '📅 ლექციები ({n})',
        'me.lec.col.day':             'დღე',
        'me.lec.col.time':            'დრო',
        'me.lec.col.room':            'აუდიტორია',
        'me.lec.col.type':            'ფორმა',
        'me.lec.col.group':           'ჯგუფი',
        'me.lec.col.source':          'წყარო',
        'me.add.heading':             '📚 დამატებითი კურსების ცხრილი ({n})',
        'me.add.col.day':             'დღე',
        'me.add.col.time':            'დრო',
        'me.add.col.room':            'აუდიტორია',
        'me.add.col.type':            'ფორმა',
        'me.add.col.faculty':         'ფაკულტეტი',
        'me.add.col.source':          'წყარო',
        'me.add.source_link':         'PDF წყარო',
        'me.midterm.heading':         '📝 შუალედური გამოცდები',
        'me.midterm.col.date':        'თარიღი',
        'me.midterm.col.time':        'დრო',
        'me.midterm.col.room':        'აუდიტორია',
        'me.midterm.col.snippet':     'ნაწყვეტი PDF-დან',
        'me.midterm.no_match':        'საგანი ნახსენებია PDF-ში, მაგრამ ზუსტი დღე/დრო/აუდიტორიის ამოცნობა ვერ მოხერხდა — გახსენი PDF.',
        'me.course.no_data':          'ვერ ვიპოვე ლექციები ან გამოცდის ჩანაწერი ჩვენს სკანირებულ მონაცემებში.',
        'me.course.credits':          '{n} კრედიტი',
        'me.footer':                  'მონაცემები მოდის leqtori.gtu.ge-ის ჩვენ მიერ სკანირებულ ვერსიიდან. პერსონალური ინფო (შენი card) მხოლოდ ბრაუზერში რჩება — სერვერზე არაფერი არ ინახება.',

        // Footer
        'footer.html':              'მონაცემები: <a href="https://leqtori.gtu.ge/" target="_blank" rel="noopener">leqtori.gtu.ge</a>. ეს არ არის უნივერსიტეტის ოფიციალური საიტი — გადაამოწმე კრიტიკული ცვლილებები. <a href="/privacy.php">კონფიდენციალურობა</a> · <a href="/terms.php">წესები</a> · <a href="mailto:voidpoko@gmail.com">voidpoko@gmail.com</a>',

        // Days
        'day.1': 'ორშ.', 'day.2': 'სამშ.', 'day.3': 'ოთხშ.', 'day.4': 'ხუთშ.',
        'day.5': 'პარ.', 'day.6': 'შაბ.',  'day.7': 'კვ.',
    },

    en: {
        'site.title':            'GTU Schedule',
        'site.subtitle.search':  'GTU Schedule — Search',
        'lang.toggle.ka':        'ქარ',
        'lang.toggle.en':        'ENG',

        'stats.teachers':        '{n} teachers',
        'stats.subjects':        '{n} subjects',
        'stats.lectures':        '{n} lectures',
        'stats.updated':         'updated {when}',
        'stats.source':          'source',

        'search.placeholder':    'Search teacher or subject…',
        'search.error':          'Search error',
        'search.lectures.count': '{n} lectures',
        'search.show_all':       'Show all results ({n})',
        'search.show_all.loading': 'Loading all results…',
        'search.show_all.empty': 'No results',
        'search.show_all.hide':  'Hide',

        // Builder page
        'builder.title':                   'Build your schedule — GTU ცხრილი',
        'builder.heading':                 'Build your schedule',
        'builder.lead':                    'Pick a subject or a teacher — then their counterpart. We assemble the schedule.',
        'builder.step1':                   'Step 1 — pick a subject or a teacher',
        'builder.placeholder.anchor':      'Subject or teacher — Georgian or English',
        'builder.suggest_for_teacher':     'Teacher — pick a subject they teach',
        'builder.suggest_for_subject':     'Subject — pick the teacher who teaches you',
        'builder.suggest_hint.teacher':    'This teacher teaches {n} subject(s). Click "+ Add" next to yours.',
        'builder.suggest_hint.subject':    '{n} teacher(s) teach this subject. Click "+ Add" next to yours.',
        'builder.suggest_empty':           'No counterpart found.',
        'builder.change_anchor':           '← Change',
        'builder.add':                     '+ Add',
        'builder.added':                   '✓ Added',
        'builder.your_courses':            'Your courses',
        'builder.empty_hint':              'No courses yet. Search above for a subject or teacher, then click + Add.',
        'builder.view':                    '📅 View weekly schedule →',
        'builder.clear':                   'Clear all',
        'builder.clear.confirm':           'Remove all added courses?',
        'builder.cta':                     'Build a schedule',
        'badge.html':            'timetable',
        'badge.pdf':             'extra',
        'badge.subject':         'subject',
        'badge.midterm':         'midterm',

        'group.heading':         'Group schedule',
        'group.help.html':       'Enter your group code — e.g. <code>052510-შ</code> — to see the full week.',
        'group.placeholder':     'Group code…',
        'group.error':           'Could not load data',
        'group.summary':         '{n} lectures · {teachers} teachers · {subjects} subjects · {sources} sources',
        'group.title':           'Group {code}',

        'teacher.error':         'Could not load data',
        'teacher.summary':       '{n} lectures · {sources} sources',
        'teacher.unknown':       '(unknown source)',
        'subject.col.source':    'source',
        'subject.col.teacher':   'teacher',
        'subject.col.day':       'day',
        'subject.col.times':     'time',
        'subject.col.room':      'room',
        'subject.col.type':      'type',
        'subject.col.faculty':   'faculty / section',
        'pdfteacher.col.faculty': 'faculty',
        'pdfteacher.col.subject': 'subject',
        'pdfteacher.col.type':    'type',
        'pdfteacher.col.day':     'day',
        'pdfteacher.col.times':   'time',
        'pdfteacher.col.room':    'room',

        'faculties.heading':       'Additional courses',
        'faculties.help':          'Per faculty. The PDF text is extracted so you can search in the browser (Ctrl+F). Faculties with hard layouts are stored as raw text only.',
        'faculties.empty':         'Nothing loaded. Run sync.',
        'faculties.kind.add':      'Additional courses',
        'faculties.kind.midterm':  'Midterm exams',
        'faculties.tab.structured': 'Structured',
        'faculties.tab.raw':        'Text (view PDF)',
        'faculties.filter.placeholder': 'Filter inside this faculty… (teacher, subject, room)',
        'faculties.no_structured':  'No structured data for this faculty yet — switch to "Text".',
        'faculties.cell.lectures_struct': '{n} structured lectures',
        'faculties.cell.text_only':       'text only',
        'faculties.cell.detail':          '{lectures} · {pages} pages · {date}',
        'faculties.subjlabel.pages':      '{n} pages',
        'faculties.col.q':                'q',
        'faculties.col.num':              '#',
        'faculties.col.teacher':          'teacher',
        'faculties.col.subject':          'subject',
        'faculties.col.type':             'type',
        'faculties.col.day':              'day',
        'faculties.col.times':            'time',
        'faculties.col.room':             'room',
        'faculties.source':               'PDF source',

        'me.title':                   'My schedule',
        'me.back':                    '← gtu.cortexgrid.ge — main search page',
        'me.weekgrid.heading':        '🗓️ Weekly schedule',
        'me.weekgrid.help':           'Every lecture across your ongoing courses, laid out as a Mon–Sat grid.',
        'me.weekgrid.empty':          'Nothing landed on the grid — no lectures matched a weekday slot.',
        'me.midterm.agg.heading':     '📝 All midterms ({n})',
        'me.midterm.agg.help':        'Every midterm exam across your courses, sorted by date.',
        'me.midterm.agg.col.date':    'date',
        'me.midterm.agg.col.time':    'time',
        'me.midterm.agg.col.room':    'room',
        'me.midterm.agg.col.subject': 'subject',
        'me.midterm.agg.col.faculty': 'faculty',
        'me.course.teacher_n':        'Teacher {n}',
        'me.course.lectures_for':     'Lectures from {teacher}',
        'me.empty.heading':           'Nothing here yet',
        'me.empty.html':              'This page is opened by the extension — install the <code>extension/</code> folder as an unpacked extension in Chrome, log in to vici.gtu.ge, then click the <strong>📅 My schedule</strong> button.',
        'me.empty.error':             'Payload error: {msg}',
        'me.no_courses.heading':      'No current courses found',
        'me.no_courses.body':         'No book in your vici card matched book.semester === view.semester. Maybe the card hasn’t been populated yet.',
        'me.summary.semester':        'Semester {n}',
        'me.summary.gpa':             'GPA {gpa} ({grade})',
        'me.summary.gpa_no_grade':    'GPA {gpa}',
        'me.summary.courses':         '{n} ongoing course(s)',
        'me.allLec.heading':          '📋 All lectures ({n})',
        'me.allLec.help':             'Every lecture across your ongoing courses, sorted by day.',
        'me.allLec.col.day':          'day',
        'me.allLec.col.times':        'time',
        'me.allLec.col.subject':      'subject',
        'me.allLec.col.teacher':      'teacher',
        'me.allLec.col.room':         'room',
        'me.allLec.col.type':         'type',
        'me.allLec.col.group':        'group',
        'me.allLec.col.source':       'source',
        'me.allLec.add_badge':        'extra',
        'me.allLec.source_link':      'source',
        'me.lec.heading':             '📅 Lectures ({n})',
        'me.lec.col.day':             'day',
        'me.lec.col.time':            'time',
        'me.lec.col.room':            'room',
        'me.lec.col.type':            'type',
        'me.lec.col.group':           'group',
        'me.lec.col.source':          'source',
        'me.add.heading':             '📚 Additional course schedule ({n})',
        'me.add.col.day':             'day',
        'me.add.col.time':            'time',
        'me.add.col.room':            'room',
        'me.add.col.type':            'type',
        'me.add.col.faculty':         'faculty',
        'me.add.col.source':          'source',
        'me.add.source_link':         'PDF source',
        'me.midterm.heading':         '📝 Midterm exams',
        'me.midterm.col.date':        'date',
        'me.midterm.col.time':        'time',
        'me.midterm.col.room':        'room',
        'me.midterm.col.snippet':     'PDF excerpt',
        'me.midterm.no_match':        'Subject is mentioned in the PDF, but we could not pin down the exact day/time/room — open the PDF.',
        'me.course.no_data':          'No lectures or exam entries found in our scraped data.',
        'me.course.credits':          '{n} credits',
        'me.footer':                  'Data comes from our scrape of leqtori.gtu.ge. Personal info (your card) stays in your browser only — nothing is stored on the server.',

        'footer.html':              'Data: <a href="https://leqtori.gtu.ge/" target="_blank" rel="noopener">leqtori.gtu.ge</a>. This is not the official university site — verify critical changes there. <a href="/privacy.php">Privacy</a> · <a href="/terms.php">Terms</a> · <a href="mailto:voidpoko@gmail.com">voidpoko@gmail.com</a>',

        'day.1': 'Mon', 'day.2': 'Tue', 'day.3': 'Wed', 'day.4': 'Thu',
        'day.5': 'Fri', 'day.6': 'Sat', 'day.7': 'Sun',
    },
};

let _CURRENT_LANG = null;

function getLang() {
    if (_CURRENT_LANG) return _CURRENT_LANG;
    let stored = null;
    try { stored = localStorage.getItem('lang'); } catch {}
    if (stored === 'ka' || stored === 'en') {
        _CURRENT_LANG = stored;
    } else {
        _CURRENT_LANG = (typeof navigator !== 'undefined' && /^en\b/i.test(navigator.language || ''))
            ? 'en' : 'ka';
    }
    return _CURRENT_LANG;
}

function setLang(lang) {
    if (lang !== 'ka' && lang !== 'en') return;
    _CURRENT_LANG = lang;
    try { localStorage.setItem('lang', lang); } catch {}
    document.documentElement.lang = lang;
    applyTranslations();
    document.dispatchEvent(new CustomEvent('langchange', { detail: { lang } }));
}

function t(key, params = {}) {
    const lang = getLang();
    let s = (I18N_DICT[lang] && I18N_DICT[lang][key])
         ?? (I18N_DICT.ka  && I18N_DICT.ka[key])
         ?? key;
    for (const k in params) {
        s = s.split(`{${k}}`).join(String(params[k]));
    }
    return s;
}

function days() {
    const out = [''];
    for (let i = 1; i <= 7; i++) out.push(t('day.' + i));
    return out;
}

/**
 * Pulls interpolation params off data-arg-* attributes.
 * `<span data-i18n="stats.teachers" data-arg-n="58">…</span>` → t('stats.teachers', {n: '58'}).
 */
function readArgs(el) {
    const args = {};
    for (const attr of el.attributes) {
        if (attr.name.startsWith('data-arg-')) {
            args[attr.name.slice(9)] = attr.value;
        }
    }
    return args;
}

function applyTranslations() {
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const v = t(el.dataset.i18n, readArgs(el));
        if (v != null) el.textContent = v;
    });
    document.querySelectorAll('[data-i18n-html]').forEach(el => {
        const v = t(el.dataset.i18nHtml, readArgs(el));
        if (v != null) el.innerHTML = v;
    });
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
        const v = t(el.dataset.i18nPlaceholder, readArgs(el));
        if (v != null) el.placeholder = v;
    });
    document.querySelectorAll('[data-i18n-title]').forEach(el => {
        const v = t(el.dataset.i18nTitle, readArgs(el));
        if (v != null) el.title = v;
    });
    // Day cells rendered server-side as <span data-day="N">ორშ.</span> get
    // text-swapped here so they pick up the active language.
    document.querySelectorAll('[data-day]').forEach(el => {
        const n = parseInt(el.dataset.day, 10);
        if (n >= 1 && n <= 7) el.textContent = t('day.' + n);
    });

    // Long-form bilingual blocks: <section data-i18n-lang="ka">…</section>
    // and <section data-i18n-lang="en">…</section>. Used for privacy/terms
    // pages where the text is too long to live in the dictionary. We just
    // show the block matching the active language and hide the others.
    const cur = getLang();
    document.querySelectorAll('[data-i18n-lang]').forEach(el => {
        el.style.display = (el.dataset.i18nLang === cur) ? '' : 'none';
    });

    document.querySelectorAll('.lang-switcher [data-lang]').forEach(btn => {
        btn.setAttribute('aria-pressed', btn.dataset.lang === getLang() ? 'true' : 'false');
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.documentElement.lang = getLang();
    applyTranslations();
    document.querySelectorAll('.lang-switcher [data-lang]').forEach(btn => {
        btn.addEventListener('click', () => setLang(btn.dataset.lang));
    });
});
