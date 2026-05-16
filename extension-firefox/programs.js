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
        let groupOrder = 0;
        for (const mod of (prog.modules || [])) {
            for (const g of (mod.groups || [])) {
                const go = groupOrder++;
                for (const b of (g.books || [])) {
                    books.push({
                        entry: b,
                        moduleName: mod.name,
                        moduleIndex: mod.index || '',
                        groupName: g.name,
                        groupId: g.id,
                        groupOrder: go,
                        groupCreditLimit: g.creditLimit ?? null,
                    });
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
                moduleIndex: x.moduleIndex,
                group:   x.groupName,
                groupId: x.groupId,
                groupOrder: x.groupOrder,
                groupCreditLimit: x.groupCreditLimit,
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
        // Earliest recommended semester (first entry in semesters[]), or 99 if none.
        const minSem = (m) => {
            const ns = (m.semesters || []).map(s => parseInt(s, 10)).filter(n => !isNaN(n));
            return ns.length ? Math.min(...ns) : 99;
        };
        const bySemThenName = (a, b) => (minSem(a) - minSem(b)) || byName(a, b);

        passed.sort(bySemThenName);
        blocked.sort(bySemThenName);

        // Choosable: GROUP by the program's requirement bucket (group), keep
        // the program's own group order, and sort subjects inside each group
        // by recommended semester then name. `choosableGrouped` is a flat list
        // with {__group:true,…} divider rows so the existing collapsible
        // section() renderer can lay it out without special-casing.
        const groupsMap = new Map();
        for (const m of choosable) {
            if (!groupsMap.has(m.groupOrder)) {
                groupsMap.set(m.groupOrder, {
                    order: m.groupOrder,
                    name: m.group || '—',
                    moduleIndex: m.moduleIndex,
                    creditLimit: m.groupCreditLimit,
                    items: [],
                });
            }
            groupsMap.get(m.groupOrder).items.push(m);
        }
        const orderedGroups = Array.from(groupsMap.values())
            .sort((a, b) => a.order - b.order);
        for (const grp of orderedGroups) grp.items.sort(bySemThenName);

        return {
            passed, choosable, blocked,
            choosableGroups: orderedGroups,   // [{name,moduleIndex,creditLimit,items[]}]
            total: books.length,
        };
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

    // The panel + button live inside a Shadow DOM so vici.gtu.ge's own theme
    // (especially its LIGHT theme, which uses `!important` rules) cannot
    // override our colors. Page stylesheets do not cross the shadow boundary;
    // `:host{all:initial}` also blocks inherited color/font from leaking in.
    let _shadow = null;
    function shadow() {
        if (_shadow) return _shadow;
        const host = document.createElement('div');
        host.id = 'gtu-programs-host';
        host.style.setProperty('all', 'initial', 'important');
        _shadow = host.attachShadow({ mode: 'open' });
        const st = document.createElement('style');
        st.textContent =
            ':host{all:initial;}' +
            '*{box-sizing:border-box;margin:0;}' +
            ':host{color:' + C.text + ';color-scheme:dark;' +
            'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",system-ui,sans-serif;}';
        _shadow.appendChild(st);
        document.body.appendChild(host);
        return _shadow;
    }
    function shadowQ(sel) { return _shadow ? _shadow.querySelector(sel) : null; }

    function copyText(s) {
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                return navigator.clipboard.writeText(s);
            }
        } catch {}
        // Fallback for older engines / non-secure contexts.
        try {
            const ta = document.createElement('textarea');
            ta.value = s;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            ta.remove();
        } catch {}
        return Promise.resolve();
    }

    /** A bookId badge that copies the id to the clipboard when clicked. */
    function copyableId(id, color) {
        const badge = el('button', {
            color: color || C.accent, fontWeight: '700',
            fontVariantNumeric: 'tabular-nums',
            background: 'rgba(255,255,255,0.04)',
            border: `1px solid ${C.border}`,
            borderRadius: '5px', padding: '2px 8px', marginRight: '8px',
            cursor: 'pointer', fontSize: '13px', lineHeight: '1.4',
            fontFamily: 'inherit',
        }, String(id));
        badge.type = 'button';
        badge.title = 'დააკოპირე ID / copy ID';
        badge.addEventListener('click', (e) => {
            e.stopPropagation();
            copyText(String(id));
            const old = badge.textContent;
            badge.textContent = '✓ ' + old;
            badge.style.color = C.green;
            setTimeout(() => {
                badge.textContent = old;
                badge.style.color = color || C.accent;
            }, 1100);
        });
        return badge;
    }

    function section(title, subtitle, items, color, renderItem, defaultOpen, countOverride, customBody) {
        const wrap = el('section', { marginBottom: '10px' });
        const count = (countOverride != null) ? countOverride : items.length;

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
            `${subtitle} · ${count}`));
        wrap.appendChild(h);

        const body = el('div', { paddingTop: '6px' });
        if (typeof customBody === 'function') {
            if (count === 0) {
                body.appendChild(el('div', { color: C.muted, fontSize: '12px',
                    fontStyle: 'italic', padding: '4px 0' }, '—'));
            } else {
                body.appendChild(customBody());
            }
        } else if (!items.length) {
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


    /** Category heading shown above each table (div, not <li>). */
    function groupHeader(g, color) {
        const d = el('div', {
            display: 'flex', alignItems: 'baseline', gap: '6px',
            flexWrap: 'wrap', margin: '14px 0 6px',
        });
        if (g.moduleIndex) {
            d.appendChild(el('span', { color: C.muted, fontWeight: '600',
                fontSize: '12px' }, g.moduleIndex + ' ·'));
        }
        d.appendChild(el('span', { fontSize: '13px', fontWeight: '700',
            color: color || C.green }, g.name));
        const meta = [];
        if (g.creditLimit != null) meta.push(g.creditLimit + ' კრ');
        meta.push((g.items ? g.items.length : g.count) + ' საგანი');
        d.appendChild(el('span', { color: C.muted, fontSize: '11px' },
            '— ' + meta.join(' · ')));
        return d;
    }

    /**
     * Render a list of subjects as a real table with columns:
     *   ID · საგანი · კოდი · ECTS · სემ.  (+ წინაპირობა when opts.why).
     * `color` tints the ID badge / header underline per status.
     */
    function subjectTable(items, color, opts) {
        opts = opts || {};
        const table = el('table', {
            width: '100%', borderCollapse: 'collapse',
            fontSize: '13px', tableLayout: 'auto',
        });

        const cols = ['ID', 'საგანი', 'კოდი', 'ECTS', 'სემ.'];
        if (opts.why) cols.push('წინაპირობა');

        const thead = el('thead');
        const htr = el('tr');
        cols.forEach((c, i) => {
            htr.appendChild(el('th', {
                textAlign: i <= 1 || (opts.why && i === cols.length - 1)
                    ? 'left' : 'center',
                padding: '6px 8px', fontSize: '11px', fontWeight: '700',
                color: C.muted, textTransform: 'uppercase',
                letterSpacing: '0.4px',
                borderBottom: `2px solid ${color || C.border}`,
                whiteSpace: 'nowrap',
            }, c));
        });
        thead.appendChild(htr);
        table.appendChild(thead);

        const tbody = el('tbody');
        items.forEach((m, idx) => {
            const tr = el('tr', {
                background: idx % 2 ? 'rgba(255,255,255,0.02)' : 'transparent',
            });
            const td = (style) => {
                const c = el('td', Object.assign({
                    padding: '7px 8px', verticalAlign: 'top',
                    borderBottom: `1px solid ${C.border}`,
                }, style || {}));
                tr.appendChild(c);
                return c;
            };

            // ID — copyable badge.
            td({ whiteSpace: 'nowrap' }).appendChild(copyableId(m.bookId, color));

            // Name — KA bold, EN muted under it.
            const nameCell = td({ wordBreak: 'break-word', minWidth: '220px' });
            nameCell.appendChild(el('div', {
                fontWeight: '600', color: C.text, fontSize: '14px',
                lineHeight: '1.35',
            }, m.name));
            if (m.nameEn && m.nameEn !== m.name) {
                nameCell.appendChild(el('div', {
                    fontSize: '11px', color: C.muted, marginTop: '1px',
                }, m.nameEn));
            }

            // Code · ECTS · Semester.
            td({ textAlign: 'center', whiteSpace: 'nowrap', color: C.muted,
                fontVariantNumeric: 'tabular-nums' }).textContent = m.code || '—';
            td({ textAlign: 'center', whiteSpace: 'nowrap',
                fontVariantNumeric: 'tabular-nums' }).textContent =
                (m.credit != null ? m.credit : '—');
            td({ textAlign: 'center', whiteSpace: 'nowrap', color: C.muted,
                fontVariantNumeric: 'tabular-nums' }).textContent =
                (m.semesters && m.semesters.length ? m.semesters.join(',') : '—');

            // Blocked: which prerequisite is still missing.
            if (opts.why) {
                const w = td({ color: C.red, fontSize: '11px',
                    wordBreak: 'break-word' });
                w.textContent = (m.missing || [])
                    .map(p => '[' + p.id + '] ' + p.name).join(' · ');
            }

            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        return table;
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
            background: C.bg, color: C.text, marginTop: '3vh',
            width: 'min(900px, 96vw)', maxHeight: '94vh', overflowY: 'auto',
            border: `1px solid ${C.border}`, borderRadius: '10px',
            padding: '20px 24px',
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

        box.appendChild(el('p', { margin: '0 0 14px', fontSize: '12px',
            color: C.muted },
            `${data.total} საგანი პროგრამაში — დახარისხებული სტატუსის მიხედვით. ` +
            `დააწექი ID-ს დასაკოპირებლად.`));

        // Choosable: one TABLE per requirement group (category), with a
        // heading above each.
        box.appendChild(section('🟢 ასარჩევი', 'Choosable now',
            null, C.green, null, true, data.choosable.length, () => {
                const c = el('div');
                for (const g of data.choosableGroups) {
                    c.appendChild(groupHeader(g, C.green));
                    c.appendChild(subjectTable(g.items, C.green));
                }
                return c;
            }));

        // Blocked: single table with an extra "prerequisite" column.
        box.appendChild(section('🔒 დაბლოკილი', 'Blocked by prerequisite',
            null, C.red, null, true, data.blocked.length, () =>
                subjectTable(data.blocked, C.red, { why: true })));

        // Passed: single table.
        box.appendChild(section('✅ გავლილი', 'Passed',
            null, C.accent, null, false, data.passed.length, () =>
                subjectTable(data.passed, C.accent)));

        overlay.appendChild(box);
        const sr = shadow();
        const existing = sr.querySelector('#gtu-programs-overlay');
        if (existing) existing.remove();
        sr.appendChild(overlay);
    }

    function injectButton() {
        if (shadowQ('#gtu-programs-fab')) return;
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
        shadow().appendChild(fab);
    }

    function removeButton() {
        const f = shadowQ('#gtu-programs-fab');
        if (f) f.remove();
        const o = shadowQ('#gtu-programs-overlay');
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
