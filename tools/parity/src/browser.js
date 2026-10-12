// Browser lifecycle: deterministic contexts, demo login and the localhost CORS harness.
const { chromium } = require('playwright');

const FREEZE_CSS = '*{caret-color:transparent !important}';

async function launch(config) {
  return chromium.launch({ channel: config.chromeChannel });
}

async function proxyApiWithCors(context, config) {
  const origin = new URL(config.target).origin;
  const cors = {
    'access-control-allow-origin': origin,
    'access-control-allow-credentials': 'true',
    'access-control-allow-headers': '*',
    'access-control-allow-methods': 'GET,POST,PUT,PATCH,DELETE,OPTIONS',
  };
  await context.route(`${config.apiOrigin}/**`, async (route) => {
    if (route.request().method() === 'OPTIONS') return route.fulfill({ status: 204, headers: cors });
    const response = await route.fetch();
    await route.fulfill({ response, headers: { ...response.headers(), ...cors } });
  });
}

// prefers-reduced-motion stays OFF: motion is under test.
async function newContext(browser, config, { width, height }, { side, storageState, locale }) {
  const context = await browser.newContext({
    viewport: { width, height },
    deviceScaleFactor: 1,
    reducedMotion: 'no-preference',
    storageState: side === 'target' ? storageState : undefined,
  });
  if (side === 'target') {
    await context.addCookies([{ name: 'NEXT_LOCALE', value: locale, url: config.target }]);
    if (config.localTarget) await proxyApiWithCors(context, config);
  }
  await context.addInitScript((css) => {
    const style = document.createElement('style');
    style.textContent = css;
    document.addEventListener('DOMContentLoaded', () => document.head.appendChild(style));
  }, FREEZE_CSS);
  return context;
}

async function waitForSettled(page, quietMs = 500, maxMs = 15000) {
  await page.evaluate(() => document.fonts.ready);
  await page.evaluate(({ quietMs, maxMs }) => new Promise((resolve) => {
    let timer = setTimeout(done, quietMs);
    const limit = setTimeout(done, maxMs);
    const observer = new MutationObserver(() => { clearTimeout(timer); timer = setTimeout(done, quietMs); });
    observer.observe(document.documentElement, { subtree: true, childList: true, attributes: true });
    function done() { observer.disconnect(); clearTimeout(limit); clearTimeout(timer); resolve(); }
  }), { quietMs, maxMs });
}

// Lazy sections only render after a scroll pass.
async function scrollThrough(page) {
  await page.evaluate(async () => {
    const step = Math.max(400, innerHeight * 0.8);
    for (let y = 0; y < document.body.scrollHeight; y += step) { scrollTo(0, y); await new Promise((r) => setTimeout(r, 120)); }
    scrollTo(0, 0);
  });
}

async function open(page, url, { scroll = true } = {}) {
  const response = await page.goto(url, { waitUntil: 'load', timeout: 180000 });
  await page.waitForTimeout(1500);
  if (scroll) await scrollThrough(page);
  await waitForSettled(page);
  await page.waitForTimeout(300);
  return { status: response ? response.status() : 0, url: page.url() };
}

async function loginDemo(browser, config) {
  const { email, password } = config.demo;
  if (!email || !password) return null;
  const context = await newContext(browser, config, config.viewports[0], { side: 'target', locale: 'en' });
  const page = await context.newPage();
  await open(page, `${config.target}/login`, { scroll: false });
  await page.fill('input[type=email], input[name=identifier], input[name=email], input[name=login]', email);
  await page.fill('input[type=password]', password);
  await Promise.all([
    page.waitForURL((u) => !/\/login/.test(u.toString()), { timeout: 120000 }).catch(() => {}),
    page.click('button[type=submit]'),
  ]);
  await page.waitForTimeout(3000);
  const state = await context.storageState();
  await context.close();
  return state;
}

module.exports = { launch, newContext, open, loginDemo, waitForSettled, FREEZE_CSS };
