#!/usr/bin/env node
// Runner: npm run gate -- --target <url> [--reference <url|path>] [--pages a,b] [--viewports 1440,744,390] [--locale en|ar] [--out <dir>]
const { buildConfig, NEEDS_LOGIN } = require('./config');
const browserTools = require('./browser');
const { selectPages, referenceOnly } = require('./pages');
const { auditPage } = require('./audit');
const { Findings } = require('./findings');
const { writeReports } = require('./report');
const { runScenario } = require('./scenarios');
const { runFlows } = require('./flows');
const { loadIconNamer } = require('./iconLibrary');
const { auditRtl } = require('./rtl');
const { coverage } = require('./spec');
const { runSweep } = require('./sweep');
const { loadScenarios } = require('../scenarios');

function scenarioSkipReason(env, scenario) {
  if (scenario.writes && !env.config.localTarget) return 'skipped: writes data, runs only against a local target';
  if (scenario.login && !env.storageState) return NEEDS_LOGIN;
  return null;
}

async function runScenarios(env, pages, skipped) {
  const { config } = env;
  if (config.skipScenarios) return;
  // Scenario selectors name English copy and the reference has no Arabic side, so an Arabic run would only
  // report its own selectors failing. Arabic runs check the RTL mirroring.
  if (config.locale !== 'en') { skipped.push({ what: 'scenarios', reason: `not run in --locale ${config.locale}: the scenarios pair English copy with the English reference` }); return; }
  const wanted = new Set(pages.map((p) => p.id));
  for (const scenario of loadScenarios().filter((s) => wanted.has(s.page) && (!config.only || config.only.includes(s.id)))) {
    const reason = scenarioSkipReason(env, scenario);
    if (reason) { skipped.push({ what: `scenario ${scenario.id}`, reason }); continue; }
    for (const width of scenario.viewports.filter((w) => config.viewports.some((v) => v.width === w))) {
      process.stdout.write(`scenario ${scenario.id} @${width} ... `);
      try {
        await browserTools.ensureBrowser(env);
        await runScenario(env, scenario, config.viewports.find((v) => v.width === width));
        console.log('done');
      } catch (error) {
        console.log(`failed: ${error.message}`);
        skipped.push({ what: `scenario ${scenario.id} @${width}`, reason: `run failed: ${error.message.split(String.fromCharCode(10))[0]}` });
      }
    }
  }
}

// A phase that breaks (a browser killed under it, say) is listed under Skipped; the report keeps what ran.
async function guarded(name, env, skipped, work) {
  try {
    await browserTools.ensureBrowser(env);
    await work();
  } catch (error) {
    console.log(`${name} stopped: ${error.message.split(String.fromCharCode(10))[0]}`);
    skipped.push({ what: name, reason: `stopped: ${error.message.split(String.fromCharCode(10))[0]}` });
  }
}

async function main() {
  const config = buildConfig(process.argv.slice(2));
  const pages = selectPages(config);
  const findings = new Findings();
  const summary = { compared: [] };
  const skipped = [];
  const browser = await browserTools.launch(config);
  const storageState = await browserTools.loginDemo(browser, config);
  if (config.demo && !storageState) skipped.push({ what: 'demo login', reason: 'QB_DEMO_EMAIL / QB_DEMO_PASSWORD are set but the login did not leave /login; signed-in pages are skipped' });
  const nameIcon = await loadIconNamer(browser);
  const env = { browser, config, findings, summary, storageState, outDir: config.out, nameIcon, warnings: [], swept: new Set() };
  try {
    for (const pageDef of config.skipPages ? [] : pages) {
      if (pageDef.login && !storageState) { skipped.push({ what: pageDef.id, reason: NEEDS_LOGIN }); continue; }
      for (const width of pageDef.viewports) {
        const viewport = config.viewports.find((v) => v.width === width);
        process.stdout.write(`${pageDef.id} @${width} ... `);
        try {
          await browserTools.ensureBrowser(env);
          await (config.locale === 'ar' ? auditRtl : auditPage)(env, pageDef, viewport);
          console.log(`${findings.list.filter((f) => f.page === pageDef.id && f.viewport === width).length} findings`);
        } catch (error) {
          console.log(`failed: ${error.message}`);
          skipped.push({ what: `${pageDef.id} @${width}`, reason: `capture failed: ${error.message}` });
        }
      }
    }
    await guarded('scenarios', env, skipped, () => runScenarios(env, pages, skipped));
    if (!config.skipFlows) await guarded('flows', env, skipped, () => runFlows(env, skipped));
    if (!config.skipInventory) await guarded('inventory sweep', env, skipped, () => runSweep(env, skipped));
  } finally {
    await env.browser.close().catch(() => {});
  }
  skipped.push(...env.warnings.map((w) => ({ ...w, reason: `warning: ${w.reason}` })));
  const meta = { target: config.target, reference: config.reference, locale: config.locale, viewports: config.viewports.map((v) => v.width), finishedAt: new Date().toISOString() };
  const totals = writeReports(config.out, { meta, findings: findings.list, accepted: findings.accepted, skipped, referenceOnly: referenceOnly(), compared: summary.compared, coverage: coverage(require('../targets.json'), env.swept) });
  console.log(`\n${totals.findings} findings -> ${config.out}`);
  process.exitCode = totals.findings ? 1 : 0;
}

main().catch((error) => { console.error(error); process.exitCode = 2; });
