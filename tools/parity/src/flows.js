// Prototype flows: clicking an element on a reference page lands on a reference page; the matching
// element on the target must land on the route that page maps to.
const { openSide, closeSide } = require('./sides');
const { open: browserOpen } = require('./browser');
const { navigationFlows } = require('./spec');
const { selectPages } = require('./pages');
const { NEEDS_LOGIN } = require('./config');

const NAVIGATION_WAIT_MS = 2500;
const DRAWER_OPEN_MS = 700;
const TARGET_BURGER = 'button[aria-label="Open menu"]';
const TARGET_DRAWER = '[role=dialog]';
// The prototype's mobile drawer (assets/mobilemenu.js) shows the signed-in demo user's menu.
const REFERENCE_DRAWER = '.qb-mdrawer, .qb-mnav';
// Clicking these saves something for the signed-in user (a saved search), so they only run against a local target.
const WRITING_FLOWS = new Set(['ca.pill.save', 'pc.pill.save', 'ca.circle.search']);
// Logging out would revoke the run's shared session, so a logout control is checked for presence, never clicked.
const LOGOUT = /^log ?out\b/;

const isDrawerFlow = (flow) => /^chrome\.drawer\./.test(flow.element) || /qb-mdrawer|qb-mnav/.test(flow.refSelector);
const isCrumbFlow = (flow) => /\.crumb\./.test(flow.element);

// Runs in the reference page: what the element says, where it lives, and which twin it is.
function describeReferenceElement({ selector, drawerSelector, crumb }) {
  const norm = (t) => (t || '').trim().replace(/\s+/g, ' ').toLowerCase();
  const el = [...document.querySelectorAll(selector)].find((n) => n.getBoundingClientRect().width > 0);
  if (!el) return null;
  const landmark = el.closest('header, footer, aside, nav, main, [role=dialog]');
  const scope = landmark || document.body;
  const text = norm(el.innerText).slice(0, 60);
  const twins = [...scope.querySelectorAll('a, button, [role=button], [role=link], [role=tab], [role=menuitem]')].filter((n) => norm(n.innerText).slice(0, 60) === text);
  // A breadcrumb is matched by its place in the trail: its labels are data (the category of the sample ad).
  const trail = crumb ? [...(el.closest('.qb-crumbs') || el.parentElement).children].filter((n) => !/^[>›/|]?$/.test(norm(n.innerText))) : [];
  return {
    text,
    label: norm(el.getAttribute('aria-label') || el.getAttribute('title')),
    landmark: landmark ? landmark.tagName.toLowerCase() : 'body',
    occurrence: Math.max(0, twins.findIndex((n) => n === el || n.contains(el))),
    drawer: Boolean(el.closest(drawerSelector)),
    footer: Boolean(el.closest('footer')),
    crumbIndex: trail.findIndex((n) => n === el || n.contains(el)),
  };
}

// Runs in the target page: the clickable that corresponds to the reference element. A breadcrumb goes by its place
// in the trail; otherwise the same text wins (same occurrence among twins), else any link that goes where the
// reference goes.
function findTargetElement({ text, label, landmark, occurrence, hrefExact, hrefPrefix, crumbIndex, footer, scopeSelector }) {
  const norm = (t) => (t || '').trim().replace(/\s+/g, ' ').toLowerCase();
  const visible = (n) => { const r = n.getBoundingClientRect(); return r.width > 0 && r.height > 0 && getComputedStyle(n).visibility !== 'hidden'; };
  const mark = (found) => {
    document.querySelectorAll('[data-pgf]').forEach((n) => n.removeAttribute('data-pgf'));
    found.setAttribute('data-pgf', '1');
    return true;
  };
  // Footer columns fold into accordions on narrow screens; their links exist once a column is open.
  if (footer) document.querySelectorAll('footer details:not([open])').forEach((d) => { d.open = true; });
  if (crumbIndex >= 0) {
    const trail = [...document.querySelectorAll('nav[aria-label] ol')].find((ol) => /crumb/i.test(ol.closest('nav').getAttribute('aria-label')) && visible(ol));
    const item = trail ? trail.children[crumbIndex] : null;
    const link = item ? item.querySelector('a, button, [role=link]') : null;
    if (link) return mark(link);
  }
  const scope = scopeSelector ? document.querySelector(scopeSelector) || document.body
    : landmark === 'body' ? document.body : document.querySelector(landmark) || document.body;
  const all = [...scope.querySelectorAll('a, button, [role=button], [role=link], [role=tab], [role=menuitem]')].filter(visible);
  const labelled = (n) => [n.getAttribute('aria-label'), n.getAttribute('title')].map(norm);
  const sameText = all.filter((n) => (text && norm(n.innerText).slice(0, 60) === text) || (label && labelled(n).includes(label)));
  const href = (n) => n.getAttribute('href') || '';
  const goesThere = all.filter((n) => (hrefExact && href(n) === hrefExact) || (hrefPrefix && href(n).startsWith(hrefPrefix)));
  const found = sameText[Math.min(occurrence, sameText.length - 1)] || goesThere[0];
  return found ? mark(found) : false;
}

function destination(toPage) {
  const page = selectPages({ pages: null, viewports: [{ width: 1440 }] }).find((p) => p.id === toPage);
  return page ? { path: page.route.split('?')[0], aliases: page.aliases, login: Boolean(page.login) } : null;
}

const segments = (p) => p.split('/').filter(Boolean);
const firstSegment = (p) => `/${segments(p)[0] || ''}`;

function routeMatches(reached, { path, aliases }) {
  const same = (candidate) => (segments(candidate).length <= 1
    ? reached.replace(/\/$/, '') === candidate.replace(/\/$/, '') || reached === candidate
    : firstSegment(reached) === firstSegment(candidate));
  return same(path) || aliases.some((a) => reached.startsWith(a));
}

const linkHints = (path) => (segments(path).length <= 1 ? { hrefExact: path } : { hrefPrefix: `${firstSegment(path)}/` });

async function clickAndRead(side) {
  await side.page.locator('[data-pgf]').first().click({ timeout: 5000 });
  await side.page.waitForTimeout(NAVIGATION_WAIT_MS);
  return new URL(side.page.url());
}

// The target renders its drawer only once the menu button is pressed.
async function openTargetDrawer(page) {
  const burger = page.locator(TARGET_BURGER).filter({ visible: true }).first();
  if (!(await burger.count())) return false;
  await burger.click({ timeout: 5000 });
  await page.waitForTimeout(DRAWER_OPEN_MS);
  return true;
}

async function locateOnTarget(target, description, dest) {
  const hints = { ...description, ...linkHints(dest.path) };
  if (!description.drawer) return target.page.evaluate(findTargetElement, hints);
  if (!(await openTargetDrawer(target.page))) return false;
  return target.page.evaluate(findTargetElement, { ...hints, scopeSelector: TARGET_DRAWER });
}

async function checkFlow(env, flow, { description, target, viewport }) {
  const base = { page: flow.page, viewport: viewport.width, locale: env.config.locale, component: `flow:${flow.element}`, state: 'flow', category: 'flows' };
  const dest = destination(flow.to);
  if (!dest) return `${flow.element} -> ${flow.to}.html: destination has no target route`;
  if (!description) return `${flow.element}: not visible on ${flow.page} at ${viewport.width}`;
  if (description.text.includes('@')) return `${flow.element}: its label is user data (${flow.page})`;
  if (WRITING_FLOWS.has(flow.element) && !env.config.localTarget) return `${flow.element}: clicking it saves data for the demo user, runs only against a local target`;
  const said = description.text || description.label || flow.element;
  const found = await locateOnTarget(target, description, dest);
  if (!found) {
    env.findings.add({ ...base, severity: 'high', property: 'missing-clickable', ref: `"${said}" navigates to ${flow.to}.html`, target: 'no matching link or button', note: `${flow.element}: nothing on the target page corresponds to ${flow.refSelector}` });
    await browserOpen(target.page, target.landed.url);
    return null;
  }
  if (LOGOUT.test(description.text)) {
    await browserOpen(target.page, target.landed.url);
    return null;
  }
  const reached = await clickAndRead(target);
  if (!routeMatches(reached.pathname, dest)) {
    env.findings.add({ ...base, severity: 'high', property: 'route', ref: `${flow.to}.html -> ${dest.path}`, target: reached.pathname, note: `${flow.element} ("${said}")` });
  }
  await browserOpen(target.page, target.landed.url);
  return null;
}

async function runGroup(env, group) {
  const { pageDef, width, flows, login } = group;
  const viewport = env.config.viewports.find((v) => v.width === width);
  const reference = await openSide(env, 'reference', pageDef, viewport);
  const target = await openSide(env, 'target', { ...pageDef, login }, viewport);
  const skipped = [];
  try {
    for (const flow of flows) {
      const description = await reference.page.evaluate(describeReferenceElement, { selector: flow.refSelector, drawerSelector: REFERENCE_DRAWER, crumb: isCrumbFlow(flow) });
      try {
        const reason = await checkFlow(env, flow, { description, target, viewport });
        if (reason) skipped.push({ what: `flow ${flow.page}@${width}`, reason });
      } catch (error) {
        skipped.push({ what: `flow ${flow.element} on ${flow.page}@${width}`, reason: `failed: ${error.message.split(String.fromCharCode(10))[0]}` });
        await browserOpen(target.page, target.landed.url).catch(() => {});
      }
    }
  } finally {
    await closeSide(env, reference);
    await closeSide(env, target);
  }
  return skipped;
}

// Flows into a signed-in-only route run signed in, and so do the drawer's (the prototype's drawer is the signed-in
// demo user's menu); the rest run as a guest like the prototype.
function groupFlows(config, pages) {
  const groups = new Map();
  const selectors = new Set();
  for (const flow of navigationFlows()) {
    // Several inventory entries can share one selector (three social buttons); the first stands for the set.
    const key = `${flow.page}|${flow.refSelector}`;
    if (selectors.has(key)) continue;
    selectors.add(key);
    const pageDef = pages.find((p) => p.id === flow.page);
    if (!pageDef) continue;
    const login = Boolean(pageDef.login || (destination(flow.to) || {}).login || isDrawerFlow(flow));
    for (const width of flow.viewports.filter((w) => pageDef.viewports.includes(w))) {
      const key = `${flow.page}@${width}@${login}`;
      groups.set(key, { pageDef, width, login, flows: [...(groups.get(key)?.flows || []), flow] });
    }
  }
  return [...groups.values()];
}

async function runFlows(env, skipped) {
  const { config } = env;
  if (config.locale === 'ar' || (config.only && !config.only.includes('flows'))) return;
  for (const group of groupFlows(config, selectPages(config))) {
    if (group.login && !env.storageState) { skipped.push({ what: `flows ${group.pageDef.id}@${group.width}`, reason: NEEDS_LOGIN }); continue; }
    process.stdout.write(`flows ${group.pageDef.id} @${group.width} login=${group.login} (${group.flows.length}) ... `);
    skipped.push(...(await runGroup(env, group)));
    console.log('done');
  }
}

module.exports = { runFlows, groupFlows, isDrawerFlow, isCrumbFlow, routeMatches };
