'use strict';

const ME_PAGE = 'https://gtu.cortexgrid.ge/me.php';
const $ = (id) => document.getElementById(id);
const show = (id) => $(id).classList.remove('hidden');
const hide = (id) => $(id).classList.add('hidden');

async function load() {
    const data = await chrome.storage.local.get('lastCard');
    const card = data.lastCard;

    if (!card) {
        $('status').textContent = 'extension არ ჩაუშვია vici.gtu.ge-ზე';
        show('empty');
        return;
    }

    const ago = Math.round((Date.now() - card.ts) / 1000);
    const agoLabel = ago < 60 ? `${ago}წ წინ`
                  : ago < 3600 ? `${Math.round(ago / 60)} წთ წინ`
                  : `${Math.round(ago / 3600)} სთ წინ`;
    $('status').textContent = `ბოლოს განახლდა ${agoLabel}`;

    if (card.error === 'not_logged_in') { show('empty'); return; }
    if (card.error) {
        $('errorMsg').textContent = card.error;
        if (card.bodyPreview) $('errorBody').textContent = card.bodyPreview;
        show('error');
        return;
    }

    const p = card.payload;
    if (!p) {
        $('errorMsg').textContent = 'payload missing';
        show('error');
        return;
    }

    show('found');
    $('name').textContent     = p.name      || '(უცნობი)';
    $('school').textContent   = p.school    || '—';
    $('semester').textContent = p.semester ?? '—';
    $('year').textContent     = p.year      || '—';
    $('gpa').textContent      = (p.gpa ?? '—') + (p.avgResult ? ` (${p.avgResult})` : '');

    const list = $('coursesList');
    list.innerHTML = '';
    $('courseCount').textContent = p.courses.length;
    for (const c of p.courses) {
        const li = document.createElement('li');
        const subj = document.createElement('div');
        subj.className = 'subject';
        subj.textContent = c.subject || c.subjectEn || '(?)';
        const meta = document.createElement('div');
        meta.className = 'meta';
        const parts = [];
        if (c.teacher) parts.push(c.teacher);
        if (c.credit) parts.push(`${c.credit} კრედიტი`);
        if (c.result) parts.push(`${c.result} (${c.score})`);
        meta.textContent = parts.join(' · ');
        li.appendChild(subj);
        li.appendChild(meta);
        list.appendChild(li);
    }

    $('openMe').href = card.meUrl || ME_PAGE;

    if (card.json) {
        show('rawDetails');
        try { $('rawJson').textContent = JSON.stringify(card.json, null, 2); }
        catch { $('rawJson').textContent = String(card.json); }
    }
}

load();
