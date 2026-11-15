# Web accessibility, RTL and Lighthouse audit (FE-16.7)

> **Date:** 2026-11-15 · **Scope:** `qbazaar-web`, nine pages in Arabic and English · **Before:** `main` at `b86fc239`
> · **After:** branch `fix/web-a11y-lighthouse` · **Tools:** `tools/parity` (`npm run a11y`, `npm run lighthouse`)

## 1. Summary

- **Critical axe violations:** none, before or after.
- **Serious violations fixed:** the login language pill's accessible name now starts with its visible text (WCAG 2.5.3),
  and the password eye toggle is a 24 px target (WCAG 2.5.8). Neither changes how the page looks.
- **Serious violations reported, not fixed:**
  - Colour contrast. The design colours are kept by rule; the failing pairs are in section 4 for the owner.
  - The home carousel dots (9 px dots 7 px apart). A fix needs a visual spacing change.
  - The Leaflet attribution link on the ad page. It is third-party map credit, and underlining it changes the look.
- **A real bug the audit found:** under concurrent traffic, Arabic pages could be server-rendered with English text in
  client components, because the client locale was a module-level value shared by all requests. Fixed (section 6).
- **RTL:** `lang`/`dir` are right on every page, and no control is left in English order in Arabic.
- **Lighthouse:** accessibility 92–97 before and 93–100 after (login reaches 100 in Arabic; the rest is contrast and the
  carousel dots). **Mobile performance does not reach 90**: it is 49–86 after, on a machine whose CPU was saturated by other
  work (section 8 explains why and what would move it). Two cheap, visually identical wins went in. zod left the
  catalog, search, ad and seller pages (65 KB less JavaScript each). An empty search renders its recommended ads on the
  server, so the layout no longer shifts (mobile CLS 0.19–0.56 → 0).

## 2. Method

| | |
|---|---|
| Target | A local production build (`next build` + `next start`) of each commit, reading the production API (GET only) |
| Pages | home `/`, category `/c/cars`, search `/search?q=toyota` (no results), product `/ads/<id>`, seller `/u/<id>`, companies `/companies`, login `/login`, account `/account`, post-ad `/post-ad` |
| Opened states | phone menu (home, 390), phone filter sheet (category, 390), login form errors (390 and 1440); after only |
| Widths and locales | 390 and 1440 px, `ar` and `en` (cookie `NEXT_LOCALE`) |
| axe | axe-core 4.14.0, tags `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa`, `wcag22aa`, `best-practice` |
| Document checks | `lang` and `dir` follow the locale, one `main`, one visible `h1`, no skipped heading level |
| RTL mirroring | the parity gate's check: the horizontal order of each pair of controls must flip between `en` and `ar` |
| Lighthouse | 13.6.0 through npx, mobile (default throttling) and desktop presets, one run per cell, simulated throttling |
| Signed-in pages | no demo account in this environment, so a stand-in session lives in the browser: fixture profile, real public data, every write blocked. Lighthouse skips these two pages |

The live site was not audited: the local build of each commit is the only way to compare before and after. Production
numbers belong to PageSpeed Insights / CrUX (follow-up).

## 3. axe results

Node counts over the 36 page runs (9 pages × 2 locales × 2 widths). No critical violation in any run.

| Rule | Impact | Before: nodes (runs) | After: nodes (runs) | Where | Status |
|---|---|---|---|---|---|
| color-contrast | serious | 969 (34) | 1029 (34) | all nine pages | Reported only, section 4. The rise is post-ad, which now renders (see below) |
| target-size | serious | 34 (8) | 30 (4) | home carousel dots, login eye toggle | Eye fixed; dots reported (follow-up 2) |
| label-content-name-mismatch | serious | 4 (4) | 0\* | login language pill | Fixed |
| link-in-text-block | serious | 4 (4) | 3 (3) | ad page map credit (Leaflet) | Reported (follow-up 3); one run had not loaded the map |
| page-has-heading-one | moderate | 4 (4) | 0 | post-ad | Not an app bug: in the before run the stand-in session lacked the account summary, so the page showed its error screen. With the fixture the form renders with its `h1` |

Opened states (after only; 8 runs):

| State | Locale · width | Violations |
|---|---|---|
| Phone menu (home) | ar · 390 / en · 390 | colour contrast only (21 / 42 nodes) |
| Phone filter sheet (category) | ar · 390 / en · 390 | colour contrast only (63 / 118 nodes) |
| Login form errors | ar · 390, 1440 | none\* |
| Login form errors | en · 390, 1440 | colour contrast only (8 nodes each)\* |

\* From a re-run of login (page and error state) on the final build. The first fix put the code in brackets,
"Language: English (EN)", and axe still flagged it because it ignores bracketed text. The name now starts with the code,
"EN, Language: English", and that re-run has no label or target-size violation (login in Arabic: 0 violations).

Document checks: `lang` and `dir` follow the locale, there is one `main` and one visible `h1`, and no heading level is
skipped on any page, in both runs (except the before post-ad error screen above).

## 4. Colour contrast (reported only)

The design colours are exact by rule (never darkened), so these are left for the owner to decide. Pairs are axe's
computed text and background colours, with the lowest ratio seen.

| Text | Background | Ratio | Needed | Nodes | Pages |
|---|---|---|---|---|---|
| #9e9696 | #f5f5f5 | 2.65 | 4.5:1 | 337 | home, category, search, phone menu, filter sheet |
| #9e9e9e | #ffffff | 2.67 | 4.5:1 | 263 | home, category, search, product, seller, companies, account, post-ad |
| #ffffff | #f38057 | 2.61 | 4.5:1 | 112 | every page (white text on the brand orange) |
| #9e9696 | #ffffff | 2.89 | 4.5:1 | 103 | category, search, product, filter sheet |
| #f38057 | #fff0ea | 2.35 | 4.5:1 | 74 | product, companies, post-ad |
| #f38057 | #f9f9f9 | 2.48 | 4.5:1 | 70 | most pages |
| #f38057 | #ffffff | 2.61 | 4.5:1 | 35 | category, product, seller, login, account, post-ad |
| #9e9e9e | #f9f9f9 | 2.54 | 4.5:1 | 27 | home, category, companies, account |
| #1bad07 | #e8ffec | 2.83 | 4.5:1 | 23 | home, companies |
| #17a2d6 | #e0f6ff | 2.61 | 4.5:1 | 17 | companies |
| #2b6fdb | #ebeeff | 4.13 | 4.5:1 | 12 | companies |
| #e83f3f | #ffe8e8 | 3.44 | 4.5:1 | 12 | companies |
| #909090 | #ffffff | 3.19 | 4.5:1 | 10 | seller |
| #a4adba | #f9f9f9 | 2.15 | 4.5:1 | 8 | category, search, product, seller |
| #a19f9f | #ffffff | 2.63 | 4.5:1 | 8 | login |
| #bdbdbd | #ffffff | 1.87 | 4.5:1 | 7 | home, search, seller |
| #e64646 | #ffffff | 3.94 | 4.5:1 | 4 | login form errors |

28 pairs in all; the full list is in the run's `a11y.json`. Three colours cover most nodes: the greys `#9e9696`/`#9e9e9e`
and the brand orange `#f38057`, as text or as the background of white text.

## 5. RTL

- `html lang`/`dir` are `ar`/`rtl` and `en`/`ltr` on all 36 page runs, before and after, and on every state run.
- Mirroring (the seven public pages at 390 and 1440): **0 pairs of controls left in English order**, before and after.
- The locale leak in section 6 was the one RTL-related defect: under load, Arabic pages showed English strings inside an
  RTL layout.

## 6. Locale leak in server rendering

**What happened.** `t()` and `getLocale()` are synchronous. In a client component they read a module-level value that
`<LocaleProvider>` sets during render. The browser renders one locale, so that is fine there. The Node server renders many
requests at once, and React streams: a render that waits for data resumes after other requests' providers have run, and
then reads their locale.

**Evidence** (rounds of concurrent `/search?q=toyota` requests with the `NEXT_LOCALE` cookie alternating `ar`/`en`):

| Build | Requests | With text of the other language |
|---|---|---|
| before | 2 rounds of 6, plus 1 Arabic request alone | 6: every concurrent Arabic response had English strings |
| after | rounds of 6, 6 and 12 | 0 |

The Arabic request made alone was right, which is why the leak never showed in manual testing.

**Fix.** `<LocaleProvider>` also puts the locale in a React context. On the server, `getLocale()` reads that context of
the render in progress (`use(context)`) and falls back to the module value outside a render. The browser path is
unchanged. `LocaleProvider.test.tsx` streams an Arabic render that waits for data while an English render completes; it
fails without the fix.

## 7. Lighthouse

**Read the scores with care.** Each cell is one run (search in Arabic: the median of three, because its first after run
hit a CPU index of 114). The machine was shared with other jobs, and memory compression plus the virus scanner kept
the CPU near 100 % at times. Lighthouse's CPU benchmark index swung from 114 to 2,076 between runs, against about 1,000–2,000
on a normal laptop. Pages whose JavaScript did not change (home, companies, login) moved by up to ±30 points between the two
builds, so a difference smaller than that says nothing. The byte counts and CLS below do not depend on the CPU, so they
show what the changes did.

### 7.1 What each page sends (mobile runs)

| Page | JS KB (requests) | Unused JS KB | Total KB (ar) | CLS ar / en |
|---|---|---|---|---|
| home | 328 (21) → 328 (21) | 26 → 26 | 734 → 734 | 0 / 0 → 0 / 0 |
| category | 497 (27) → **432 (26)** | 96 → **45** | 1122 → 1057 | 0.005 / 0 → 0.005 / 0 |
| search (no results) | 492 (27) → **427 (26)** | 102 → **51** | 825 → 760 | **0.56 / 0.19 → 0 / 0** |
| product | 439 (25) → **374 (24)** | 76 → **25** | 728 → 663 | 0 / 0 → 0 / 0 |
| seller | 439 (26) → **374 (25)** | 76 → **25** | 667 → 602 | 0 / 0 → 0 / 0 |
| companies | 307 (20) → 307 (20) | 26 → 27 | 551 → 551 | 0 / 0 → 0 / 0 |
| login | 389 (23) → 389 (23) | 76 → 76 | 604 → 604 | 0 / 0 → 0 / 0 |

Desktop CLS on search went from 0.16 to 0.03–0.07: a small shift is left, from an image without explicit size above the
footer (follow-up 9).

### 7.2 Scores (before → after)

| Page | Locale | Mobile perf | Desktop perf | Accessibility | SEO | Mobile LCP (s) | Mobile TBT (ms) |
|---|---|---|---|---|---|---|---|
| home | ar | 57 → 66 | 98 → 78 | 93 → 93 | 100 → 100 (desktop 92\*) | 4.6 → 2.7 | 1588 → 2258 |
| home | en | 49 → 56 | 81 → 93 | 93 → 93 | 100 → 100 | 5.1 → 4.5 | 2669 → 1624 |
| category | ar | 45 → 55 | 84 → 97 | 96 → 96 | 100 → 100 | 6.8 → 5.5 | 4427 → 1233 |
| category | en | 47 → 53 | 96 → 84 | 96 → 96 | 100 → 100 | 5.9 → 5.5 | 3674 → 1418 |
| search | ar | 18 → 52 | 71 → 81 | 96 → 96 | 69 → 69 | 6.6 → 5.5 | 3444 → 1625 |
| search | en | 41 → 51 | 78 → 92 | 96 → 96 | 69 → 69 | 6.2 → 5.5 | 1579 → 1786 |
| product | ar | 51 → 56 | 97 → 71 | 97 → 97 | 100 → 100 | 5.9 → 5.0 | 1838 → 1263 |
| product | en | 41 → 49 | 96 → 97 | 97 → 97 | 100 → 100 | 7.8 → 6.6 | 1296 → 1579 |
| seller | ar | 54 → 58 | 85 → 76 | 97 → 97 | 100 → 100 | 5.4 → 4.8 | 1477 → 1329 |
| seller | en | 58 → 56 | 87 → 85 | 97 → 97 | 100 → 100 | 5.6 → 5.1 | 876 → 1314 |
| companies | ar | 74 → 54 | 98 → 98 | 96 → 96 | 100 → 100 | 4.5 → 4.7 | 421 → 2048 |
| companies | en | 80 → 61 | 98 → 98 | 96 → 96 | 100 → 100 | 4.7 → 4.6 | 209 → 1052 |
| login | ar | 75 → 76 | 100 → 70 | 96 → **100** | 66 → 66 | 1.2 → 1.2 | 1285 → 1173 |
| login | en | 74 → 86 | 100 → 93 | 92 → **96** | 66 → 66 | 1.2 → 1.2 | 1460 → 538 |

- Accessibility: what is left is colour contrast on every page and the carousel dots on home (both reported above).
- SEO 66–69 on search and login is `noindex`, which is intended there. \*The desktop run of home in Arabic timed out
  fetching `robots.txt` under load; the file answers in 0.16 s on its own.

## 8. Why mobile performance stays below 90

The mobile preset slows the CPU four times and the network to a slow 4G link, then scores LCP (25 %), TBT (30 %), CLS
(25 %), FCP and Speed Index (10 % each). Here:

- **TBT is the main loss.** The React DOM and Next.js runtime chunk that every page loads costs about 2.2–2.7 s of
  script time under throttling on its own. On the catalog pages, Lighthouse also charges up to 2.8 s to the HTTP client's
  chunk (axios with a `Buffer` polyfill). Neither can be trimmed cheaply without changing how the pages load data and
  render (follow-ups 4 and 10).
- **The machine is slow and shared.** Lighthouse's CPU benchmark index here was 114–2,076 (a typical developer laptop
  scores about 1,000–2,000), and other jobs were running. Lighthouse warns that the numbers are pessimistic below 1,000.
  TBT and LCP swing by seconds between neighbouring runs of the same page.
- **LCP** is the hero text on home and the first card or gallery image on the catalog pages (already `fetchpriority=high`).
  It waits for the render-blocking CSS (31 KB, about 0.4–0.6 s of simulated delay) and for the main thread.
- **Server response** includes the API round trips from this machine to the production API (`/search` is `no-store`),
  0.1–2.2 s, which a server next to the API does not pay.
- Image delivery (up to 69 KB), legacy JavaScript (14 KB) and font display (passing) carry no estimated FCP or LCP
  saving, so they were left alone.

What went in instead: zod (60 KB, about 51 KB of it unused there) no longer loads on the catalog, search, ad and seller
pages, and an empty search no longer shifts its layout. Reaching 90 needs less client JavaScript on the catalog pages
and a measurement on production hardware (follow-ups 4–6).

## 9. What changed

| Change | Why | Visual change |
|---|---|---|
| `AuthLanguagePill`: the name is "EN, Language: English" (Arabic "AR، اللغة: العربية") instead of "Language: English" | The visible code must be part of the name, so speech users can say what they see (2.5.3) | none |
| `PasswordInput`: the eye toggle is a 24 px box with the 20 px glyph centred, and the pointer area stays 36 px | 2.5.8 needs a 24 px target; the glyph keeps the design's 14 px inset | none |
| `LocaleProvider` + `lib/i18n/locale`: server renders read the locale from a context | The locale leak (section 6) | none |
| `SaveSearchButton`, `ReportDialog`: validation without zod | These are the only zod users on the catalog, search, ad and seller pages; one required field each does not need a 60 KB schema library (about 51 KB of it unused there) | none |
| `catalog-seed`: a search with no results also seeds the featured ads | They loaded in the browser after the first paint and pushed the footer down (CLS up to 0.56) | none |
| `tools/parity`: `npm run a11y` and `npm run lighthouse` | The audit is repeatable, with a browser-only stand-in session for signed-in pages | n/a |

## 10. Follow-ups

1. **Colour contrast** (owner decision): the pairs in section 4. The design colours stay as they are until the owner decides.
2. **Home carousel dots**: 9 px dots 7 px apart fail the 24 px target rule. A fix enlarges the hit area or the spacing, which
   moves the dots, so it needs a design decision.
3. **Leaflet attribution link** on the ad page: third-party credit, coloured but not underlined. An underline would change the
   map's look; leave it unless the owner wants it.
4. **Client JavaScript on the catalog pages** (the main mobile cost): render the card grids as server components, load the
   filter sheet and dialogs with `next/dynamic` when opened, and measure the shared framework chunks.
5. **Render-blocking CSS** (about 0.4–0.6 s of simulated FCP/LCP): `experimental.inlineCss` was not tried. It inlines the
   CSS into every HTML response and repeats it in the RSC payload of client navigations, so it needs an A/B on production.
6. **Production numbers**: run PageSpeed Insights on the live pages and watch CrUX field data once there is traffic; this
   machine's CPU makes the local mobile numbers pessimistic.
7. **Signed-in pages with a real session**: re-run `npm run a11y` with `QB_DEMO_EMAIL`/`QB_DEMO_PASSWORD` set.
8. **SEO 66–69 on search and login** comes from `noindex`, which is intended there; no action.
9. **Small desktop layout shift** (CLS 0.03–0.07 on search and category): Lighthouse blames an image without an explicit
   size above the footer. It is under the 0.1 "good" line; find the image and give it its size.
10. **The HTTP client chunk on the catalog pages** (up to 2.8 s of throttled script time): find out what runs there. A
    likely cause is large responses handled in the browser, such as the category tree and locations, which the server
    could seed instead. Also check why a `Buffer` polyfill ships to the browser.

## 11. Re-running

```bash
cd tools/parity
npm run a11y -- --target http://localhost:3000 --out out/a11y
npm run lighthouse -- --target http://localhost:3000 --out out/a11y --runs 3
```

Set `QB_DEMO_EMAIL`/`QB_DEMO_PASSWORD` to audit the signed-in pages with a real session.
