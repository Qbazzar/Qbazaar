#!/usr/bin/env node
// axe + RTL audit of the FE-16.7 pages in both languages.
// Usage: node src/a11y/cli.js --target http://localhost:3000 [--pages home,product] [--locales ar,en] [--viewports 390,1440]
//        [--out dir] [--skip-rtl] [--skip-states]
const fs = require('fs');
const path = require('path');
const { buildConfig, parseArgs } = require('../config');
const { launch, newContext, open, loginDemo } = require('../browser');
const { auditRtl } = require('../rtl');
const { selectAuditPages, selectAuditStates, listArg } = require('./pages');
const { runAxe, readDocumentFacts, documentIssues } = require('./axe');
const { installSessionStub } = require('./session-stub');
const { renderMarkdown, countByImpact } = require('./report');

const DEFAULT_VIEWPORTS = '390,1440';

// An opened state may submit a form: its writes never reach the API.
async function blockApiWrites(context, config) {
  await context.route(`${config.apiOrigin}/**`, (route) => {
    const method = route.request().method();
    return method === 'GET' || method === 'OPTIONS' ? route.fallback() : route.abort();
  });
}

async function openState(page, state) {
  await page.click(state.click, { timeout: 15000 });
  await page.waitForSelector(state.waitFor, { timeout: 15000 });
  await page.waitForTimeout(600);
}

async function auditPage(browser, config, pageDef, locale, viewport, storageState) {
  const base = { page: pageDef.id, locale, viewport: viewport.width, route: pageDef.route };
  const context = await newContext(browser, config, viewport, { side: 'target', storageState: pageDef.login ? storageState : undefined, locale });
  try {
    if (pageDef.login && !storageState) await installSessionStub(context, config, locale);
    if (pageDef.click) await blockApiWrites(context, config);
    const page = await context.newPage();
    const { status, url } = await open(page, config.target + pageDef.route);
    if (pageDef.login && new URL(url).pathname.startsWith('/login')) throw new Error('the session was sent to /login');
    if (pageDef.click) await openState(page, pageDef);
    const facts = await page.evaluate(readDocumentFacts);
    const violations = await runAxe(page);
    return { ...base, status, finalUrl: url, facts, issues: documentIssues(facts, locale), violations };
  } catch (error) {
    return { ...base, error: String(error.message || error).split('\n')[0] };
  } finally {
    await context.close();
  }
}

async function auditMirroring(browser, config, pages) {
  const rows = [];
  for (const pageDef of pages) {
    for (const viewport of config.viewports) {
      const found = [];
      const env = { browser, config, findings: { add: (finding) => found.push(finding) } };
      try {
        await auditRtl(env, pageDef, viewport);
        const dirFinding = found.find((finding) => finding.property === 'dir');
        const pairs = found.filter((finding) => finding.property === 'mirror').map((finding) => finding.component.replace(/^rtl:/, '').split(' before '));
        rows.push({ page: pageDef.id, viewport: viewport.width, dir: dirFinding ? dirFinding.target : 'rtl', pairs });
      } catch (error) {
        rows.push({ page: pageDef.id, viewport: viewport.width, error: String(error.message || error).split('\n')[0] });
      }
      console.log(`rtl ${pageDef.id} @${viewport.width}: ${rows.at(-1).error || `${rows.at(-1).pairs.length} unmirrored pairs`}`);
    }
  }
  return rows;
}

function logRun(run) {
  if (run.error) return console.log(`axe ${run.page} ${run.locale} @${run.viewport}: not audited (${run.error})`);
  const counts = countByImpact(run.violations);
  console.log(`axe ${run.page} ${run.locale} @${run.viewport}: critical ${counts.critical}, serious ${counts.serious}, moderate ${counts.moderate}, minor ${counts.minor}${run.issues.length ? ` · ${run.issues.join('; ')}` : ''}`);
}

async function main(argv) {
  const args = parseArgs(argv);
  const config = buildConfig(args.viewports ? argv : [...argv, '--viewports', DEFAULT_VIEWPORTS]);
  const locales = listArg(args.locales, ['ar', 'en']);
  const pages = selectAuditPages(config.pages);
  const startedAt = new Date().toISOString();
  fs.mkdirSync(config.out, { recursive: true });

  const browser = await launch(config);
  const storageState = await loginDemo(browser, config);
  const session = storageState ? 'demo account' : 'browser-only stub session (no demo account in the environment)';

  const runs = [];
  for (const pageDef of pages) {
    for (const locale of locales) {
      for (const viewport of config.viewports) {
        runs.push(await auditPage(browser, config, pageDef, locale, viewport, storageState));
        logRun(runs.at(-1));
      }
    }
  }
  if (!args['skip-states']) {
    for (const state of selectAuditStates(config.pages)) {
      for (const locale of locales) {
        for (const viewport of config.viewports.filter((v) => state.viewports.includes(v.width))) {
          runs.push(await auditPage(browser, config, state, locale, viewport, storageState));
          logRun(runs.at(-1));
        }
      }
    }
  }
  const rtl = args['skip-rtl'] ? [] : await auditMirroring(browser, config, pages.filter((page) => !page.login));
  await browser.close();

  fs.writeFileSync(path.join(config.out, 'a11y.json'), JSON.stringify({ target: config.target, startedAt, session, runs, rtl }, null, 2));
  fs.writeFileSync(path.join(config.out, 'a11y.md'), renderMarkdown({ target: config.target, startedAt, session, runs, rtl }));
  console.log(`report: ${path.join(config.out, 'a11y.md')}`);
}

main(process.argv.slice(2)).catch((error) => {
  console.error(error);
  process.exit(1);
});
