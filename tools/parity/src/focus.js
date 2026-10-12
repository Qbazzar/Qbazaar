// Focus decoration policy. The reference declares no focus style of its own (only the auth inputs change their
// border colour, assets/auth.css:69,115), so the browser draws its default ring there; the target keeps an
// accessible focus ring by the owner's rule. A focus difference in ring decoration (outline, ring shadow, border
// colour) is therefore an accepted difference, counted once per run, unless the reference itself changes that
// property on focus.
const { diffStyles } = require('./compare');

const FOCUS_RULE = 'focus-ring';
const FOCUS_DECOR = new Set(['outline', 'box-shadow', 'border-color']);
const RING_LONGHANDS = ['outline-width', 'outline-color', 'outline-style', 'outline-offset'];

// The browser's own focus ring is drawn with outline-style auto; the derived outline value starts with it.
const isUserAgentRing = (outline) => /^auto\b/.test(String(outline));

/** The ring properties the reference itself changes when the element takes focus. */
function declaredFocusDecor(refDefault, refFocused) {
  return new Set(diffStyles(refDefault, refFocused, { motion: false })
    .filter((d) => FOCUS_DECOR.has(d.property) && !(d.property === 'outline' && isUserAgentRing(d.target)))
    .map((d) => d.property));
}

/** Splits focus diffs into those to report and those the policy accepts. */
function applyFocusPolicy(diffs, refDefault, refFocused) {
  const declared = declaredFocusDecor(refDefault, refFocused);
  const kept = [];
  const accepted = [];
  for (const d of diffs) (FOCUS_DECOR.has(d.property) && !declared.has(d.property) ? accepted : kept).push(d);
  return { kept, accepted };
}

/** State-change props the reference changes on purpose: the browser's own focus ring does not count. */
function designedChanges(changedProps, refFocusedStyles) {
  if (refFocusedStyles['outline-style'] !== 'auto') return changedProps;
  return changedProps.filter((p) => !RING_LONGHANDS.includes(p));
}

module.exports = { FOCUS_RULE, FOCUS_DECOR, isUserAgentRing, declaredFocusDecor, applyFocusPolicy, designedChanges };
