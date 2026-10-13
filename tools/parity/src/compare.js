// Style comparison with the gate's tolerances: exact colours and fonts, +-0.5px sizes,
// derived signatures for transitions and animations.
const { COLOUR_PROPS, PX_TOLERANCE_PROPS } = require('./props');

const SIZE_TOLERANCE = 0.5;
const LETTER_SPACING_TOLERANCE = 0.15;
const TIME_TOLERANCE_S = 0.01;
const BEZIER = {
  ease: [0.25, 0.1, 0.25, 1], linear: [0, 0, 1, 1], 'ease-in': [0.42, 0, 1, 1],
  'ease-out': [0, 0, 0.58, 1], 'ease-in-out': [0.42, 0, 0.58, 1],
};

function toHex(css) {
  const m = css.match(/rgba?\(([^)]+)\)/);
  if (!m) return css.trim().toLowerCase();
  const [r, g, b, a = 1] = m[1].split(/[ ,/]+/).filter(Boolean).map(Number);
  const hex = (n) => Math.round(n).toString(16).padStart(2, '0');
  return `#${hex(r)}${hex(g)}${hex(b)}${a >= 1 ? '' : hex(a * 255)}`.toUpperCase();
}

const normaliseColours = (value) => value.replace(/rgba?\([^)]+\)/g, (m) => toHex(m));
// Tailwind stacks shadows that draw nothing: transparent ones, and zero-size ones (no offset, blur or spread)
// whatever their colour, such as a focus ring caught before its transition starts. Only drawn shadows count.
const zeroSize = (shadow) => {
  const lengths = shadow.match(/-?[\d.]+px/g) || [];
  return lengths.length > 0 && lengths.every((l) => parseFloat(l) === 0);
};
const normaliseShadow = (v) => {
  const drawn = normaliseColours(v).split(',').map((s) => s.trim()).filter((s) => s !== 'none' && !/^#[0-9A-F]{6}00/.test(s) && !zeroSize(s));
  return drawn.join(', ') || 'none';
};
const pxList = (value) => value.split(/\s+/).map((t) => (t === 'normal' || t === 'auto' ? NaN : parseFloat(t)));
const sameLists = (a, b, tol) => a.length === b.length && a.every((x, i) => (Number.isNaN(x) && Number.isNaN(b[i])) || Math.abs(x - b[i]) <= tol);
const firstFamily = (v) => v.split(',')[0].replace(/[^a-z0-9]/gi, '').toLowerCase();
const timeList = (v) => v.split(',').map((t) => parseFloat(t) * (t.includes('ms') ? 0.001 : 1));

// Border sides with no width draw nothing, so their colour and style do not count.
function dropInvisibleBorders(styles) {
  const widths = pxList(styles['border-width']);
  const hide = (key, replacement) => styles[key].split(/ (?![^(]*\))/).map((p, i) => (widths[i] === 0 ? replacement : p)).join(' ');
  return { ...styles, 'border-color': hide('border-color', '-'), 'border-style': hide('border-style', '-') };
}

// The four outline longhands read as one derived property; an outline that is not drawn is just "none".
function deriveOutline(styles) {
  const { 'outline-style': style, 'outline-width': width, 'outline-color': colour, 'outline-offset': offset, ...rest } = styles;
  const drawn = style !== 'none' && parseFloat(width) > 0;
  return { ...rest, outline: drawn ? `${style} ${width} ${colour} offset ${offset}` : 'none' };
}

const LAYOUT_BOXES = /flex|grid/;
const TYPOGRAPHY = new Set(['font-family', 'font-size', 'font-weight', 'font-style', 'line-height', 'letter-spacing', 'text-transform', 'text-decoration-line', 'color']);

function transitionSignature(s) {
  const props = s['transition-property'].split(',').map((x) => x.trim());
  const dur = timeList(s['transition-duration']);
  const del = timeList(s['transition-delay']);
  const fn = s['transition-timing-function'].split(/,(?![^(]*\))/).map((x) => x.trim());
  const entries = props.map((prop, i) => ({ prop, d: dur[i % dur.length], delay: del[i % del.length], fn: fn[i % fn.length] }));
  return entries.filter((e) => e.d > 0 || e.delay > 0);
}

function bezier(fn) {
  if (BEZIER[fn]) return BEZIER[fn];
  const m = fn.match(/cubic-bezier\(([^)]+)\)/);
  return m ? m[1].split(',').map(Number) : fn;
}

const sameBezier = (a, b) => {
  const x = bezier(a);
  const y = bezier(b);
  return Array.isArray(x) && Array.isArray(y) ? x.every((n, i) => Math.abs(n - y[i]) < 0.03) : x === y;
};

function describeTransition(sig) {
  return sig.length ? sig.map((e) => `${e.prop} ${e.d}s ${e.fn}${e.delay ? ` +${e.delay}s` : ''}`).join(', ') : 'none';
}

function diffTransition(ref, tgt) {
  const a = transitionSignature(ref);
  const b = transitionSignature(tgt);
  const same = a.length === b.length || (!a.length && !b.length)
    ? a.every((e, i) => Math.abs(e.d - b[i].d) <= TIME_TOLERANCE_S && Math.abs(e.delay - b[i].delay) <= TIME_TOLERANCE_S && sameBezier(e.fn, b[i].fn))
    : false;
  if (same) return null;
  const maxA = Math.max(0, ...a.map((e) => e.d));
  const maxB = Math.max(0, ...b.map((e) => e.d));
  // Different property lists with the same longest duration and easing read the same on screen.
  if (a.length && b.length && Math.abs(maxA - maxB) <= TIME_TOLERANCE_S && sameBezier(a[0].fn, b[0].fn)) return null;
  return { property: 'transition', ref: describeTransition(a), target: describeTransition(b) };
}

function diffAnimation(ref, tgt) {
  const live = (s) => s['animation-name'] !== 'none';
  if (!live(ref) && !live(tgt)) return null;
  const summary = (s) => (live(s) ? `${s['animation-duration']} ${s['animation-timing-function']} x${s['animation-iteration-count']} +${s['animation-delay']}` : 'none');
  const same = live(ref) && live(tgt)
    && sameLists(timeList(ref['animation-duration']), timeList(tgt['animation-duration']), TIME_TOLERANCE_S)
    && sameBezier(ref['animation-timing-function'], tgt['animation-timing-function'])
    && ref['animation-iteration-count'] === tgt['animation-iteration-count']
    && sameLists(timeList(ref['animation-delay']), timeList(tgt['animation-delay']), TIME_TOLERANCE_S);
  return same ? null : { property: 'animation', ref: summary(ref), target: summary(tgt) };
}

// Offsets only reposition a box (a centring translate, say); a scale, rotation or skew is the visible part.
function sameTransform(a, b, mode) {
  const count = mode === 'full' ? 6 : 4;
  const parts = (t) => (t === 'none' ? [1, 0, 0, 1, 0, 0] : (t.match(/-?[\d.]+(?:e-?\d+)?/g) || []).map(Number)).slice(0, count);
  return sameLists(parts(a), parts(b), mode === 'full' ? 0.5 : 0.01);
}

// A brightness within 2% of 1 is a transition caught mid-flight, not a filter.
const neutralFilter = (v) => normaliseColours(v).replace(/brightness\((0?\.9[89]\d*|1(\.0\d*)?)\)/, 'none');

function sameValue(property, a, b, options) {
  if (COLOUR_PROPS.has(property)) return normaliseColours(a) === normaliseColours(b);
  if (property === 'font-family') return firstFamily(a) === firstFamily(b);
  if (property === 'letter-spacing') return sameLists(pxList(a), pxList(b), LETTER_SPACING_TOLERANCE);
  // Browsers pad inputs by a pixel or two on their own; that inset is not a design decision.
  if (property === 'padding' && options.kind === 'input') return sameLists(pxList(a), pxList(b), 2.5);
  if (PX_TOLERANCE_PROPS.has(property)) return sameLists(pxList(a), pxList(b), SIZE_TOLERANCE);
  if (property === 'opacity') return Math.abs(a - b) < 0.011;
  if (property === 'transform') return sameTransform(a, b, options.transform);
  if (property === 'background-image') return /url\(/.test(a) && /url\(/.test(b) ? true : normaliseColours(a) === normaliseColours(b);
  if (property === 'box-shadow') return normaliseShadow(a) === normaliseShadow(b);
  if (property === 'filter') return neutralFilter(a) === neutralFilter(b);
  if (property === 'outline') return normaliseColours(a) === normaliseColours(b);
  if (property === 'display') return (a === 'none') === (b === 'none');
  if (property === 'border-style') return a === b;
  return a === b;
}

// Unstyled native controls in the reference draw with the browser's own font (13.33px Arial on Windows), and a
// checkbox or radio keeps the default cursor. Those values come from the user agent, not from the design.
const UA_CONTROL_FONT_SIZE = '13.3333px';
function userAgentDefault(property, refValue, refNative) {
  if (!refNative) return false;
  if (property === 'font-family') return firstFamily(refValue) === 'arial';
  if (property === 'font-size') return refValue === UA_CONTROL_FONT_SIZE;
  if (property === 'cursor') return refNative === 'toggle' && refValue === 'default';
  return false;
}

// A margin and a parent's flex or grid gap draw the same space: margins count only when the space each element keeps
// in its parent's flow (margin plus the gap after it, inpage flowMargin) differs.
const sameFlowSpace = (ref, tgt) => Boolean(ref['margin-flow'] && tgt['margin-flow'])
  && sameLists(pxList(ref['margin-flow']), pxList(tgt['margin-flow']), SIZE_TOLERANCE);

/** The value a property is compared by, so two spellings of the same drawing read as one. */
function canonicalValue(property, value) {
  const text = String(value);
  if (property === 'box-shadow') return normaliseShadow(text);
  if (property === 'filter') return neutralFilter(text);
  if (COLOUR_PROPS.has(property) || property === 'outline') return normaliseColours(text);
  return text;
}

const SKIPPED = new Set(['margin-flow', 'transition-property', 'transition-duration', 'transition-timing-function', 'transition-delay',
  'animation-name', 'animation-duration', 'animation-timing-function', 'animation-iteration-count', 'animation-delay']);

/** Returns [{ property, ref, target }] for every property that differs beyond tolerance. */
function diffStyles(refRaw, tgtRaw, options = {}) {
  const ref = deriveOutline(dropInvisibleBorders(refRaw));
  const tgt = deriveOutline(dropInvisibleBorders(tgtRaw));
  const diffs = [];
  for (const property of Object.keys(ref)) {
    if (SKIPPED.has(property)) continue;
    if ((property === 'width' || property === 'height') && !options.fixedSize) continue;
    if (property === 'z-index' && !options.overlay) continue;
    if (options.skip && options.skip.includes(property)) continue;
    // Text styles only matter where text is drawn; cursor only where the element is interactive.
    if (options.ownText === false && TYPOGRAPHY.has(property)) continue;
    if (property === 'gap' && !(LAYOUT_BOXES.test(ref.display) && LAYOUT_BOXES.test(tgt.display))) continue;
    if (property === 'cursor' && !options.cursor) continue;
    // Same box, same content: a different padding or gap draws the same pixels.
    if (options.sameBox && (property === 'padding' || property === 'gap' || property === 'line-height')) continue;
    if (userAgentDefault(property, ref[property], options.refNative)) continue;
    if (property === 'margin' && sameFlowSpace(ref, tgt)) continue;
    if (!sameValue(property, ref[property], tgt[property], options)) diffs.push({ property, ref: ref[property], target: tgt[property] });
  }
  if (options.motion === false) return diffs;
  for (const motion of [diffTransition(ref, tgt), diffAnimation(ref, tgt)]) if (motion) diffs.push(motion);
  return diffs;
}

module.exports = { diffStyles, canonicalValue, normaliseColours, sameBezier, toHex, firstFamily, UA_CONTROL_FONT_SIZE };
