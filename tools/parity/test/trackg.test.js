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
const { selectPages, referenceOnly, writeSkipReason, liveDataRule } = require('../src/pages');
const { loadScenarios } = require('../scenarios');
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

// The hop reads list items' own margins: a staggered row of pills sets them by place, and sample cities sit in
// another order on the target.
test('a list item at another place in its list has its margin counted under list-place, at the same place compared', async () => {
  const { placesDiffer, listPlaceRule } = require('../src/accepted');
  const pill = (index, margin, extra = {}) => ({ key: 't:lusail#0', kind: 'text', rect: rect(90, 53), ownText: true, styles: styles({ margin: `${margin}px 0px 0px 0px`, 'margin-flow': `${margin}px 12px 0px 0px`, ...extra }), place: { index, count: 9 } });
  assert.equal(placesDiffer(pill(1, 8), pill(2, 0)), true);
  assert.equal(placesDiffer(pill(2, 8), pill(2, 0)), false);
  assert.equal(placesDiffer({ place: { index: 0, count: 2 } }, { place: { index: 1, count: 2 } }), false);
  assert.equal(listPlaceRule(pill(1, 8), pill(2, 0), { property: 'margin' }), 'list-place');
  assert.equal(listPlaceRule(pill(1, 8), pill(2, 0), { property: 'padding' }), null);
  const findings = new Findings();
  const probe = (node) => unitProbe({ descendants: [node] });
  // Lusail second in the reference row, third on the target: its margin is the pattern's, counted, not dropped.
  await new UnitComparer({ ref: probe(pill(1, 8)), target: probe(pill(2, 0)), findings, base: BASE })
    .compare({ name: 'city-pills', refSel: 'a', tgtSel: 'a', icons: false, states: false });
  assert.deepEqual(findings.list, []);
  assert.deepEqual(findings.accepted.map((a) => [a.rule, a.count, a.examples[0].component, a.examples[0].ref, a.examples[0].target]),
    [['list-place', 1, 'city-pills > t:lusail', '8px 0px 0px 0px', '0px 0px 0px 0px']]);
  // Only the margin follows the place: the pill's colour at another place is still a finding.
  const coloured = new Findings();
  await new UnitComparer({ ref: probe(pill(1, 8)), target: probe(pill(2, 0, { color: 'rgb(0, 0, 0)' })), findings: coloured, base: BASE })
    .compare({ name: 'city-pills', refSel: 'a', tgtSel: 'a', icons: false, states: false });
  assert.deepEqual(coloured.list.map((f) => f.property), ['color']);
  // At the same place the margins are compared.
  const same = new Findings();
  await new UnitComparer({ ref: probe(pill(2, 8)), target: probe(pill(2, 0)), findings: same, base: BASE })
    .compare({ name: 'city-pills', refSel: 'a', tgtSel: 'a', icons: false, states: false });
  assert.deepEqual(same.list.map((f) => f.property), ['margin']);
  assert.deepEqual(same.accepted, []);
  // A box outside the placeLists has no place (inpage.js): a divider at another index in its card is compared.
  const divider = (index) => ({ ...pill(index, index ? 22 : 0), key: 't:or continue with#0', place: null });
  const card = new Findings();
  await new UnitComparer({ ref: probe(divider(3)), target: probe(divider(0)), findings: card, base: BASE })
    .compare({ name: 'auth-card', refSel: 'a', tgtSel: 'a', icons: false, states: false });
  assert.deepEqual(card.list.map((f) => [f.component, f.property]), [['auth-card > t:or continue with', 'margin']]);
  assert.deepEqual(card.accepted, []);
});

// The places opt in per side: each probe hands its side's lists to the in-page reader, and only the city pills are listed.
test('only the lists components.json names in placeLists give their items a place, on each side', async () => {
  const { Probe } = require('../src/capture');
  const { placeLists } = require('../components.json');
  assert.deepEqual([placeLists.reference, placeLists.target], [['.qb-places'], ['section[aria-labelledby="home-places"] ul']]);
  const sent = [];
  const page = { evaluate: async (fn, arg) => { sent.push(arg); return null; } };
  await new Probe(page, null, 'reference').read('a', { descendants: true, hop: true });
  await new Probe(page, null, 'target').read('a');
  await new Probe(page, null, 'elsewhere').read('a');
  assert.deepEqual(sent.map((a) => a.placeLists), [placeLists.reference, placeLists.target, []]);
  assert.deepEqual(sent.map((a) => [a.descendants, a.hop]), [[true, true], [false, false], [false, false]]);
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
  assert.equal(arabicFontRule('العربية', kufi), 'arabic-font');
  assert.equal(arabicFontRule('English', kufi), null);
  assert.equal(arabicFontRule('العربية', { property: 'color', ref: 'a', target: 'Noto Kufi' }), null);
  // Through the comparer: an Arabic language row's font is accepted, its colour is not.
  const row = (extra) => ({ rect: rect(), tag: 'button', text: 'العربية', ownText: true, ownCopy: 'العربية', styles: styles(extra), descendants: [] });
  const findings = new Findings();
  const probe = (node, font) => ({ ...unitProbe(node), renderedFonts: async () => font });
  await new UnitComparer({ ref: probe(row({ color: 'rgb(75, 75, 75)' }), 'Segoe UI'), target: probe(row({ color: 'rgb(33, 33, 33)' }), 'Noto Kufi Arabic'), findings, base: BASE })
    .compare({ name: 'text:العربية', refSel: 'a', tgtSel: 'a', icons: false, states: false, descendants: false });
  assert.deepEqual(findings.list.map((f) => f.property), ['color']);
  assert.equal(findings.accepted[0].rule, 'arabic-font');
});

// The rule reads the text an element writes itself and the font it asks for first, never the text of a whole unit.
test('a Latin text in a container that also holds Arabic is still compared, and so is a Latin font ahead of Noto Kufi', async () => {
  const rendered = (ref, target) => ({ property: 'rendered-font', ref, target });
  const family = (target) => ({ property: 'font-family', ref: 'Segoe UI, sans-serif', target });
  assert.equal(arabicFontRule('English العربية', rendered('Segoe UI', 'Noto Kufi Arabic')), null);
  // Digits next to Arabic letters stay in the reference's Latin font; a font only the target drew is a finding.
  assert.equal(arabicFontRule('منذ 3 أيام', rendered('Poppins + Segoe UI', 'Noto Kufi Arabic + Poppins')), 'arabic-font');
  assert.equal(arabicFontRule('منذ 3 أيام', rendered('Arial + Segoe UI', 'Noto Kufi Arabic + Poppins')), null);
  assert.equal(arabicFontRule('العربية', family('"notoKufiArabic", poppins, sans-serif')), 'arabic-font');
  assert.equal(arabicFontRule('العربية', family('montserrat, notoKufiArabic, "montserrat Fallback", sans-serif')), null);

  // A language panel: its own heading is Latin, its rows are English and Arabic.
  const text = (copy, font) => ({ key: `t:${copy.toLowerCase()}#0`, kind: 'text', rect: rect(), ownText: true, ownCopy: copy, styles: styles({ 'font-family': font }) });
  const panel = (font, rows) => ({ text: 'Choose your language English العربية', ownText: true, ownCopy: 'Choose your language', styles: styles({ 'font-family': font }), descendants: rows });
  const ref = panel('Poppins, sans-serif', [text('English', 'Poppins, sans-serif'), text('العربية', 'Segoe UI, sans-serif')]);
  const target = panel('montserrat, notoKufiArabic, sans-serif', [text('English', 'notoKufiArabic, poppins, sans-serif'), text('العربية', 'notoKufiArabic, poppins, sans-serif')]);
  const probe = (node, font) => ({ ...unitProbe(node), renderedFonts: async () => font });
  const findings = new Findings();
  await new UnitComparer({ ref: probe(ref, 'Poppins'), target: probe(target, 'Montserrat + Noto Kufi Arabic'), findings, base: BASE })
    .compare({ name: 'language-panel', refSel: 'a', tgtSel: 'a', icons: false, states: false });
  assert.deepEqual(findings.list.map((f) => [f.component, f.property]), [
    ['language-panel', 'font-family'], ['language-panel', 'rendered-font'], ['language-panel > t:english', 'font-family'],
  ]);
  assert.deepEqual(findings.accepted.map((a) => [a.rule, a.count, a.examples[0].component]), [['arabic-font', 1, 'language-panel > t:العربية']]);
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

test('scenarios: only the first observation after Escape may find the target closed', async () => {
  const { press, observe } = require('../scenarios/dsl');
  const modal = { ref: '.modal', target: '[role=dialog]' };
  const hidden = { text: 'skip', styles: false };
  const twice = await runSteps([press('Escape'), observe('closed-by-escape', modal, hidden), observe('still-open', modal, hidden)]);
  assert.deepEqual(twice.accepted.map((a) => [a.rule, a.count]), [['escape-key', 1]]);
  assert.deepEqual(twice.list.map((f) => [f.component, f.property, f.ref, f.target]), [['scenario:x:still-open', 'exists', 'visible', 'not visible']]);
});

test('scenarios: the language list accepts fewer rows than the reference, never more, and keeps its heading', async () => {
  const { observe, languageList } = require('../scenarios/dsl');
  const rows = { ref: '.modal', target: '[role=dialog]' };
  const counted = { count: true, text: 'skip', styles: false, accepted: languageList };
  const fewer = await runSteps([observe('option-rows', rows, counted)], { refCount: 9, targetCount: 2 });
  assert.deepEqual(fewer.list, []);
  assert.deepEqual(fewer.accepted.map((a) => [a.rule, a.examples[0].property, a.examples[0].ref, a.examples[0].target]), [['svg-flags', 'count', '9', '2']]);
  const more = await runSteps([observe('option-rows', rows, counted)], { refCount: 2, targetCount: 3 });
  assert.deepEqual(more.list.map((f) => [f.property, f.ref, f.target]), [['count', '2', '3']]);
  assert.deepEqual(more.accepted, []);
  // A count rule left as a plain flag accepts nothing.
  const flag = await runSteps([observe('option-rows', rows, { ...counted, accepted: { ...languageList, count: true } })], { refCount: 9, targetCount: 2 });
  assert.deepEqual(flag.list.map((f) => f.property), ['count']);
  // The heading is still compared next to an accepted count.
  const renamed = await runSteps([observe('panel', rows, { count: true, styles: false, accepted: languageList })], {
    refCount: 9, targetCount: 2, refText: 'Choose your language English Arabic', targetText: 'Pick a language English',
  });
  assert.deepEqual(renamed.list.map((f) => f.property), ['text']);
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
test('auth steps that send a code or a mail run only against a local target, live-data elements are named per page', () => {
  const ids = selectPages({ pages: null, viewports: [{ width: 1440 }] }).map((p) => p.id);
  // enter-code is the code step of /verify-otp, which sends a one-time code on open: compared on a local target only.
  const [enterCode] = selectPages({ pages: ['enter-code'], viewports: [{ width: 1440 }] });
  assert.equal(enterCode.route, '/verify-otp?phone=%2B97455512345');
  assert.match(writeSkipReason(enterCode, { localTarget: false }), /runs only against a local target/);
  assert.equal(writeSkipReason(enterCode, { localTarget: true }), null);
  assert.equal(writeSkipReason(selectPages({ pages: ['login'], viewports: [{ width: 1440 }] })[0], { localTarget: false }), null);
  // send-code is the sent state of /forgot-password (a scenario that submits), verify-identity has no route of its own.
  for (const id of ['verify-identity', 'send-code']) {
    assert.ok(!ids.includes(id));
    assert.ok(referenceOnly().some((r) => r.reference === `${id}.html`));
  }
  const sentScenario = loadScenarios().find((s) => s.id === 'forgot-password-sent');
  assert.equal(sentScenario.page, 'forgot-password');
  assert.equal(sentScenario.writes, true);
  assert.equal(selectPages({ pages: ['signup-verify'], viewports: [{ width: 1440 }] })[0].route, '/verify-otp?phone=%2B97455512345&intro=1');
  assert.equal(liveDataRule('saved-search', 'ss.notif.on').items, 'main article');
  assert.equal(liveDataRule('saved-search', 'ss.crumb.home'), null);
  assert.equal(liveDataRule('index', 'ca.list.card'), null);
});

// The page audit's missing cards: the category's ad card is a liveData element, skipped only while the list is empty.
test('a live-data component is a warning while the target lists no items, and a finding once it lists some', async () => {
  const { componentsFor, componentUnits, reportMissingComponents } = require('../src/audit');
  const { liveDataSkipReason } = require('../src/pages');
  const found = { refFound: [{ id: 'ca.list.card~0', name: 'ca.list.card', index: 0 }], tgtFound: [] };
  const { missing } = componentUnits(componentsFor('category'), found, {}, 1440);
  assert.deepEqual(missing.map((m) => m.name), ['ca.list.card']);
  const run = async (listsItems) => {
    const env = { warnings: [] };
    const emitted = [];
    await reportMissingComponents(env, { evaluate: async () => listsItems }, { page: 'category', viewport: 1440 }, missing, (f) => emitted.push(f));
    return { warnings: env.warnings, emitted };
  };
  const empty = await run(false);
  assert.deepEqual(empty.emitted, []);
  assert.deepEqual(empty.warnings.map((w) => w.what), ['component ca.list.card on category@1440']);
  assert.match(empty.warnings[0].reason, /live ads in this category/);
  const listed = await run(true);
  assert.deepEqual(listed.warnings, []);
  assert.deepEqual(listed.emitted.map((f) => [f.component, f.property, f.severity]), [['ca.list.card', 'missing-component', 'high']]);
  // An element no liveData rule names never asks the page.
  let asked = false;
  assert.equal(await liveDataSkipReason({ evaluate: async () => { asked = true; return false; } }, 'index', 'ca.list.card'), null);
  assert.equal(asked, false);
});

// A signed-in page that lands on /login lost the demo session: sign in again and reopen, never compare the login form.
test('a signed-in page bounced to /login signs in again once; a failed sign-in is a skip, not findings', async () => {
  const browserTools = require('../src/browser');
  const { Probe } = require('../src/capture');
  const { openSide, onLoginPage, SessionLostError } = require('../src/sides');
  assert.ok(onLoginPage('https://qbazaar.qa/login?next=%2Fpost-ad'));
  assert.ok(onLoginPage('https://qbazaar.qa/ar/login'));
  assert.ok(!onLoginPage('https://qbazaar.qa/post-ad'));
  assert.ok(!onLoginPage('https://qbazaar.qa/login-help'));
  assert.equal(new SessionLostError().skipReason, new SessionLostError().message);

  const saved = { newContext: browserTools.newContext, open: browserTools.open, loginDemo: browserTools.loginDemo, attach: Probe.attach };
  let signIns = 0;
  let freshState = { cookies: ['fresh'] };
  // The stale state lands on /login, the fresh one on the page.
  browserTools.newContext = async (_browser, _config, _viewport, { storageState }) => ({ newPage: async () => ({ storageState }), close: async () => {} });
  browserTools.open = async (page, url) => ({ status: 200, url: page.storageState && page.storageState.cookies[0] === 'fresh' ? url : 'https://qbazaar.qa/login' });
  browserTools.loginDemo = async () => { signIns += 1; return freshState; };
  Probe.attach = async () => ({});
  const pageDef = { id: 'add-ads', reference: 'add-ads.html', route: '/post-ad', login: true };
  const viewport = { width: 1440, height: 900 };
  try {
    const env = { browser: {}, config: { target: 'https://qbazaar.qa', reference: 'file:///ref/', locale: 'en' }, storageState: { cookies: ['stale'] } };
    const side = await openSide(env, 'target', pageDef, viewport);
    assert.equal(signIns, 1);
    assert.equal(side.landed.url, 'https://qbazaar.qa/post-ad');
    assert.equal(side.loggedIn, true);
    assert.ok(!side.sessionLost);
    assert.deepEqual(env.storageState, freshState);

    // The sign-in fails too: the side is marked, the state is dropped (later signed-in pages are skipped).
    freshState = null;
    env.storageState = { cookies: ['stale'] };
    const lost = await openSide(env, 'target', pageDef, viewport);
    assert.equal(signIns, 2);
    assert.equal(lost.sessionLost, true);
    assert.equal(lost.loggedIn, false);
    assert.equal(env.storageState, null);

    // The login page itself and the reference side never sign in again.
    env.storageState = { cookies: ['stale'] };
    await openSide(env, 'target', { id: 'login', reference: 'login.html', route: '/login', login: false }, viewport);
    await openSide(env, 'reference', pageDef, viewport);
    assert.equal(signIns, 2);
  } finally {
    Object.assign(browserTools, { newContext: saved.newContext, open: saved.open, loginDemo: saved.loginDemo });
    Probe.attach = saved.attach;
  }
});
