'use strict';

/**
 * Builder page — two-step picker:
 *   1) User searches for a subject OR a teacher (the "anchor")
 *   2) Page fetches the anchor's full schedule and shows its COUNTERPART
 *      side as clickable cards (teacher → their subjects, subject → its
 *      teachers). One click adds the pair to the schedule.
 *
 * Final list is persisted in localStorage; "View" builds the same payload the
 * Chrome extension produces and opens /me.php in a new tab.
 */

const $ = (sel) => document.querySelector(sel);

const anchorQ        = $('#anchorQ');
const anchorResults  = $('#anchorResults');
const suggestSection = $('#suggestSection');
const suggestKindEl  = $('#suggestKind');
const suggestNameEl  = $('#suggestName');
const suggestHint    = $('#suggestHint');
const suggestList    = $('#suggestList');
const suggestEmpty   = $('#suggestEmpty');
const changeAnchor   = $('#changeAnchorBtn');
const courseList     = $('#courseList');
const courseCount    = $('#courseCount');
const emptyHint      = $('#emptyHint');
const viewBtn        = $('#viewBtn');
const clearBtn       = $('#clearBtn');

const STORAGE_KEY = 'gtu_builder_courses';

/** @type {{subject:string, teacher:string}[]} */
let courses = loadCourses();

/** Current anchor selection. `kind` is 'teacher' or 'subject'. */
let anchor = null;

// ------------------------------------------------------------------
// Storage
// ------------------------------------------------------------------

function loadCourses() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return [];
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed.filter(c => c && c.subject && c.teacher) : [];
    } catch { return []; }
}
function saveCourses() {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(courses)); }
    catch { /* private mode / quota — silent */ }
}

// ------------------------------------------------------------------
// Step 1: anchor search
// ------------------------------------------------------------------

let searchTimer = null;
let lastQ = null;

anchorQ.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(runAnchorSearch, 150);
});

async function runAnchorSearch() {
    const q = anchorQ.value.trim();
    if (q === lastQ) return;
    lastQ = q;

    if (q === '') {
        anchorResults.innerHTML = '';
        anchorResults.classList.remove('show');
        return;
    }

    try {
        const r = await fetch('api/search.php?q=' + encodeURIComponent(q) + '&limit=20');
        const data = await r.json();
        renderAnchorResults(data.results || []);
    } catch {
        anchorResults.innerHTML = `<li class="muted">${t('search.error')}</li>`;
        anchorResults.classList.add('show');
    }
}

function renderAnchorResults(items) {
    anchorResults.innerHTML = '';
    if (!items.length) {
        anchorResults.classList.remove('show');
        return;
    }
    for (const r of items) {
        const li = document.createElement('li');
        const left = document.createElement('div');
        left.className = 'left';

        const badge = document.createElement('span');
        const type = r.type ?? 'teacher';
        if (type === 'subject') {
            badge.className = 'source-badge type-subject';
            badge.textContent = t('badge.subject');
        } else {
            badge.className = 'source-badge ' + (r.source === 'pdf' ? 'source-pdf' : 'source-html');
            badge.textContent = r.source === 'pdf' ? t('badge.pdf') : t('badge.html');
        }
        left.appendChild(badge);

        const name = document.createElement('span');
        name.className = 'name';
        name.textContent = r.name;
        left.appendChild(name);

        const right = document.createElement('div');
        right.className = 'right';
        const count = document.createElement('span');
        count.className = 'count';
        count.textContent = t('search.lectures.count', {n: r.lecture_count});
        right.appendChild(count);

        li.appendChild(left);
        li.appendChild(right);
        li.addEventListener('click', () => pickAnchor(r));
        anchorResults.appendChild(li);
    }
    anchorResults.classList.add('show');
}

// ------------------------------------------------------------------
// Step 2: drill-down. Fetch counterpart side and render clickable cards.
// ------------------------------------------------------------------

async function pickAnchor(r) {
    anchor = {
        type:   r.type ?? 'teacher',
        source: r.source,
        ref:    r.ref ?? r.id ?? null,
        name:   r.name,
    };

    anchorResults.classList.remove('show');
    anchorQ.value = '';
    lastQ = null;

    // Show step-2 panel with a loading state.
    suggestSection.classList.remove('hidden');
    suggestKindEl.textContent = anchor.type === 'teacher'
        ? t('builder.suggest_for_teacher')
        : t('builder.suggest_for_subject');
    suggestNameEl.textContent = anchor.name;
    suggestList.innerHTML = `<li class="muted">${t('search.show_all.loading')}</li>`;
    suggestEmpty.classList.add('hidden');
    suggestHint.textContent = '';

    try {
        const data = await fetchAnchorDetail();
        const items = anchor.type === 'teacher'
            ? extractSubjectsFromTeacher(data)
            : extractTeachersFromSubject(data);
        renderSuggestList(items);
    } catch {
        suggestList.innerHTML = `<li class="muted">${t('teacher.error')}</li>`;
    }
}

function fetchAnchorDetail() {
    if (anchor.type === 'subject') {
        return fetch('api/subject.php?name=' + encodeURIComponent(anchor.name))
            .then(r => r.json());
    }
    if (anchor.source === 'pdf') {
        return fetch('api/pdf_teacher.php?name=' + encodeURIComponent(anchor.name))
            .then(r => r.json());
    }
    if (anchor.ref != null) {
        return fetch('api/teacher.php?id=' + encodeURIComponent(anchor.ref))
            .then(r => r.json());
    }
    return Promise.resolve(null);
}

/**
 * Strip course codes and trailing lesson-type words that the PDF parser
 * sometimes bakes into subject_name (e.g. "Elements of Academic Writing
 * (ELACWR05) Practical Work" -> "Elements of Academic Writing"). Same
 * subject with different lesson types should dedupe to one entry.
 */
function normalizeSubject(name) {
    let s = (name || '').trim();
    // Drop parenthesised course codes — they vary across pdfs.
    s = s.replace(/\([^)]*\)/g, ' ');
    // Drop trailing lesson-type tails (EN and KA variants).
    const tail = /\s+(Lecture|Practical Work|Practical|Laboratory|Lab\.?|Lect\.?|Pract\.?|Course paper|Course Paper|ლექცია|პრაქტიკული|პრაქტ\.?|ლაბორატორია|ლაბ\.?|საკურსო)\s*$/iu;
    while (tail.test(s)) s = s.replace(tail, '');
    return s.replace(/\s+/g, ' ').trim();
}

/** {name, count, raw} list, deduped by normalized form, sorted by count desc. */
function extractSubjectsFromTeacher(data) {
    if (!data) return [];
    const lectures = data.lectures || [];
    const byKey = new Map();
    for (const l of lectures) {
        const raw = (l.subject_name || '').trim();
        if (!raw) continue;
        const norm = normalizeSubject(raw) || raw;
        const key = norm.toLowerCase();
        const cur = byKey.get(key);
        if (cur) {
            cur.count++;
            // Prefer the cleanest display: shortest unless it's empty.
            if (norm.length && norm.length < cur.name.length) cur.name = norm;
        } else {
            byKey.set(key, { name: norm, count: 1, raw });
        }
    }
    return Array.from(byKey.values()).sort((a, b) => b.count - a.count);
}

/** {teacher_name, count} list, sorted by count desc. */
function extractTeachersFromSubject(data) {
    if (!data) return [];
    const lectures = data.lectures || [];
    const byName = new Map();
    for (const l of lectures) {
        const name = (l.teacher_name || '').trim();
        if (!name) continue;
        byName.set(name, (byName.get(name) || 0) + 1);
    }
    return Array.from(byName, ([name, count]) => ({name, count}))
                .sort((a, b) => b.count - a.count);
}

function renderSuggestList(items) {
    suggestList.innerHTML = '';
    if (!items.length) {
        suggestEmpty.classList.remove('hidden');
        return;
    }
    suggestEmpty.classList.add('hidden');
    suggestHint.textContent = anchor.type === 'teacher'
        ? t('builder.suggest_hint.teacher', {n: items.length})
        : t('builder.suggest_hint.subject', {n: items.length});

    for (const it of items) {
        // Build the pair for this candidate.
        const pair = anchor.type === 'teacher'
            ? { subject: it.name, teacher: anchor.name }
            : { subject: anchor.name, teacher: it.name };
        const alreadyAdded = courses.some(c =>
            c.subject === pair.subject && c.teacher === pair.teacher);

        const li = document.createElement('li');
        li.className = 'suggest-item';
        if (alreadyAdded) li.classList.add('suggest-item--added');

        const text = document.createElement('div');
        text.className = 'suggest-item-text';
        const main = document.createElement('span');
        main.className = 'suggest-item-name';
        main.textContent = it.name;
        text.appendChild(main);
        const meta = document.createElement('span');
        meta.className = 'suggest-item-meta';
        meta.textContent = t('search.lectures.count', {n: it.count});
        text.appendChild(meta);

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'suggest-item-add';
        if (alreadyAdded) {
            btn.textContent = t('builder.added');
            btn.disabled = true;
        } else {
            btn.textContent = t('builder.add');
            btn.addEventListener('click', () => addPair(pair, li, btn));
        }

        li.appendChild(text);
        li.appendChild(btn);
        suggestList.appendChild(li);
    }
}

function addPair(pair, li, btn) {
    if (courses.some(c => c.subject === pair.subject && c.teacher === pair.teacher)) return;
    courses.push(pair);
    saveCourses();
    renderCourses();
    li.classList.add('suggest-item--added');
    btn.textContent = t('builder.added');
    btn.disabled = true;
}

changeAnchor.addEventListener('click', () => {
    anchor = null;
    suggestSection.classList.add('hidden');
    suggestList.innerHTML = '';
    anchorQ.focus();
});

// ------------------------------------------------------------------
// Course list rendering + actions
// ------------------------------------------------------------------

function renderCourses() {
    courseList.innerHTML = '';
    courseCount.textContent = `(${courses.length})`;
    if (courses.length === 0) {
        emptyHint.classList.remove('hidden');
        viewBtn.disabled = true;
        return;
    }
    emptyHint.classList.add('hidden');
    viewBtn.disabled = false;

    courses.forEach((c, idx) => {
        const li = document.createElement('li');

        const text = document.createElement('div');
        text.className = 'course-text';
        const subj = document.createElement('span');
        subj.className = 'course-subj';
        subj.textContent = c.subject;
        text.appendChild(subj);
        const tch = document.createElement('span');
        tch.className = 'course-tch';
        tch.textContent = c.teacher;
        text.appendChild(tch);

        const rm = document.createElement('button');
        rm.type = 'button';
        rm.className = 'course-remove';
        rm.textContent = '×';
        rm.setAttribute('aria-label', 'remove');
        rm.addEventListener('click', () => {
            courses.splice(idx, 1);
            saveCourses();
            renderCourses();
            // If the suggest panel is open, refresh its "added" badges.
            if (anchor) {
                const items = suggestList.querySelectorAll('li');
                if (items.length) {
                    // Cheapest re-render: just rerun the extract + render with
                    // the currently-cached anchor.
                    fetchAnchorDetail().then(data => {
                        const fresh = anchor.type === 'teacher'
                            ? extractSubjectsFromTeacher(data)
                            : extractTeachersFromSubject(data);
                        renderSuggestList(fresh);
                    }).catch(() => {});
                }
            }
        });

        li.appendChild(text);
        li.appendChild(rm);
        courseList.appendChild(li);
    });
}

viewBtn.addEventListener('click', () => {
    if (!courses.length) return;
    const payload = {
        name: '',
        school: '',
        courses: courses.map(c => ({
            subject:   c.subject,
            subjectEn: '',
            teacher:   c.teacher,
            teacherEn: '',
        })),
    };
    // Build a readable, shareable URL: /me.php?c[]=Subject|Teacher&c[]=…
    // URLSearchParams handles UTF-8 percent-encoding so Georgian text is safe.
    const params = new URLSearchParams();
    for (const c of courses) {
        params.append('c[]', c.subject + '|' + c.teacher);
    }
    window.open('/me.php?' + params.toString(), '_blank', 'noopener');
});

clearBtn.addEventListener('click', () => {
    if (courses.length === 0) return;
    if (!confirm(t('builder.clear.confirm'))) return;
    courses = [];
    saveCourses();
    renderCourses();
    // If suggest panel open, refresh "added" badges.
    if (anchor) {
        fetchAnchorDetail().then(data => {
            const fresh = anchor.type === 'teacher'
                ? extractSubjectsFromTeacher(data)
                : extractTeachersFromSubject(data);
            renderSuggestList(fresh);
        }).catch(() => {});
    }
});

// Outside click closes the search dropdown.
document.addEventListener('click', (e) => {
    if (!anchorResults.contains(e.target) && e.target !== anchorQ) {
        anchorResults.classList.remove('show');
    }
});

// Initial render of the saved courses list.
renderCourses();
