// Audits one reference page against its target route at one viewport.
const { openSide, closeSide } = require('./sides');
const inpage = require('./inpage');
const { markComponents } = require('./marking');
const { UnitComparer } = require('./unit');
const { pairTexts, isUserData } = require('./matching');
const { compareKeyframes } = require('./motion');
const componentsFile = require('../components.json');
const targetsFile = require('../targets.json');
const { loadStreams } = require('./spec');

const INTERACTIVE_CAP = 40;
const TEXT_CAP = 250;
const HEADING = /^h[1-6]$/;

// Hand-written components, plus every inventory element that targets.json maps to a target selector.
function componentsFor(pageId) {
  const own = componentsFile.pages[pageId] || {};
  const inherited = own.$extends ? componentsFile.pages[own.$extends] : {};
  const { $extends, ...specific } = own;
  const merged = { ...componentsFile.shared, ...inherited, ...specific };
  const fromInventory = loadStreams().flatMap((s) => s.elements)
    .filter((e) => e.page === `${pageId}.html` && targetsFile[e.id])
    .map((e) => [e.id, { ref: e.refSelector, interactive: Boolean(e.interactive), pixel: false, ...targetsFile[e.id] }]);
  return Object.entries({ ...Object.fromEntries(fromInventory), ...merged }).map(([name, c]) => ({ name, ...c }));
}

function statesForViewport(config, width) {
  return width >= 1000 || config.hoverEverywhere ? ['hover', 'focus-visible', 'active'] : ['focus-visible', 'active'];
}

async function markUnits(refSide, targetSide, components) {
  const refComps = components.filter((c) => c.ref).map((c) => ({ name: c.name, selector: c.ref, all: c.all }));
  const tgtComps = components.filter((c) => c.target).map((c) => ({ name: c.name, selector: c.target, all: c.all }));
  const [refFound, tgtFound] = await Promise.all([
    markComponents(refSide.page, refComps),
    markComponents(targetSide.page, tgtComps),
  ]);
  return { refFound, tgtFound };
}

function componentUnits(components, found, config, width) {
  const tgtIds = new Set(found.tgtFound.map((f) => f.id));
  const states = statesForViewport(config, width);
  const units = [];
  const missing = [];
  for (const ref of found.refFound) {
    const spec = components.find((c) => c.name === ref.name);
    if (!tgtIds.has(ref.id)) {
      // Later items of a repeated component depend on how much data the target has.
      if (ref.id.endsWith('~0')) missing.push({ name: ref.id.replace(/~0$/, ''), spec });
      continue;
    }
    units.push({
      name: ref.id.replace(/~0$/, ''), refSel: `[data-pgc="${ref.id}"]`, tgtSel: `[data-pgc="${ref.id}"]`,
      fixedSize: spec.fixedSize, overlay: spec.overlay, mask: spec.mask, icons: spec.icons !== false,
      states: spec.interactive ? states : false, pixel: spec.pixel !== false,
    });
  }
  const refNames = new Set(found.refFound.map((f) => f.name));
  const targetOnly = components.filter((c) => c.ref && c.target && !refNames.has(c.name) && found.tgtFound.some((f) => f.name === c.name));
  return { units, missing, targetOnly };
}

function textUnits(pairs, config, width) {
  const states = statesForViewport(config, width);
  const seen = new Map();
  const units = [];
  const interactiveMismatch = [];
  let interactiveCount = 0;
  for (const { ref, target } of pairs.slice(0, TEXT_CAP)) {
    const n = seen.get(ref.key) || 0;
    seen.set(ref.key, n + 1);
    const name = `text:${ref.key}${n ? `#${n}` : ''}`;
    const bothInteractive = ref.interactive && target.interactive;
    if (ref.interactive && !target.interactive) interactiveMismatch.push({ name, ref, target });
    if (bothInteractive && interactiveCount < INTERACTIVE_CAP) {
      interactiveCount++;
      units.push({ name, refSel: `[data-pgi="${ref.id}"]`, tgtSel: `[data-pgi="${target.id}"]`, states,
        statesOnly: ref.inComponent || target.inComponent, icons: !ref.inComponent });
    } else if (!ref.inComponent && !target.inComponent) {
      units.push({ name, refSel: `[data-pgt="${ref.id}"]`, tgtSel: `[data-pgt="${target.id}"]`, descendants: false, states: false });
    }
  }
  return { units, interactiveMismatch };
}

function reportUnmatchedTexts(unmatched, emit) {
  for (const row of unmatched) {
    // Links inside content cards carry data (category and place names), so only headings and chrome count.
    if (isUserData(row.text) || !(HEADING.test(row.tag) || (row.interactive && row.chrome))) continue;
    emit({ component: `text:${row.key}`, category: 'missing', severity: 'medium', property: 'missing-text', ref: row.text, target: 'absent',
      note: `"${row.text}" (${row.tag}) is on the reference page but not on the target page` });
  }
}

async function checkKeyframes(refSide, targetSide, emit) {
  const [refFrames, used, targetFrames] = await Promise.all([refSide.probe.keyframes(), refSide.probe.usedAnimations(), targetSide.probe.keyframes()]);
  for (const p of compareKeyframes(refFrames, used, targetFrames)) emit({ component: 'page', category: 'motion', severity: 'medium', ...p });
}

async function timed(config, label, work) {
  const started = Date.now();
  await work();
  if (config.verbose) console.log(`  ${Date.now() - started}ms ${label}`);
}

async function auditPage(env, pageDef, viewport) {
  const { config, findings, summary } = env;
  const base = { page: pageDef.id, viewport: viewport.width, locale: config.locale };
  const emit = (extra) => findings.add({ ...base, ...extra });
  const refSide = await openSide(env, 'reference', pageDef, viewport);
  const targetSide = await openSide(env, 'target', pageDef, viewport);
  try {
    if (targetSide.landed.status >= 400 || (!pageDef.login && /\/login/.test(targetSide.landed.url) && !/login/.test(pageDef.id))) {
      emit({ component: 'page', category: 'missing', severity: 'high', property: 'page-unavailable', ref: 'renders', target: `HTTP ${targetSide.landed.status} at ${targetSide.landed.url}` });
      return;
    }
    const components = componentsFor(pageDef.id).filter((c) => !(c.guestOnly && pageDef.login));
    const found = await markUnits(refSide, targetSide, components);
    const comps = componentUnits(components, found, config, viewport.width);
    for (const m of comps.missing) emit({ component: m.name, category: 'missing', severity: 'high', property: 'missing-component', ref: m.spec.ref, target: 'not found', note: `component ${m.name} is on the reference but its target selector (${m.spec.target}) matches nothing visible` });
    const [refRows, tgtRows] = await Promise.all([refSide.page.evaluate(inpage.collectTexts), targetSide.page.evaluate(inpage.collectTexts)]);
    const { pairs, unmatchedRef } = pairTexts(refRows, tgtRows);
    reportUnmatchedTexts(unmatchedRef, emit);
    const texts = textUnits(pairs, config, viewport.width);
    for (const m of texts.interactiveMismatch) {
      emit({ component: m.name, category: 'states', severity: 'medium', property: 'not-interactive', ref: `interactive (${m.ref.tag} inside a button/link)`, target: 'plain text on the target', note: 'the reference makes this text clickable, the target does not' });
    }
    const comparer = new UnitComparer({ ref: refSide.probe, target: targetSide.probe, findings, base, nameIcon: env.nameIcon });
    for (const unit of [...comps.units, ...texts.units]) await timed(config, unit.name, () => comparer.compare(unit));
    for (const unit of comps.units.filter((u) => u.pixel)) await timed(config, `pixels ${unit.name}`, () => comparer.pixelDiff(unit, env.outDir));
    await checkKeyframes(refSide, targetSide, emit);
    summary.compared.push({ ...base, components: comps.units.length, texts: pairs.length, unmatched: unmatchedRef.length });
  } finally {
    await closeSide(env, refSide);
    await closeSide(env, targetSide);
  }
}

module.exports = { auditPage };
