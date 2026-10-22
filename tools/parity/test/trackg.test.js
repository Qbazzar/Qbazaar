// Track G (final gate re-run): the gate artefacts of the 2026-10-21 re-run stay away, the owner-accepted differences
// are counted once under "Accepted differences", and the real differences next to them are still reported.
const test = require('node:test');
const assert = require('node:assert/strict');
const { diffStyles } = require('../src/compare');
const { Findings } = require('../src/findings');
const { UnitComparer, ownerView } = require('../src/unit');
const { pairDescendants, referenceOnlyText, withKeptCopy } = require('../src/matching');
const { compareKeyframes } = require('../src/motion');
const { arabicFontRule, flagRule } = require('../src/accepted');
const { namesAnotherControl, routeMatches, CATEGORY_PAGES } = require('../src/flows');
const { selectPages, referenceOnly, liveDataRule } = require('../src/pages');
const { copyOf } = require('../src/scenarios');
const { languageList } = require('../scenarios/dsl');

const styles = (extra = {}) => ({
  color: 'rgb(33, 33, 33)', 'background-color': 'rgba(0, 0, 0, 0)', 'background-image': 'none',
  'border-width': '0px 0px 0px 0px', 'border-style': 'none none none none', 'border-color': 'rgb(33, 33, 33) rgb(33, 33, 33) rgb(33, 33, 33) rgb(33, 33, 33)',
  'border-radius': '0px 0px 0px 0px', 'box-shadow': 'none', 'outline-width': '0px', 'outline-style': 'none', 'outline-color': 'rgb(0, 0, 0)', 'outline-offset': '0px',
  opacity: '1', transform: 'none', filter: 'none', cursor: 'auto', 'font-family': 'Poppins, sans-serif', 'font-size': '16px', 'font-weight': '400', 'font-style': 'normal',
  'line-height': '24px', 'letter-spacing': '0px', 'text-transform': 'none', 'text-decoration-line': 'none', padding: '0px 0px 0px 0px', margin: '0px 0px 0px 0px',
  'margin-flow': '0px 0px 0px 0px', gap: 'normal normal', width: 'auto', height: 'auto', display: 'block', visibility: 'visible', 'z-index': 'auto',
  'transition-property': 'all', 'transition-duration': '0s', 'transition-timing-function': 'ease', 'transition-delay': '0s',
  'animation-name': 'none', 'animation-duration': '0s', 'animation-timing-function': 'ease', 'animation-iteration-count': '1', 'animation-delay': '0s',
  ...extra,
});
const BASE = { page: 'index', viewport: 744, locale: 'en' };
const rect = (w = 100, h = 25) => ({ x: 0, y: 0, w, h });
const boxOf = (tag, extra, w = 704, h = 57) => ({ tag, rect: rect(w, h), styles: { ...styles(), ...extra } });

// G-sc-interp-hop: the prototype's text sits in span.sc-interp, its box is the heading around it.
test('a text in a template wrapper is measured with the box that owns it, on each side', () => {
  const heading = { padding: '16px 2px 16px 2px', cursor: 'pointer' };
  const ref = { key: 't:information#0', kind: 'text', rect: rect(96, 25), styles: styles({ cursor: 'pointer' }), ownText: true, templateWrapper: true, box: boxOf('h3', heading) };
  const target = { key: 't:information#0', kind: 'text', rect: rect(704, 57), styles: styles(heading), ownText: true };
  const [r, t] = ownerView(ref, target);
  assert.deepEqual(diffStyles(r.styles, t.styles, { kind: 'text', ownText: true }), []);
  // A real difference in the owning box is still reported: the target heading keeps less padding.
  const tighter = { ...target, styles: styles({ ...heading, padding: '12px 2px 12px 2px' }) };
  const [r2, t2] = ownerView(ref, tighter);
  assert.deepEqual(diffStyles(r2.styles, t2.styles, { kind: 'text', ownText: true }).map((d) => [d.property, d.ref, d.target]), [['padding', '16px 2px 16px 2px', '12px 2px 12px 2px']]);
  // The footer heading at 1440: margin on the reference h3, on the target h2 itself.
  const refWide = { ...ref, box: boxOf('h3', { margin: '0px 0px 20px 0px', 'margin-flow': '0px 0px 20px 0px' }) };
  const targetWide = { ...target, styles: styles({ margin: '0px 0px 20px 0px', 'margin-flow': '0px 0px 20px 0px' }) };
  assert.deepEqual(diffStyles(...ownerView(refWide, targetWide).map((n) => n.styles), { kind: 'text', ownText: true }), []);
});

// G-hidden-sizer-pairing: a combobox whose label sits in a span next to an invisible sizer.
test('a box that writes its text through one element is read with that element\'s typography', () => {
  const ref = { rect: rect(210, 52), styles: styles({ color: 'rgb(189, 189, 189)', 'font-size': '20px', padding: '10px 14px 10px 14px' }), ownText: true, descendants: [] };
  const label = { tag: 'span', styles: { color: 'rgb(189, 189, 189)', 'font-size': '20px' } };
  const target = { rect: rect(210, 52), styles: styles({ padding: '10px 14px 10px 14px' }), ownText: false, textCarrier: label, descendants: [] };
  const [r, t] = ownerView(ref, target);
  assert.deepEqual(diffStyles(r.styles, t.styles, { ownText: true }), []);
  // The label really drawn in another colour is still a finding.
  const dark = { ...target, textCarrier: { tag: 'span', styles: { ...label.styles, color: 'rgb(33, 33, 33)' } } };
  assert.deepEqual(diffStyles(...ownerView(ref, dark).map((n) => n.styles), { ownText: true }).map((d) => d.property), ['color']);
  // Both sides without own text: the descendants carry the typography, the roots are not merged.
  assert.equal(ownerView({ ...ref, ownText: false }, target)[1].ownText, false);
});

// G-keyframe-name: the prototype's qbFade and the build's minified qb-fade draw the same frames.
test('keyframes compare by their frames, whatever the name or the spacing of the sheet', () => {
  const fade = { name: 'qbFade', steps: { '0%': 'opacity: 0;transform: translatey(8px)', '100%': 'opacity: 1;transform: none' }, source: 'assets/styles.css:233' };
  const screen = { name: 'qbFade', count: 1, sample: 'div[data-screen-label="Home"]', pageLevel: true };
  const minified = { name: 'qb-fade', steps: { '0%': 'opacity:0;transform:translatey(8px)', '100%': 'opacity:1;transform:none' } };
  const running = [{ name: 'qb-fade', count: 1, sample: 'div.animate-qb-page-enter', pageLevel: true }];
  assert.deepEqual(compareKeyframes([fade], [screen], [minified], running), []);
  // Other frames under any name are still a finding.
  const deeper = { name: 'qb-fade', steps: { '0%': 'opacity:0;transform:translatey(16px)', '100%': 'opacity:1;transform:none' } };
  assert.equal(compareKeyframes([fade], [screen], [deeper], running).length, 1);
  // Declaration order follows the names once spacing is gone (transform-origin sorts before transform:).
  const origin = { ...fade, steps: { '0%': 'transform: scale(0.9);transform-origin: top', '100%': 'transform: none' } };
  const originMin = { name: 'qb-fade', steps: { '0%': 'transform-origin:top;transform:scale(0.9)', '100%': 'transform:none' } };
  assert.deepEqual(compareKeyframes([origin], [screen], [originMin], running), []);
});

// G-input-wrapper-pairing / A-signup-extra-fields: the sign-up form also asks for the full name and the phone.
test('inputs pair by field and type, so extra fields do not shift the checkbox', () => {
  const input = (i, inputType, field) => ({ key: `input#${i}`, kind: 'input', inputType, field, styles: styles(), rect: rect() });
  const placeholder = (i, of) => ({ key: `placeholder#${i}`, kind: 'placeholder', of: `input#${of}`, styles: styles(), rect: rect() });
  const ref = { descendants: [input(0, 'email', 'email'), placeholder(0, 0), input(1, 'password', 'password'), input(2, 'checkbox', 'i agree to the terms')] };
  const target = { descendants: [input(0, 'text', 'full name'), placeholder(0, 0), input(1, 'email', 'email'), placeholder(1, 1), input(2, 'tel', 'phone number'),
    input(3, 'password', 'password'), input(4, 'checkbox', 'i agree to the terms and the privacy policy')] };
  const { pairs, missing } = pairDescendants(ref, target);
  const named = Object.fromEntries(pairs.map((p) => [p.ref.key, p.target.key]));
  assert.deepEqual(named, { 'input#0': 'input#1', 'placeholder#0': 'placeholder#1', 'input#1': 'input#3', 'input#2': 'input#4' });
  assert.deepEqual(missing, []);
  // A field typed differently on each side (an email field drawn as a text input) still pairs.
  const plain = { descendants: [input(0, 'text', 'email'), input(1, 'password', 'password')] };
  assert.equal(pairDescendants({ descendants: ref.descendants.slice(0, 1) }, plain).pairs[0].target.key, 'input#0');
  // Older captures without input types keep pairing by order.
  const untyped = (d) => ({ descendants: d.descendants.map(({ inputType, field, ...rest }) => rest) });
  assert.equal(pairDescendants(untyped(ref), untyped(target)).pairs.find((p) => p.ref.key === 'input#2').target.key, 'input#2');
});

// A unit read by a probe double: root styles and descendants, and the icon lists rasteriseIcons would return.
function unitProbe(node, icons = [], drawn = 0) {
  return {
    read: async () => ({ rect: rect(704, 88), tag: 'header', native: null, text: '', ownText: false, styles: styles(), descendants: [], ...node }),
    withState: async (selector, state, read) => read(),
    page: { evaluate: async (fn) => (fn.name === 'countDrawnIcons' ? drawn : icons) },
    renderedFonts: async () => null,
    sourceOf: async () => null,
  };
}
const icon = (extra = {}) => ({ bits: '1'.repeat(48 * 48), ink: 'rgb(33, 33, 33)', width: 21, height: 21, stroke: 1.5, strokeUser: 1.7, viewBox: '24x24', flag: false, ...extra });

// G-hidden-icon-count: at phone widths the prototype pins its header icons outside <header> and draws the burger in CSS.
test('icon counts take the phone bar, CSS glyphs and flags into account', async () => {
  const findings = new Findings();
  const comparer = new UnitComparer({ ref: unitProbe({}, [icon(), icon()], 1), target: unitProbe({}, [icon(), icon(), icon()]), findings, base: BASE });
  await comparer.compare({ name: 'header', refSel: 'header', tgtSel: 'header', refExtra: '#qb-mglobe', countDrawn: true, icons: true, states: false });
  assert.deepEqual(findings.list.filter((f) => f.property === 'icon-count'), []);
  // A target flag is the accepted flag difference, not one icon more.
  const flagged = new Findings();
  await new UnitComparer({ ref: unitProbe({}, []), target: unitProbe({}, [icon({ flag: 'flag', width: 20, height: 14 })]), findings: flagged, base: BASE })
    .compare({ name: 'header', refSel: 'header', tgtSel: 'header', icons: true, states: false });
  assert.deepEqual(flagged.list, []);
  assert.equal(flagged.accepted[0].rule, 'svg-flags');
  // A glyph really missing on the target is still counted.
  const short = new Findings();
  await new UnitComparer({ ref: unitProbe({}, [icon(), icon()]), target: unitProbe({}, [icon()]), findings: short, base: BASE })
    .compare({ name: 'header', refSel: 'header', tgtSel: 'header', icons: true, states: false });
  assert.deepEqual(short.list.map((f) => [f.property, f.ref, f.target]), [['icon-count', '2', '1']]);
});

// A-auth-static-lang-pill / A-svg-flags: flag pairs are accepted, never compared as glyphs or boxes.
test('language flags are counted under their accepted rule, the static auth pill under its own', async () => {
  assert.equal(flagRule('static', 'flag'), 'auth-static-pill');
  assert.equal(flagRule(null, 'flag'), 'svg-flags');
  assert.equal(flagRule(false, false), null);
  const svg = (flag, extra) => ({ key: 'svg#0', kind: 'icon', rect: rect(15, 15), styles: styles(extra), ownText: false, flag });
  const findings = new Findings();
  const comparer = new UnitComparer({
    ref: unitProbe({ descendants: [svg('static')] }, [icon({ flag: 'static', width: 15, height: 15 })]),
    target: unitProbe({ descendants: [svg('flag', { 'border-radius': '2px 2px 2px 2px' })] }, [icon({ flag: 'flag', width: 20, height: 14 })]),
    findings, base: { ...BASE, page: 'forgot-password' },
  });
  await comparer.compare({ name: 'header', refSel: 'header', tgtSel: 'header', icons: true, states: false });
  assert.deepEqual(findings.list, []);
  assert.deepEqual(findings.accepted.map((a) => [a.rule, a.count]), [['auth-static-pill', 2]]);
});

test('Arabic text in Noto Kufi Arabic is accepted, Latin text in it is not', async () => {
  const kufi = { property: 'rendered-font', ref: 'Segoe UI', target: 'Noto Kufi Arabic' };
  assert.equal(arabicFontRule('text:العربية', kufi), 'arabic-font');
  assert.equal(arabicFontRule('text:english', kufi), null);
  assert.equal(arabicFontRule('text:العربية', { property: 'color', ref: 'a', target: 'Noto Kufi' }), null);
  // Through the comparer: an Arabic language row's font is accepted, its colour is not.
  const row = (extra) => ({ rect: rect(), tag: 'button', text: 'العربية', ownText: true, styles: styles(extra), descendants: [] });
  const findings = new Findings();
  const probe = (node, font) => ({ ...unitProbe(node), renderedFonts: async () => font });
  await new UnitComparer({ ref: probe(row({ color: 'rgb(75, 75, 75)' }), 'Segoe UI'), target: probe(row({ color: 'rgb(33, 33, 33)' }), 'Noto Kufi Arabic'), findings, base: BASE })
    .compare({ name: 'text:العربية', refSel: 'a', tgtSel: 'a', icons: false, states: false, descendants: false });
  assert.deepEqual(findings.list.map((f) => f.property), ['color']);
  assert.equal(findings.accepted[0].rule, 'arabic-font');
});

test('a component can accept named properties: sign-up\'s card is taller for its two extra fields', async () => {
  const card = (h, extra) => ({ rect: rect(716, h), tag: 'div', styles: styles({ height: `${h}px`, ...extra }), descendants: [] });
  const findings = new Findings();
  const accepted = { rule: 'signup-extra-fields', properties: ['height', 'pixel-mismatch'] };
  await new UnitComparer({ ref: unitProbe(card(734)), target: unitProbe(card(973, { 'border-radius': '20px 20px 20px 20px' })), findings, base: { ...BASE, page: 'signup' } })
    .compare({ name: 'auth-card', refSel: 'a', tgtSel: 'a', fixedSize: true, icons: false, states: false, accepted });
  assert.deepEqual(findings.list.map((f) => f.property), ['border-radius']);
  assert.deepEqual(findings.accepted.map((a) => [a.rule, a.count]), [['signup-extra-fields', 1]]);
});

// copy-5: the owner kept five strings in the phase-3 copy pass (PR #274).
test('the five kept copy strings are accepted as missing texts and in scenario copy', () => {
  const page = (id) => ({ id });
  assert.equal(referenceOnlyText('Sales Overview', page('sales-overview')).accepted, 'copy-5');
  assert.equal(referenceOnlyText('Transaction History', page('sales-overview')).accepted, 'copy-5');
  // The wallet's "Transaction History" was applied in #274 and stays under test.
  assert.equal(referenceOnlyText('Transaction History', page('wallet')), null);
  assert.equal(referenceOnlyText('No payment will be processed at this stage.', page('buy-now')).accepted, 'copy-5');
  const drawer = 'MENU Home Categories Favorites Messages Notifications Saved Search My Ads Sales Overview Account Settings Wallet Log Out Add Ads';
  const target = 'MENU Home Categories Favorites Messages Notifications Saved Search My Ads My orders Account Settings Wallet Log Out Add Ads';
  assert.equal(copyOf(withKeptCopy(drawer, page('index'))), copyOf(target));
  assert.equal(withKeptCopy('Transaction History Transaction History', page('sales-overview')), 'Your purchases Your sales');
  // Any other copy difference survives.
  assert.notEqual(copyOf(withKeptCopy(drawer.replace('Wallet', 'Purse'), page('index'))), copyOf(target));
});

test('the language list keeps its heading under test and counts its rows as the flag difference', () => {
  const ref = copyOf('Choose your language 🇬🇧 English 🇸🇦 Arabic 🇮🇳 India 🇵🇰 Urdu');
  const target = copyOf('Choose your language English العربية');
  assert.equal(languageList.text(ref), languageList.text(target));
  assert.notEqual(languageList.text(ref), languageList.text('Pick a language English'));
  assert.equal(languageList.rule, 'svg-flags');
});

// A scenario side whose observations find `counts[selector]` matches, with the given text.
function scenarioSide(name, counts, text = '') {
  const page = {
    keyboard: { press: async () => {} },
    waitForTimeout: async () => {},
    waitForLoadState: async () => {},
    evaluate: async () => {},
    url: () => 'https://qbazaar.qa/ads/1',
    locator: (selector) => {
      const located = { first: () => located, filter: () => located, click: async () => {}, evaluateAll: async () => ({ count: counts[selector] || 0, text, attributes: {}, properties: {}, focused: false }) };
      return located;
    },
  };
  return { name, page, probe: unitProbe({}), landed: { url: 'https://qbazaar.qa/ads/1' } };
}

async function runSteps(steps, { refCount = 1, targetCount = 0, refText = '', targetText = '', page = 'product' } = {}) {
  const { ScenarioRun } = require('../src/scenarios');
  const findings = new Findings();
  const env = { findings, warnings: [], config: { verbose: false, locale: 'en' } };
  const sides = { reference: scenarioSide('reference', { '.modal': refCount }, refText), target: scenarioSide('target', { '[role=dialog]': targetCount }, targetText) };
  const run = new ScenarioRun(env, { id: 'x', page }, { width: 1440 }, sides);
  for (const step of steps) await run.step(step);
  return findings;
}

test('scenarios: closing on Escape is accepted, other open/closed differences are not', async () => {
  const { press, click, observe } = require('../scenarios/dsl');
  const modal = { ref: '.modal', target: '[role=dialog]' };
  const escaped = await runSteps([press('Escape'), observe('stays-open-on-escape', modal, { text: 'skip', styles: false })]);
  assert.deepEqual(escaped.list, []);
  assert.equal(escaped.accepted[0].rule, 'escape-key');
  // Closed after a click (not Escape) where the reference stays open: a real behaviour difference.
  const clicked = await runSteps([click(modal), observe('after-click', modal, { text: 'skip', styles: false })]);
  assert.deepEqual(clicked.list.map((f) => [f.property, f.ref, f.target]), [['exists', 'visible', 'not visible']]);
  // The target staying open where the reference closed on Escape is still reported.
  const stuck = await runSteps([press('Escape'), observe('closed-by-escape', modal, { text: 'skip', styles: false })], { refCount: 0, targetCount: 1 });
  assert.deepEqual(stuck.list.map((f) => f.property), ['exists']);
});

test('scenarios: copy that differs only by a kept string or a language list is accepted', async () => {
  const { observe, languageList } = require('../scenarios/dsl');
  const drawer = { ref: '.modal', target: '[role=dialog]' };
  const kept = await runSteps([observe('drawer-open', drawer, { styles: false })], {
    page: 'index', targetCount: 1,
    refText: 'MENU Home My Ads Sales Overview Account Settings', targetText: 'MENU Home My Ads My orders Account Settings',
  });
  assert.deepEqual(kept.list, []);
  assert.equal(kept.accepted[0].rule, 'copy-5');
  const languages = await runSteps([observe('panel', drawer, { styles: false, accepted: languageList })], {
    page: 'index', targetCount: 1, refText: 'Choose your language 🇬🇧 English 🇸🇦 Arabic 🇮🇳 India', targetText: 'Choose your language English العربية',
  });
  assert.deepEqual(languages.list, []);
  assert.equal(languages.accepted[0].rule, 'svg-flags');
  const real = await runSteps([observe('drawer-open', drawer, { styles: false })], { page: 'index', targetCount: 1, refText: 'MENU Home Wallet', targetText: 'MENU Home Purse' });
  assert.deepEqual(real.list.map((f) => f.property), ['text']);
});

// G-flows-mapping.
test('flows: category crumbs reach any category page, template drift is not a missing control', () => {
  const destinations = selectPages({ pages: CATEGORY_PAGES, viewports: [{ width: 1440 }] }).map((p) => ({ path: p.route.split('?')[0], aliases: p.aliases }));
  assert.ok(destinations.some((d) => routeMatches('/c/home-and-garden', d)));
  assert.ok(!destinations.some((d) => routeMatches('/account/messages', d)));
  assert.equal(namesAnotherControl('Settings nav item "Wallet"', 'profile settings'), true);
  assert.equal(namesAnotherControl('Settings nav item "Wallet"', 'wallet'), false);
  assert.equal(namesAnotherControl('"View All →" section link (4x)', 'view all'), false);
  assert.equal(namesAnotherControl('Header avatar "FA" button', 'mt'), false);
  assert.equal(namesAnotherControl('Page wrapper', 'anything'), false);
});

// G-auth-page-mapping and live data.
test('auth steps that send a code or a mail are reference-only, live-data elements are named per page', () => {
  const ids = selectPages({ pages: null, viewports: [{ width: 1440 }] }).map((p) => p.id);
  for (const id of ['enter-code', 'verify-identity', 'send-code']) {
    assert.ok(!ids.includes(id));
    assert.ok(referenceOnly().some((r) => r.reference === `${id}.html`));
  }
  assert.equal(selectPages({ pages: ['signup-verify'], viewports: [{ width: 1440 }] })[0].route, '/verify-otp?phone=%2B97455512345&intro=1');
  assert.equal(liveDataRule('saved-search', 'ss.notif.on').items, 'main article');
  assert.equal(liveDataRule('saved-search', 'ss.crumb.home'), null);
  assert.equal(liveDataRule('index', 'ca.list.card'), null);
});
