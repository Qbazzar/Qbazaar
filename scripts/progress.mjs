#!/usr/bin/env node
// Computes V2 progress from qbazaar-contracts/MILESTONES-V2.md and rewrites
// the block between the progress markers in README.md and ROADMAP.md, plus
// the counts in the milestones Status table.
//
//   node scripts/progress.mjs          update the files
//   node scripts/progress.mjs --check  exit 1 if any file is stale

import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const MILESTONES = join(ROOT, 'qbazaar-contracts', 'MILESTONES-V2.md');
const TARGETS = [join(ROOT, 'README.md'), join(ROOT, 'qbazaar-contracts', 'ROADMAP.md')];

const START_MARKER = '<!-- progress:start -->';
const END_MARKER = '<!-- progress:end -->';
const BAR_WIDTH = 10;

// Same shape the issue-sync script reads: "| ID | ... | [Px] | ...".
const TASK_ROW = /^\| ([A-Z]{2,3}-\d+\.\d+) \|.*\| \[P\d\] \|/;
const PHASE_HEADING = /^## Sprint [\d+]+ — (M\w+) (.+)$/;
const SUBSECTION_HEADING = /^### (.+)$/;

function parsePhases(markdown) {
  const phases = [];
  let phase = null;
  let inDoneTable = false;

  for (const line of markdown.split(/\r?\n/)) {
    const phaseMatch = line.match(PHASE_HEADING);
    if (phaseMatch) {
      phase = { id: phaseMatch[1], title: stripParenthetical(phaseMatch[2]), total: 0, done: 0 };
      phases.push(phase);
      inDoneTable = false;
      continue;
    }

    const subsectionMatch = line.match(SUBSECTION_HEADING);
    if (subsectionMatch) {
      inDoneTable = subsectionMatch[1].startsWith('Done');
      continue;
    }

    if (phase && TASK_ROW.test(line)) {
      phase.total += 1;
      if (inDoneTable) phase.done += 1;
    }
  }

  return phases;
}

function stripParenthetical(title) {
  return title.replace(/\s*\(.*\)\s*$/, '');
}

function percent(done, total) {
  return total === 0 ? 0 : Math.floor((done / total) * 100);
}

function bar(done, total) {
  const filled = total === 0 ? 0 : Math.round((done / total) * BAR_WIDTH);
  return '█'.repeat(filled) + '░'.repeat(BAR_WIDTH - filled);
}

function renderBlock(phases) {
  const total = phases.reduce((sum, p) => sum + p.total, 0);
  const done = phases.reduce((sum, p) => sum + p.done, 0);
  const current = phases.find((p) => p.done < p.total) ?? phases[phases.length - 1];
  const idWidth = Math.max(...phases.map((p) => p.id.length));

  const rows = phases.map((p) => {
    const pct = `${percent(p.done, p.total)}%`.padStart(4);
    const count = `(${p.done}/${p.total})`.padEnd(9);
    return `${p.id.padEnd(idWidth)}  ${bar(p.done, p.total)}  ${pct} ${count} ${p.title}`;
  });

  return [
    START_MARKER,
    `**Current phase:** ${current.id} ${current.title} · **Overall:** ${percent(done, total)}% (${done}/${total} tasks)`,
    '',
    '```text',
    ...rows,
    '```',
    '',
    '_Generated from [`MILESTONES-V2.md`](https://github.com/Qbazzar/Qbazaar/blob/main/qbazaar-contracts/MILESTONES-V2.md) by `node scripts/progress.mjs`; a task counts as done when it sits in a **Done** table._',
    END_MARKER,
  ];
}

function replaceBlock(content, blockLines, file) {
  const eol = content.includes('\r\n') ? '\r\n' : '\n';
  const start = content.indexOf(START_MARKER);
  const end = content.indexOf(END_MARKER);
  if (start === -1 || end === -1 || end < start) {
    throw new Error(`${relative(ROOT, file)}: missing ${START_MARKER} / ${END_MARKER}`);
  }
  return content.slice(0, start) + blockLines.join(eol) + content.slice(end + END_MARKER.length);
}

// Rewrites the Tasks / Done / Open cells of the Status table; the State column stays hand-written.
function updateStatusTable(markdown, phases) {
  const byId = new Map(phases.map((p) => [p.id, p]));
  const totals = {
    total: phases.reduce((sum, p) => sum + p.total, 0),
    done: phases.reduce((sum, p) => sum + p.done, 0),
  };
  const lines = markdown.split('\n');
  let inStatus = false;

  return lines
    .map((line) => {
      if (line.startsWith('## ')) inStatus = line.trimEnd() === '## Status';
      if (!inStatus || !line.startsWith('| ')) return line;

      const cr = line.endsWith('\r') ? '\r' : '';
      const cells = line.trimEnd().slice(1, -1).split('|').map((cell) => cell.trim());
      if (cells.length !== 6) return line;

      const [name] = cells;
      if (name === '**Total**') {
        cells.splice(2, 3, `**${totals.total}**`, `**${totals.done}**`, `**${totals.total - totals.done}**`);
      } else {
        const phase = byId.get(name.split(' ')[0]);
        if (!phase) return line;
        cells.splice(2, 3, String(phase.total), String(phase.done), String(phase.total - phase.done));
      }
      return `|${cells.map((cell) => (cell ? ` ${cell} ` : ' ')).join('|')}|${cr}`;
    })
    .join('\n');
}

function main() {
  const checkOnly = process.argv.includes('--check');
  const milestones = readFileSync(MILESTONES, 'utf8');
  const phases = parsePhases(milestones);
  if (phases.length === 0) throw new Error('No "## Sprint N — Mx" phases found in MILESTONES-V2.md');

  const block = renderBlock(phases);
  const updates = [[MILESTONES, milestones, updateStatusTable(milestones, phases)]];
  for (const file of TARGETS) {
    const content = readFileSync(file, 'utf8');
    updates.push([file, content, replaceBlock(content, block, file)]);
  }

  const stale = updates.filter(([, before, after]) => before !== after);
  if (checkOnly) {
    for (const [file] of stale) console.error(`stale: ${relative(ROOT, file)}`);
    if (stale.length > 0) {
      console.error('Run "node scripts/progress.mjs" and commit the result.');
      process.exit(1);
    }
    return;
  }

  for (const [file, , after] of stale) {
    writeFileSync(file, after);
    console.log(`updated: ${relative(ROOT, file)}`);
  }
  console.log(block.slice(1, -1).join('\n'));
}

main();
