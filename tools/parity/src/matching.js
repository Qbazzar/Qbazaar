// Element matching between the reference and the target: by normalised text, by named
// component, and by position inside a matched component.
const referenceText = require('../reference-text.json');

const MAX_RELATIVE_DISTANCE = 0.3;

const distanceOf = (ref, t) => Math.hypot(t.relTop - ref.relTop, (t.relLeft - ref.relLeft) * 0.5);

/**
 * Pairs text rows with the same key, the closest pair on the page first: a text that appears twice on one side (a
 * city pill and an ad's location) pairs by where it sits, not by which comes first in the document. Pairs keep
 * the reference's document order.
 */
function pairTexts(refRows, targetRows) {
  const byKey = new Map();
  for (const row of targetRows) byKey.set(row.key, [...(byKey.get(row.key) || []), row]);
  const candidates = [];
  refRows.forEach((ref, refIndex) => {
    for (const t of byKey.get(ref.key) || []) {
      const distance = distanceOf(ref, t);
      if (distance <= MAX_RELATIVE_DISTANCE) candidates.push({ refIndex, t, distance });
    }
  });
  candidates.sort((a, b) => a.distance - b.distance || a.refIndex - b.refIndex);
  const targetOf = new Map();
  const taken = new Set();
  for (const c of candidates) {
    if (targetOf.has(c.refIndex) || taken.has(c.t)) continue;
    targetOf.set(c.refIndex, c.t);
    taken.add(c.t);
  }
  const pairs = [];
  const unmatchedRef = [];
  refRows.forEach((ref, refIndex) => (targetOf.has(refIndex) ? pairs.push({ ref, target: targetOf.get(refIndex) }) : unmatchedRef.push(ref)));
  return { pairs, unmatchedRef };
}

/** Descendants are matched by key (text, nth svg, nth input, nth image). */
function pairDescendants(refUnit, targetUnit) {
  const byKey = new Map(targetUnit.descendants.map((d) => [d.key, d]));
  const pairs = [];
  const missing = [];
  for (const ref of refUnit.descendants) {
    const target = byKey.get(ref.key);
    if (target) pairs.push({ ref, target }); else missing.push(ref);
  }
  return { pairs, missing };
}

/** Text that is content (prices, counts, ad titles) must not count as "missing on target". */
function isUserData(text) {
  return /\d/.test(text) || text.length > 45 || /\bqar\b/i.test(text);
}

const normalise = (text) => String(text).trim().replace(/\s+/g, ' ').toLowerCase();

/** The rule of reference-text.json that makes a text demo state or sample data on this page, or null. */
function referenceOnlyText(text, pageDef, rules = referenceText.rules) {
  const key = normalise(text);
  return rules.find((rule) => rule.texts.some((t) => normalise(t) === key)
    && (!rule.pages || rule.pages.includes(pageDef.id))
    && (!rule.loginPages || Boolean(pageDef.login))) || null;
}

module.exports = { pairTexts, pairDescendants, isUserData, referenceOnlyText };
