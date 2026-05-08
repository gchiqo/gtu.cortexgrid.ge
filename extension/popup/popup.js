'use strict';

const ME_PAGE  = 'https://gtu.cortexgrid.ge/me.php';
const ME_API   = 'https://gtu.cortexgrid.ge/api/me.php';

const $ = (id) => document.getElementById(id);
const show = (id) => $(id).classList.remove('hidden');
const hide = (id) => $(id).classList.add('hidden');

let _card = null;       // chrome.storage.local["lastCard"]
let _matches = null;    // response from /api/me.php

document.addEventListener('DOMContentLoaded', async () => {
    await popupLang();
    document.documentElement.lang = _LANG;
    bindLangSwitcher();
    applyStaticStrings();
    await load();
});

document.addEventListener('popup-langchange', () => {
    document.documentElement.lang = _LANG;
    applyStaticStrings();
    if (_card && _card.payload) renderFound(_card, _matches);
});

function bindLangSwitcher() {
    document.querySelectorAll('.lang-switcher [data-lang]').forEach(btn => {
        btn.addEventListener('click', () => popupSetLang(btn.dataset.lang));
        btn.setAttribute('aria-pressed', btn.dataset.lang === _LANG ? 'true' : 'false');
    });
}

function applyStaticStrings() {
    $('t_title').textContent = pt('title');
    document.querySelectorAll('[data-k]').forEach(el => {
        el.textContent = pt(el.dataset.k);
    });
    $('t_noLogin').textContent     = pt('no_login');
    $('t_openVici').textContent    = pt('open_extension');
    $('t_rawSummary').textContent  = pt('debug.raw');
    document.querySelectorAll('.lang-switcher [data-lang]').forEach(btn => {
        btn.setAttribute('aria-pressed', btn.dataset.lang === _LANG ? 'true' : 'false');
    });
}

function agoLabel(ts) {
    const ago = Math.round((Date.now() - ts) / 1000);
    if (ago < 60)   return pt('ago.s', {n: ago});
    if (ago < 3600) return pt('ago.m', {n: Math.round(ago / 60)});
    return pt('ago.h', {n: Math.round(ago / 3600)});
}

async function load() {
    const data = await chrome.storage.local.get('lastCard');
    _card = data.lastCard;

    if (!_card) {
        $('status').textContent = pt('no_extension');
        show('empty');
        return;
    }
    $('status').textContent = pt('updated', {ago: agoLabel(_card.ts)});

    if (_card.error === 'not_logged_in') { show('empty'); return; }
    if (_card.error) {
        $('errorMsg').textContent = _card.error;
        if (_card.bodyPreview) $('errorBody').textContent = _card.bodyPreview;
        show('error');
        return;
    }

    if (!_card.payload) {
        $('errorMsg').textContent = pt('no_payload');
        show('error');
        return;
    }

    show('found');
    // Fetch matches from gtu.cortexgrid.ge so the popup shows real lecture/exam
    // data inline — not just a redirect link.
    _matches = await fetchMatches(_card.payload);
    renderFound(_card, _matches);
}

async function fetchMatches(payload) {
    try {
        const resp = await fetch(ME_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                school:  payload.school || '',
                courses: payload.courses || [],
            }),
        });
        if (!resp.ok) return { error: `HTTP ${resp.status}`, courses: [], totals: {} };
        return await resp.json();
    } catch (err) {
        console.error('[gtu-bridge] fetchMatches:', err);
        return { error: String(err), courses: [], totals: {} };
    }
}

function renderFound(card, matches) {
    const p = card.payload;

    // Top kv block — names also flip to English when available.
    // Student name has no `nameEn` in the card, so it stays in Georgian script.
    $('name').textContent     = p.name || '—';
    $('school').textContent   = (_LANG === 'en' ? (p.schoolEn || p.school) : p.school) || '—';
    $('semester').textContent = p.semester ?? '—';
    $('year').textContent     = p.year      || '—';
    $('gpa').textContent      = (p.gpa ?? '—') + (p.avgResult ? ` (${p.avgResult})` : '');

    // Course list — counts per course based on the matches API response.
    const list = $('coursesList');
    list.innerHTML = '';
    $('t_courseHeading').textContent = pt('courses.heading', {n: p.courses.length});

    // When the popup is in EN mode, prefer the card's English fields
    // (altName / altProf). They're the official English titles GTU itself
    // uses, so foreign students see something they recognise instead of
    // Georgian script.
    const useEn = (_LANG === 'en');
    const pick = (en, ka) => useEn ? (en || ka || '') : (ka || en || '');

    const matchedCourses = (matches && matches.courses) || [];
    for (let i = 0; i < p.courses.length; i++) {
        const c = p.courses[i];
        const m = matchedCourses[i] || { lectures: [], additional: [], midterm: [] };
        const examsCount = (m.midterm || []).reduce(
            (s, mid) => s + (mid.exams ? mid.exams.length : 0), 0);
        const totalLec = (m.lectures || []).length + (m.additional || []).length;
        const nextExam = nextUpcomingExam(m.midterm || []);

        const li = document.createElement('li');
        const subj = document.createElement('div');
        subj.className = 'subject';
        subj.textContent = pick(c.subjectEn, c.subject) || '(?)';
        const meta = document.createElement('div');
        meta.className = 'meta';
        const parts = [];
        const teacherName = pick(c.teacherEn, c.teacher);
        if (teacherName) parts.push(teacherName);
        if (c.credit) parts.push(`${c.credit}cr`);
        if (c.result) parts.push(`${c.result} (${c.score})`);
        meta.textContent = parts.join(' · ');

        const stats = document.createElement('div');
        stats.className = 'meta course-stats';
        const statParts = [];
        if (totalLec > 0)   statParts.push(pt('courses.lectures', {n: totalLec}));
        if (examsCount > 0) statParts.push(pt('courses.exam',     {n: examsCount}));
        stats.textContent = statParts.join(' · ');

        li.appendChild(subj);
        li.appendChild(meta);

        // Concrete lecture lines: day · time · room. Combines regular +
        // additional matches; regular lectures have a fixed slot range so we
        // show start_time–end_time, additional rows have a list of times so
        // we show each entry as its own line.
        const lecLines = collectLectureLines(m.lectures || [], m.additional || []);
        if (lecLines.length) {
            const lecBox = document.createElement('div');
            lecBox.className = 'course-lectures';
            for (const ln of lecLines) {
                const line = document.createElement('div');
                line.className = 'lec-line';
                line.textContent = ln;
                lecBox.appendChild(line);
            }
            li.appendChild(lecBox);
        }

        if (statParts.length) li.appendChild(stats);

        if (nextExam) {
            const ne = document.createElement('div');
            ne.className = 'meta next-exam';
            ne.textContent = pt('courses.next_exam', {
                date: nextExam.date || '—',
                time: nextExam.time || '',
                room: nextExam.room || '',
            });
            li.appendChild(ne);
        }

        list.appendChild(li);
    }

    if (matches && matches.totals) {
        $('t_totals').textContent = pt('totals', {
            lec:   matches.totals.lectures      ?? 0,
            add:   matches.totals.additional    ?? 0,
            exams: matches.totals.midterm_exams ?? 0,
        });
    } else {
        $('t_totals').textContent = '';
    }

    $('openMe').href = card.meUrl || ME_PAGE;
    $('openMe').textContent = pt('open_me');

    $('t_cantFind').innerHTML = pt('cant_find_html');

    if (card.json) {
        show('rawDetails');
        try { $('rawJson').textContent = JSON.stringify(card.json, null, 2); }
        catch { $('rawJson').textContent = String(card.json); }
    }
}

/**
 * Build the compact "day · time · room" lines we show under each course.
 * Sorted by weekday then by time so they read like a mini-week.
 *
 * Regular lectures have a fixed start_time / end_time range (one line each).
 * Additional rows have a list of `times[]` and `rooms[]` — one line per time
 * entry, paired with the matching room when the array lengths agree.
 */
function collectLectureLines(regular, additional) {
    const out = [];
    const dayOf = (n) => n ? pt('day.' + n) : '';

    for (const l of regular) {
        const day = dayOf(l.weekday);
        const t = (l.start_time && l.end_time)
            ? `${(l.start_time + '').slice(0,5)}–${(l.end_time + '').slice(0,5)}`
            : '';
        const r = l.room || '';
        if (!day && !t) continue;
        out.push({ key: (l.weekday ?? 99) + '|' + (l.start_time ?? ''),
                   text: [day, t, r].filter(Boolean).join(' · ') });
    }

    for (const l of additional) {
        const day = dayOf(l.weekday) || (l.day_label || '');
        const times = Array.isArray(l.times) ? l.times : [];
        const rooms = Array.isArray(l.rooms) ? l.rooms : [];
        if (times.length === 0) {
            out.push({ key: (l.weekday ?? 98) + '|', text: [day, rooms.join(', ')].filter(Boolean).join(' · ') });
            continue;
        }
        for (let i = 0; i < times.length; i++) {
            const t = times[i];
            const r = rooms[i] || rooms[0] || '';
            out.push({ key: (l.weekday ?? 98) + '|' + t,
                       text: [day, t, r].filter(Boolean).join(' · ') });
        }
    }

    out.sort((a, b) => a.key.localeCompare(b.key));
    // Dedupe identical adjacent entries.
    const seen = new Set();
    return out.map(o => o.text).filter(s => !seen.has(s) && (seen.add(s) || true));
}

/** Pick the next exam at or after today; falls back to first listed if none. */
function nextUpcomingExam(midtermBlocks) {
    const now = new Date();
    let best = null, bestKey = null;
    for (const mid of midtermBlocks) {
        for (const ex of (mid.exams || [])) {
            if (!ex.date) continue;
            const m = ex.date.match(/^(\d{2})\.(\d{2})\.(\d{4})$/);
            if (!m) continue;
            const dt = new Date(+m[3], +m[2] - 1, +m[1]);
            if (dt < new Date(now.getFullYear(), now.getMonth(), now.getDate())) continue;
            const key = m[3] + m[2] + m[1] + (ex.time || '99:99');
            if (bestKey === null || key < bestKey) { best = ex; bestKey = key; }
        }
    }
    return best;
}
