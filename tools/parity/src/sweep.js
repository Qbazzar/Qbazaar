// Inventory sweep: every interactive element of the reference behaviour inventories that no scenario, flow or
// mapped component covers is paired with its twin on the target (same text, same label, same role and order)
// and compared in every state. Elements that change state when clicked are clicked on both sides and compared again.
const { openSide, closeSide } = require('./sides');
const { UnitComparer } = require('./unit');
const { loadStreams, navigationFlows, usedIds } = require('./spec');
const { loadScenarios } = require('../scenarios');
const { selectPages, liveDataRule, lacksLiveData, liveDataReason } = require('./pages');
const targets = require('../targets.json');
const { NEEDS_LOGIN } = require('./config');
const { open: browserOpen, ensureBrowser } = require('./browser');

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
  const box = el.getBoundingClientRect();
  // A wrapper around a field (a label holding a glyph and an input) has no text of its own: the target finds it
  // through the field it holds.
  const field = el.matches('input, textarea, select') ? null : el.querySelector('input, textarea, select');
  // Selected: an ARIA state, or the one sibling painted apart from its peers (the prototype's current page chip).
  const paint = (n) => getComputedStyle(n).backgroundColor;
  const siblings = el.parentElement ? [...el.parentElement.children].filter((n) => n !== el && n.tagName === el.tagName) : [];
  const ariaOn = ['aria-current', 'aria-selected', 'aria-pressed'].some((a) => el.getAttribute(a) && el.getAttribute(a) !== 'false');
  const paintedApart = siblings.length >= 2 && siblings.every((n) => paint(n) === paint(siblings[0])) && paint(siblings[0]) !== paint(el);
  return {
    field: field ? { placeholder: norm(field.placeholder), label: norm(field.getAttribute('aria-label')) } : null,
    selected: ariaOn || paintedApart,
    fingerprint: el.outerHTML.slice(0, 160),
    // The prototype numbers its template nodes per layout: at another width an inventory id can name an icon.
    icon: Boolean(el.closest('svg')),
    toggle: el.tagName === 'INPUT' && ['checkbox', 'radio'].includes(el.type),
    text: norm(el.innerText || el.value || el.placeholder).slice(0, 60) || caption.slice(0, 60),
    label: norm(el.getAttribute('aria-label') || el.getAttribute('title') || el.getAttribute('placeholder')),
    landmark: landmark ? landmark.tagName.toLowerCase() : 'body',
    // -1 when the element is not one of its kind's controls (a styled div acting as a toggle); the target then
    // pairs by where it sits on the page (findTwin).
    ordinal: peers.findIndex((n) => n === el || n.contains(el) || el.contains(n)),
    centre: { x: box.left + box.width / 2, y: box.top + scrollY + box.height / 2 },
  };
}

// Target page: the twin by text or label first, else by position among the same kind of control. A reference
// element that is not one of those controls has no place in their order: the control nearest to where it sits is
// its twin (a list card's heart, not the first filter checkbox of the page). A native checkbox or radio of the
// reference pairs with the control itself (or the box painted for a hidden one), not with the label that names it.
// A wrapper around a field pairs with the target's box around the same field (its placeholder or name), not with
// the bare input. A page chip pairs with a chip in the same state: the prototype's current page is 3, the target's
// is 1, so the reference's inactive "1" is compared with an inactive target chip.
// Returns the twin's markup for the finding note, or false.
function findTwin({ text, label, landmark, ordinal, centre, roleSelector, clickable, toggle, field, selected, kind }) {
  const norm = (t) => (t || '').trim().replace(/\s+/g, ' ').toLowerCase();
  const visible = (n) => { const r = n.getBoundingClientRect(); return r.width > 0 && r.height > 0 && getComputedStyle(n).visibility !== 'hidden'; };
  const scope = landmark === 'body' ? document.body : document.querySelector(landmark) || document.body;
  const pool = [...scope.querySelectorAll(`${clickable}, ${roleSelector || clickable}`)].filter(visible);
  const named = (n) => [n.innerText, n.value, n.placeholder, n.getAttribute('aria-label'), n.getAttribute('title')].map((v) => norm(v).slice(0, 60));
  const byName = pool.filter((n) => (text && named(n).includes(text)) || (label && named(n).includes(label)));
  const byRole = roleSelector ? [...scope.querySelectorAll(roleSelector)].filter(visible) : [];
  const TOGGLES = 'input[type=checkbox], input[type=radio], [role=checkbox], [role=radio], [role=switch]';
  const controlOf = (n) => (n.matches(TOGGLES) ? n : n.control || n.querySelector(TOGGLES) || n);
  const paintedBox = (control) => {
    const r = control.getBoundingClientRect();
    if (r.width >= 4 && r.height >= 4 && getComputedStyle(control).opacity !== '0') return control;
    const scope = control.closest('label') || control.parentElement || control;
    const drawn = (n) => {
      const b = n.getBoundingClientRect();
      const s = getComputedStyle(n);
      return b.width >= 8 && b.width <= 48 && Math.abs(b.width - b.height) <= 4 && (parseFloat(s.borderTopWidth) > 0 || s.backgroundColor !== 'rgba(0, 0, 0, 0)');
    };
    return [...scope.querySelectorAll('*')].find((n) => n !== control && !n.contains(control) && drawn(n)) || control;
  };
  const distance = (n) => {
    const r = n.getBoundingClientRect();
    return Math.hypot(r.left + r.width / 2 - centre.x, r.top + scrollY + r.height / 2 - centre.y);
  };
  const nearest = (nodes) => nodes.reduce((best, n) => (best === undefined || distance(n) < distance(best) ? n : best), undefined);
  const FIELDS = 'input, textarea, select';
  // The box drawn around one field: the nearest ancestor holding only that field that paints a border or background.
  const wrapperOf = (input) => {
    for (let node = input.parentElement; node && node !== document.body && node.querySelectorAll(FIELDS).length === 1; node = node.parentElement) {
      const s = getComputedStyle(node);
      if (parseFloat(s.borderTopWidth) > 0 || s.backgroundColor !== 'rgba(0, 0, 0, 0)' || s.boxShadow !== 'none') return node;
    }
    return input.closest('label') || input;
  };
  const byField = () => {
    if (!field) return undefined;
    const same = [...scope.querySelectorAll(FIELDS)].filter(visible)
      .find((n) => (field.placeholder && norm(n.placeholder) === field.placeholder) || (field.label && norm(n.getAttribute('aria-label')) === field.label));
    return same ? wrapperOf(same) : undefined;
  };
  const placed = ordinal >= 0 || !centre ? byRole[Math.min(Math.max(ordinal, 0), byRole.length - 1)] : nearest(byRole);
  const byPlace = placed && field && placed.matches(FIELDS) ? wrapperOf(placed) : placed;
  const selectedOf = (n) => {
    if (['aria-current', 'aria-selected', 'aria-pressed'].some((a) => n.getAttribute(a) && n.getAttribute(a) !== 'false')) return true;
    const peers = n.parentElement ? [...n.parentElement.children].filter((p) => p !== n && p.tagName === n.tagName) : [];
    const paint = (p) => getComputedStyle(p).backgroundColor;
    return peers.length >= 2 && peers.every((p) => paint(p) === paint(peers[0])) && paint(peers[0]) !== paint(n);
  };
  const sameState = (n) => {
    if (kind !== 'pagination' || typeof selected !== 'boolean' || !n || !n.parentElement || selectedOf(n) === selected) return n;
    return [...n.parentElement.children].find((p) => p.tagName === n.tagName && visible(p) && norm(p.innerText) && selectedOf(p) === selected) || n;
  };
  const picked = sameState(byName[0] || byField() || byPlace);
  if (!picked) return false;
  const found = toggle ? paintedBox(controlOf(picked)) : picked;
  document.querySelectorAll('[data-pgf]').forEach((n) => n.removeAttribute('data-pgf'));
  found.setAttribute('data-pgf', '1');
  return found.outerHTML.replace(/\s+/g, ' ').slice(0, 200);
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
  if (description.icon) {
    env.warnings.push({ what: `sweep ${element.id} on ${base.page}@${base.viewport}`, reason: 'its inventory selector names an icon at this width, not the control' });
    return;
  }
  const live = liveDataRule(base.page, element.id);
  if (live && (await lacksLiveData(target.page, live))) {
    ctx.skipped.push({ what: `inventory ${element.id} on ${base.page}@${base.viewport}`, reason: liveDataReason(live) });
    return;
  }
  const twin = await target.page.evaluate(findTwin, { ...description, roleSelector, kind: element.kind, clickable: 'a, button, [role=button], [role=link], label, input' });
  const name = `inv:${element.id}`;
  env.swept.add(element.id);
  if (!twin) {
    if (REQUIRED_KINDS.has(element.kind)) env.findings.add({ ...base, component: name, category: 'missing', severity: 'medium', property: 'missing-control', ref: `${element.kind} "${description.text || description.label}"`, target: 'no counterpart', note: element.component });
    return;
  }
  const note = `target twin: ${twin}`;
  // The prototype re-renders its template on state changes; a node that was replaced is no longer the one described.
  const stillMarked = await reference.page.evaluate((fingerprint) => {
    const marked = document.querySelector('[data-pgf]');
    return Boolean(marked) && marked.outerHTML.slice(0, 160) === fingerprint;
  }, description.fingerprint);
  if (!stillMarked) {
    env.warnings.push({ what: `sweep ${element.id} on ${base.page}@${base.viewport}`, reason: 'the reference re-rendered the element before it could be compared' });
    return;
  }
  await comparer.compare({ name, refSel: '[data-pgf]', tgtSel: '[data-pgf]', states: ctx.states, icons: true, mask: [], note });
  const writes = element.kind === 'toggle/switch' && !env.config.localTarget;
  if (STATE_CHANGING.has(element.kind) && !writes) await compareAfterClick(ctx, name, note);
}

async function compareAfterClick(ctx, name, note) {
  const { reference, target, comparer } = ctx;
  const clicks = await Promise.allSettled([reference, target].map((side) => side.page.locator('[data-pgf]').first().click({ timeout: CLICK_TIMEOUT_MS, force: true })));
  // A control that cannot be clicked on either side has no after-click state to compare.
  if (clicks.some((c) => c.status === 'rejected')) return;
  await reference.page.waitForTimeout(SETTLE_MS);
  comparer.stateLabel = 'after-click';
  await comparer.compare({ name, refSel: '[data-pgf]', tgtSel: '[data-pgf]', states: false, icons: false, dedupeKey: name, note });
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
  const ctx = { seen: new Set(), reference, target, comparer, base, skipped, states: width >= 1000 ? ['hover', 'focus-visible', 'active'] : ['focus-visible', 'active'] };
  try {
    for (const element of elements) {
      try {
        await sweepElement(env, ctx, element);
      } catch (error) {
        skipped.push({ what: `inventory ${element.id} @${width}`, reason: `sweep failed: ${error.message.split(String.fromCharCode(10))[0].slice(0, 160)}` });
        await Promise.all([reference, target].map((side) => browserOpen(side.page, side.landed.url))).catch(() => {});
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
    if (pageDef.login && !env.storageState) { skipped.push({ what: `inventory sweep ${pageDef.id}`, reason: NEEDS_LOGIN }); continue; }
    for (const width of pageDef.viewports) {
      process.stdout.write(`inventory ${pageDef.id} @${width} ... `);
      await ensureBrowser(env);
      await sweepPage(env, pageDef, width, skipped);
      console.log('done');
    }
  }
}

module.exports = { runSweep, findTwin };
