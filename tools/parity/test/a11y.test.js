const test = require('node:test');
const assert = require('node:assert/strict');
const { planApiRequest, STUB_TOKEN } = require('../src/a11y/session-stub');
const { documentIssues } = require('../src/a11y/axe');
const { countByImpact, aggregateRules, aggregateContrast, renderMarkdown } = require('../src/a11y/report');
const { lighthouseArgs, summariseLighthouse, medianRun } = require('../src/a11y/lighthouse');
const { selectAuditPages, selectAuditStates } = require('../src/a11y/pages');

const stubbed = { authorization: `Bearer ${STUB_TOKEN}` };

test('the session stub answers its fixtures, forwards stub reads and never sends a write', () => {
  const profile = planApiRequest({ method: 'GET', pathname: '/api/v1/account/profile', headers: stubbed, locale: 'ar' });
  assert.equal(profile.action, 'fixture');
  assert.equal(profile.fixture.language, 'ar');
  assert.equal(planApiRequest({ method: 'GET', pathname: '/api/v1/account/summary', headers: stubbed }).fixture.ads_by_status.active, 0);
  assert.equal(planApiRequest({ method: 'GET', pathname: '/api/v1/conversations/unread-count', headers: stubbed }).action, 'fixture');
  assert.equal(planApiRequest({ method: 'GET', pathname: '/api/v1/categories', headers: stubbed }).action, 'forward');
  assert.equal(planApiRequest({ method: 'POST', pathname: '/api/v1/ads', headers: stubbed }).action, 'swallow');
  assert.equal(planApiRequest({ method: 'GET', pathname: '/api/v1/categories', headers: {} }).action, 'pass');
  assert.equal(planApiRequest({ method: 'OPTIONS', pathname: '/api/v1/categories', headers: {} }).action, 'preflight');
});

test('document checks follow the locale and the page structure', () => {
  const facts = { lang: 'ar', dir: 'rtl', mains: 1, h1: 1, headings: [1, 2, 3, 2] };
  assert.deepEqual(documentIssues(facts, 'ar'), []);
  assert.deepEqual(documentIssues({ ...facts, dir: 'ltr' }, 'ar'), ['html dir is "ltr", expected "rtl"']);
  assert.match(documentIssues({ ...facts, headings: [1, 3] }, 'ar')[0], /jumps from h1 to h3/);
  assert.match(documentIssues({ ...facts, h1: 0 }, 'en').join(), /lang is "ar"/);
});

const violation = (id, impact, nodeCount) => ({ id, impact, help: id, nodeCount, nodes: [{ target: `#${id}` }] });

test('axe counts are per node and rules aggregate across runs, worst first', () => {
  const runs = [
    { page: 'home', locale: 'ar', viewport: 390, issues: [], violations: [violation('label', 'critical', 2), violation('region', 'moderate', 5)] },
    { page: 'login', locale: 'en', viewport: 390, issues: [], violations: [violation('label', 'critical', 1)] },
  ];
  assert.deepEqual(countByImpact(runs[0].violations), { critical: 2, serious: 0, moderate: 5, minor: 0 });
  const rules = aggregateRules(runs);
  assert.deepEqual(rules.map((rule) => [rule.id, rule.runs, rule.nodes]), [['label', 2, 3], ['region', 1, 5]]);
  assert.match(renderMarkdown({ target: 't', startedAt: 's', session: 'stub', runs, rtl: [] }), /critical 3, serious 0, moderate 5/);
});

test('lighthouse runs desktop with the preset and lifts web security only for a local build', () => {
  const base = { url: 'http://localhost:3000/', jsonPath: 'a.json', headersPath: 'h.json' };
  const mobileLocal = lighthouseArgs({ ...base, form: 'mobile', localTarget: true });
  assert.ok(mobileLocal.includes('--chrome-flags=--headless=new --disable-web-security'));
  assert.ok(!mobileLocal.includes('--preset=desktop'));
  const desktopLive = lighthouseArgs({ ...base, form: 'desktop', localTarget: false });
  assert.ok(desktopLive.includes('--preset=desktop'));
  assert.ok(desktopLive.includes('--chrome-flags=--headless=new'));
});

test('lighthouse summaries keep scores, metrics, failing audits and the median run', () => {
  const lhr = {
    categories: { performance: { score: 0.91, auditRefs: [] }, accessibility: { score: 0.95, auditRefs: [{ id: 'label', weight: 7 }, { id: 'tabindex', weight: 7 }] } },
    audits: {
      'largest-contentful-paint': { numericValue: 2100 },
      label: { score: 0 },
      tabindex: { score: 1 },
      'unused-javascript': { score: 0.5, metricSavings: { LCP: 300, FCP: 0 } },
    },
    environment: { benchmarkIndex: 1200 },
  };
  const summary = summariseLighthouse(lhr);
  assert.equal(summary.performance, 91);
  assert.equal(summary.lcp, 2100);
  assert.deepEqual(summary.failingAccessibility, ['label']);
  assert.deepEqual(summary.opportunities, ['unused-javascript (~300 ms)']);
  assert.equal(medianRun([{ performance: 50 }, { performance: 90 }, { performance: 70 }]).performance, 70);
});

test('the audit covers the nine FE-16.7 pages, two of them signed in', () => {
  const pages = selectAuditPages(null);
  assert.equal(pages.length, 9);
  assert.deepEqual(pages.filter((page) => page.login).map((page) => page.id), ['account', 'post-ad']);
});

test('opened states inherit their page route and follow the page filter', () => {
  const states = selectAuditStates(['login']);
  assert.deepEqual(states.map((state) => state.id), ['login+errors']);
  assert.equal(states[0].route, '/login');
  assert.ok(states[0].click && states[0].waitFor);
  assert.equal(selectAuditStates(null).length, 3);
});

test('contrast failures group by colour pair with the worst ratio kept', () => {
  const contrast = [
    { fg: '#f38057', bg: '#ffffff', ratio: 2.6, expected: '4.5:1' },
    { fg: '#f38057', bg: '#ffffff', ratio: 2.4, expected: '4.5:1' },
    { fg: '#9aa0a6', bg: '#ffffff', ratio: 2.6, expected: '4.5:1' },
  ];
  const runs = [{ page: 'home', violations: [{ id: 'color-contrast', impact: 'serious', nodeCount: 3, nodes: [], contrast }] }];
  const pairs = aggregateContrast(runs);
  assert.equal(pairs.length, 2);
  assert.deepEqual([pairs[0].fg, pairs[0].nodes, pairs[0].ratio], ['#f38057', 2, 2.4]);
});
