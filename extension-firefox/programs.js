/**
 * Programs panel for vici.gtu.ge  (#/programs route).
 *
 * Adds a floating "📚 ჩემი პროგრამა" button that opens an overlay sorting
 * every subject in the student's degree program into three buckets:
 *
 *   ✅ Passed      — book.hasPassed === true
 *   🟢 Choosable   — not passed, and every prerequisite is passed
 *   🔒 Blocked     — not passed, and ≥1 prerequisite is NOT passed yet
 *                    (we show which prerequisite is missing)
 *
 * Data source (discovered from the public Angular bundle, no password used):
 *   GET  https://vici.gtu.ge/student/card          → card.view.programId
 *   POST https://vici.gtu.ge/student/program/get   body {id: programId}
 *     → { modules:[{groups:[{books:[{ bookId, hasPassed, conditionStatus,
 *          book:{ name, altName, code, credit } }]}]}],
 *         conditions:{ "<bookId>": ["[<prereqId>] <prereq name>", …] },
 *         conditionsEng:{ … } }
 *
 * The `conditions` map lists each subject's prerequisites as
 * "[<prereqBookId>] <name>" strings. A subject is *blocked* when any of its
 * prerequisite bookIds is not in the set of passed bookIds. We compute this
 * ourselves rather than trusting `conditionStatus` (that field only marks
 * "has prerequisites defined", not "blocked for this student").
 */

(function () {
    'use strict';

    const TOKEN_KEY = 'Student-Token';
    const CARD_URL  = 'https://vici.gtu.ge/student/card';
    const PROG_URL  = 'https://vici.gtu.ge/student/program/get';

    const log = (...a) => console.log('[gtu-programs]', ...a);

    function readToken() {
        try { return localStorage.getItem(TOKEN_KEY) || null; } catch { return null; }
    }
    function onProgramsRoute() {
        return /(^|#|\/)programs\b/.test(location.hash || '');
    }

    async function api(url, opts) {
        const token = readToken();
        if (!token) throw new Error('not_logged_in');
        opts = opts || {};
        // Spread opts FIRST, then force credentials + the merged headers last,
        // so opts.headers ({'Content-Type':…} on the POST) can never clobber
        // the Authorization header. (That bug caused a 401 on the POST while
        // the GET — which has no opts.headers — worked.)
        const resp = await fetch(url, {
            ...opts,
            credentials: 'omit',
            headers: {
                'Authorization': `Bearer ${token}`,
                ...(opts.headers || {}),
            },
        });
        const text = await resp.text();
        let json = null;
        try { json = JSON.parse(text); } catch {}
        return { status: resp.status, json, text };
    }

    /** Flatten modules→groups→books and classify each subject. */
    function classify(prog) {
        const books = [];
        for (const mod of (prog.modules || [])) {
            for (const g of (mod.groups || [])) {
                for (const b of (g.books || [])) {
                    books.push({ entry: b, moduleName: mod.name, groupName: g.name });
                }
            }
        }

        const passedIds = new Set(
            books.filter(x => x.entry.hasPassed).map(x => x.entry.bookId)
        );

        const cond    = prog.conditions    || {};
        const condEng = prog.conditionsEng || {};

        // "[6104] საინჟინრო მათემატიკა 2.1" → { id: 6104, name: "…" }
        function parsePrereqs(bookId) {
            const raw = cond[String(bookId)] || [];
            const rawEn = condEng[String(bookId)] || [];
            const out = [];
            raw.forEach((s, i) => {
                const m = /^\[(\d*)\]\s*(.*)$/.exec(String(s).trim());
                if (!m || !m[1]) return;
                const enM = /^\[(\d*)\]\s*(.*)$/.exec(String(rawEn[i] || '').trim());
                out.push({
                    id: parseInt(m[1], 10),
                    name: m[2] || '',
                    nameEn: (enM && enM[2]) || '',
                });
            });
            return out;
        }

        const passed = [], choosable = [], blocked = [];
        for (const x of books) {
            const e = x.entry;
            const meta = {
                bookId:  e.bookId,
                name:    e.book ? e.book.name : ('#' + e.bookId),
                nameEn:  e.book ? (e.book.altName || '') : '',
                code:    e.book ? (e.book.code || '') : '',
                credit:  e.book ? (e.book.credit ?? null) : null,
                semesters: Array.isArray(e.semesters) ? e.semesters : [],
                module:  x.moduleName,
            };
            if (e.hasPassed) { passed.push(meta); continue; }

            const prereqs = parsePrereqs(e.bookId);
            const missing = prereqs.filter(p => !passedIds.has(p.id));
            if (missing.length) {
                blocked.push({ ...meta, missing });
            } else {
                choosable.push(meta);
            }
        }

        const byName = (a, b) => (a.name || '').localeCompare(b.name || '', 'ka');
        passed.sort(byName); choosable.sort(byName); blocked.sort(byName);
        return { passed, choosable, blocked, total: books.length };
    }

    let _data = null;     // cached classify() result
    let _loading = false;

    async function loadProgram() {
        if (_data || _loading) return _data;
        _loading = true;
        try {
            const card = await api(CARD_URL, { method: 'GET' });
            const programId = card.json && card.json.card &&
                              card.json.card.view && card.json.card.view.programId;
            if (!programId) throw new Error('no_program_id');

            const prog = await api(PROG_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: programId }),
            });
            if (!prog.json || !prog.json.modules) throw new Error('bad_program_response');

            _data = classify(prog.json);
            log('classified', _data.total, 'subjects:',
                _data.passed.length, 'passed,',
                _data.choosable.length, 'choosable,',
                _data.blocked.length, 'blocked');
            return _data;
        } finally {
            _loading = false;
        }
    }

    // ---------- UI ----------

    const C = {
        bg: '#0f1115', panel: '#181b22', panel2: '#20242c',
        text: '#e7e9ee', muted: '#8a91a0', border: '#2a2f39',
        accent: '#6ea8ff', green: '#3fb950', red: '#ff7b6b', purple: '#8b5cf6',
    };

    function el(tag, style, text) {
        const n = document.createElement(tag);
        if (style) Object.assign(n.style, style);
        if (text != null) n.textContent = text;
        return n;
    }

    function section(title, subtitle, items, color, renderItem, defaultOpen) {
        const wrap = el('section', { marginBottom: '10px' });

        // Clickable header row → toggles the body (collapsible dropdown).
        const h = el('div', {
            display: 'flex', alignItems: 'baseline', gap: '8px',
            margin: '0', padding: '8px 4px',
            borderBottom: `1px solid ${C.border}`,
            cursor: 'pointer', userSelect: 'none',
        });
        const chevron = el('span', {
            fontSize: '11px', color: C.muted, width: '12px',
            transition: 'transform 0.15s', display: 'inline-block',
        }, '▸');
        h.appendChild(chevron);
        h.appendChild(el('span', {
            fontSize: '14px', fontWeight: '700', color,
        }, title));
        h.appendChild(el('span', { fontSize: '12px', color: C.muted },
            `${subtitle} · ${items.length}`));
        wrap.appendChild(h);

        const body = el('div', { paddingTop: '6px' });
        if (!items.length) {
            body.appendChild(el('div', { color: C.muted, fontSize: '12px',
                fontStyle: 'italic', padding: '4px 0' }, '—'));
        } else {
            const ul = el('ul', { listStyle: 'none', margin: '0', padding: '0' });
            for (const it of items) ul.appendChild(renderItem(it));
            body.appendChild(ul);
        }
        wrap.appendChild(body);

        let open = !!defaultOpen;
        function apply() {
            body.style.display = open ? '' : 'none';
            chevron.textContent = open ? '▾' : '▸';
        }
        apply();
        h.addEventListener('click', () => { open = !open; apply(); });

        return wrap;
    }

    function subjectRow(meta, extra, color) {
        const li = el('li', {
            background: C.panel2, border: `1px solid ${C.border}`,
            borderRadius: '6px', padding: '8px 10px', marginBottom: '6px',
        });
        // Primary line: "<id>  <KA name>" — the bookId is what the user wants
        // visible (e.g. "6103 საინჟინრო მათემატიკა 1.1").
        const top = el('div', { fontWeight: '600', fontSize: '13px',
            color: C.text, wordBreak: 'break-word' });
        top.appendChild(el('span', {
            color: color || C.accent, fontWeight: '700',
            fontVariantNumeric: 'tabular-nums', marginRight: '6px',
        }, String(meta.bookId)));
        top.appendChild(document.createTextNode(meta.name));
        li.appendChild(top);

        // Secondary line: English name, then code · credits · semester.
        const sub = [];
        if (meta.nameEn && meta.nameEn !== meta.name) sub.push(meta.nameEn);
        const bits = [];
        if (meta.code) bits.push(meta.code);
        if (meta.credit != null) bits.push(meta.credit + ' cr');
        if (meta.semesters && meta.semesters.length) bits.push('სემ. ' + meta.semesters.join(','));
        if (bits.length) sub.push(bits.join(' · '));
        if (sub.length) {
            li.appendChild(el('div', { fontSize: '11px', color: C.muted,
                marginTop: '2px', wordBreak: 'break-word' }, sub.join('  —  ')));
        }
        if (extra) li.appendChild(extra);
        return li;
    }

    function buildPanel(data) {
        const overlay = el('div', {
            position: 'fixed', inset: '0', zIndex: 1000000,
            background: 'rgba(0,0,0,0.55)', display: 'flex',
            justifyContent: 'center', alignItems: 'flex-start',
        });
        overlay.id = 'gtu-programs-overlay';
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) overlay.remove();
        });

        const box = el('div', {
            background: C.bg, color: C.text, marginTop: '4vh',
            width: 'min(560px, 94vw)', maxHeight: '88vh', overflowY: 'auto',
            border: `1px solid ${C.border}`, borderRadius: '10px',
            padding: '16px 18px',
            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif',
            boxShadow: '0 10px 40px rgba(0,0,0,0.5)',
        });

        const head = el('div', { display: 'flex', justifyContent: 'space-between',
            alignItems: 'center', marginBottom: '12px' });
        head.appendChild(el('h2', { margin: '0', fontSize: '17px' },
            '📚 ჩემი პროგრამა'));
        const close = el('button', {
            background: C.panel2, color: C.muted, border: `1px solid ${C.border}`,
            borderRadius: '4px', padding: '5px 11px', cursor: 'pointer',
            fontSize: '13px',
        }, '✕');
        close.addEventListener('click', () => overlay.remove());
        head.appendChild(close);
        box.appendChild(head);

        box.appendChild(el('p', { margin: '0 0 12px', fontSize: '12px',
            color: C.muted },
            `${data.total} საგანი პროგრამაში — დახარისხებული სტატუსის მიხედვით.`));

        box.appendChild(section('🟢 ასარჩევი', 'Choosable now',
            data.choosable, C.green, m => subjectRow(m, null, C.green), true));

        box.appendChild(section('🔒 დაბლოკილი', 'Blocked by prerequisite',
            data.blocked, C.red, m => {
                const why = el('div', {
                    marginTop: '4px', fontSize: '11px', color: C.red,
                    background: 'rgba(255,123,107,0.08)',
                    border: '1px solid rgba(255,123,107,0.3)',
                    borderRadius: '4px', padding: '4px 6px',
                });
                why.appendChild(el('span', { fontWeight: '600' },
                    'ჯერ ჩააბარე: '));
                why.appendChild(document.createTextNode(
                    m.missing.map(p => '[' + p.id + '] ' + p.name).join(' · ')));
                return subjectRow(m, why, C.red);
            }, true));

        box.appendChild(section('✅ გავლილი', 'Passed',
            data.passed, C.accent, m => subjectRow(m, null, C.accent), false));

        overlay.appendChild(box);
        document.body.appendChild(overlay);
    }

    function injectButton() {
        if (document.getElementById('gtu-programs-fab')) return;
        const fab = el('button', {
            position: 'fixed', left: '18px', bottom: '18px', zIndex: 999999,
            padding: '10px 14px', background: C.bg, color: C.text,
            border: `1px solid ${C.border}`, borderLeft: `3px solid ${C.purple}`,
            borderRadius: '999px',
            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif',
            fontSize: '13px', fontWeight: '600', cursor: 'pointer',
            boxShadow: '0 4px 14px rgba(0,0,0,0.4)',
        }, '📚 ჩემი პროგრამა');
        fab.id = 'gtu-programs-fab';
        fab.addEventListener('click', async () => {
            fab.disabled = true;
            const prev = fab.textContent;
            fab.textContent = '⏳ იტვირთება…';
            try {
                const data = await loadProgram();
                if (data) buildPanel(data);
            } catch (err) {
                log('load failed:', err);
                fab.textContent = '⚠️ ' + (err && err.message || 'error');
                setTimeout(() => { fab.textContent = prev; }, 2500);
                fab.disabled = false;
                return;
            }
            fab.textContent = prev;
            fab.disabled = false;
        });
        document.body.appendChild(fab);
    }

    function removeButton() {
        const f = document.getElementById('gtu-programs-fab');
        if (f) f.remove();
        const o = document.getElementById('gtu-programs-overlay');
        if (o) o.remove();
    }

    function sync() {
        if (onProgramsRoute() && readToken()) injectButton();
        else removeButton();
    }

    window.addEventListener('hashchange', sync);
    // SPA route changes don't always fire hashchange reliably on first paint;
    // poll briefly after load too.
    let n = 0;
    const iv = setInterval(() => { sync(); if (++n > 40) clearInterval(iv); }, 1500);
    setTimeout(sync, 800);
})();
