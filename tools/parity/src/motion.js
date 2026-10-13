// Motion: @keyframes comparison and frame-sequence recording for JS-driven motion.
const { toHex } = require('./compare');

const SAMPLE_MS = 25;
const DURATION_TOLERANCE_MS = 80;
const CURVE_TOLERANCE = 0.2;

function canonDeclarations(text) {
  return text
    .replace(/#([0-9a-f])([0-9a-f])([0-9a-f])\b/g, '#$1$1$2$2$3$3')
    .replace(/rgba?\([^)]+\)/g, (m) => toHex(m).toLowerCase())
    .replace(/\s*,\s*/g, ',');
}

const canonSteps = (steps) => Object.fromEntries(Object.entries(steps).map(([k, v]) => [k, canonDeclarations(v)]));
const propertyNames = (steps) => [...new Set(Object.values(steps).flatMap((d) => d.split(';').map((x) => x.split(':')[0])))].sort().join(',');

const sameSteps = (a, b) => JSON.stringify(canonSteps(a.steps)) === JSON.stringify(canonSteps(b.steps));
const where = (use) => `${use.sample}${use.count > 1 ? ` and ${use.count - 1} more` : ''}`;

/**
 * Every reference animation in use on a page must run on the target too: keyframes with the same content, on a
 * page-sized wrapper when the reference runs it on one (a screen fade). Keyframes the target only defines (a toast
 * library's swipe-out, say) do not count. All gaps of a page form one finding, so its id stays stable.
 */
function compareKeyframes(refFrames, refUses, targetFrames, targetUses) {
  const framesOf = (use) => targetFrames.filter((t) => t.name === use.name);
  const gaps = [];
  for (const use of refUses) {
    const ref = refFrames.find((k) => k.name === use.name);
    if (!ref) continue;
    const running = targetUses.filter((t) => !use.pageLevel || t.pageLevel);
    if (running.some((t) => framesOf(t).some((frames) => sameSteps(frames, ref)))) continue;
    const similar = running.flatMap(framesOf).find((t) => propertyNames(t.steps) === propertyNames(ref.steps));
    gaps.push({ ref, use, similar });
  }
  if (!gaps.length) return [];
  const runningNames = targetUses.map((u) => u.name).join(', ') || 'none';
  return [{
    property: 'keyframes', source: gaps[0].ref.source,
    ref: gaps.map(({ ref, use }) => `@keyframes ${ref.name}: ${JSON.stringify(ref.steps)} on ${where(use)}`).join('; '),
    target: gaps.map(({ ref, use, similar }) => (similar
      ? `@keyframes ${similar.name} runs instead and animates the same properties differently: ${JSON.stringify(similar.steps)}`
      : `nothing runs ${ref.name}'s keyframes${use.pageLevel ? ' on a page-sized wrapper' : ''} (running: ${runningNames})`)).join('; '),
  }];
}

// Runs inside the page: samples a scalar series of the element until the animation settles.
function startSampler({ selector, intervalMs }) {
  const samples = [];
  const t0 = performance.now();
  const read = () => {
    const el = document.querySelector(selector);
    if (!el) return null;
    const s = getComputedStyle(el);
    const r = el.getBoundingClientRect();
    const m = new DOMMatrixReadOnly(s.transform === 'none' ? undefined : s.transform);
    // Tailwind moves boxes with the individual `translate` property, not `transform`.
    const [shiftX, shiftY] = s.translate === 'none' ? [0, 0] : s.translate.split(' ').map(parseFloat);
    return { scroll: el.scrollLeft, opacity: Number(s.opacity), tx: m.m41 + (shiftX || 0), ty: m.m42 + (shiftY || 0), scale: m.a, top: r.top, left: r.left, height: r.height, width: r.width, visible: s.visibility !== 'hidden' && s.display !== 'none' ? 1 : 0 };
  };
  window.__pgSamples = samples;
  window.__pgTimer = setInterval(() => samples.push({ t: performance.now() - t0, v: read() }), intervalMs);
}

function stopSampler() {
  clearInterval(window.__pgTimer);
  return window.__pgSamples;
}

/** Records a series while `trigger` runs on the page, then reduces it to the dominant moving scalar. */
async function recordSequence(page, selector, trigger, durationMs = 700) {
  await page.evaluate(startSampler, { selector, intervalMs: SAMPLE_MS });
  await page.waitForTimeout(SAMPLE_MS * 3);
  await trigger();
  await page.waitForTimeout(durationMs);
  return reduceSamples(await page.evaluate(stopSampler));
}

function reduceSamples(samples) {
  const valid = samples.filter((s) => s.v);
  if (valid.length < 3) return { moved: false, duration: 0, curve: [] };
  const keys = Object.keys(valid[0].v);
  const span = (k) => Math.max(...valid.map((s) => s.v[k])) - Math.min(...valid.map((s) => s.v[k]));
  const key = keys.reduce((best, k) => (span(k) > span(best) ? k : best), keys[0]);
  if (span(key) < 0.01) return { moved: false, duration: 0, curve: [], key };
  const first = valid[0].v[key];
  const startIdx = valid.findIndex((s) => Math.abs(s.v[key] - first) > span(key) * 0.02);
  const last = valid[valid.length - 1].v[key];
  let endIdx = valid.length - 1;
  while (endIdx > 0 && Math.abs(valid[endIdx - 1].v[key] - last) <= span(key) * 0.02) endIdx--;
  const duration = valid[endIdx].t - valid[Math.max(startIdx - 1, 0)].t;
  const curve = Array.from({ length: 11 }, (_, i) => {
    const t = valid[Math.max(startIdx - 1, 0)].t + (duration * i) / 10;
    const near = valid.reduce((a, b) => (Math.abs(b.t - t) < Math.abs(a.t - t) ? b : a));
    return Math.round(((near.v[key] - first) / (last - first || 1)) * 100) / 100;
  });
  return { moved: true, key, duration: Math.round(duration), curve };
}

function compareSequences(ref, target) {
  if (!ref.moved && !target.moved) return [];
  if (ref.moved !== target.moved) return [{ property: 'js-motion', ref: ref.moved ? `moves ${ref.key} over ${ref.duration}ms` : 'static', target: target.moved ? `moves ${target.key} over ${target.duration}ms` : 'static' }];
  const problems = [];
  if (Math.abs(ref.duration - target.duration) > Math.max(DURATION_TOLERANCE_MS, ref.duration * 0.25)) {
    problems.push({ property: 'js-motion', ref: `${ref.duration}ms`, target: `${target.duration}ms`, note: 'motion duration differs' });
  }
  const deviation = Math.max(...ref.curve.map((v, i) => Math.abs(v - target.curve[i])));
  if (deviation > CURVE_TOLERANCE) problems.push({ property: 'js-motion', ref: `easing ${ref.curve.join(' ')}`, target: `easing ${target.curve.join(' ')}`, note: 'easing shape differs' });
  return problems;
}

module.exports = { compareKeyframes, recordSequence, compareSequences };
