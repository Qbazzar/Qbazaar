// Arabic is checked for mirroring only: every element of the Arabic page must sit where the English
// page's element sits, reflected across the vertical centre line.
const browserTools = require('./browser');

const POSITION_TOLERANCE_PX = 3;
const MAX_ELEMENTS = 60;

// Runs in the page: document-order geometry of the controls that carry layout.
function collectGeometry(limit) {
  const visible = (el) => { const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0 && r.bottom > 0 && r.top < innerHeight * 3; };
  const nodes = [...document.querySelectorAll('header a, header button, main a, main button, main input, footer a, footer button')].filter(visible).slice(0, limit);
  return {
    dir: document.documentElement.dir,
    width: document.documentElement.clientWidth,
    items: nodes.map((el) => {
      const r = el.getBoundingClientRect();
      const label = (el.getAttribute('aria-label') || el.getAttribute('href') || el.tagName).slice(0, 40);
      return { label, x: r.left, y: r.top + scrollY, w: r.width, h: r.height };
    }),
  };
}

async function capture(env, pageDef, viewport, locale) {
  const context = await browserTools.newContext(env.browser, env.config, viewport, { side: 'target', storageState: pageDef.login ? env.storageState : undefined, locale });
  const page = await context.newPage();
  await browserTools.open(page, env.config.target + pageDef.route);
  const geometry = await page.evaluate(collectGeometry, MAX_ELEMENTS);
  if (pageDef.login) env.storageState = await context.storageState();
  await context.close();
  return geometry;
}

async function auditRtl(env, pageDef, viewport) {
  const base = { page: pageDef.id, viewport: viewport.width, locale: 'ar', category: 'rtl', state: 'default' };
  const [english, arabic] = [await capture(env, pageDef, viewport, 'en'), await capture(env, pageDef, viewport, 'ar')];
  if (arabic.dir !== 'rtl') env.findings.add({ ...base, component: 'html', severity: 'high', property: 'dir', ref: 'rtl', target: arabic.dir || 'unset' });
  const pairs = Math.min(english.items.length, arabic.items.length);
  for (let i = 0; i < pairs; i++) {
    const [en, ar] = [english.items[i], arabic.items[i]];
    const mirroredX = english.width - (en.x + en.w);
    if (Math.abs(ar.x - mirroredX) > POSITION_TOLERANCE_PX + Math.abs(ar.w - en.w)) {
      env.findings.add({ ...base, component: `rtl:${i}:${en.label}`, severity: 'medium', property: 'mirror', ref: `x=${Math.round(mirroredX)} (mirrored)`, target: `x=${Math.round(ar.x)}`,
        note: 'the control is not mirrored in the Arabic layout' });
    }
  }
}

module.exports = { auditRtl };
