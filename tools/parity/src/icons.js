// Icon comparison: rasterised glyph shape score, rendered size and rendered stroke width.
const { normaliseColours } = require('./compare');

const GRID = 48;
const DILATE = 2;
const SAME_GLYPH = 0.9;
const DIFFERENT_GLYPH = 0.7;
const SIZE_TOLERANCE = 0.5;
const STROKE_TOLERANCE = 0.15;

function dilate(bits) {
  const out = new Uint8Array(GRID * GRID);
  for (let i = 0; i < bits.length; i++) {
    if (bits[i] !== '1') continue;
    const x = i % GRID;
    const y = Math.floor(i / GRID);
    for (let dy = -DILATE; dy <= DILATE; dy++) {
      for (let dx = -DILATE; dx <= DILATE; dx++) {
        const nx = x + dx;
        const ny = y + dy;
        if (nx >= 0 && ny >= 0 && nx < GRID && ny < GRID) out[ny * GRID + nx] = 1;
      }
    }
  }
  return out;
}

function coverage(bitsA, dilatedB) {
  let ink = 0;
  let covered = 0;
  for (let i = 0; i < bitsA.length; i++) {
    if (bitsA[i] !== '1') continue;
    ink++;
    covered += dilatedB[i];
  }
  return ink ? covered / ink : 1;
}

/** 1 when the two glyphs overlap within a dilation of a couple of pixels, lower when they differ. */
function shapeScore(bitsA, bitsB) {
  if (!bitsA || !bitsB) return null;
  return Math.min(coverage(bitsA, dilate(bitsB)), coverage(bitsB, dilate(bitsA)));
}

function classify(score) {
  if (score === null) return null;
  if (score >= SAME_GLYPH) return 'same';
  return score >= DIFFERENT_GLYPH ? 'redrawn' : 'different';
}

/** Returns the list of problems for one icon pair. */
function compareIcon(ref, target) {
  const problems = [];
  const score = shapeScore(ref.bits, target.bits);
  const verdict = classify(score);
  if (verdict && verdict !== 'same') problems.push({ property: 'glyph', ref: 'reference glyph', target: `${verdict} glyph (shape score ${score.toFixed(2)})`, severity: verdict === 'different' ? 'high' : 'medium', score });
  if (Math.abs(ref.width - target.width) > SIZE_TOLERANCE || Math.abs(ref.height - target.height) > SIZE_TOLERANCE) {
    problems.push({ property: 'icon-size', ref: `${ref.width}x${ref.height}`, target: `${target.width}x${target.height}`, severity: 'medium' });
  }
  if (Math.abs(ref.stroke - target.stroke) > STROKE_TOLERANCE) {
    problems.push({ property: 'icon-stroke', ref: `${ref.stroke}px rendered (${ref.strokeUser} in a ${ref.viewBox} box)`, target: `${target.stroke}px rendered (${target.strokeUser} in a ${target.viewBox} box)`, severity: 'medium' });
  }
  if (ref.ink && target.ink && normaliseColours(ref.ink) !== normaliseColours(target.ink)) {
    problems.push({ property: 'icon-colour', ref: normaliseColours(ref.ink), target: normaliseColours(target.ink), severity: 'high' });
  }
  return problems;
}

/** Same order when the counts agree; otherwise each reference icon takes its best unused target glyph. */
function pairIcons(refs, targets) {
  if (refs.length === targets.length) return refs.map((ref, index) => ({ ref, target: targets[index], index }));
  const free = new Set(targets.keys());
  return refs.map((ref, index) => {
    let best = null;
    for (const t of free) {
      const score = shapeScore(ref.bits, targets[t].bits) || 0;
      if (!best || score > best.score) best = { t, score };
    }
    if (best) free.delete(best.t);
    return { ref, target: best ? targets[best.t] : null, index };
  });
}

module.exports = { shapeScore, compareIcon, classify, pairIcons };
