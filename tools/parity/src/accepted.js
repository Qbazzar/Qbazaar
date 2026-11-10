// Owner-accepted differences the comparers meet element by element (findings.js ACCEPTED_REASONS holds the wording).
// Each is counted once per rule under "Accepted differences", never reported as a finding. The focus ring lives in
// focus.js; demo state, sample data and the kept copy are listed in reference-text.json (matching.js).

const ARABIC = /[؀-ۿݐ-ݿࢠ-ࣿﭐ-﷿ﹰ-﻿]/;
const LATIN = /[A-Za-z]/;
const KUFI = /noto ?kufi/i;
const FONT_PROPS = new Set(['font-family', 'rendered-font']);

/** Arabic letters and no Latin one: digits and punctuation may sit next to them, a Latin word may not. */
const isArabicText = (text) => ARABIC.test(String(text || '')) && !LATIN.test(String(text || ''));

const firstFont = (stack) => String(stack).split(',')[0].trim().replace(/^["']|["']$/g, '');
const renderedList = (value) => String(value || '').split(' + ').map((f) => f.trim()).filter(Boolean);

// The stack's first font is the one the text asks for, so Noto Kufi must lead it: a Latin font first with Noto Kufi
// behind it is still a Latin font choice. The fonts that really drew the text (rendered-font, capture.js) may hold
// Noto Kufi next to fonts the reference drew with too (Poppins for the digits), never a font of the target's own.
function drawnInKufi(diff) {
  if (diff.property === 'font-family') return KUFI.test(firstFont(diff.target));
  const [ref, target] = [renderedList(diff.ref), renderedList(diff.target)];
  return target.some((f) => KUFI.test(f)) && target.filter((f) => !KUFI.test(f)).every((f) => ref.includes(f));
}

/**
 * Arabic text drawn in Noto Kufi Arabic on the target. `ownText` is the text the element writes itself (not the
 * text of its descendants), so a Latin label in a container that also holds Arabic is still compared.
 */
function arabicFontRule(ownText, diff) {
  return FONT_PROPS.has(diff.property) && isArabicText(ownText) && drawnInKufi(diff) ? 'arabic-font' : null;
}

// A list item's margin follows its place in the list (a staggered row of pills): an item that sits at another place
// in its list on the other side (sample data in another order) keeps the margin of that place, not its own.
const LIST_MIN = 3;
const placesDiffer = (r, t) => Boolean(r.place && t.place) && r.place.count >= LIST_MIN && t.place.count >= LIST_MIN && r.place.index !== t.place.index;

/** The margin of a list item compared at another place in its list (counted, never dropped without a trace). */
function listPlaceRule(ref, target, diff) {
  return diff.property === 'margin' && placesDiffer(ref, target) ? 'list-place' : null;
}

/**
 * A language flag on either side (inpage isFlag: 'flag' for an SVG flag, 'static' for the auth pill's fallback rect).
 * The reference's static rect is the auth-static-pill difference; any other flag pair is the svg-flags one.
 */
function flagRule(refFlag, targetFlag) {
  if (refFlag === 'static') return 'auth-static-pill';
  return refFlag || targetFlag ? 'svg-flags' : null;
}

module.exports = { ARABIC, isArabicText, arabicFontRule, flagRule, placesDiffer, listPlaceRule };
