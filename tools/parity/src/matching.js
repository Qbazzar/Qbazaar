// Element matching between the reference and the target: by normalised text, by named
// component, and by position inside a matched component.

const MAX_RELATIVE_DISTANCE = 0.3;

/** Pairs text rows with the same key, nearest relative page position first. */
function pairTexts(refRows, targetRows) {
  const pairs = [];
  const unmatchedRef = [];
  const pool = new Map();
  for (const row of targetRows) pool.set(row.key, [...(pool.get(row.key) || []), row]);
  for (const ref of refRows) {
    const candidates = pool.get(ref.key) || [];
    let best = null;
    for (const t of candidates) {
      const distance = Math.hypot(t.relTop - ref.relTop, (t.relLeft - ref.relLeft) * 0.5);
      if (distance <= MAX_RELATIVE_DISTANCE && (!best || distance < best.distance)) best = { t, distance };
    }
    if (!best) { unmatchedRef.push(ref); continue; }
    pool.set(ref.key, candidates.filter((c) => c !== best.t));
    pairs.push({ ref, target: best.t });
  }
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

module.exports = { pairTexts, pairDescendants, isUserData };
