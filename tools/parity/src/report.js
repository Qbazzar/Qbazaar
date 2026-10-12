// report.json (machine-readable) and report.md (summary by page and category).
const fs = require('fs');
const path = require('path');
const { CATEGORIES } = require('./findings');

const SEVERITIES = ['high', 'medium', 'low'];
const MD_ROWS_PER_PAGE = 40;
const RECURRING_MIN = 4;
const RECURRING_ROWS = 40;

function count(list, keyOf) {
  const out = {};
  for (const item of list) out[keyOf(item)] = (out[keyOf(item)] || 0) + 1;
  return out;
}

function table(headers, rows) {
  const line = (cells) => `| ${cells.join(' | ')} |`;
  return [line(headers), line(headers.map(() => '---')), ...rows.map(line)].join('\n');
}

function totalsByPage(findings, pages) {
  const rows = pages.map((page) => {
    const mine = findings.filter((f) => f.page === page);
    const byCategory = count(mine, (f) => f.category);
    return [page, ...CATEGORIES.map((c) => byCategory[c] || 0), mine.length];
  });
  return table(['page', ...CATEGORIES, 'total'], rows);
}

// The same difference repeated across components and pages is one design decision to fix once.
function recurringPatterns(findings) {
  const groups = new Map();
  for (const f of findings) {
    if (!f.property || f.category === 'pixels') continue;
    const key = [f.category, f.property, f.ref, f.target].join('|');
    const entry = groups.get(key) || { f, count: 0, pages: new Set(), examples: [] };
    entry.count++;
    entry.pages.add(f.page);
    if (entry.examples.length < 2) entry.examples.push(f.component);
    groups.set(key, entry);
  }
  const rows = [...groups.values()].filter((g) => g.count >= RECURRING_MIN).sort((a, b) => b.count - a.count).slice(0, RECURRING_ROWS)
    .map((g) => [g.count, g.pages.size, g.f.category, g.f.property, cell(g.f.ref), cell(g.f.target), cell(g.examples.join(', '))]);
  return rows.length ? table(['count', 'pages', 'category', 'property', 'reference', 'target', 'examples'], rows) : '';
}

function pageSection(page, findings) {
  const mine = findings.filter((f) => f.page === page);
  const order = { high: 0, medium: 1, low: 2 };
  mine.sort((a, b) => order[a.severity] - order[b.severity]);
  const rows = mine.slice(0, MD_ROWS_PER_PAGE).map((f) => [f.severity, f.category, f.viewport, `${f.component}`, f.state, f.property || '', cell(f.ref), cell(f.target), f.id]);
  const more = mine.length > MD_ROWS_PER_PAGE ? `\n\n${mine.length - MD_ROWS_PER_PAGE} more in report.json.` : '';
  return `### ${page} (${mine.length})\n\n${table(['sev', 'category', 'vp', 'component', 'state', 'property', 'reference', 'target', 'id'], rows)}${more}`;
}

const cell = (value) => String(value === undefined ? '' : value).replaceAll('|', '\\|').replace(/\s+/g, ' ').slice(0, 90);

function buildMarkdown(result) {
  const { meta, findings, skipped, referenceOnly, coverage } = result;
  const pages = [...new Set(findings.map((f) => f.page))];
  const bySeverity = count(findings, (f) => f.severity);
  const byCategory = count(findings, (f) => f.category);
  return [
    '# Design parity report',
    `Target ${meta.target} · reference ${meta.reference} · locale ${meta.locale} · viewports ${meta.viewports.join(', ')} · ${meta.finishedAt}`,
    `**${findings.length} findings** (${SEVERITIES.map((s) => `${bySeverity[s] || 0} ${s}`).join(', ')})`,
    '## By category',
    table(['category', 'findings'], CATEGORIES.map((c) => [c, byCategory[c] || 0])),
    '## Most repeated differences',
    recurringPatterns(findings),
    '## By page',
    totalsByPage(findings, pages),
    coverage && coverage.length ? `## Inventory coverage

Elements of the reference inventories that a flow, a scenario step or a mapped component exercises individually. All other interactive elements still get hover, focus and active checks through the text sweep when their text matches.

${table(['stream', 'elements', 'with behaviour', 'exercised individually', 'not individually'], coverage.map((c) => [c.group, c.elements, c.withBehaviour, c.exercised, c.unexercised.length]))}` : '',
    skipped.length ? `## Skipped\n\n${skipped.map((s) => `- ${s.what}: ${s.reason}`).join('\n')}` : '',
    referenceOnly.length ? `## Reference pages without a target route\n\n${referenceOnly.map((r) => `- ${r.reference}: ${r.note}`).join('\n')}` : '',
    '## Findings per page',
    ...pages.map((p) => pageSection(p, findings)),
  ].filter(Boolean).join('\n\n');
}

function writeReports(outDir, result) {
  fs.mkdirSync(outDir, { recursive: true });
  const totals = { findings: result.findings.length, byCategory: count(result.findings, (f) => f.category), bySeverity: count(result.findings, (f) => f.severity), byPage: count(result.findings, (f) => f.page) };
  fs.writeFileSync(path.join(outDir, 'report.json'), JSON.stringify({ ...result, totals }, null, 1));
  fs.writeFileSync(path.join(outDir, 'report.md'), buildMarkdown(result));
  return totals;
}

module.exports = { writeReports, buildMarkdown };
