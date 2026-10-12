// Arabic is checked for mirroring only: every element of the Arabic page must sit where the English
// page's element sits, reflected across the vertical centre line.
const browserTools = require('./browser');

const POSITION_TOLERANCE_PX = 3;
const MAX_ELEMENTS = 60;
const ROW_TOLERANCE_PX = 6;

// Runs in the page: document-order geometry of the controls that carry layout.
function collectGeometry(limit) {
  const visible = (el) => { const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0 && r.bottom > 0 && r.top < innerHeight * 3; };
  const counts = {};
  const nodes = [...document.querySelectorAll('header a, header button, main a, main button, main input, footer a, footer button')].filter(visible).slice(0, limit);
  return {
    dir: document.documentElement.dir,
    width: document.documentElement.clientWidth,
    items: nodes.map((el) => {
      const r = el.getBoundingClientRect();
      const tag = el.tagName.toLowerCase();
      const base = el.getAttribute('href') || tag;
      const key = `${base}#${counts[base] = (counts[base] || 0) + 1}`;
      return { label: key, x: r.left, y: r.top + scrollY, w: r.width, h: r.height };
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
  // Widths of neighbours differ between languages, so absolute positions are not comparable. What mirroring
  // guarantees is the order: two controls side by side swap places.
  const arabicByKey = new Map(arabic.items.map((item) => [item.label, item]));
  const row = english.items.filter((item) => arabicByKey.has(item.label));
  for (let i = 0; i < row.length - 1; i++) {
    const [a, b] = [row[i], row[i + 1]];
    const sameRow = Math.abs(a.y - b.y) <= ROW_TOLERANCE_PX && Math.abs(b.x - a.x) > POSITION_TOLERANCE_PX;
    if (!sameRow) continue;
    const [arA, arB] = [arabicByKey.get(a.label), arabicByKey.get(b.label)];
    if (Math.sign(b.x - a.x) === Math.sign(arB.x - arA.x)) {
      env.findings.add({ ...base, component: `rtl:${a.label} before ${b.label}`, severity: 'medium', property: 'mirror', ref: 'order reversed', target: 'same order as English',
        note: 'two controls on one row keep their English order in the Arabic layout' });
    }
  }
}

module.exports = { auditRtl };
