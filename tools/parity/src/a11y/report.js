// Turns the axe runs into counts per page and per rule, and renders both as Markdown.
const IMPACTS = ['critical', 'serious', 'moderate', 'minor'];

function countByImpact(violations) {
  const counts = Object.fromEntries(IMPACTS.map((impact) => [impact, 0]));
  for (const violation of violations) counts[violation.impact] = (counts[violation.impact] || 0) + violation.nodeCount;
  return counts;
}

/** One row per rule across every run: how many runs and nodes it hit, and one example target. */
function aggregateRules(runs) {
  const rules = new Map();
  for (const run of runs) {
    for (const violation of run.violations || []) {
      const rule = rules.get(violation.id) || { id: violation.id, impact: violation.impact, help: violation.help, runs: 0, nodes: 0, pages: new Set(), example: violation.nodes[0] };
      rule.runs += 1;
      rule.nodes += violation.nodeCount;
      rule.pages.add(run.page);
      rules.set(violation.id, rule);
    }
  }
  return [...rules.values()].sort((a, b) => IMPACTS.indexOf(a.impact) - IMPACTS.indexOf(b.impact) || b.nodes - a.nodes);
}

/** Failing colour pairs across every run, the most frequent first. */
function aggregateContrast(runs) {
  const pairs = new Map();
  for (const run of runs) {
    for (const violation of run.violations || []) {
      for (const node of violation.contrast || []) {
        const key = `${node.fg}|${node.bg}|${node.expected}`;
        const pair = pairs.get(key) || { fg: node.fg, bg: node.bg, expected: node.expected, ratio: node.ratio, nodes: 0, pages: new Set() };
        pair.nodes += 1;
        pair.ratio = Math.min(pair.ratio, node.ratio);
        pair.pages.add(run.page);
        pairs.set(key, pair);
      }
    }
  }
  return [...pairs.values()].sort((a, b) => b.nodes - a.nodes);
}

function totals(runs) {
  const sum = Object.fromEntries(IMPACTS.map((impact) => [impact, 0]));
  for (const run of runs) {
    const counts = countByImpact(run.violations || []);
    for (const impact of IMPACTS) sum[impact] += counts[impact];
  }
  return sum;
}

const cell = (text) => String(text ?? '').replaceAll('|', '\\|').replace(/\s+/g, ' ').trim();

function renderRuns(runs) {
  const lines = ['| Page | Locale | Width | Critical | Serious | Moderate | Minor | Document checks |', '|---|---|---|---|---|---|---|---|'];
  for (const run of runs) {
    if (run.error) {
      lines.push(`| ${run.page} | ${run.locale} | ${run.viewport} | — | — | — | — | ${cell(`not audited: ${run.error}`)} |`);
      continue;
    }
    const counts = countByImpact(run.violations);
    const doc = run.issues.length ? run.issues.join('; ') : 'ok';
    lines.push(`| ${run.page} | ${run.locale} | ${run.viewport} | ${IMPACTS.map((impact) => counts[impact]).join(' | ')} | ${cell(doc)} |`);
  }
  return lines.join('\n');
}

function renderRules(runs) {
  const rules = aggregateRules(runs);
  if (!rules.length) return 'No axe violations.';
  const lines = ['| Rule | Impact | Runs | Nodes | Pages | What it means | Example |', '|---|---|---|---|---|---|---|'];
  for (const rule of rules) {
    const example = rule.example ? `\`${cell(rule.example.target).slice(0, 90)}\`` : '';
    lines.push(`| ${rule.id} | ${rule.impact} | ${rule.runs} | ${rule.nodes} | ${[...rule.pages].join(', ')} | ${cell(rule.help)} | ${example} |`);
  }
  return lines.join('\n');
}

const CONTRAST_ROWS = 25;

function renderContrast(runs) {
  const pairs = aggregateContrast(runs);
  if (!pairs.length) return 'No contrast failures.';
  const lines = ['| Text | Background | Ratio | Needed | Nodes | Pages |', '|---|---|---|---|---|---|'];
  for (const pair of pairs.slice(0, CONTRAST_ROWS)) {
    lines.push(`| ${pair.fg} | ${pair.bg} | ${pair.ratio} | ${pair.expected} | ${pair.nodes} | ${[...pair.pages].join(', ')} |`);
  }
  if (pairs.length > CONTRAST_ROWS) lines.push('', `${pairs.length - CONTRAST_ROWS} more pairs in a11y.json.`);
  return lines.join('\n');
}

function renderRtl(rtlRuns) {
  if (!rtlRuns.length) return 'Not run.';
  const lines = ['| Page | Width | Arabic dir | Controls in English order |', '|---|---|---|---|'];
  for (const run of rtlRuns) {
    const pairs = run.error ? `not checked: ${run.error}` : run.pairs.map(([a, b]) => `${a} before ${b}`).join('; ') || 'none';
    lines.push(`| ${run.page} | ${run.viewport} | ${run.dir || '—'} | ${cell(pairs)} |`);
  }
  return lines.join('\n');
}

function renderMarkdown({ target, startedAt, session, runs, rtl }) {
  const sum = totals(runs);
  return [
    `# axe and RTL audit`,
    '',
    `Target: ${target} · started ${startedAt} · signed-in pages: ${session}`,
    '',
    `Totals (nodes): critical ${sum.critical}, serious ${sum.serious}, moderate ${sum.moderate}, minor ${sum.minor}`,
    '',
    '## Per page',
    '',
    renderRuns(runs),
    '',
    '## Per rule',
    '',
    renderRules(runs),
    '',
    '## Colour contrast (reported only: the design colours are kept)',
    '',
    renderContrast(runs),
    '',
    '## RTL mirroring',
    '',
    renderRtl(rtl),
    '',
  ].join('\n');
}

module.exports = { IMPACTS, countByImpact, aggregateRules, aggregateContrast, totals, renderMarkdown };
