// Runs axe-core inside a loaded page and reads the document facts that axe does not judge on its own.
const fs = require('fs');

const AXE_SOURCE = fs.readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');
const AXE_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];
const MAX_NODES_PER_RULE = 8;

async function runAxe(page) {
  // evaluate() is not subject to the page's script policy, unlike an injected <script> tag.
  await page.evaluate(AXE_SOURCE);
  return page.evaluate(async ({ tags, maxNodes }) => {
    const result = await window.axe.run(document, { runOnly: { type: 'tag', values: tags }, resultTypes: ['violations'] });
    return result.violations.map((violation) => ({
      id: violation.id,
      impact: violation.impact,
      help: violation.help,
      helpUrl: violation.helpUrl,
      nodeCount: violation.nodes.length,
      // Every failing colour pair, so the report can list them for the design owner (colours are not changed here).
      contrast: violation.id === 'color-contrast' ? violation.nodes.map((node) => {
        const data = (node.any[0] && node.any[0].data) || {};
        return { fg: data.fgColor, bg: data.bgColor, ratio: data.contrastRatio, expected: data.expectedContrastRatio };
      }) : undefined,
      nodes: violation.nodes.slice(0, maxNodes).map((node) => ({
        target: node.target.join(' '),
        html: node.html.slice(0, 240),
        summary: (node.failureSummary || '').slice(0, 400),
      })),
    }));
  }, { tags: AXE_TAGS, maxNodes: MAX_NODES_PER_RULE });
}

// Runs in the page: language, direction and the structure a screen reader user navigates by.
function readDocumentFacts() {
  const visible = (el) => el.getClientRects().length > 0;
  return {
    lang: document.documentElement.lang,
    dir: document.documentElement.dir,
    title: document.title,
    path: location.pathname,
    h1: [...document.querySelectorAll('h1')].filter(visible).length,
    mains: document.querySelectorAll('main, [role=main]').length,
    headings: [...document.querySelectorAll('h1, h2, h3, h4, h5, h6')].filter(visible).map((h) => Number(h.tagName[1])),
  };
}

/** Problems in the document facts: the language and direction must follow the locale, one main, one visible h1. */
function documentIssues(facts, locale) {
  const issues = [];
  const expectedDir = locale === 'ar' ? 'rtl' : 'ltr';
  if (facts.lang !== locale) issues.push(`html lang is "${facts.lang}", expected "${locale}"`);
  if (facts.dir !== expectedDir) issues.push(`html dir is "${facts.dir}", expected "${expectedDir}"`);
  if (facts.mains !== 1) issues.push(`${facts.mains} main landmarks, expected 1`);
  if (facts.h1 !== 1) issues.push(`${facts.h1} visible h1 headings, expected 1`);
  const skipped = facts.headings.findIndex((level, i) => i > 0 && level > facts.headings[i - 1] + 1);
  if (skipped > 0) issues.push(`heading level jumps from h${facts.headings[skipped - 1]} to h${facts.headings[skipped]}`);
  return issues;
}

module.exports = { runAxe, readDocumentFacts, documentIssues, AXE_TAGS };
