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

| flag | default | meaning |
|---|---|---|
| `--target <url>` | required | the site under test: `https://qbazaar.qa` or a local `next start` |
| `--reference <url\|path>` | `file:///D:/Doc/q-bazaar/` | the design; the GitHub Pages URL works too |
| `--pages a,b` | all in `pages.json` | page ids |
| `--viewports 1440,744,390` | all three | hover states are captured at 1440 only (touch viewports have no hover) |
| `--locale en\|ar` | `en` | `ar` runs the RTL mirroring check only (no reference involved) |
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
8. **Pixels.** Component crops on both sides, images / video masked, compared with pixelmatch. Anything above 2% mismatch is reported with a diff PNG.
9. **Icons.** Each icon pair is rasterised and compared (shape score, rendered size, rendered stroke, ink colour). The reference icon is named from
   `reference-icons/` (e.g. `add-ads-pin`). `reference-icons/index.json` lists every glyph with its stroke width, size and the pages that use it.
10. **RTL** (`--locale ar`). The Arabic page must mirror the English page (each control sits at the reflected x position), `dir="rtl"` must be set,
    and scenario facts (existence, ARIA state) must hold.

## Reading report.json

```jsonc
{ "meta": {...}, "totals": {...}, "skipped": [{ "what": "...", "reason": "..." }], "referenceOnly": [...],
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

## CI

CI can run the gate against the GitHub Pages reference (`--reference https://qbazzar.github.io/Qbazaar-front/`) and a built `next start` target
(localhost, so the CORS harness applies). No CI job is added yet.
