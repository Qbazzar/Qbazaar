#!/usr/bin/env node
// Lighthouse, mobile and desktop, for the public FE-16.7 pages in both languages. Lighthouse runs through npx at a
// pinned version, so the gate does not carry it (and its Chrome tooling) as a dependency.
// Usage: node src/a11y/lighthouse.js --target https://qbazaar.qa [--pages home,product] [--locales ar,en]
//        [--forms mobile,desktop] [--runs 1] [--out dir]
// Chrome's throw-away profile goes to the OS temp folder; point TMP/TEMP elsewhere when that folder is shared.
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');
const { buildConfig, parseArgs } = require('../config');
const { selectAuditPages, listArg } = require('./pages');

const LIGHTHOUSE = 'lighthouse@13.6.0';
const CATEGORIES = 'performance,accessibility,best-practices,seo';

function lighthouseArgs({ url, form, jsonPath, headersPath, localTarget }) {
  // The API only answers the production origin, so a localhost build needs the same CORS relief the gate gives it.
  const chromeFlags = ['--headless=new', ...(localTarget ? ['--disable-web-security'] : [])].join(' ');
  return [
    '--yes', LIGHTHOUSE, url, '--quiet', '--output=json', `--output-path=${jsonPath}`, `--extra-headers=${headersPath}`,
    `--only-categories=${CATEGORIES}`, `--chrome-flags=${chromeFlags}`, ...(form === 'desktop' ? ['--preset=desktop'] : []),
  ];
}

const quote = (arg) => `"${arg.replaceAll('"', '\\"')}"`;

function savingsOf(audit) {
  const savings = audit.metricSavings || {};
  return (savings.LCP || 0) + (savings.FCP || 0) + (savings.TBT || 0);
}

/** The numbers the report keeps from one Lighthouse result. */
function summariseLighthouse(lhr) {
  const score = (id) => (lhr.categories[id] ? Math.round(lhr.categories[id].score * 100) : null);
  const metric = (id) => (lhr.audits[id] ? lhr.audits[id].numericValue : null);
  const failing = (category) => (lhr.categories[category]?.auditRefs || [])
    .filter((ref) => ref.weight > 0 && lhr.audits[ref.id] && lhr.audits[ref.id].score === 0)
    .map((ref) => ref.id);
  const opportunities = Object.entries(lhr.audits)
    .filter(([, audit]) => audit.score !== null && audit.score < 0.9 && savingsOf(audit) > 0)
    .sort(([, a], [, b]) => savingsOf(b) - savingsOf(a))
    .slice(0, 4)
    .map(([id, audit]) => `${id} (~${Math.round(savingsOf(audit))} ms)`);
  return {
    performance: score('performance'),
    accessibility: score('accessibility'),
    bestPractices: score('best-practices'),
    seo: score('seo'),
    fcp: metric('first-contentful-paint'),
    lcp: metric('largest-contentful-paint'),
    tbt: metric('total-blocking-time'),
    cls: metric('cumulative-layout-shift'),
    speedIndex: metric('speed-index'),
    bytes: metric('total-byte-weight'),
    benchmarkIndex: lhr.environment ? lhr.environment.benchmarkIndex : null,
    finalUrl: lhr.finalDisplayedUrl || lhr.finalUrl,
    runtimeError: lhr.runtimeError ? lhr.runtimeError.message : null,
    failingAccessibility: failing('accessibility'),
    opportunities,
  };
}

/** The run with the median performance score, so one noisy run does not decide the cell. */
function medianRun(summaries) {
  const sorted = [...summaries].sort((a, b) => (a.performance ?? -1) - (b.performance ?? -1));
  return sorted[Math.floor((sorted.length - 1) / 2)];
}

const seconds = (ms) => (ms == null ? '—' : (ms / 1000).toFixed(2));
const millis = (ms) => (ms == null ? '—' : Math.round(ms));

function renderLighthouse({ target, startedAt, cells }) {
  const lines = [
    '# Lighthouse',
    '',
    `Target: ${target} · started ${startedAt} · ${LIGHTHOUSE}, simulated throttling`,
    '',
    '| Page | Locale | Form | Perf | A11y | BP | SEO | FCP (s) | LCP (s) | TBT (ms) | CLS | SI (s) | KB | CPU index | Top savings | Failing a11y audits |',
    '|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|',
  ];
  for (const { page, locale, form, result, error } of cells) {
    if (error || !result) {
      lines.push(`| ${page} | ${locale} | ${form} | ${error || 'no result'} |||||||||||||`);
      continue;
    }
    const r = result;
    lines.push(`| ${page} | ${locale} | ${form} | ${r.performance} | ${r.accessibility} | ${r.bestPractices} | ${r.seo} | ${seconds(r.fcp)} | ${seconds(r.lcp)} | ${millis(r.tbt)} | ${r.cls == null ? '—' : r.cls.toFixed(3)} | ${seconds(r.speedIndex)} | ${r.bytes == null ? '—' : Math.round(r.bytes / 1024)} | ${r.benchmarkIndex ?? '—'} | ${r.opportunities.join(', ') || '—'} | ${r.failingAccessibility.join(', ') || '—'} |`);
  }
  return `${lines.join('\n')}\n`;
}

function runOnce(config, cell, index, outDir, headersPath) {
  const jsonPath = path.join(outDir, `${cell.page}-${cell.locale}-${cell.form}-${index}.json`);
  const args = lighthouseArgs({ url: cell.url, form: cell.form, jsonPath, headersPath, localTarget: config.localTarget });
  // npx is a .cmd file on Windows, which only a shell runs; the arguments are quoted into one command line.
  const child = spawnSync(['npx', ...args.map(quote)].join(' '), { shell: true, stdio: ['ignore', 'inherit', 'inherit'], timeout: 300000 });
  if (child.status !== 0 || !fs.existsSync(jsonPath)) return { error: `lighthouse exited with ${child.status ?? child.signal}` };
  return { result: summariseLighthouse(JSON.parse(fs.readFileSync(jsonPath, 'utf8'))) };
}

async function main(argv) {
  const args = parseArgs(argv);
  const config = buildConfig(argv);
  const locales = listArg(args.locales, ['ar', 'en']);
  const forms = listArg(args.forms, ['mobile', 'desktop']);
  const runs = Math.max(1, Number(args.runs) || 1);
  // Lighthouse cannot sign in, so the signed-in pages are left to the axe audit.
  const pages = selectAuditPages(config.pages).filter((page) => !page.login);
  const outDir = path.join(config.out, 'lighthouse');
  const startedAt = new Date().toISOString();
  fs.mkdirSync(outDir, { recursive: true });

  const cells = [];
  for (const page of pages) {
    for (const locale of locales) {
      const headersPath = path.join(outDir, `headers-${locale}.json`);
      fs.writeFileSync(headersPath, JSON.stringify({ Cookie: `NEXT_LOCALE=${locale}` }));
      for (const form of forms) {
        const cell = { page: page.id, locale, form, url: config.target + page.route };
        const attempts = Array.from({ length: runs }, (_, i) => runOnce(config, cell, i + 1, outDir, headersPath));
        const results = attempts.filter((attempt) => attempt.result).map((attempt) => attempt.result);
        cells.push(results.length ? { ...cell, result: medianRun(results) } : { ...cell, error: attempts[0].error });
        const last = cells.at(-1);
        console.log(`lighthouse ${cell.page} ${locale} ${form}: ${last.error || `perf ${last.result.performance}, a11y ${last.result.accessibility}, LCP ${seconds(last.result.lcp)} s, TBT ${millis(last.result.tbt)} ms`}`);
      }
    }
  }

  fs.writeFileSync(path.join(config.out, 'lighthouse.json'), JSON.stringify({ target: config.target, startedAt, cells }, null, 2));
  fs.writeFileSync(path.join(config.out, 'lighthouse.md'), renderLighthouse({ target: config.target, startedAt, cells }));
  console.log(`report: ${path.join(config.out, 'lighthouse.md')}`);
}

if (require.main === module) {
  main(process.argv.slice(2)).catch((error) => {
    console.error(error);
    process.exit(1);
  });
}

module.exports = { lighthouseArgs, summariseLighthouse, medianRun, renderLighthouse };
