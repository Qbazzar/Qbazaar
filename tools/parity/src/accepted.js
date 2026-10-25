// Owner-accepted differences the comparers meet element by element (findings.js ACCEPTED_REASONS holds the wording).
// Each is counted once per rule under "Accepted differences", never reported as a finding. The focus ring lives in
// focus.js; demo state, sample data and the kept copy are listed in reference-text.json (matching.js).

const ARABIC = /[؀-ۿݐ-ݿࢠ-ࣿﭐ-﷿ﹰ-﻿]/;
const FONT_PROPS = new Set(['font-family', 'rendered-font']);

/** Arabic text drawn in Noto Kufi Arabic on the target; a Latin text in Noto Kufi is still a finding. */
function arabicFontRule(subject, diff) {
  return FONT_PROPS.has(diff.property) && ARABIC.test(String(subject)) && /noto ?kufi/i.test(String(diff.target)) ? 'arabic-font' : null;
}

/**
 * A language flag on either side (inpage isFlag: 'flag' for an SVG flag, 'static' for the auth pill's fallback rect).
 * The reference's static rect is the auth-static-pill difference; any other flag pair is the svg-flags one.
 */
function flagRule(refFlag, targetFlag) {
  if (refFlag === 'static') return 'auth-static-pill';
  return refFlag || targetFlag ? 'svg-flags' : null;
}

module.exports = { ARABIC, arabicFontRule, flagRule };
