/**
 * Content script for vici.gtu.ge.
 *
 * The Angular bundle exposes (and we verified by inspecting it without ever
 * touching credentials):
 *   - Token storage: localStorage["Student-Token"]
 *   - URL prefix interceptor: every API path is rewritten to "/student/<path>"
 *   - Auth interceptor: sets Authorization: Bearer <token>
 *   - Endpoints we use:
 *       GET  https://vici.gtu.ge/student/card    — full learning card
 *       POST https://vici.gtu.ge/student/getCardDetails  {resultId}
 *   - CORS: Access-Control-Allow-Origin: * — no extra headers needed.
 *
 * This script:
 *   1. Waits until the user is logged in (token in localStorage).
 *   2. Calls /student/card with their existing token (we never see/use their password).
 *   3. Filters card.books to the courses where book.semester === card.view.semester
 *      — those are the *currently ongoing* subjects.
 *   4. Builds a compact payload (student name, faculty, semester, courses[]) and
 *      base64-encodes it into a URL pointing at gtu.cortexgrid.ge/me.php.
 *   5. Stashes both the raw card and the link in chrome.storage.local["lastCard"]
 *      so the popup can show them.
 *   6. Injects a floating "📅 ჩემი ცხრილი" button on the page that opens that link.
 */

const API_CARD = 'https://vici.gtu.ge/student/card';
const ME_PAGE = 'https://gtu.cortexgrid.ge/me.php';
const TOKEN_KEY = 'Student-Token';

const log = (...a) => console.log('[gtu-bridge]', ...a);

function readToken() {
    try { return localStorage.getItem(TOKEN_KEY) || null; } catch { return null; }
}

async function fetchCard(token) {
    const resp = await fetch(API_CARD, {
        method: 'GET',
        headers: { 'Authorization': `Bearer ${token}` },
        credentials: 'omit',
    });
    const text = await resp.text();
    let json = null;
    try { json = JSON.parse(text); } catch {}
    return { status: resp.status, body: text, json };
}

/**
 * Pick out the current-semester courses. Each entry preserves only the fields
 * me.php actually uses — keeps the payload short enough to fit comfortably in
 * a URL.
 */
function buildPayload(card) {
    const view = card?.view;
    if (!view) return null;
    const currentSem = view.semester;

    const books = Array.isArray(card.books) ? card.books : [];
    const ongoing = books.filter(b => b && b.semester === currentSem);

    const courses = ongoing.map(b => ({
        subject:    b.name      ?? '',
        subjectEn:  b.altName   ?? '',
        teacher:    b.prof      ?? '',
        teacherEn:  b.altProf   ?? '',
        teacherMail: b.profMail ?? null,
        credit:     b.credit    ?? null,
        result:     b.result    ?? '',
        score:      b.score     ?? null,
        listId:     b.listId    ?? null,    // the resultId for /student/getCardDetails
    }));

    return {
        name:       view.name        ?? '',
        school:     view.schoolName  ?? '',
        schoolEn:   view.altSchoolName ?? '',
        special:    view.specialName ?? '',
        specialEn:  view.altSpecialName ?? '',
        semester:   currentSem,
        year:       (Array.isArray(card.year) && card.year.length)
                       ? card.year[card.year.length - 1]
                       : '',
        gpa:        view.gpa ?? null,
        avgScore:   view.averageScore ?? null,
        avgResult:  view.averageResult ?? '',
        courses,
    };
}

/** Base64-encode a UTF-8 JSON string in a way that survives URL transport. */
function encodePayload(payload) {
    const json = JSON.stringify(payload);
    // btoa needs Latin-1; encode UTF-8 → escape → btoa is the standard trick.
    const utf8 = unescape(encodeURIComponent(json));
    return btoa(utf8);
}

function buildMeUrl(payload) {
    if (!payload) return ME_PAGE;
    const encoded = encodePayload(payload);
    return `${ME_PAGE}?d=${encodeURIComponent(encoded)}`;
}

function injectFloatingButton(href, label) {
    let fab = document.getElementById('gtu-bridge-fab');
    if (!fab) {
        fab = document.createElement('a');
        fab.id = 'gtu-bridge-fab';
        Object.assign(fab.style, {
            position: 'fixed',
            right: '18px',
            bottom: '18px',
            zIndex: 999999,
            padding: '10px 14px',
            background: '#0f1115',
            color: '#e7e9ee',
            border: '1px solid #2a2f39',
            borderLeft: '3px solid #8b5cf6',
            borderRadius: '999px',
            textDecoration: 'none',
            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif',
            fontSize: '13px',
            fontWeight: '600',
            boxShadow: '0 4px 14px rgba(0,0,0,0.4)',
            cursor: 'pointer',
        });
        document.body.appendChild(fab);
    }
    fab.href = href;
    fab.target = '_blank';
    fab.rel = 'noopener';
    fab.textContent = label;
}

async function syncCard() {
    const token = readToken();
    if (!token) {
        log('no Student-Token in localStorage — user not logged in yet');
        chrome.storage.local.set({ lastCard: { ts: Date.now(), error: 'not_logged_in' } });
        return;
    }
    log('fetching /student/card');
    let card;
    try {
        card = await fetchCard(token);
    } catch (err) {
        log('fetch failed:', err);
        chrome.storage.local.set({ lastCard: { ts: Date.now(), error: String(err) } });
        return;
    }

    if (card.status !== 200 || !card.json || card.json.result !== 'yes') {
        log('non-OK response:', card.status, card.body.slice(0, 200));
        chrome.storage.local.set({
            lastCard: { ts: Date.now(), error: `HTTP ${card.status}`, bodyPreview: card.body.slice(0, 500) },
        });
        return;
    }

    const payload = buildPayload(card.json.card);
    const meUrl = buildMeUrl(payload);
    log('built payload:', payload?.courses?.length ?? 0, 'courses; me url length:', meUrl.length);

    chrome.storage.local.set({
        lastCard: {
            ts: Date.now(),
            payload,
            meUrl,
            json: card.json.card,
        },
    });

    if (payload) {
        const label = payload.courses.length
            ? `📅 ჩემი ცხრილი (${payload.courses.length})`
            : '📅 ჩემი ცხრილი';
        injectFloatingButton(meUrl, label);
    }
}

// Wait for the SPA to settle, then look for a token. Re-poll briefly so the
// extension catches the token after the user logs in mid-page.
let attempts = 0;
function tick() {
    if (attempts++ > 20) return;
    if (readToken()) { syncCard(); return; }
    setTimeout(tick, 3000);
}
setTimeout(tick, 800);
