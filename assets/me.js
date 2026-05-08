'use strict';

/**
 * Personal-schedule page interactivity.
 *
 *   - Multi-teacher courses get a chip per teacher in the course header. The
 *     chip is a clickable toggle: click to mark "this teacher does NOT teach
 *     me" → all their rendered lessons get hidden across the week grid, the
 *     all-lectures table, and the per-course tables.
 *   - The exclusion list is persisted in localStorage so it survives reloads
 *     and language toggles. It's keyed globally (not per-course) — the
 *     assumption is that surname matches reliably enough that excluding
 *     "ლილი პეტრიაშვილი" only affects rows actually taught by them.
 *   - Matching is by ≥4-character word substring against each row's
 *     `data-teacher` attribute, so a Georgian-name exclusion still hits
 *     Latin-romanised rows ("Petreashvili Lili") — the surname is the bridge.
 */

const EXCLUDED_KEY = 'excluded_teachers';

function loadExcluded() {
    try {
        const raw = localStorage.getItem(EXCLUDED_KEY);
        const arr = raw ? JSON.parse(raw) : [];
        return Array.isArray(arr) ? arr : [];
    } catch { return []; }
}
function saveExcluded(list) {
    try { localStorage.setItem(EXCLUDED_KEY, JSON.stringify(list)); } catch {}
}

/** A teacher's "match keys": the lowercased ≥4-character words from their name. */
function teacherKeys(name) {
    if (!name) return [];
    return name.toLowerCase()
        .split(/[\s,]+/)
        .filter(w => w.length >= 4);
}

function isExcluded(rowTeacher, excludedList) {
    if (!rowTeacher || !excludedList.length) return false;
    const lower = rowTeacher.toLowerCase();
    for (const ex of excludedList) {
        const keys = teacherKeys(ex);
        if (keys.length === 0) continue;
        // ALL ≥4-char words from the excluded name must appear in the row.
        // This avoids the surname-collision case: excluding
        // "ლილი პეტრიაშვილი" must NOT also hide "ჟუჟუნა პეტრიაშვილი" rows
        // — the surname alone isn't enough; the first name has to match too.
        if (keys.every(w => lower.includes(w))) return true;
    }
    return false;
}

function applyExclusions() {
    const excluded = loadExcluded();

    // Mark every chip first so the user sees which teachers they've excluded.
    document.querySelectorAll('.teacher-chip[data-teacher-toggle]').forEach(btn => {
        const isOff = excluded.some(e => e.toLowerCase() === btn.dataset.teacherToggle.toLowerCase());
        btn.classList.toggle('teacher-chip--off', isOff);
        btn.setAttribute('aria-pressed', isOff ? 'true' : 'false');
    });

    // Toggle visibility on every element that carries a data-teacher attribute.
    document.querySelectorAll('[data-teacher]').forEach(el => {
        const hide = isExcluded(el.dataset.teacher, excluded);
        el.classList.toggle('teacher-excluded', hide);
    });

    // Some week-grid cells are <td>s containing multiple .grid-lesson divs.
    // If ALL inner .grid-lesson are hidden, hide the cell so the empty space
    // doesn't sit there styled as a "lesson". Otherwise leave the TD visible.
    document.querySelectorAll('.week-grid td.lesson, .week-grid td.lesson-add').forEach(td => {
        const lessons = td.querySelectorAll('.grid-lesson');
        const visible = Array.from(lessons).filter(d => !d.classList.contains('teacher-excluded'));
        if (lessons.length > 0 && visible.length === 0) {
            td.classList.add('teacher-excluded-cell');
        } else {
            td.classList.remove('teacher-excluded-cell');
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    applyExclusions();

    // Wire chip toggles: click a teacher chip in a course header to add/remove
    // them from the exclusion list.
    document.querySelectorAll('.teacher-chip[data-teacher-toggle]').forEach(btn => {
        btn.addEventListener('click', () => {
            const name = btn.dataset.teacherToggle;
            if (!name) return;
            const list = loadExcluded();
            const lower = name.toLowerCase();
            const idx = list.findIndex(x => x.toLowerCase() === lower);
            if (idx >= 0) {
                list.splice(idx, 1);
            } else {
                list.push(name);
            }
            saveExcluded(list);
            applyExclusions();
        });
    });
});

// Re-apply after language toggles (i18n.js fires this) — chip tooltips and
// labels might change, but the data-teacher matching should remain stable.
document.addEventListener('langchange', applyExclusions);
