// Compares one matched element pair (a component or an interactive text) across every state.
const inpage = require('./inpage');
const { diffStyles, canonicalValue } = require('./compare');
const { FOCUS_RULE, applyFocusPolicy, designedChanges, isUserAgentRing } = require('./focus');
const { pairDescendants, isUserData } = require('./matching');
const { compareIcon, pairIcons } = require('./icons');
const { arabicFontRule, flagRule } = require('./accepted');
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

// Both sides carry text in elements that draw nothing themselves: the prototype's template engine wraps every
// interpolated value in span.sc-interp, the target wraps labels in spans. Box properties (margin, padding, border,
// background, cursor, size, transition) are read from the box that owns the text (inpage ownerBox), on each side.
// When only one side writes its text itself, the other side's typography is read from the one visible element that
// writes it (inpage textCarrierOf: a combobox around its label, not the invisible sizer next to it).
function ownerView(ref, target) {
  if (!ref || !target) return [ref, target];
  const boxed = (node) => (node.box ? { ...node, rect: node.box.rect, styles: { ...node.styles, ...node.box.styles } } : node);
  const written = (node, other) => (!node.ownText && other.ownText && node.textCarrier
    ? { ...node, ownText: true, styles: { ...node.styles, ...node.textCarrier.styles } } : node);
  const [r, t] = [boxed(ref), boxed(target)];
  return [written(r, t), written(t, r)];
}

// Icons of a unit: its own svgs, plus the roots named in `extra` (the reference's phone header bar is pinned outside
// <header>), plus glyphs drawn with CSS when the unit asks for them (the prototype's phone burger).
const iconScope = (selector, extra) => (extra ? { selector, extra } : selector);

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
    // Resting differences already reported for a component's descendants, by descendant key (t:doha).
    this.covered = new Set();
    this.resting = null;
  }

  static diffKey(dedupe, subject, d) {
    return `${dedupe || ''}|${subject}|${d.property}|${canonicalValue(d.property, d.ref)}|${canonicalValue(d.property, d.target)}`;
  }

  static coverKey(descendantKey, d) {
    return `${descendantKey}|${d.property}|${canonicalValue(d.property, d.ref)}|${canonicalValue(d.property, d.target)}`;
  }

  emit(extra) {
    const labelled = this.stateLabel ? { ...extra, state: this.stateLabel } : extra;
    // The sweep says which target element it paired, so a wrong twin is visible in the report.
    const noted = this.unitNote && !labelled.note ? { ...labelled, note: this.unitNote } : labelled;
    return this.findings.add({ ...this.base, ...noted });
  }

  accept(rule, component, state, d) {
    this.findings.accept(rule, { ...this.base, component, state: this.stateLabel || state, property: d.property, ref: d.ref, target: d.target });
  }

  // An owner-accepted difference: Arabic text in Noto Kufi, or a property the unit's spec accepts (sign-up's taller card).
  acceptedRule(component, d) {
    const root = Boolean(this.unit) && component === this.unit.name;
    const font = arabicFontRule(root ? `${component} ${this.unitText || ''}` : component, d);
    if (font) return font;
    const accepted = root && this.unit.accepted;
    return accepted && accepted.properties.includes(d.property) ? accepted.rule : null;
  }

  // A state only reports what differs beyond the default: repeating a default diff per state is noise.
  emitDiffs(component, state, diffs) {
    for (const d of diffs) {
      // The browser's own focus ring on a focused reference element is not a design decision.
      if (d.property === 'outline' && isUserAgentRing(d.ref)) { this.accept(FOCUS_RULE, component, state, d); continue; }
      const rule = this.acceptedRule(component, d);
      if (rule) { this.accept(rule, component, state, d); continue; }
      // Focus rings follow one site-wide rule, so they are reported once, not per control.
      const subject = d.property === 'outline' && state === 'focus-visible' ? 'focus-ring' : component;
      const key = UnitComparer.diffKey(this.dedupe, subject, d);
      if (state !== 'default' && this.resting && this.resting.has(key)) { this.reportResting(this.resting.get(key)); continue; }
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
    this.unitNote = unit.note || null;
    this.unit = unit;
    const options = { descendants: unit.descendants !== false, hop: Boolean(unit.hop) };
    const [ref, target] = ownerView(...(await Promise.all([this.ref.read(unit.refSel, options), this.target.read(unit.tgtSel, options)])));
    if (!ref || !target) return;
    this.unitText = `${ref.text || ''} ${target.text || ''}`;
    this.resting = unit.statesOnly ? this.restingDiffs(unit, ref, target) : null;
    if (!unit.statesOnly) {
      await this.compareRoot(unit, ref, target);
      this.compareChildren(unit, ref, target, 'default');
      if (unit.icons) await this.compareIcons(unit);
    }
    if (unit.states !== false) await this.compareStates(unit, ref, target);
  }

  rootOptions(unit, ref, target) {
    return { ...unit, sameBox: sameBox(ref.rect, target.rect), ownText: ref.ownText || target.ownText, cursor: Array.isArray(unit.states), refNative: ref.native };
  }

  async compareRoot(unit, ref, target) {
    const diffs = diffStyles(ref.styles, target.styles, this.rootOptions(unit, ref, target));
    for (const d of diffs) d.source = await this.sourceFor(unit.refSel, d.property);
    this.emitDiffs(unit.name, 'default', diffs);
    if (ref.text && target.text) await this.compareRenderedFont(unit, ref, target);
  }

  async compareRenderedFont(unit, ref, target) {
    const [a, b] = await Promise.all([this.ref.renderedFonts(unit.refSel), this.target.renderedFonts(unit.tgtSel)]);
    // An unstyled native control renders the browser's Arial: the same user-agent default diffStyles skips.
    if (ref.native && a === 'Arial') return;
    if (!a || !b || a === b) return;
    const d = { property: 'rendered-font', ref: a, target: b };
    const rule = this.acceptedRule(unit.name, d);
    if (rule) this.accept(rule, unit.name, 'default', d); else this.emit({ component: unit.name, ...d });
  }

  async sourceFor(selector, property) {
    if (this.sourceBudget <= 0) return undefined;
    this.sourceBudget--;
    return (await this.ref.sourceOf(selector, property).catch(() => null)) || undefined;
  }

  childDiffs(unit, ref, target) {
    const { pairs, missing } = pairDescendants(ref, target);
    // A language flag is the accepted flag difference, counted once, not a glyph whose box is compared.
    const flags = pairs.filter((p) => p.ref.kind === 'icon' && (p.ref.flag || p.target.flag));
    const diffs = pairs.filter((p) => !flags.includes(p)).map((pair) => {
      const [r, t] = ownerView(pair.ref, pair.target);
      const fixed = unit.fixedSize || (r.kind === 'icon') || (r.kind === 'input');
      const options = { fixedSize: fixed && r.kind !== 'text', skip: r.kind === 'icon' ? ['width', 'height'] : [], sameBox: sameBox(r.rect, t.rect), motion: false, ownText: r.ownText || t.ownText, kind: r.kind, refNative: r.native };
      return { key: r.key.replace(/#\d+$/, ''), diffs: diffStyles(r.styles, t.styles, options) };
    });
    return { diffs, missing, flags: flags.map((p) => ({ key: p.ref.key.replace(/#\d+$/, ''), rule: flagRule(p.ref.flag, p.target.flag) })) };
  }

  // A text inside a component is compared in its states only (the component compares it at rest). Its states still
  // show its resting differences; those are not state differences, so each is reported once, at rest, unless the
  // component already reported the same descendant with the same values.
  restingDiffs(unit, ref, target) {
    const resting = new Map();
    const add = (component, coverKey, diffs) => {
      for (const d of diffs) resting.set(UnitComparer.diffKey(this.dedupe, component, d), { component, coverKey, d, reported: false });
    };
    const rootKey = ref.ownText && ref.text ? `t:${ref.text.toLowerCase().slice(0, 60)}` : null;
    add(unit.name, rootKey, diffStyles(ref.styles, target.styles, { ...this.rootOptions(unit, ref, target), motion: false }));
    for (const child of this.childDiffs(unit, ref, target).diffs) add(`${unit.name} > ${child.key}`, child.key, child.diffs);
    return resting;
  }

  reportResting(entry) {
    if (entry.reported) return;
    entry.reported = true;
    if (entry.coverKey && this.covered.has(UnitComparer.coverKey(entry.coverKey, entry.d))) return;
    this.emit({ component: entry.component, state: 'default', ...entry.d });
  }

  compareChildren(unit, ref, target, state) {
    const { diffs, missing, flags } = this.childDiffs(unit, ref, target);
    for (const child of diffs) {
      if (state === 'default') for (const d of child.diffs) this.covered.add(UnitComparer.coverKey(child.key, d));
      this.emitDiffs(`${unit.name} > ${child.key}`, state, child.diffs);
    }
    if (state !== 'default') return;
    for (const f of flags) this.accept(f.rule, `${unit.name} > ${f.key}`, state, { property: 'flag', ref: 'reference flag', target: 'target flag' });
    for (const m of missing) {
      if (m.kind === 'text' && isUserData(m.key.slice(2))) continue;
      if (m.flag) { this.accept(flagRule(m.flag, null), `${unit.name} > ${m.key.replace(/#\d+$/, '')}`, state, { property: 'missing-descendant', ref: 'flag', target: 'absent' }); continue; }
      this.emit({ component: `${unit.name} > ${m.key.replace(/#\d+$/, '')}`, category: 'missing', severity: m.kind === 'text' ? 'medium' : 'low',
        property: 'missing-descendant', ref: m.kind, target: 'absent', note: `${m.kind} ${m.key} exists in the reference component but not in the target one` });
    }
  }

  async compareIcons(unit) {
    const [refScope, tgtScope] = [iconScope(unit.refSel, unit.refExtra), iconScope(unit.tgtSel, unit.targetExtra)];
    const drawn = (probe, scope) => (unit.countDrawn ? probe.page.evaluate(inpage.countDrawnIcons, scope) : 0);
    const [refAll, tgtAll, refDrawn, tgtDrawn] = await Promise.all([
      this.ref.page.evaluate(inpage.rasteriseIcons, refScope),
      this.target.page.evaluate(inpage.rasteriseIcons, tgtScope),
      drawn(this.ref, refScope),
      drawn(this.target, tgtScope),
    ]);
    // Language flags are the accepted flag difference: counted once, kept out of the glyph count and pairing.
    const [refFlags, tgtFlags] = [refAll.filter((i) => i.flag), tgtAll.filter((i) => i.flag)];
    if (refFlags.length || tgtFlags.length) {
      this.accept(flagRule(refFlags.length ? refFlags[0].flag : null, tgtFlags.length ? tgtFlags[0].flag : null), unit.name, 'default',
        { property: 'flag', ref: `${refFlags.length} flag svg`, target: `${tgtFlags.length} flag svg` });
    }
    const [refIcons, tgtIcons] = [refAll.filter((i) => !i.flag), tgtAll.filter((i) => !i.flag)];
    const [refCount, tgtCount] = [refIcons.length + refDrawn, tgtIcons.length + tgtDrawn];
    if (refCount !== tgtCount) {
      const drawnNote = refDrawn || tgtDrawn ? { note: `counts include glyphs drawn with CSS: reference ${refDrawn}, target ${tgtDrawn}` } : {};
      this.emit({ component: unit.name, category: 'icons', severity: 'medium', property: 'icon-count', ref: String(refCount), target: String(tgtCount), ...drawnNote });
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
      const read = (probe, sel) => probe.withState(sel, state, () => probe.read(sel, { descendants: full, hop: Boolean(unit.hop) }));
      const [r, t] = ownerView(...(await Promise.all([read(this.ref, unit.refSel), read(this.target, unit.tgtSel)])));
      if (!r || !t) continue;
      const [rd, td] = [withDelta(ref, r), withDelta(target, t)];
      const diffs = diffStyles(rd.styles, td.styles, { ...this.rootOptions(unit, r, t), motion: false, transform: 'full' });
      this.emitDiffs(unit.name, state, state === 'focus-visible' ? this.focusPolicy(unit.name, diffs, ref, r) : diffs);
      if (full) this.compareChildren(unit, r, t, state);
      this.compareStateChange(unit, state, { ref, target }, { ref: r, target: t });
    }
  }

  focusPolicy(component, diffs, refDefault, refFocused) {
    const { kept, accepted } = applyFocusPolicy(diffs, refDefault.styles, refFocused.styles);
    for (const d of accepted) this.accept(FOCUS_RULE, component, 'focus-visible', d);
    return kept;
  }

  // The reference reacts to a state and the target does not: one explicit finding beats a dozen property rows.
  compareStateChange(unit, state, base, forced) {
    const changed = (before, after) => VISUAL_STATE_PROPS.filter((p) => before.styles[p] !== after.styles[p]);
    const refChange = designedChanges(changed(base.ref, forced.ref), forced.ref.styles);
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
    if (unit.accepted && unit.accepted.properties.includes('pixel-mismatch')) {
      this.accept(unit.accepted.rule, unit.name, 'default', { property: 'pixel-mismatch', ref: result.size.ref.join('x'), target: result.size.target.join('x') });
      return;
    }
    this.emit({ component: unit.name, category: 'pixels', severity: result.ratio > 0.15 ? 'high' : 'medium', property: 'pixel-mismatch',
      ref: `${result.size.ref.join('x')}`, target: `${result.size.target.join('x')}`, mismatch: Math.round(result.ratio * 1000) / 10,
      diff: path.relative(outDir, path.join(dir, `${slug}-diff.png`)) });
  }
}

module.exports = { UnitComparer, ownerView };
