# GTU ცხრილი — Chrome extension (vici-bridge)

Pulls your group code from `vici.gtu.ge` (using your existing logged-in
session) and links to your full weekly schedule on `gtu.cortexgrid.ge`.

The extension never touches your password. It reads the JWT that
`vici.gtu.ge` already stored for you in `localStorage["Student-Token"]`,
calls `https://vici.gtu.ge/student/card` with that token (same call the
SPA itself makes), and looks up your group code in the response.

## Install (developer / unpacked, while we're iterating)

1. In Chrome, go to **chrome://extensions**.
2. Toggle **Developer mode** (top-right).
3. Click **Load unpacked**.
4. Select this `extension/` folder
   (`/home/cortexgrid-gtu/htdocs/gtu.cortexgrid.ge/extension`).
5. Open `vici.gtu.ge` and log in normally.
6. Within ~1 second of being logged in, a floating **📅 ჯგუფი_კოდი** button
   appears bottom-right. Click it — opens your schedule on
   `gtu.cortexgrid.ge` automatically scoped to your group.

You can also click the extension icon in the Chrome toolbar to see what
the extension captured (student name + group code + raw JSON for debug).

## What it does, step by step

1. Content script runs on every `https://vici.gtu.ge/*` page.
2. Polls `localStorage` for `Student-Token` (waits until you log in).
3. Fetches `GET https://vici.gtu.ge/student/card` with
   `Authorization: Bearer <Student-Token>`.
4. Walks the JSON response looking for a group-code-shaped string
   (4-7 digits, optional `-X` or `/N` suffix — same shape we see in the
   public `leqtori.gtu.ge` schedules).
5. Stores the discovered info in `chrome.storage.local["lastCard"]`.
6. Injects a floating button linking to
   `https://gtu.cortexgrid.ge/?group=<code>`.

## Files

- `manifest.json` — MV3 manifest. Permissions: `storage` only.
  Host permissions: `vici.gtu.ge` and `gtu.cortexgrid.ge`.
- `content.js`    — runs on vici.gtu.ge, fetches the card.
- `popup/`        — popup shown when clicking the toolbar icon.

## Privacy

- Stays on **your own machine**. No data is sent to any server we control;
  the fetch goes directly from your browser to `vici.gtu.ge` over HTTPS,
  with the same token you already use.
- Group code is encoded into a URL parameter when you click the button —
  that hits our own `gtu.cortexgrid.ge`, which only uses it to query the
  public lecture data we already scraped from `leqtori.gtu.ge`.
- The popup's "raw JSON" panel exists so you can see exactly what the
  extension captured. Nothing is uploaded automatically.

## Limitations / next steps

- **Group code only**, for now. The richer "show me a personal week with
  ONLY my courses" view needs a backend endpoint that takes the list of
  enrolled subjects+teachers from the card response. That's phase 2B.
- We don't yet know the full shape of `/student/card`'s JSON because we
  need an authenticated session to inspect it. The extension dumps the
  response into the popup's debug panel — once you install it and look,
  copy that JSON back so we can build the richer match.
- No icons yet — Chrome shows the default puzzle-piece icon. Cosmetic
  fix any time we feel like it.

## Pack for the Chrome Web Store (later)

```
cd extension/
zip -r ../gtu-cxrili.zip . -x "*.DS_Store" "README.md"
```

Submit `gtu-cxrili.zip` to https://chrome.google.com/webstore/devconsole/.
