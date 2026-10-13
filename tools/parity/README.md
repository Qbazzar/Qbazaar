# Design-parity gate

Measures the QBazaar web (`qbazaar-web`) against the original HTML design and reports every difference.
The reference is the source of truth: the prototype at <https://qbazzar.github.io/Qbazaar-front/>
(identical to the local copy in `D:/Doc/q-bazaar`). Figma is only used for pages the HTML lacks.

The gate is its own package. It adds nothing to `qbazaar-web`.

## Run it

```bash
cd tools/parity
npm install
export QB_DEMO_EMAIL=...                        # demo account, from the environment only
export QB_DEMO_PASSWORD=...                    # never write this into a file
npm run gate -- --target https://qbazaar.qa
```

**Signed-in pages need the demo account.** `QB_DEMO_EMAIL` and `QB_DEMO_PASSWORD` are read from the environment only
(never a flag, a file or a log line); both must be set. Without them the signed-in pages (`login: true` in `pages.json`: account,
wallet, messages, notifications, the sell and order pages), their scenarios, their sweeps and the drawer flows are listed under
"Skipped" with the reason. When they are set but the login does not leave `/login`, the report says so under "Skipped" too.
A CI job or a fix agent that verifies signed-in pages must pass both variables through its environment (a secret in CI).

**Ad-hoc probes and scripts** must set the `NEXT_LOCALE` cookie or the site answers in Arabic (its default). Use the gate's own context:

```js
const { launch, targetContext, open } = require('./src/browser');
const browser = await launch({ chromeChannel: 'chrome' });
const context = await targetContext(browser, { target: 'https://qbazaar.qa' }); // locale 'en' unless asked
await open(await context.newPage(), 'https://qbazaar.qa/');
```

| flag | default | meaning |
|---|---|---|
| `--target <url>` | required | the site under test: `https://qbazaar.qa` or a local `next start` |
| `--reference <url\|path>` | `file:///D:/Doc/q-bazaar/` | the design; the GitHub Pages URL works too |
| `--pages a,b` | all in `pages.json` | page ids |
| `--viewports 1440,744,390` | all three | hover states are captured at 1440 only (touch viewports have no hover) |
| `--locale en\|ar` | `en` | `ar` runs the RTL mirroring check only (no reference involved; scenarios, flows and the sweep are skipped) |
| `--out <dir>` | `out/<timestamp>` | `report.json`, `report.md`, `diffs/` |
| `--skip-pages` / `--skip-scenarios` / `--skip-flows` | off | leave out the page audit, the behaviour scenarios or the navigation flows |
| `--only id,id,flows` | all | restrict scenarios by id; `flows` selects the navigation flows |
| `--verbose` | off | per-component timings and scenario observation counts |

The exit code is 1 when there are findings, 2 when the run itself broke.

Chrome is launched with `channel: 'chrome'`. Pages load with `waitUntil: 'load'` plus explicit waits, DPR 1,
`prefers-reduced-motion: no-preference` (motion is under test) and a hidden caret.

When the target is `localhost`, requests to `https://api.qbazaar.qa` are proxied through Playwright and
answered with CORS headers, so a local build can read the live API.
Scenarios marked `writes: true` (they would post, offer or order) run only against a local target and are listed under "Skipped" otherwise.

## What it measures

1. **Text matching.** Every visible text on a reference page is paired with the same text on the target (nearest page position wins).
   Typography is compared on the text element; interactive texts (inside a button, link or tab) are compared with their whole subtree.
2. **Components.** `components.json` names components with a reference and a target selector. `targets.json` maps ids of the
   reference inventory (`spec/*.json`) to target selectors, so the inventory's own selectors drive the reference side.
   Unmatched components are reported as `missing`.
3. **States.** `default`, `:hover` (element, ancestors and the whole subtree), `:focus-visible` and `:active` through
   `CSS.forcePseudoState`. A state reports only what differs beyond the default. When the reference reacts to a state and the target does not,
   one `state-change` finding says so.
4. **Properties.** colour, background, borders per side, radius, shadow, outline, opacity, transform (a state's transform is read as the
   move it adds), filter, cursor, font family and the *rendered* font (`CSS.getPlatformFontsForNode`), size, weight, line height,
   letter spacing, transform/decoration, padding, margin, gap, width/height (fixed-size components only), display, visibility, z-index (overlays).
   Tolerances: colours and fonts exact, sizes +-0.5px, letter spacing +-0.15px, times +-10ms.
5. **Motion.** Transition and animation signatures, `@keyframes` content (read through CDP, so `file://` stylesheets work and the reference
   `file:line` is known), and for JS-driven motion a 25ms frame sequence compared for duration and easing shape.
6. **Scenarios** (`scenarios/*.js`). The same steps run in lockstep on both sides; each `observe` compares existence, text, ARIA state,
   styles and a screenshot crop. Written with the small DSL in `scenarios/dsl.js`.
7. **Flows.** `spec/*.json` lists every click that navigates between reference pages. The matching target element (same text, same
   occurrence, else any link to the destination route) is clicked and must land on the route `pages.json` maps the destination page to.
   Breadcrumbs are matched by their place in the trail (their labels are data). Drawer links run signed in with the target's menu opened
   first (the prototype's drawer is the demo user's menu); footer accordions are opened first. Log-out controls are checked for presence
   and never clicked (that would end the run's session); clicks that save data (Save Search) run only against a local target.
8. **Pixels.** Component crops on both sides, images / video masked, compared with pixelmatch. Anything above 2% mismatch is reported with a diff PNG.
9. **Icons.** Each icon pair is rasterised and compared (shape score, rendered size, rendered stroke, ink colour). The reference icon is named from
   `reference-icons/` (e.g. `add-ads-pin`). `reference-icons/index.json` lists every glyph with its stroke width, size and the pages that use it.
10. **RTL** (`--locale ar`). `dir="rtl"` must be set, and controls that share a row in English must swap places in Arabic (widths differ between languages, so
    only the order is compared). Pairs that wrap onto two lines in Arabic, and links set inside one sentence of copy (their order follows each
    language's sentence), have no order to compare. Scenarios do not run in Arabic: their selectors name English copy and the reference has no Arabic side.

## Reading report.json

```jsonc
{ "meta": {...}, "totals": {...}, "skipped": [{ "what": "...", "reason": "..." }], "referenceOnly": [...],
  "accepted": [{ "rule": "focus-ring", "reason": "...", "count": 52, "pages": [...], "examples": [...] }],
  "findings": [{
    "id": "a1b2c3d4e5f6",        // stable: page|viewport|locale|component|state|property
    "page": "index", "viewport": 1440, "locale": "en",
    "component": "category-tile > t:cars", "state": "hover",
    "category": "typography|colour|spacing|states|motion|behaviour|icons|flows|pixels|missing|rtl",
    "severity": "high|medium|low",
    "property": "font-size", "ref": "20px", "target": "16px",
    "source": "assets/styles.css:123 (.qb-card)",   // reference file:line when known
    "diff": "diffs/index/header-1440-diff.png",     // pixel findings
    "note": "..." }] }
```

How a fix agent should use it:

1. Filter by `page` and `category`, start with `severity: high`.
2. `ref` is what the design computes, `target` what the site computes. `source` points at the reference rule that produces the value.
3. **Check Figma before moving the site toward the reference.** The prototype drifts from Figma in places (see the sweep notes
   in the PR); for those the owner's rule is: the reference decides, unless the page is missing from the HTML.
4. Fix, then re-run only the page: `npm run gate -- --target <url> --pages index --viewports 1440`. A fixed finding disappears; its id never changes
   for the same component/state/property, so ids can be tracked across runs.
5. `missing` findings mean the component or text does not exist on the target. `flows` findings mean the click does not reach the mapped route.
6. Do not edit `reference-icons/` while fixing: import the SVGs from there.

## Layout

```
src/        config, browser, capture (CDP), inpage (browser-side code), matching, compare, unit, motion, icons,
            iconLibrary, pixels, scenarios, flows, rtl, audit (page orchestrator), report, cli
scenarios/  behaviour scenarios + dsl.js
spec/       trimmed reference behaviour inventories, one file per stream (scripts/import-spec.js regenerates them)
reference-icons/  the deduplicated reference SVG icon library + index.json
pages.json  reference page <-> target route, login need, aliases; reference pages with no route
components.json, targets.json  component selectors
test/       unit tests: npm test
```

## Tuning notes (what was damped and why)

See the PR description for the list of noisy rules and how each was damped (borders without width, undrawn outlines, transparent shadow stacks,
modern colour spaces, pill radii, translate-only transforms, same-box padding/gap/line-height, typography on text-less elements, cursor on non-interactive
elements, per-state repeats of default diffs).

Second round, from the triage of the first full report:

- **Accepted differences** are counted once per run under `accepted` (report.json) and "Accepted differences" (report.md), never per element:
  - `focus-ring`: the reference declares no focus style (the browser draws its own `auto` ring) and the owner keeps our accessible ring.
    On focus, outline, ring shadow and border colour are compared only where the reference itself changes them (the auth inputs' border colour).
  - `reference-demo-state` / `reference-sample-data`: texts the prototype shows for its demo user (the FA avatar, the guest header on signed-in
    pages, the EN pill) or as sample data (cities, sample categories, seller and chat names). The list, with the pages each entry applies to,
    is `reference-text.json`.
- **Template wrappers.** The prototype's engine wraps interpolated values in `span.sc-interp`. When such a wrapper is all a chip holds
  (a city pill), box properties (background, border, radius, padding, shadow, size, transition) are read from the chip, on the target too
  when its text sits the same way; text properties stay on the text element.
- **Text pairing** assigns the closest same-text pairs first, so a text shown twice (a city pill and an ad's location) pairs by position,
  not by document order.
- **Native controls.** A checkbox or radio draws no text, so its font and colour are not compared; the reference's unstyled controls report
  the browser's Arial, 13.33px and default cursor, which are not design values.
- **Shadows** with no offset, blur or spread draw nothing whatever their colour.
- **Keyframes** pair with the animations that run on the target (a page fade with a page-sized wrapper), not with any keyframes the target
  defines; all gaps of a page are one finding.
- **Sweep twins.** A native checkbox or radio pairs with the target's control (or the box painted for a hidden one), not its label; every
  sweep finding notes the target twin's markup. A reference element that is not one of its kind's controls (a list card's heart drawn as a
  styled element) pairs with the target control nearest to where it sits, not the first one on the page.
- Scenario steps are retried once after a short pause before a failure is reported (the reference's own pages settle late at times).

Third round, from the live re-run of the tuned gate:

- **Radii** are capped by the box as laid out, before transforms: the home city pills are tilted, and a tilted pill's bounding rect is taller
  than the pill.
- **Margins and gaps.** A margin on a child and a flex/grid gap on its parent draw the same space, so a margin difference counts only when
  the space each element keeps in its parent's flow (margin plus the gap after it) differs.
- **Texts inside components** are compared in their states only; their resting differences (a city label's gap, its font size) showed up again
  under hover and focus. They are now reported once, at rest, and not at all when the component already reported the same descendant with the same values.
- **Scenario copy** drops icons the reference draws as text characters (✕, ›) and masks data with an observation's `textMask` (the share link);
  the hero category panel's names are API data, so its text is not compared (its count and crop are).
- **Flows** wait for the URL to change (up to 8s) instead of a fixed pause, open the footer accordions and wait for their transition before
  looking for a link, and try a refused click once more on a fresh page.
- **Observations** that find their element on one side only look once more after the page settles (a drawer still sliding in), and one that a
  client-side navigation interrupted is read again. Text kept for screen readers only is not compared as copy. A `textMask` can also be a
  function: the gallery counter compares its place in the set (first, #2, last), not the sample ad's photo count.
- **Demo-user state.** The drawer content and the favourite toggle run signed in, like the drawer flows (the prototype is the demo user's
  session; a guest's heart sends them to /login by design). The toggle saves a favourite, so it runs only against a local target.
- **Sweep.** The prototype numbers its template nodes per layout, so an inventory id can name an icon at another width; such elements, and ones
  the prototype re-rendered before the compare, are listed as warnings instead of being compared.

## CI

CI can run the gate against the GitHub Pages reference (`--reference https://qbazzar.github.io/Qbazaar-front/`) and a built `next start` target
(localhost, so the CORS harness applies). No CI job is added yet.
