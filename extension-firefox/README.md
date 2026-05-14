# GTU ცხრილი — Firefox build

Firefox-compatible build of the **GTU ცხრილი** extension. Works on:

- **Firefox Desktop** (Linux / macOS / Windows) — Firefox 115+
- **Firefox for Android** — Firefox 120+ (Nightly / Beta / Stable), via Custom Add-on Collection or AMO

Same behaviour as the Chrome build: reads your vici.gtu.ge `/student/card`, builds a personal schedule, opens [gtu.cortexgrid.ge/me.php](https://gtu.cortexgrid.ge/me.php) with your courses.

---

## What's different from the Chrome version

This is a near-1:1 port. Diffs vs `../extension/`:

| Change | Why |
|---|---|
| `manifest.json`: added `browser_specific_settings.gecko.id` | Required by Firefox for self-distribution + AMO submission |
| `manifest.json`: added `browser_specific_settings.gecko.strict_min_version` (115) and `gecko_android.strict_min_version` (120) | Locks to versions that actually support MV3 + content scripts on each platform |
| `content.js` / `popup/popup.js` / `popup/i18n.js`: `chrome.*` → `ext = browser ?? chrome` | Firefox uses the `browser.*` promise-based namespace natively. The alias keeps the same source working on both engines |
| `popup/popup.css`: added `max-width: 100vw` on `html, body` | Firefox Android opens the popup full-screen — without this, the 360 px body would horizontally scroll on narrow viewports |

No background script (we don't need one). No service worker (Firefox MV3 doesn't support `service_worker` anyway). The whole thing is content script + popup.

---

## Install on Firefox Desktop (developer / unsigned)

1. **Build the zip:**
   ```bash
   cd extension-firefox
   zip -r ../gtu-cxrili-firefox.zip . -x "README.md" "*.zip" ".DS_Store"
   ```
2. Open `about:debugging#/runtime/this-firefox`.
3. Click **Load Temporary Add-on…** and pick the **`manifest.json`** inside `extension-firefox/`.
4. Open https://vici.gtu.ge, log in. The extension's `content.js` reads the token, builds the payload, drops the floating `📅 ჩემი ცხრილი` button in the bottom-right of the page.

Temporary add-ons are removed when Firefox restarts.

---

## Install on Firefox for Android (Nightly recommended)

Firefox Android only loads extensions from a **Custom Add-on Collection on AMO** (or via the Nightly debugger). Easiest dev path:

### Option A — Submit to AMO (production)

1. Sign in at https://addons.mozilla.org/developers/.
2. Upload `gtu-cxrili-firefox.zip` as a new listing.
3. Choose "On this site" (private) or "Listed publicly" (public).
4. Wait for review (usually faster than Chrome, 1–5 days for first listing).
5. Once approved, install from `addons.mozilla.org` directly on Firefox Android.

### Option B — Custom Add-on Collection (Nightly)

For testing without going through AMO review:

1. Use **Firefox Nightly for Android** (not stable Firefox — stable only allows AMO-approved add-ons).
2. Get an AMO account; create a private add-on collection at https://addons.mozilla.org/en-US/firefox/collections/.
3. Upload your `.xpi` to the collection.
4. In Firefox Nightly: **Settings → About → tap the Firefox logo 5×** → developer mode unlocked.
5. **Settings → Install extension from collection** → enter your AMO user ID and collection name.
6. The collection's add-ons become installable.

### Option C — Debug via `web-ext` (USB)

If you have `web-ext` installed and your phone in USB debugging mode:

```bash
npx web-ext run --target firefox-android \
  --android-device <DEVICE_ID> \
  --source-dir ./extension-firefox
```

This sideloads the unsigned extension to the running Firefox Nightly on the phone, useful for live development.

---

## Mobile UX notes

- **Toolbar action**: Firefox Android doesn't put extension icons in the URL bar like desktop. To open the popup, tap **≡ (menu) → extension name**.
- **Floating button**: the `content.js` injects a `📅 ჩემი ცხრილი` button at the bottom-right of any `vici.gtu.ge` page. Mobile users will rely on this much more than the popup, since it's always one tap away.
- **Storage**: uses `browser.storage.local` — same persistence model as desktop.
- **No `tabs` or `webRequest`**: zero extra permissions needed beyond `storage` and the two host origins.

---

## Verification

After installation, on https://vici.gtu.ge:

1. Open DevTools (desktop) / `about:debugging` extension inspector (mobile) → console.
2. You should see `[gtu-bridge] fetching /student/card` and then `[gtu-bridge] built payload: N courses; me url length: …`.
3. The floating button should appear bottom-right with `📅 ჩემი ცხრილი (N)`.
4. Clicking the popup icon → see the same name / school / GPA / courses list as on Chrome.

If `[gtu-bridge] no Student-Token in localStorage` appears, you're not logged in — log in to vici and the extension will retry every 3 s for the first minute.
