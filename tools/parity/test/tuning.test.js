// Gate tuning: the false positives of the first full run (triage false-positives.json) stay damped,
// and the real differences next to them are still reported.
const test = require('node:test');
const assert = require('node:assert/strict');
const { diffStyles, canonicalValue } = require('../src/compare');
const { applyFocusPolicy, designedChanges } = require('../src/focus');
const { Findings } = require('../src/findings');
const { UnitComparer, chipView } = require('../src/unit');
const { referenceOnlyText, pairTexts } = require('../src/matching');
const { compareKeyframes } = require('../src/motion');
const { unmirroredPairs } = require('../src/rtl');
const { groupFlows, isDrawerFlow, isCrumbFlow } = require('../src/flows');
const { selectPages } = require('../src/pages');
const { demoCredentials, buildConfig } = require('../src/config');

const base = () => ({
  color: 'rgb(51, 51, 51)', 'background-color': 'rgba(0, 0, 0, 0)', 'background-image': 'none',
  'border-width': '1px 1px 1px 1px', 'border-style': 'solid solid solid solid', 'border-color': 'rgb(237, 237, 237) rgb(237, 237, 237) rgb(237, 237, 237) rgb(237, 237, 237)',
  'border-radius': '0px 0px 0px 0px', 'box-shadow': 'none', 'outline-width': '0px', 'outline-style': 'none', 'outline-color': 'rgb(0, 0, 0)', 'outline-offset': '0px',
  opacity: '1', transform: 'none', filter: 'none', cursor: 'auto', 'font-family': 'Poppins, sans-serif', 'font-size': '16px', 'font-weight': '400', 'font-style': 'normal',
  'line-height': '24px', 'letter-spacing': '0px', 'text-transform': 'none', 'text-decoration-line': 'none', padding: '0px 0px 0px 0px', margin: '0px 0px 0px 0px', gap: 'normal normal',
  width: '100px', height: '40px', display: 'block', visibility: 'visible', 'z-index': 'auto',
  'transition-property': 'all', 'transition-duration': '0s', 'transition-timing-function': 'ease', 'transition-delay': '0s',
  'animation-name': 'none', 'animation-duration': '0s', 'animation-timing-function': 'ease', 'animation-iteration-count': '1', 'animation-delay': '0s',
});

const TW_RING_IDLE = 'rgba(0, 0, 0, 0) 0px 0px 0px 0px, rgba(0, 0, 0, 0) 0px 0px 0px 0px, rgba(0, 0, 0, 0) 0px 0px 0px 0px, rgba(245, 130, 85, 0.2) 0px 0px 0px 0px, rgba(0, 0, 0, 0) 0px 0px 0px 0px';
const TW_RING_ON = TW_RING_IDLE.replace('0.2) 0px 0px 0px 0px', '0.2) 0px 0px 0px 2px');
const UA_RING = { 'outline-style': 'auto', 'outline-width': '1px', 'outline-color': 'rgb(16, 16, 16)' };
const OUR_RING = { 'outline-style': 'solid', 'outline-width': '2px', 'outline-color': 'rgb(211, 102, 76)', 'outline-offset': '2px' };

test('a zero-size shadow draws nothing whatever its colour; a spread ring still counts', () => {
  assert.deepEqual(diffStyles(base(), { ...base(), 'box-shadow': TW_RING_IDLE }), []);
  assert.equal(diffStyles(base(), { ...base(), 'box-shadow': TW_RING_ON })[0].property, 'box-shadow');
  assert.equal(diffStyles(base(), { ...base(), 'box-shadow': 'rgba(161, 161, 161, 0.1) 0px 4px 30px 0px' })[0].property, 'box-shadow');
  assert.equal(canonicalValue('box-shadow', TW_RING_IDLE), 'none');
});

test('user-agent defaults of unstyled native controls are not design values', () => {
  const ref = { ...base(), 'font-family': 'Arial', 'font-size': '13.3333px', cursor: 'default' };
  const target = { ...base(), 'font-family': 'poppins, notoKufiArabic, sans-serif', 'font-size': '15px', cursor: 'pointer' };
  assert.deepEqual(diffStyles(ref, target, { refNative: 'toggle', cursor: true }), []);
  assert.deepEqual(diffStyles(ref, target, { refNative: 'control', cursor: true }).map((d) => d.property), ['cursor']);
  // A reference element that is not a native control keeps its typography under test.
  assert.deepEqual(diffStyles(ref, target, { cursor: true }).map((d) => d.property), ['cursor', 'font-family', 'font-size']);
  assert.deepEqual(diffStyles({ ...ref, 'font-family': 'Poppins' }, target, { refNative: 'control' }).map((d) => d.property), []);
  assert.deepEqual(diffStyles({ ...ref, 'font-size': '14px' }, target, { refNative: 'control' }).map((d) => d.property), ['font-size']);
});

test('focus decoration the reference does not declare is accepted, a declared one is compared', () => {
  const refDefault = base();
  const refFocused = { ...base(), ...UA_RING };
  const diffs = [
    { property: 'outline', ref: 'auto 1px rgb(16, 16, 16) offset 0px', target: 'solid 2px rgb(211, 102, 76) offset 2px' },
    { property: 'box-shadow', ref: 'none', target: TW_RING_ON },
    { property: 'border-color', ref: 'x', target: 'y' },
    { property: 'background-color', ref: 'a', target: 'b' },
  ];
  const plain = applyFocusPolicy(diffs, refDefault, refFocused);
  assert.deepEqual(plain.kept.map((d) => d.property), ['background-color']);
  assert.equal(plain.accepted.length, 3);
  // The auth inputs (assets/auth.css) turn their border orange on focus: that one stays under test.
  const authFocused = { ...refFocused, 'border-color': 'rgb(243, 128, 87) rgb(243, 128, 87) rgb(243, 128, 87) rgb(243, 128, 87)' };
  assert.deepEqual(applyFocusPolicy(diffs, refDefault, authFocused).kept.map((d) => d.property), ['border-color', 'background-color']);
  assert.deepEqual(designedChanges(['outline-width', 'outline-color'], refFocused), []);
  assert.deepEqual(designedChanges(['outline-width', 'border-color'], { ...refFocused, 'outline-style': 'solid' }), ['outline-width', 'border-color']);
});

function stubProbe(defaultStyles, focusStyles) {
  let forced = false;
  return {
    read: async () => ({ rect: { x: 0, y: 0, w: 100, h: 40 }, styles: forced ? focusStyles : defaultStyles, ownText: true, tag: 'a', native: null, text: 'x', descendants: [] }),
    withState: async (selector, state, read) => { forced = true; try { return await read(); } finally { forced = false; } },
  };
}

async function focusRun(refFocus, targetFocus) {
  const findings = new Findings();
  const comparer = new UnitComparer({ ref: stubProbe(base(), { ...base(), ...refFocus }), target: stubProbe(base(), { ...base(), ...targetFocus }), findings, base: { page: 'index', viewport: 1440, locale: 'en' } });
  await comparer.compare({ name: 'text:sell', refSel: 'a', tgtSel: 'a', states: ['focus-visible'], statesOnly: true, icons: false });
  return findings;
}

test('the focus ring is counted once as accepted, not reported per element', async () => {
  const findings = await focusRun(UA_RING, { ...OUR_RING, 'box-shadow': TW_RING_ON });
  assert.deepEqual(findings.list, []);
  assert.equal(findings.accepted[0].rule, 'focus-ring');
  assert.equal(findings.accepted[0].count, 2);
  // A target with no focus change at all is not reported either: the reference only has the browser's ring.
  assert.deepEqual((await focusRun(UA_RING, {})).list, []);
});

test('a real focus difference next to the ring is still reported', async () => {
  const findings = await focusRun(UA_RING, { ...OUR_RING, 'background-color': 'rgb(250, 250, 250)' });
  assert.deepEqual(findings.list.map((f) => f.property), ['background-color']);
});

test('demo state and sample data texts are recognised per page', () => {
  const page = (id, login = false) => ({ id, login });
  assert.equal(referenceOnlyText('FA', page('index')).id, 'demo-state');
  assert.equal(referenceOnlyText('Sign Up', page('wallet', true)).id, 'demo-state');
  assert.equal(referenceOnlyText('Sign Up', page('index')), null);
  assert.equal(referenceOnlyText('al  rayyan', page('category')).id, 'sample-data');
  assert.equal(referenceOnlyText('Al Rayyan', page('index')), null);
  assert.equal(referenceOnlyText('Browse Categories', page('category')), null);
});

test('keyframes pair with what runs on the target, page fades with page-sized wrappers', () => {
  const fade = { name: 'qbFade', steps: { '0%': 'opacity: 0;transform: translatey(8px)', '100%': 'opacity: 1;transform: none' }, source: 'assets/styles.css:233' };
  const swipe = { name: 'swipe-out-left', steps: { '0%': 'opacity:1;transform:none', '100%': 'opacity:0;transform:translatex(-100%)' }, source: 'inline' };
  const screen = { name: 'qbFade', count: 21, sample: 'div[data-screen-label="Home"]', pageLevel: true };
  const [gap] = compareKeyframes([fade], [screen], [swipe, { ...fade, name: 'qb-page-enter' }], []);
  assert.match(gap.ref, /on div\[data-screen-label="Home"\] and 20 more/);
  assert.match(gap.target, /nothing runs qbFade's keyframes on a page-sized wrapper/);
  // The same keyframes on a menu do not make up for a missing page fade.
  assert.equal(compareKeyframes([fade], [screen], [{ ...fade, name: 'menu-in' }], [{ name: 'menu-in', count: 1, sample: 'div', pageLevel: false }]).length, 1);
  assert.equal(compareKeyframes([fade], [screen], [{ ...fade, name: 'qb-page-enter' }], [{ name: 'qb-page-enter', count: 1, sample: 'main', pageLevel: true }]).length, 0);
  // Several gaps on one page stay one finding (one stable id).
  const spin = { name: 'spin', steps: { '100%': 'transform: rotate(360deg)' }, source: 'x' };
  assert.equal(compareKeyframes([fade, spin], [screen, { name: 'spin', count: 1, sample: 'svg', pageLevel: false }], [], []).length, 1);
});

test('RTL: pairs that wrap in Arabic or sit in one sentence have no order to compare', () => {
  const item = (label, x, y, sentence = -1) => ({ label, x, y, w: 40, h: 20, sentence });
  const english = [item('/p/terms#1', 100, 500, 0), item('/p/privacy#1', 300, 500, 0), item('/login#1', 100, 50), item('/register#1', 200, 50)];
  const arabic = [item('/p/terms#1', 300, 500, 0), item('/p/privacy#1', 100, 520, 0), item('/login#1', 100, 50), item('/register#1', 200, 50)];
  assert.deepEqual(unmirroredPairs(english, arabic).map(([a, b]) => `${a.label}>${b.label}`), ['/login#1>/register#1']);
  const sameLine = arabic.map((i) => (i.label === '/p/privacy#1' ? { ...i, y: 500 } : i));
  assert.equal(unmirroredPairs(english, sameLine).length, 1);
});

test('drawer flows run signed in, crumbs are matched by position', () => {
  const pages = selectPages({ pages: ['index', 'product'], viewports: [{ width: 390 }] });
  const groups = groupFlows({}, pages);
  const drawerGroups = groups.filter((g) => g.flows.some(isDrawerFlow));
  assert.ok(drawerGroups.length > 0);
  assert.ok(drawerGroups.every((g) => g.login));
  assert.ok(groups.some((g) => !g.login));
  assert.equal(isCrumbFlow({ element: 'product.crumb.category' }), true);
  assert.equal(isDrawerFlow({ element: 'chrome.drawer.link.home', refSelector: 'body > nav.qb-mdrawer > a' }), true);
});

test('the demo login is read from the environment only, both values required', () => {
  assert.equal(demoCredentials({}), null);
  assert.equal(demoCredentials({ QB_DEMO_EMAIL: 'a@b.c' }), null);
  assert.deepEqual(demoCredentials({ QB_DEMO_EMAIL: ' a@b.c ', QB_DEMO_PASSWORD: 'x' }), { email: 'a@b.c', password: 'x' });
  assert.equal(buildConfig(['--target', 'https://qbazaar.qa'], {}).demo, null);
});

test('accepted differences add up per rule', () => {
  const findings = new Findings();
  findings.accept('focus-ring', { page: 'index', viewport: 1440, component: 'a', property: 'outline' });
  findings.accept('focus-ring', { page: 'login', viewport: 390, component: 'b', property: 'box-shadow' });
  const [entry] = findings.accepted;
  assert.equal(entry.count, 2);
  assert.deepEqual(entry.pages, ['index', 'login']);
  assert.match(entry.reason, /focus ring/);
});

test('a text that appears twice pairs by where it sits, not by document order', () => {
  const row = (key, relTop, extra = {}) => ({ key, text: key, relTop, relLeft: 0.1, ...extra });
  const adLocation = row('al khor', 0.40, { tag: 'span' });
  const cityPill = row('al khor', 0.62, { tag: 'span' });
  const { pairs, unmatchedRef } = pairTexts([adLocation, cityPill], [row('al khor', 0.6, { tag: 'a' })]);
  assert.equal(pairs.length, 1);
  assert.equal(pairs[0].ref, cityPill);
  assert.equal(unmatchedRef[0], adLocation);
});

test('a template wrapper in a chip compares the chips on both sides', () => {
  const node = (styles, extra = {}) => ({ rect: { x: 0, y: 0, w: 50, h: 20 }, styles, ...extra });
  const pill = { 'background-color': 'rgb(255, 255, 255)', padding: '10px 20px 10px 20px' };
  const bare = { 'background-color': 'rgba(0, 0, 0, 0)', padding: '0px 0px 0px 0px', color: 'rgb(0, 0, 0)' };
  const ref = node(bare, { templateWrapper: true, chip: { rect: { x: 0, y: 0, w: 90, h: 45 }, styles: pill } });
  const [r, t] = chipView(ref, node({ ...pill, color: 'rgb(0, 0, 0)' }));
  assert.equal(r.styles['background-color'], 'rgb(255, 255, 255)');
  assert.equal(r.styles.color, 'rgb(0, 0, 0)');
  assert.equal(r.rect.w, 90);
  assert.equal(t.styles.padding, '10px 20px 10px 20px');
  // Without a template wrapper on the reference nothing moves, so other pairings keep their values.
  const plain = node(bare, { chip: { rect: ref.chip.rect, styles: pill } });
  assert.equal(chipView(plain, plain)[0].styles['background-color'], 'rgba(0, 0, 0, 0)');
});

// Third round: what the live re-run of the tuned gate still showed.

test('a margin and a parent gap that keep the same space are not a difference', () => {
  const ref = { ...base(), margin: '0px 0px 12px 0px', 'margin-flow': '0px 0px 12px 0px' };
  const target = { ...base(), 'margin-flow': '0px 0px 12px 0px' };
  assert.deepEqual(diffStyles(ref, target), []);
  // The space itself differs: still reported, with the margins as the values.
  const tighter = { ...target, 'margin-flow': '0px 0px 8px 0px' };
  assert.deepEqual(diffStyles(ref, tighter).map((d) => [d.property, d.ref, d.target]), [['margin', '0px 0px 12px 0px', '0px 0px 0px 0px']]);
  // Styles read without the flow value (older captures, pseudo elements) compare margins as before.
  const { 'margin-flow': dropped, ...plainRef } = ref;
  assert.deepEqual(diffStyles(plainRef, base()).map((d) => d.property), ['margin']);
});

// A probe that answers like readUnit: the root, its descendants, and forced states.
function unitProbe(rest, states = {}) {
  let forced = null;
  return {
    read: async () => {
      const node = forced ? states[forced] || rest : rest;
      return { rect: { x: 0, y: 0, w: 300, h: 23 }, tag: 'label', native: null, text: 'Doha', ...node };
    },
    withState: async (selector, state, read) => { forced = state; try { return await read(); } finally { forced = null; } },
  };
}

const text = (styles, key = 't:doha#0') => ({ key, kind: 'text', tag: 'span', native: null, rect: { x: 0, y: 0, w: 40, h: 23 }, styles, ownText: true });

test('a text inside a component reports its resting differences once, at rest, not per state', async () => {
  const labelRef = { ...base(), display: 'flex', gap: '10px 10px', margin: '0px 0px 12px 0px', 'margin-flow': '0px 0px 12px 0px' };
  const labelTarget = { ...base(), display: 'flex', gap: '8px 8px', 'margin-flow': '0px 0px 12px 0px' };
  const spanRef = { ...base(), 'font-size': '15px', color: 'rgb(158, 150, 150)' };
  const spanTarget = { ...base(), 'font-size': '16px', color: 'rgb(158, 158, 158)' };
  const rest = (label, span, rect = { x: 0, y: 0, w: 310, h: 23 }) => ({ styles: label, ownText: false, rect, descendants: [text(span)] });
  const targetRect = { x: 0, y: 0, w: 304, h: 25 };
  const hovered = { ...rest(labelTarget, spanTarget, targetRect), styles: { ...labelTarget, 'background-color': 'rgb(250, 250, 250)' } };
  const findings = new Findings();
  const atRest = rest(labelTarget, spanTarget, targetRect);
  const comparer = new UnitComparer({
    ref: unitProbe(rest(labelRef, spanRef), { hover: rest(labelRef, spanRef), 'focus-visible': rest(labelRef, spanRef) }),
    target: unitProbe(atRest, { hover: hovered, 'focus-visible': atRest }),
    findings, base: { page: 'category', viewport: 1440, locale: 'en' },
  });
  // The component (the filter column) already reported the city name's colour at rest.
  comparer.covered.add(UnitComparer.coverKey('t:doha', { property: 'color', ref: spanRef.color, target: spanTarget.color }));
  await comparer.compare({ name: 'text:doha', refSel: 'x', tgtSel: 'x', states: ['hover', 'focus-visible'], statesOnly: true, icons: false });
  const rows = findings.list.map((f) => `${f.state} ${f.component} ${f.property}`).sort();
  assert.deepEqual(rows, [
    'default text:doha > t:doha font-size',
    'default text:doha gap',
    'hover text:doha background-color',
  ]);
});

test('scenario copy is compared without glyph icons and with data masked', () => {
  const { copyOf } = require('../src/scenarios');
  const adLink = 'qbazaar\\.qa/\\S+';
  assert.equal(copyOf('Share this ad ✕ W WhatsApp qbazaar.qa/ad/3380480338 Copy', adLink), copyOf('Share this ad W WhatsApp qbazaar.qa/ads/01m4edcmd846zpsr94e2jet5ma Copy', adLink));
  assert.equal(copyOf('Home › Categories ›'), 'Home Categories');
  // Real copy differences survive.
  assert.notEqual(copyOf('Report this ad ✕ Fraud or scam'), copyOf('Report this ad Select a problem to report Fraud or scam'));
});
