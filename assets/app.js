'use strict';

// Display arrays; recomputed on every render so they pick up the active
// language. (i18n.js exposes days() and t().)
function DAYS_ARR()   { return days().slice(1, 7); }     // [Mon..Sat], i.e. 6 entries
function DAY_LABEL(n) { return days()[n] || '?'; }       // 1..7

// Re-render hook: when the user toggles language, the most recently rendered
// detail view re-runs itself with the same data so translations refresh.
let _lastRender = null;
const setLastRender = (fn) => { _lastRender = fn; };
document.addEventListener('langchange', () => {
    if (typeof _lastRender === 'function') {
        try { _lastRender(); } catch {}
    }
});
const SLOTS = Array.from({length: 12}, (_, i) => ({
    n: i + 1,
    start: `${String(i + 9).padStart(2, '0')}:00`,
}));

const $ = (sel) => document.querySelector(sel);
const qInput = $('#q');
const resultsEl = $('#results');
const teacherView = $('#teacherView');
const teacherNameEl = $('#teacherName');
const teacherMetaEl = $('#teacherMeta');
const scheduleEl = $('#schedule');
const pdfTeacherView    = $('#pdfTeacherView');
const pdfTeacherNameEl  = $('#pdfTeacherName');
const pdfTeacherMetaEl  = $('#pdfTeacherMeta');
const pdfTeacherTable   = $('#pdfTeacherTable');
const subjectView       = $('#subjectView');
const subjectNameEl     = $('#subjectName');
const subjectMetaEl     = $('#subjectMeta');
const subjectTable      = $('#subjectTable');

let searchTimer = null;
let lastQuery = null;

qInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(runSearch, 150);
});

qInput.addEventListener('keydown', (e) => {
    const items = resultsEl.querySelectorAll('li');
    if (!items.length) return;
    const active = resultsEl.querySelector('li.active');
    let idx = active ? Array.from(items).indexOf(active) : -1;
    if (e.key === 'ArrowDown') { idx = Math.min(items.length - 1, idx + 1); e.preventDefault(); }
    else if (e.key === 'ArrowUp') { idx = Math.max(0, idx - 1); e.preventDefault(); }
    else if (e.key === 'Enter' && idx >= 0) { items[idx].click(); e.preventDefault(); return; }
    else return;
    items.forEach((li) => li.classList.remove('active'));
    items[idx].classList.add('active');
    items[idx].scrollIntoView({block: 'nearest'});
});

async function runSearch() {
    const q = qInput.value.trim();
    if (q === lastQuery) return;
    lastQuery = q;

    const url = 'api/search.php?q=' + encodeURIComponent(q) + '&limit=20';
    try {
        const r = await fetch(url);
        const data = await r.json();
        renderResults(data.results || []);
    } catch (err) {
        resultsEl.innerHTML = `<li class="muted">${t('search.error')}</li>`;
        resultsEl.classList.add('show');
    }
}

function renderResults(items) {
    resultsEl.innerHTML = '';
    if (!items.length) {
        resultsEl.classList.remove('show');
        return;
    }
    for (const t of items) {
        // Backward-compat for legacy responses: assume teacher if no type field.
        const type = t.type ?? 'teacher';

        const li = document.createElement('li');
        li.dataset.source = t.source;
        li.dataset.type   = type;

        const left = document.createElement('div');
        left.className = 'left';
        const badge = document.createElement('span');
        if (type === 'subject') {
            badge.className = 'source-badge type-subject';
            badge.textContent = t('badge.subject');
        } else {
            badge.className = 'source-badge ' + (t.source === 'pdf' ? 'source-pdf' : 'source-html');
            badge.textContent = t.source === 'pdf' ? t('badge.pdf') : t('badge.html');
        }
        left.appendChild(badge);
        const name = document.createElement('span');
        name.className = 'name';
        name.textContent = t.code ? `${t.name} #${t.code}` : t.name;
        left.appendChild(name);

        const right = document.createElement('div');
        right.className = 'right';
        if (t.faculties && t.faculties.length) {
            const fac = document.createElement('span');
            fac.className = 'faculties';
            fac.textContent = t.faculties.join(', ');
            right.appendChild(fac);
        }
        const count = document.createElement('span');
        count.className = 'count';
        count.textContent = t('search.lectures.count', {n: t.lecture_count});
        right.appendChild(count);

        li.appendChild(left);
        li.appendChild(right);

        const ref = t.ref ?? t.id;
        if (type === 'subject') {
            li.addEventListener('click', () => loadSubject(t.name));
        } else if (t.source === 'pdf') {
            li.addEventListener('click', () => loadPdfTeacher(t.name));
        } else if (ref != null) {
            li.addEventListener('click', () => loadTeacher(ref));
        } else {
            li.style.opacity = '0.5';
            li.title = 'no id available for this result';
        }
        resultsEl.appendChild(li);
    }
    resultsEl.classList.add('show');
}

function hideAllDetailViews() {
    teacherView.classList.add('hidden');
    pdfTeacherView.classList.add('hidden');
    subjectView.classList.add('hidden');
}

async function loadTeacher(id) {
    resultsEl.classList.remove('show');
    hideAllDetailViews();
    teacherView.classList.remove('hidden');
    teacherNameEl.textContent = '...';
    teacherMetaEl.textContent = '';
    scheduleEl.innerHTML = '';

    try {
        const r = await fetch('api/teacher.php?id=' + encodeURIComponent(id));
        const data = await r.json();
        if (data.error) { teacherNameEl.textContent = data.error; return; }
        renderTeacher(data);
        teacherView.scrollIntoView({behavior: 'smooth', block: 'start'});
    } catch (err) {
        teacherNameEl.textContent = t('teacher.error');
    }
}

async function loadPdfTeacher(name) {
    resultsEl.classList.remove('show');
    hideAllDetailViews();
    pdfTeacherView.classList.remove('hidden');
    pdfTeacherNameEl.textContent = '...';
    pdfTeacherMetaEl.textContent = '';
    pdfTeacherTable.innerHTML = '';

    try {
        const r = await fetch('api/pdf_teacher.php?name=' + encodeURIComponent(name));
        const data = await r.json();
        if (data.error) { pdfTeacherNameEl.textContent = data.error; return; }
        renderPdfTeacher(data);
        pdfTeacherView.scrollIntoView({behavior: 'smooth', block: 'start'});
    } catch (err) {
        pdfTeacherNameEl.textContent = t('teacher.error');
    }
}

function renderPdfTeacher(data) {
    setLastRender(() => renderPdfTeacher(data));
    pdfTeacherNameEl.textContent = data.name;

    // Source labels: each lecture row carries source_label; collect unique ones.
    const sources = new Map();
    for (const l of data.lectures) {
        const k = l.source_url || l.source_label;
        if (k && !sources.has(k)) sources.set(k, {
            label: l.source_label || l.source_section || '(უცნობი)',
            url:   l.source_url,
        });
    }

    const facLinks = data.faculties.map(f =>
        `<a href="#" data-faculty="${f.slug}" class="faculty-link">${f.name}</a>`
    ).join(' · ');
    const sourceLinks = Array.from(sources.values()).map(s =>
        s.url
            ? `<a href="${s.url}" target="_blank" rel="noopener">${escapeHtml(s.label)}</a>`
            : escapeHtml(s.label)
    ).join(' · ');
    pdfTeacherMetaEl.innerHTML =
        `${t('search.lectures.count', {n: data.lectures.length})} · ${facLinks}` +
        (sourceLinks ? `<br><span class="muted">წყარო: ${sourceLinks}</span>` : '');

    pdfTeacherMetaEl.querySelectorAll('.faculty-link').forEach(a => {
        a.addEventListener('click', (e) => {
            e.preventDefault();
            loadFaculty(a.dataset.faculty);
        });
    });

    pdfTeacherTable.innerHTML = '';
    const thead = document.createElement('thead');
    const headRow = document.createElement('tr');
    for (const h of [t('subject.col.source'), t('pdfteacher.col.faculty'), t('pdfteacher.col.subject'), t('pdfteacher.col.type'), t('pdfteacher.col.day'), t('pdfteacher.col.times'), t('pdfteacher.col.room')]) {
        const th = document.createElement('th');
        th.textContent = h;
        headRow.appendChild(th);
    }
    thead.appendChild(headRow);
    pdfTeacherTable.appendChild(thead);

    const tbody = document.createElement('tbody');
    for (const l of data.lectures) {
        const tr = document.createElement('tr');
        if (l.parse_quality < 70) tr.classList.add('row-low-quality');

        const td = (cls, text) => {
            const c = document.createElement('td');
            if (cls) c.className = cls;
            c.textContent = text ?? '';
            return c;
        };

        tr.appendChild(td('col-source', shortSourceLabel(l)));
        tr.appendChild(td('col-faculty', shortFacultyName(l.faculty_slug)));
        tr.appendChild(td('col-subject', l.subject_name || ''));
        tr.appendChild(td('col-type', l.lesson_type || ''));
        tr.appendChild(td('col-day', l.weekday ? DAY_LABEL(l.weekday) : (l.day_label || '')));
        tr.appendChild(td('col-times', (l.times || []).join(', ')));
        tr.appendChild(td('col-room', (l.rooms || []).join(', ')));
        tbody.appendChild(tr);
    }
    pdfTeacherTable.appendChild(tbody);
}

function shortSourceLabel(l) {
    const k = l.pdf_kind || (l.source && l.source === 'pdf' ? 'pdf' : '');
    if (k === 'additional_pdf') return t('badge.pdf');
    if (k === 'midterm_pdf')    return t('badge.midterm');
    if (l.source === 'html')    return t('badge.html');
    return '';
}

const FACULTY_SHORT = {
    construction: 'სამშენებლო', power: 'ენერგეტიკა', mining: 'სამთო-გეოლ.',
    agrarian: 'აგრარული', transport: 'სატრანსპორტო', architecture: 'არქიტექტურა',
    law: 'სამართალი', ims: 'იმს', business: 'ბიზნესი', social: 'სოც. მეცნ.',
};
function shortFacultyName(slug) { return FACULTY_SHORT[slug] || slug; }

// ---------- Subject view ----------

async function loadSubject(name) {
    resultsEl.classList.remove('show');
    hideAllDetailViews();
    subjectView.classList.remove('hidden');
    subjectNameEl.textContent = '...';
    subjectMetaEl.textContent = '';
    subjectTable.innerHTML = '';

    try {
        const r = await fetch('api/subject.php?name=' + encodeURIComponent(name));
        const data = await r.json();
        if (data.error) { subjectNameEl.textContent = data.error; return; }
        renderSubject(data);
        subjectView.scrollIntoView({behavior: 'smooth', block: 'start'});
    } catch (err) {
        subjectNameEl.textContent = t('teacher.error');
    }
}

function renderSubject(data) {
    setLastRender(() => renderSubject(data));
    subjectNameEl.textContent = data.name;

    const teacherChips = (data.teachers || []).map(t => `<span class="chip">${escapeHtml(t)}</span>`).join('');
    const facLabels = (data.faculties || []).map(shortFacultyName).join(', ');
    subjectMetaEl.innerHTML =
        `${t('search.lectures.count', {n: data.lectures.length})} · ` +
        (facLabels ? `${t('pdfteacher.col.faculty')}: ${facLabels} · ` : '') +
        (teacherChips ? `<span class="chips">${teacherChips}</span>` : '');

    subjectTable.innerHTML = '';
    const thead = document.createElement('thead');
    const headRow = document.createElement('tr');
    for (const h of [t('subject.col.source'), t('subject.col.teacher'), t('subject.col.day'), t('subject.col.times'), t('subject.col.room'), t('subject.col.type'), t('subject.col.faculty')]) {
        const th = document.createElement('th');
        th.textContent = h;
        headRow.appendChild(th);
    }
    thead.appendChild(headRow);
    subjectTable.appendChild(thead);

    const tbody = document.createElement('tbody');
    for (const l of data.lectures) {
        const tr = document.createElement('tr');
        if (l.parse_quality !== null && l.parse_quality < 70) tr.classList.add('row-low-quality');

        const td = (cls, text) => {
            const c = document.createElement('td');
            if (cls) c.className = cls;
            c.textContent = text ?? '';
            return c;
        };

        tr.appendChild(td('col-source', shortSourceLabel(l)));
        tr.appendChild(td('col-teacher', l.teacher_name || ''));
        tr.appendChild(td('col-day', l.weekday ? DAY_LABEL(l.weekday) : (l.day_label || '')));
        tr.appendChild(td('col-times', (l.times || []).join(', ')));
        tr.appendChild(td('col-room', (l.rooms || []).join(', ')));
        tr.appendChild(td('col-type', l.lesson_type || ''));
        // Combine faculty + section into one cell so the user knows whether a
        // result came from "VIII კვირის ცხრილი" or "დამატებითი კურსები" etc.
        const ctx = [
            l.faculty_slug ? shortFacultyName(l.faculty_slug) : null,
            l.source_section,
        ].filter(Boolean).join(' · ');
        tr.appendChild(td('col-faculty', ctx || '—'));
        tbody.appendChild(tr);
    }
    subjectTable.appendChild(tbody);
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[c]));
}

function renderTeacher(data) {
    setLastRender(() => renderTeacher(data));
    teacherNameEl.textContent = data.teacher.name;

    // Group lectures by source so the user sees a separate grid per source
    // (e.g. "პროფესიული სწავლების ცხრილი" vs "VIII კვირის სასწავლო ცხრილი" —
    // different scrapes of the same person, with different lecture sets).
    const groups = new Map();
    for (const l of data.lectures) {
        const key = l.source_url ?? 'unknown';
        if (!groups.has(key)) {
            groups.set(key, {
                url:     l.source_url,
                label:   l.source_label || l.source_section || '(უცნობი წყარო)',
                section: l.source_section,
                fetched: l.source_fetched_at,
                lectures: [],
            });
        }
        groups.get(key).lectures.push(l);
    }

    const meta = [t('teacher.summary', {n: data.lectures.length, sources: groups.size})];
    teacherMetaEl.innerHTML = meta.join(' · ');

    scheduleEl.replaceChildren();
    for (const g of groups.values()) {
        const wrap = document.createElement('section');
        wrap.className = 'source-group';

        const h = document.createElement('h3');
        h.className = 'source-heading';
        h.textContent = g.label;
        wrap.appendChild(h);

        const sub = document.createElement('p');
        sub.className = 'muted source-sub';
        const date = g.fetched
            ? new Date(g.fetched * 1000).toISOString().slice(0, 16).replace('T', ' ')
            : '';
        const fileName = g.url ? g.url.split('/').pop() : '';
        sub.innerHTML = [
            t('search.lectures.count', {n: g.lectures.length}),
            date ? `განახლდა ${date}` : '',
            g.url ? `<a href="${g.url}" target="_blank" rel="noopener">${fileName}</a>` : '',
        ].filter(Boolean).join(' · ');
        wrap.appendChild(sub);

        wrap.appendChild(buildScheduleGrid(g.lectures));
        scheduleEl.appendChild(wrap);
    }
}

function buildScheduleGrid(lectures) {
    const grid = Array.from({length: 12}, () => Array(6).fill(null));
    const skip = Array.from({length: 12}, () => Array(6).fill(false));

    for (const l of lectures) {
        const day = l.weekday - 1;
        const start = l.start_slot - 1;
        const end = l.end_slot - 1;
        if (day < 0 || day >= 6 || start < 0 || start >= 12) continue;
        grid[start][day] = l;
        for (let s = start + 1; s <= Math.min(11, end); s++) skip[s][day] = true;
    }

    const table = document.createElement('table');
    table.className = 'schedule-table';

    const thead = document.createElement('thead');
    const headRow = document.createElement('tr');
    headRow.appendChild(document.createElement('th'));
    for (const d of DAYS_ARR()) {
        const th = document.createElement('th');
        th.textContent = d;
        headRow.appendChild(th);
    }
    thead.appendChild(headRow);
    table.appendChild(thead);

    const tbody = document.createElement('tbody');
    for (let s = 0; s < 12; s++) {
        const tr = document.createElement('tr');
        const slotTh = document.createElement('th');
        slotTh.className = 'slot-label';
        slotTh.textContent = `${s + 1}—${SLOTS[s].start}`;
        tr.appendChild(slotTh);

        for (let d = 0; d < 6; d++) {
            if (skip[s][d]) continue;
            const td = document.createElement('td');
            const lesson = grid[s][d];
            if (lesson) {
                const span = (lesson.end_slot - lesson.start_slot) + 1;
                if (span > 1) td.rowSpan = span;
                td.className = 'lesson';
                td.appendChild(makeLessonNode(lesson));
            } else {
                td.className = 'free';
                td.textContent = '';
            }
            tr.appendChild(td);
        }
        tbody.appendChild(tr);
    }
    table.appendChild(tbody);
    return table;
}

function makeLessonNode(l) {
    const wrap = document.createElement('div');

    const subj = document.createElement('div');
    subj.className = 'subject';
    subj.textContent = l.subject_name || ('(' + t('teacher.unknown').replace(/[()]/g,'') + ')');
    wrap.appendChild(subj);

    const meta = document.createElement('div');
    meta.className = 'meta';
    const parts = [];
    if (l.group_code)   parts.push(l.group_code);
    if (l.lesson_type)  parts.push(l.lesson_type);
    if (l.subject_code) parts.push(`#${l.subject_code}`);
    parts.push(`${l.start_time}–${l.end_time}`);
    meta.textContent = parts.join(' · ');
    wrap.appendChild(meta);

    if (l.room) {
        const room = document.createElement('div');
        room.className = 'room';
        room.textContent = l.room;
        wrap.appendChild(room);
    }
    return wrap;
}

// Show top teachers on initial load.
runSearch();

// ---------- Group search ----------

const groupQEl       = $('#groupQ');
const groupResultsEl = $('#groupResults');
const groupViewEl    = $('#groupView');
const groupNameEl    = $('#groupName');
const groupMetaEl    = $('#groupMeta');
const groupScheduleEl = $('#groupSchedule');

let groupSearchTimer = null;
let lastGroupQuery = null;

groupQEl.addEventListener('input', () => {
    clearTimeout(groupSearchTimer);
    groupSearchTimer = setTimeout(runGroupSearch, 150);
});

groupQEl.addEventListener('keydown', (e) => {
    const items = groupResultsEl.querySelectorAll('li');
    if (!items.length) return;
    const active = groupResultsEl.querySelector('li.active');
    let idx = active ? Array.from(items).indexOf(active) : -1;
    if (e.key === 'ArrowDown') { idx = Math.min(items.length - 1, idx + 1); e.preventDefault(); }
    else if (e.key === 'ArrowUp') { idx = Math.max(0, idx - 1); e.preventDefault(); }
    else if (e.key === 'Enter' && idx >= 0) { items[idx].click(); e.preventDefault(); return; }
    else return;
    items.forEach((li) => li.classList.remove('active'));
    items[idx].classList.add('active');
    items[idx].scrollIntoView({block: 'nearest'});
});

async function runGroupSearch() {
    const q = groupQEl.value.trim();
    if (q === lastGroupQuery) return;
    lastGroupQuery = q;

    try {
        const r = await fetch('api/groups.php?q=' + encodeURIComponent(q) + '&limit=20');
        const data = await r.json();
        renderGroupResults(data.results || []);
    } catch (err) {
        groupResultsEl.innerHTML = `<li class="muted">${t('search.error')}</li>`;
        groupResultsEl.classList.add('show');
    }
}

function renderGroupResults(items) {
    groupResultsEl.innerHTML = '';
    if (!items.length) {
        groupResultsEl.classList.remove('show');
        return;
    }
    for (const g of items) {
        const li = document.createElement('li');
        const left = document.createElement('span');
        left.className = 'name';
        left.textContent = g.code;
        const right = document.createElement('span');
        right.className = 'count';
        right.textContent = t('search.lectures.count', {n: g.lecture_count});
        li.appendChild(left);
        li.appendChild(right);
        li.addEventListener('click', () => loadGroup(g.code));
        groupResultsEl.appendChild(li);
    }
    groupResultsEl.classList.add('show');
}

async function loadGroup(code) {
    groupResultsEl.classList.remove('show');
    groupViewEl.classList.remove('hidden');
    groupNameEl.textContent = code;
    groupMetaEl.textContent = '...';
    groupScheduleEl.innerHTML = '';

    try {
        const r = await fetch('api/group.php?code=' + encodeURIComponent(code));
        const data = await r.json();
        if (data.error) { groupNameEl.textContent = data.error; groupMetaEl.textContent = ''; return; }
        renderGroup(data);
        groupViewEl.scrollIntoView({behavior: 'smooth', block: 'start'});
    } catch (err) {
        groupNameEl.textContent = t('teacher.error');
        groupMetaEl.textContent = '';
    }
}

function renderGroup(data) {
    setLastRender(() => renderGroup(data));
    groupNameEl.textContent = t('group.title', {code: data.code});

    // Group lectures by source so a group with rows from both teachers HTMLs
    // gets one grid per source (same UX as the teacher view).
    const groups = new Map();
    for (const l of data.lectures) {
        const key = l.source_url ?? 'unknown';
        if (!groups.has(key)) {
            groups.set(key, {
                url:     l.source_url,
                label:   l.source_label || l.source_section || '(უცნობი წყარო)',
                fetched: l.source_fetched_at,
                lectures: [],
            });
        }
        groups.get(key).lectures.push(l);
    }

    const teacherLinks = (data.teachers || []).map(t => {
        const span = document.createElement('span');
        span.className = 'chip clickable';
        span.dataset.teacher = t;
        span.textContent = t;
        return span;
    });

    groupMetaEl.innerHTML = '';
    const lineA = document.createElement('div');
    lineA.textContent = t('group.summary', {n: data.lectures.length, teachers: data.teachers.length, subjects: data.subjects.length, sources: groups.size});
    groupMetaEl.appendChild(lineA);

    if (teacherLinks.length) {
        const lineB = document.createElement('div');
        lineB.className = 'chips';
        lineB.style.marginTop = '6px';
        teacherLinks.forEach(c => lineB.appendChild(c));
        groupMetaEl.appendChild(lineB);
    }

    // Click a teacher chip → open that teacher's full view.
    groupMetaEl.querySelectorAll('.clickable[data-teacher]').forEach(el => {
        el.addEventListener('click', () => {
            // Find teacher id by matching the name in the search API.
            // Simplest: query search.php and pick the first html teacher result.
            fetch('api/search.php?q=' + encodeURIComponent(el.dataset.teacher) + '&limit=5')
                .then(r => r.json())
                .then(d => {
                    const hit = (d.results || []).find(r => r.type === 'teacher' && r.source === 'html' && r.name === el.dataset.teacher);
                    if (hit && hit.ref) loadTeacher(hit.ref);
                });
        });
    });

    groupScheduleEl.replaceChildren();
    for (const g of groups.values()) {
        const wrap = document.createElement('section');
        wrap.className = 'source-group';
        const h = document.createElement('h3');
        h.className = 'source-heading';
        h.textContent = g.label;
        wrap.appendChild(h);

        const sub = document.createElement('p');
        sub.className = 'muted source-sub';
        const date = g.fetched
            ? new Date(g.fetched * 1000).toISOString().slice(0, 16).replace('T', ' ')
            : '';
        const fileName = g.url ? g.url.split('/').pop() : '';
        sub.innerHTML = [
            t('search.lectures.count', {n: g.lectures.length}),
            date ? `განახლდა ${date}` : '',
            g.url ? `<a href="${g.url}" target="_blank" rel="noopener">${fileName}</a>` : '',
        ].filter(Boolean).join(' · ');
        wrap.appendChild(sub);

        wrap.appendChild(buildGroupGrid(g.lectures));
        groupScheduleEl.appendChild(wrap);
    }
}

/**
 * Schedule grid for a group: same layout as the teacher grid, but cells lead
 * with the SUBJECT (which is what students care about) and show the teacher
 * + room underneath.
 */
function buildGroupGrid(lectures) {
    const grid = Array.from({length: 12}, () => Array(6).fill(null));
    const skip = Array.from({length: 12}, () => Array(6).fill(false));

    for (const l of lectures) {
        const day = l.weekday - 1;
        const start = l.start_slot - 1;
        const end = l.end_slot - 1;
        if (day < 0 || day >= 6 || start < 0 || start >= 12) continue;
        if (grid[start][day]) {
            // Already filled — append additional teacher/subject info instead of overwriting.
            grid[start][day]._extra ||= [];
            grid[start][day]._extra.push(l);
        } else {
            grid[start][day] = l;
            for (let s = start + 1; s <= Math.min(11, end); s++) skip[s][day] = true;
        }
    }

    const table = document.createElement('table');
    table.className = 'schedule-table';

    const thead = document.createElement('thead');
    const headRow = document.createElement('tr');
    headRow.appendChild(document.createElement('th'));
    for (const d of DAYS_ARR()) {
        const th = document.createElement('th');
        th.textContent = d;
        headRow.appendChild(th);
    }
    thead.appendChild(headRow);
    table.appendChild(thead);

    const tbody = document.createElement('tbody');
    for (let s = 0; s < 12; s++) {
        const tr = document.createElement('tr');
        const slotTh = document.createElement('th');
        slotTh.className = 'slot-label';
        slotTh.textContent = `${s + 1}—${SLOTS[s].start}`;
        tr.appendChild(slotTh);

        for (let d = 0; d < 6; d++) {
            if (skip[s][d]) continue;
            const td = document.createElement('td');
            const lesson = grid[s][d];
            if (lesson) {
                const span = (lesson.end_slot - lesson.start_slot) + 1;
                if (span > 1) td.rowSpan = span;
                td.className = 'lesson';
                td.appendChild(makeGroupCellNode(lesson));
            } else {
                td.className = 'free';
                td.textContent = '';
            }
            tr.appendChild(td);
        }
        tbody.appendChild(tr);
    }
    table.appendChild(tbody);
    return table;
}

function makeGroupCellNode(l) {
    const wrap = document.createElement('div');

    const subj = document.createElement('div');
    subj.className = 'subject';
    subj.textContent = l.subject_name || ('(' + t('teacher.unknown').replace(/[()]/g,'') + ')');
    wrap.appendChild(subj);

    const meta = document.createElement('div');
    meta.className = 'meta';
    const parts = [];
    if (l.teacher_name) parts.push(l.teacher_name);
    if (l.lesson_type) parts.push(l.lesson_type);
    if (l.subject_code) parts.push(`#${l.subject_code}`);
    parts.push(`${l.start_time}–${l.end_time}`);
    meta.textContent = parts.join(' · ');
    wrap.appendChild(meta);

    if (l.room) {
        const room = document.createElement('div');
        room.className = 'room';
        room.textContent = l.room;
        wrap.appendChild(room);
    }

    if (l._extra && l._extra.length) {
        const extras = document.createElement('div');
        extras.className = 'meta';
        extras.style.marginTop = '4px';
        extras.style.borderTop = '1px solid var(--border)';
        extras.style.paddingTop = '3px';
        extras.textContent = `+${t('search.lectures.count', {n: l._extra.length})}`;
        extras.title = l._extra.map(x => `${x.subject_name} — ${x.teacher_name} (${x.room || '—'})`).join('\n');
        wrap.appendChild(extras);
    }
    return wrap;
}

// Hide the group-results dropdown when clicking outside.
document.addEventListener('click', (e) => {
    if (!groupResultsEl.contains(e.target) && e.target !== groupQEl) {
        groupResultsEl.classList.remove('show');
    }
});

// Show suggestions only when the input is focused, so the page isn't crowded
// with 20 random group codes on first load.
groupQEl.addEventListener('focus', () => {
    if (groupQEl.value.trim() === '' && lastGroupQuery !== '') {
        runGroupSearch();
    }
});

// If the page is opened with ?group=XXX (e.g. from the Chrome extension's
// "ჩემი ცხრილი" button), auto-load that group's schedule.
const groupParam = new URLSearchParams(location.search).get('group');
if (groupParam) {
    groupQEl.value = groupParam;
    loadGroup(groupParam);
}

// ---------- Faculties / additional courses ----------

const facultyListEl = $('#facultyList');
const facultyViewEl = $('#facultyView');
const facultyNameEl = $('#facultyName');
const facultyMetaEl = $('#facultyMeta');
const facultyTextEl = $('#facultyText');
const facultyFilterEl = $('#facultyFilter');
const tabStructured = $('#tabStructured');
const tabRaw = $('#tabRaw');
const structuredView = $('#structuredView');
const structuredTable = $('#structuredTable');
const structuredEmpty = $('#structuredEmpty');
let currentFacultyText = '';
let currentLectures = [];
let currentTab = 'structured';

async function loadFaculties() {
    try {
        const r = await fetch('api/faculties.php');
        const data = await r.json();
        renderFacultyList(data.faculties || []);
    } catch (err) {
        facultyListEl.innerHTML = `<li class="muted">${t('search.error')}</li>`;
    }
}

function renderFacultyList(items) {
    facultyListEl.innerHTML = '';
    if (!items.length) {
        const li = document.createElement('li');
        li.className = 'muted';
        li.textContent = 'არ არის ჩატვირთული. გაუშვი sync.';
        facultyListEl.appendChild(li);
        return;
    }

    const kindLabel = {
        additional_pdf: 'დამატებითი სასწავლო კურსები',
        midterm_pdf:    'შუალედური გამოცდები',
    };

    // Group by kind (additional / midterm). Render one section per kind with
    // its source label (e.g. "დამატებითი სასწავლო კურსების ცხრილი") on top.
    const byKind = new Map();
    for (const f of items) {
        if (!byKind.has(f.kind)) byKind.set(f.kind, []);
        byKind.get(f.kind).push(f);
    }

    for (const [kind, group] of byKind) {
        const heading = document.createElement('li');
        heading.className = 'faculty-kind-heading';
        heading.textContent = kindLabel[kind] || kind;
        facultyListEl.appendChild(heading);

        for (const f of group) {
            const li = document.createElement('li');
            li.dataset.slug = f.faculty_slug;
            li.dataset.kind = kind;
            const name = document.createElement('div');
            name.className = 'name';
            name.textContent = f.faculty_name;
            const meta = document.createElement('div');
            meta.className = 'meta';
            const date = new Date(f.fetched_at * 1000).toISOString().slice(0, 10);
            const structured = f.structured_rows > 0
                ? t('faculties.cell.lectures_struct', {n: f.structured_rows})
                : t('faculties.cell.text_only');
            meta.textContent = t('faculties.cell.detail', {lectures: structured, pages: (f.page_count || '?'), date: date});
            li.appendChild(name);
            li.appendChild(meta);
            li.addEventListener('click', () => loadFaculty(f.faculty_slug, kind));
            facultyListEl.appendChild(li);
        }
    }
}

async function loadFaculty(slug, kind = 'additional_pdf') {
    facultyViewEl.classList.remove('hidden');
    facultyNameEl.textContent = '...';
    facultyMetaEl.textContent = '';
    facultyTextEl.textContent = '';
    structuredTable.innerHTML = '';
    facultyFilterEl.value = '';

    try {
        const r = await fetch('api/faculty.php?slug=' + encodeURIComponent(slug)
            + '&kind=' + encodeURIComponent(kind));
        const data = await r.json();
        if (data.error) { facultyNameEl.textContent = data.error; return; }

        facultyNameEl.textContent = data.faculty_name;
        const date = new Date(data.fetched_at * 1000).toISOString().slice(0, 16).replace('T', ' ');
        const structuredCount = (data.lectures || []).length;
        const sectionLine = data.source_section
            ? `<div class="muted">${escapeHtml(data.source_section)}</div>`
            : '';
        facultyMetaEl.innerHTML =
            sectionLine +
            `${t('search.lectures.count', {n: structuredCount})} · ${t('faculties.subjlabel.pages', {n: (data.page_count || '?')})} · ${t('stats.updated', {when: date})} · ` +
            `<a href="${data.source_url}" target="_blank" rel="noopener">${t('faculties.source')}</a>`;

        currentFacultyText = data.raw_text || '';
        currentLectures = data.lectures || [];
        renderStructuredTable(currentLectures);
        facultyTextEl.textContent = currentFacultyText;

        // Default tab: structured if we have rows, otherwise raw.
        switchTab(currentLectures.length > 0 ? 'structured' : 'raw');
        facultyViewEl.scrollIntoView({behavior: 'smooth', block: 'start'});
    } catch (err) {
        facultyNameEl.textContent = t('teacher.error');
    }
}


function renderStructuredTable(lectures) {
    structuredTable.innerHTML = '';
    if (!lectures.length) {
        structuredEmpty.classList.remove('hidden');
        return;
    }
    structuredEmpty.classList.add('hidden');

    const thead = document.createElement('thead');
    const headRow = document.createElement('tr');
    for (const h of [t('faculties.col.num'), t('faculties.col.teacher'), t('faculties.col.subject'), t('faculties.col.type'), t('faculties.col.day'), t('faculties.col.times'), t('faculties.col.room'), t('faculties.col.q')]) {
        const th = document.createElement('th');
        th.textContent = h;
        headRow.appendChild(th);
    }
    thead.appendChild(headRow);
    structuredTable.appendChild(thead);

    const tbody = document.createElement('tbody');
    for (const l of lectures) {
        const tr = document.createElement('tr');
        if (l.parse_quality < 70) tr.classList.add('row-low-quality');

        const td = (cls, text) => {
            const c = document.createElement('td');
            if (cls) c.className = cls;
            c.textContent = text ?? '';
            return c;
        };

        tr.appendChild(td('col-num', l.row_num));
        tr.appendChild(td('col-teacher', l.teacher_name || ''));
        tr.appendChild(td('col-subject', l.subject_name || ''));
        tr.appendChild(td('col-type', l.lesson_type || ''));
        tr.appendChild(td('col-day', l.weekday ? DAY_LABEL(l.weekday) : (l.day_label || '')));
        tr.appendChild(td('col-times', (l.times || []).join(', ')));
        tr.appendChild(td('col-room', (l.rooms || []).join(', ')));
        tr.appendChild(td('col-quality', l.parse_quality));
        tbody.appendChild(tr);
    }
    structuredTable.appendChild(tbody);
}

function switchTab(name) {
    currentTab = name;
    if (name === 'structured') {
        tabStructured.classList.add('active');
        tabRaw.classList.remove('active');
        structuredView.classList.remove('hidden');
        facultyTextEl.classList.add('hidden');
    } else {
        tabRaw.classList.add('active');
        tabStructured.classList.remove('active');
        structuredView.classList.add('hidden');
        facultyTextEl.classList.remove('hidden');
    }
    applyFacultyFilter(); // re-apply filter to the now-visible view
}

tabStructured.addEventListener('click', () => switchTab('structured'));
tabRaw.addEventListener('click', () => switchTab('raw'));

let filterTimer = null;
facultyFilterEl.addEventListener('input', () => {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(applyFacultyFilter, 100);
});

function applyFacultyFilter() {
    const q = facultyFilterEl.value.trim();

    if (currentTab === 'raw') {
        if (!currentFacultyText) return;
        if (q === '') { facultyTextEl.textContent = currentFacultyText; return; }
        const re = new RegExp(q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'giu');
        facultyTextEl.innerHTML = '';
        let last = 0, m;
        while ((m = re.exec(currentFacultyText)) !== null) {
            if (m.index > last) facultyTextEl.appendChild(document.createTextNode(currentFacultyText.slice(last, m.index)));
            const mark = document.createElement('mark');
            mark.textContent = m[0];
            facultyTextEl.appendChild(mark);
            last = m.index + m[0].length;
            if (m[0].length === 0) re.lastIndex++;
        }
        if (last < currentFacultyText.length) facultyTextEl.appendChild(document.createTextNode(currentFacultyText.slice(last)));
        return;
    }

    // Structured tab: hide non-matching rows.
    const ql = q.toLocaleLowerCase();
    const rows = structuredTable.querySelectorAll('tbody tr');
    rows.forEach((tr) => {
        if (ql === '') { tr.style.display = ''; return; }
        const text = tr.textContent.toLocaleLowerCase();
        tr.style.display = text.includes(ql) ? '' : 'none';
    });
}

loadFaculties();
