// Inventory sweep: every interactive element of the reference behaviour inventories that no scenario, flow or
// mapped component covers is paired with its twin on the target (same text, same label, same role and order)
// and compared in every state. Elements that change state when clicked are clicked on both sides and compared again.
const { openSide, closeSide } = require('./sides');
const { UnitComparer } = require('./unit');
const { loadStreams, navigationFlows, usedIds } = require('./spec');
const { loadScenarios } = require('../scenarios');
const { selectPages } = require('./pages');
const targets = require('../targets.json');

const SWEEP_KINDS = new Set(['button', 'link', 'tab', 'toggle/switch', 'checkbox/radio', 'accordion', 'dropdown/select', 'input', 'chip/pill', 'card', 'pagination', 'menu']);
// A missing counterpart is only a finding for controls a person operates, not for data cards or paging.
const REQUIRED_KINDS = new Set(['tab', 'toggle/switch', 'checkbox/radio', 'accordion', 'dropdown/select', 'input']);
const ACCORDION_MAX_WIDTH = 1000;
const STATE_CHANGING = new Set(['tab', 'checkbox/radio', 'accordion', 'toggle/switch']);
const ROLE_SELECTORS = {
  tab: '[role=tab], button, a', 'toggle/switch': '[role=switch], input[type=checkbox], button[aria-pressed]', 'checkbox/radio': 'input[type=radio], input[type=checkbox], [role=radio], [role=checkbox], label',
  accordion: 'summary, button[aria-expanded]', 'dropdown/select': 'select, [role=combobox], button[aria-haspopup]', input: 'input, textarea, select', menu: 'button[aria-haspopup], [role=menuitem]',
};
const SETTLE_MS = 450;
const CLICK_TIMEOUT_MS = 2500;

// Reference page: what the element says and which kind of twin it needs.
function describeElement({ selector, roleSelector }) {
  const norm = (t) => (t || '').trim().replace(/\s+/g, ' ').toLowerCase();
  const shown = (n) => {
    const r = n.getBoundingClientRect();
    const pinned = (() => { for (let p = n; p; p = p.parentElement) if (getComputedStyle(p).position === 'fixed') return true; return false; })();
    const withinY = pinned ? r.bottom > 0 && r.top < innerHeight : r.bottom + scrollY > 0 && r.top + scrollY < Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);
    return r.width > 0 && r.height > 0 && r.right > 0 && r.left < innerWidth && withinY;
  };
  const el = [...document.querySelectorAll(selector)].find(shown);
  if (!el) return null;
  document.querySelectorAll('[data-pgf]').forEach((n) => n.removeAttribute('data-pgf'));
  el.setAttribute('data-pgf', '1');
  const landmark = el.closest('header, footer, aside, nav, main');
  const peers = roleSelector ? [...(landmark || document.body).querySelectorAll(roleSelector)].filter((n) => n.getBoundingClientRect().width > 0) : [];
  const caption = el.closest('label') ? norm(el.closest('label').innerText) : '';
  return {
    fingerprint: el.outerHTML.slice(0, 160),
    text: norm(el.innerText || el.value || el.placeholder).slice(0, 60) || caption.slice(0, 60),
    label: norm(el.getAttribute('aria-label') || el.getAttribute('title') || el.getAttribute('placeholder')),
    landmark: landmark ? landmark.tagName.toLowerCase() : 'body',
    ordinal: Math.max(0, peers.findIndex((n) => n === el || n.contains(el) || el.contains(n))),
  };
}

// Target page: the twin by text or label first, else by position among the same kind of control.
function findTwin({ text, label, landmark, ordinal, roleSelector, clickable }) {
  const norm = (t) => (t || '').trim().replace(/\s+/g, ' ').toLowerCase();
  const visible = (n) => { const r = n.getBoundingClientRect(); return r.width > 0 && r.height > 0 && getComputedStyle(n).visibility !== 'hidden'; };
  const scope = landmark === 'body' ? document.body : document.querySelector(landmark) || document.body;
  const pool = [...scope.querySelectorAll(`${clickable}, ${roleSelector || clickable}`)].filter(visible);
  const named = (n) => [n.innerText, n.value, n.placeholder, n.getAttribute('aria-label'), n.getAttribute('title')].map((v) => norm(v).slice(0, 60));
  const byName = pool.filter((n) => (text && named(n).includes(text)) || (label && named(n).includes(label)));
  const byRole = roleSelector ? [...scope.querySelectorAll(roleSelector)].filter(visible) : [];
  const found = byName[0] || byRole[Math.min(ordinal, byRole.length - 1)];
  if (!found) return false;
  document.querySelectorAll('[data-pgf]').forEach((n) => n.removeAttribute('data-pgf'));
  found.setAttribute('data-pgf', '1');
  return true;
}

function candidates(pageDef, width, covered) {
  const own = `${pageDef.id}.html`;
  return loadStreams().flatMap((s) => s.elements).filter((e) => {
    const onPage = e.page === own || (pageDef.id === 'index' && /^chrome\./.test(e.id));
    // Footer columns are plain headings on desktop; the accordion only exists on narrower screens.
    if (e.kind === 'accordion' && width > ACCORDION_MAX_WIDTH) return false;
    return onPage && !e.deadCode && e.refSelector && SWEEP_KINDS.has(e.kind) && !covered.has(e.id) && (!e.viewports || !e.viewports.length || e.viewports.includes(width));
  });
}

function coveredIds() {
  const used = new Set(navigationFlows().map((f) => f.element));
  Object.keys(targets).forEach((id) => used.add(id));
  loadScenarios();
  usedIds().forEach((id) => used.add(id));
  return used;
}

async function sweepElement(env, ctx, element) {
  const { reference, target, comparer, base } = ctx;
  const roleSelector = ROLE_SELECTORS[element.kind];
  const description = await reference.page.evaluate(describeElement, { selector: element.refSelector, roleSelector });
  // The inventories describe some elements twice (a wrapper and its button, a desktop and a mobile entry).
  if (!description || ctx.seen.has(description.fingerprint)) return;
  ctx.seen.add(description.fingerprint);
  const twin = await target.page.evaluate(findTwin, { ...description, roleSelector, clickable: 'a, button, [role=button], [role=link], label, input' });
  const name = `inv:${element.id}`;
  env.swept.add(element.id);
  if (!twin) {
    if (REQUIRED_KINDS.has(element.kind)) env.findings.add({ ...base, component: name, category: 'missing', severity: 'medium', property: 'missing-control', ref: `${element.kind} "${description.text || description.label}"`, target: 'no counterpart', note: element.component });
    return;
  }
  await comparer.compare({ name, refSel: '[data-pgf]', tgtSel: '[data-pgf]', states: ctx.states, icons: true, mask: [] });
  const writes = element.kind === 'toggle/switch' && !env.config.localTarget;
  if (STATE_CHANGING.has(element.kind) && !writes) await compareAfterClick(ctx, name);
}

async function compareAfterClick(ctx, name) {
  const { reference, target, comparer } = ctx;
  const clicks = await Promise.allSettled([reference, target].map((side) => side.page.locator('[data-pgf]').first().click({ timeout: CLICK_TIMEOUT_MS, force: true })));
  // A control that cannot be clicked on either side has no after-click state to compare.
  if (clicks.some((c) => c.status === 'rejected')) return;
  await reference.page.waitForTimeout(SETTLE_MS);
  comparer.stateLabel = 'after-click';
  await comparer.compare({ name, refSel: '[data-pgf]', tgtSel: '[data-pgf]', states: false, icons: false, dedupeKey: name });
  comparer.stateLabel = null;
}

async function sweepPage(env, pageDef, width, skipped) {
  const viewport = env.config.viewports.find((v) => v.width === width);
  const elements = candidates(pageDef, width, coveredIds());
  if (!elements.length) return;
  const reference = await openSide(env, 'reference', pageDef, viewport);
  const target = await openSide(env, 'target', pageDef, viewport);
  const base = { page: pageDef.id, viewport: width, locale: env.config.locale };
  const comparer = new UnitComparer({ ref: reference.probe, target: target.probe, findings: env.findings, base, nameIcon: env.nameIcon });
  const ctx = { seen: new Set(), reference, target, comparer, base, states: width >= 1000 ? ['hover', 'focus-visible', 'active'] : ['focus-visible', 'active'] };
  try {
    for (const element of elements) {
      try {
        await sweepElement(env, ctx, element);
      } catch (error) {
        skipped.push({ what: `inventory ${element.id} @${width}`, reason: `sweep failed: ${error.message.split(String.fromCharCode(10))[0].slice(0, 160)}` });
        await Promise.all([reference, target].map((side) => require('./browser').open(side.page, side.landed.url))).catch(() => {});
      }
    }
  } finally {
    await closeSide(env, reference);
    await closeSide(env, target);
  }
}

async function runSweep(env, skipped) {
  const { config } = env;
  if (config.skipInventory || config.locale === 'ar') return;
  for (const pageDef of selectPages(config)) {
    if (pageDef.login && !env.storageState) { skipped.push({ what: `inventory sweep ${pageDef.id}`, reason: 'needs the demo login' }); continue; }
    for (const width of pageDef.viewports) {
      process.stdout.write(`inventory ${pageDef.id} @${width} ... `);
      await sweepPage(env, pageDef, width, skipped);
      console.log('done');
    }
  }
}

module.exports = { runSweep };
