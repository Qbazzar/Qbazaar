// Compares one matched element pair (a component or an interactive text) across every state.
const inpage = require('./inpage');
const { diffStyles } = require('./compare');
const { pairDescendants, isUserData } = require('./matching');
const { compareIcon, pairIcons } = require('./icons');
const { diffImages, cropOf } = require('./pixels');
const path = require('path');
const fs = require('fs');

const DEFAULT_STATES = ['hover', 'focus-visible', 'active'];
const VISUAL_STATE_PROPS = ['color', 'background-color', 'background-image', 'border-color', 'box-shadow', 'opacity', 'transform', 'outline-width', 'outline-color', 'text-decoration-line', 'filter'];
const SOURCE_BUDGET = 120;
const BOX_TOLERANCE = 0.5;

const sameBox = (a, b) => Math.abs(a.w - b.w) <= BOX_TOLERANCE && Math.abs(a.h - b.h) <= BOX_TOLERANCE;

// A state's transform reads as the move it adds to the default one (a hover lift of -3px).
function transformDelta(before, after) {
  if (before === after) return 'none';
  const parse = (t) => (t === 'none' ? [1, 0, 0, 1, 0, 0] : (t.match(/-?[\d.]+(?:e-?\d+)?/g) || []).map(Number));
  const [b, f] = [parse(before), parse(after)];
  return `matrix(${f[0]}, ${f[1]}, ${f[2]}, ${f[3]}, ${Math.round((f[4] - b[4]) * 10) / 10}, ${Math.round((f[5] - b[5]) * 10) / 10})`;
}

const withDelta = (base, forced) => ({ ...forced, styles: { ...forced.styles, transform: transformDelta(base.styles.transform, forced.styles.transform) } });

class UnitComparer {
  constructor({ ref, target, findings, base, nameIcon = () => null }) {
    this.nameIcon = nameIcon;
    this.ref = ref;
    this.target = target;
    this.findings = findings;
    this.base = base;
    this.sourceBudget = SOURCE_BUDGET;
    this.defaultDiffs = new Set();
    this.hoverDiffs = new Set();
  }

  emit(extra) {
    const labelled = this.stateLabel ? { ...extra, state: this.stateLabel } : extra;
    return this.findings.add({ ...this.base, ...labelled });
  }

  // A state only reports what differs beyond the default: repeating a default diff per state is noise.
  emitDiffs(component, state, diffs) {
    for (const d of diffs) {
      // Focus rings follow one site-wide rule, so they are reported once, not per control.
      const subject = d.property === 'outline' && state === 'focus-visible' ? 'focus-ring' : component;
      const key = `${this.dedupe || ""}|${subject}|${d.property}|${d.ref}|${d.target}`;
      // :active is forced together with :hover, so it repeats every hover difference.
      if (state === 'hover') this.hoverDiffs.add(key);
      if (state === 'active' && this.hoverDiffs.has(key)) continue;
      // Scenario observations of one element repeat its resting diffs, so only new ones are kept.
      if (this.dedupe && this.defaultDiffs.has(key)) continue;
      if (state === 'default') this.defaultDiffs.add(key);
      else if (this.defaultDiffs.has(key)) continue;
      this.emit({ component: subject, state, ...d });
    }
  }

  async compare(unit) {
    this.dedupe = unit.dedupeKey || null;
    const options = { descendants: unit.descendants !== false };
    const [ref, target] = await Promise.all([this.ref.read(unit.refSel, options), this.target.read(unit.tgtSel, options)]);
    if (!ref || !target) return;
    if (!unit.statesOnly) {
      await this.compareRoot(unit, ref, target);
      this.compareChildren(unit, ref, target, 'default');
      if (unit.icons) await this.compareIcons(unit);
    }
    if (unit.states !== false) await this.compareStates(unit, ref, target);
  }

  rootOptions(unit, ref, target) {
    return { ...unit, sameBox: sameBox(ref.rect, target.rect), ownText: ref.ownText || target.ownText, cursor: Array.isArray(unit.states) };
  }

  async compareRoot(unit, ref, target) {
    const diffs = diffStyles(ref.styles, target.styles, this.rootOptions(unit, ref, target));
    for (const d of diffs) d.source = await this.sourceFor(unit.refSel, d.property);
    this.emitDiffs(unit.name, 'default', diffs);
    if (ref.text && target.text) await this.compareRenderedFont(unit, ref, target);
  }

  async compareRenderedFont(unit, ref, target) {
    const [a, b] = await Promise.all([this.ref.renderedFonts(unit.refSel), this.target.renderedFonts(unit.tgtSel)]);
    if (a && b && a !== b) this.emit({ component: unit.name, property: 'rendered-font', ref: a, target: b });
  }

  async sourceFor(selector, property) {
    if (this.sourceBudget <= 0) return undefined;
    this.sourceBudget--;
    return (await this.ref.sourceOf(selector, property).catch(() => null)) || undefined;
  }

  compareChildren(unit, ref, target, state) {
    const { pairs, missing } = pairDescendants(ref, target);
    for (const { ref: r, target: t } of pairs) {
      const fixed = unit.fixedSize || (r.kind === 'icon') || (r.kind === 'input');
      const options = { fixedSize: fixed && r.kind !== 'text', skip: r.kind === 'icon' ? ['width', 'height'] : [], sameBox: sameBox(r.rect, t.rect), motion: false, ownText: r.ownText || t.ownText, kind: r.kind };
      this.emitDiffs(`${unit.name} > ${r.key.replace(/#\d+$/, '')}`, state, diffStyles(r.styles, t.styles, options));
    }
    if (state !== 'default') return;
    for (const m of missing) {
      if (m.kind === 'text' && isUserData(m.key.slice(2))) continue;
      this.emit({ component: `${unit.name} > ${m.key.replace(/#\d+$/, '')}`, category: 'missing', severity: m.kind === 'text' ? 'medium' : 'low',
        property: 'missing-descendant', ref: m.kind, target: 'absent', note: `${m.kind} ${m.key} exists in the reference component but not in the target one` });
    }
  }

  async compareIcons(unit) {
    const [refIcons, tgtIcons] = await Promise.all([
      this.ref.page.evaluate(inpage.rasteriseIcons, unit.refSel),
      this.target.page.evaluate(inpage.rasteriseIcons, unit.tgtSel),
    ]);
    if (refIcons.length !== tgtIcons.length) {
      this.emit({ component: unit.name, category: 'icons', severity: 'medium', property: 'icon-count', ref: String(refIcons.length), target: String(tgtIcons.length) });
    }
    for (const { ref, target, index } of pairIcons(refIcons, tgtIcons)) {
      if (!target) continue;
      const name = this.nameIcon(ref.bits);
      for (const p of compareIcon(ref, target)) this.emit({ component: `${unit.name} > svg#${index}`, category: 'icons', ...(name ? { note: `reference icon "${name}"` } : {}), ...p });
    }
  }

  async compareStates(unit, ref, target) {
    for (const state of unit.states || DEFAULT_STATES) {
      const full = state === 'hover';
      const read = (probe, sel) => probe.withState(sel, state, () => probe.read(sel, { descendants: full }));
      const [r, t] = await Promise.all([read(this.ref, unit.refSel), read(this.target, unit.tgtSel)]);
      if (!r || !t) continue;
      const [rd, td] = [withDelta(ref, r), withDelta(target, t)];
      this.emitDiffs(unit.name, state, diffStyles(rd.styles, td.styles, { ...this.rootOptions(unit, r, t), motion: false, transform: 'full' }));
      if (full) this.compareChildren(unit, r, t, state);
      this.compareStateChange(unit, state, { ref, target }, { ref: r, target: t });
    }
  }

  // The reference reacts to a state and the target does not: one explicit finding beats a dozen property rows.
  compareStateChange(unit, state, base, forced) {
    const changed = (before, after) => VISUAL_STATE_PROPS.filter((p) => before.styles[p] !== after.styles[p]);
    const refChange = changed(base.ref, forced.ref);
    const targetChange = changed(base.target, forced.target);
    if (!refChange.length || targetChange.length) return;
    this.emit({ component: unit.name, state, category: 'states', severity: 'high', property: 'state-change',
      ref: `${state} changes ${refChange.join(', ')}`, target: `${state} changes nothing` });
  }

  async pixelDiff(unit, outDir) {
    const [a, b] = await Promise.all([cropOf(this.ref.page, unit.refSel, unit.mask), cropOf(this.target.page, unit.tgtSel, unit.mask)]);
    if (!a || !b) return;
    const slug = `${unit.name.replace(/[^\w-]+/g, '_')}-${this.base.viewport}`;
    const dir = path.join(outDir, 'diffs', this.base.page);
    fs.mkdirSync(dir, { recursive: true });
    fs.writeFileSync(path.join(dir, `${slug}-ref.png`), a);
    fs.writeFileSync(path.join(dir, `${slug}-target.png`), b);
    const result = diffImages(a, b, path.join(dir, `${slug}-diff.png`));
    if (result.ratio < 0.02) return;
    this.emit({ component: unit.name, category: 'pixels', severity: result.ratio > 0.15 ? 'high' : 'medium', property: 'pixel-mismatch',
      ref: `${result.size.ref.join('x')}`, target: `${result.size.target.join('x')}`, mismatch: Math.round(result.ratio * 1000) / 10,
      diff: path.relative(outDir, path.join(dir, `${slug}-diff.png`)) });
  }
}

module.exports = { UnitComparer };
