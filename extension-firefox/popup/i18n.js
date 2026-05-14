'use strict';

/**
 * Tiny standalone i18n for the extension popup. Mirrors the website's
 * assets/i18n.js but is its own copy so the popup doesn't depend on a
 * network fetch to the website to localise its UI.
 */

const POPUP_DICT = {
    ka: {
        'title':            'GTU ცხრილი',
        'loading':          'იტვირთება…',
        'updated':          'ბოლოს განახლდა {ago}',
        'ago.s':            '{n}წ წინ',
        'ago.m':            '{n} წთ წინ',
        'ago.h':            '{n} სთ წინ',
        'kv.student':       'სტუდენტი',
        'kv.school':        'ფაკულტეტი',
        'kv.semester':      'სემესტრი',
        'kv.year':          'წელი',
        'kv.gpa':           'GPA',
        'courses.heading':  'ამჟამინდელი საგნები ({n})',
        'courses.lectures': '{n} ლექცია',
        'courses.exam':     '{n} გამოცდა',
        'courses.next_exam':'შემდეგი გამოცდა: {date} {time} {room}',
        'open_me':          '📅 ნახე სრული ცხრილი',
        'open_extension':   '🔓 გახსენი vici.gtu.ge',
        'no_login':         'vici.gtu.ge-ზე ჯერ არ ხარ შესული.',
        'no_extension':     'extension არ ჩაუშვია vici.gtu.ge-ზე',
        'no_payload':       'მონაცემები ვერ ვიპოვე.',
        'fetch_err':        'ვერ შევძელი ცხრილის წამოღება.',
        'cant_find_prefix': 'ვერ იპოვე? უფრო დეტალური ცხრილისთვის ეწვიე ',
        'totals':           'სულ: {lec} ლექცია · {add} დამატ. · {exams} გამოცდა',
        'debug.raw':        'raw card JSON (debug)',
        'lang.toggle.ka':   'ქარ',
        'lang.toggle.en':   'ENG',
        'day.1': 'ორშ.', 'day.2': 'სამშ.', 'day.3': 'ოთხშ.', 'day.4': 'ხუთშ.',
        'day.5': 'პარ.', 'day.6': 'შაბ.',  'day.7': 'კვ.',
    },
    en: {
        'title':            'GTU Schedule',
        'loading':          'Loading…',
        'updated':          'updated {ago}',
        'ago.s':            '{n}s ago',
        'ago.m':            '{n}m ago',
        'ago.h':            '{n}h ago',
        'kv.student':       'Student',
        'kv.school':        'Faculty',
        'kv.semester':      'Semester',
        'kv.year':          'Year',
        'kv.gpa':           'GPA',
        'courses.heading':  'Ongoing courses ({n})',
        'courses.lectures': '{n} lectures',
        'courses.exam':     '{n} exams',
        'courses.next_exam':'Next exam: {date} {time} {room}',
        'open_me':          '📅 Open full schedule',
        'open_extension':   '🔓 Open vici.gtu.ge',
        'no_login':         'You are not logged in to vici.gtu.ge yet.',
        'no_extension':     'The extension has not run on vici.gtu.ge yet',
        'no_payload':       'No data found.',
        'fetch_err':        'Could not fetch the schedule.',
        'cant_find_prefix': 'Can’t find what you need? For a fuller schedule visit ',
        'totals':           'Totals: {lec} lectures · {add} extras · {exams} exams',
        'debug.raw':        'raw card JSON (debug)',
        'lang.toggle.ka':   'ქარ',
        'lang.toggle.en':   'ENG',
        'day.1': 'Mon', 'day.2': 'Tue', 'day.3': 'Wed', 'day.4': 'Thu',
        'day.5': 'Fri', 'day.6': 'Sat', 'day.7': 'Sun',
    },
};

let _LANG = null;
// Cross-browser namespace alias — Firefox prefers `browser.*`, Chromium `chrome.*`.
const _ext = (typeof browser !== 'undefined') ? browser : chrome;

async function popupLang() {
    if (_LANG) return _LANG;
    try {
        const v = await _ext.storage.local.get('lang');
        if (v.lang === 'ka' || v.lang === 'en') { _LANG = v.lang; return _LANG; }
    } catch {}
    _LANG = (typeof navigator !== 'undefined' && /^en\b/i.test(navigator.language || '')) ? 'en' : 'ka';
    return _LANG;
}

function popupSetLang(lang) {
    if (lang !== 'ka' && lang !== 'en') return;
    _LANG = lang;
    try { _ext.storage.local.set({ lang }); } catch {}
    document.documentElement.lang = lang;
    document.dispatchEvent(new CustomEvent('popup-langchange', { detail: { lang } }));
}

function pt(key, params = {}) {
    const lang = _LANG || 'ka';
    let s = (POPUP_DICT[lang] && POPUP_DICT[lang][key])
         ?? (POPUP_DICT.ka  && POPUP_DICT.ka[key])
         ?? key;
    for (const k in params) s = s.split(`{${k}}`).join(String(params[k]));
    return s;
}
