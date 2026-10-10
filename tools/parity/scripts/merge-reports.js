#!/usr/bin/env node
// Merges the report.json files of several runs (a full baseline is run in chunks) into one report.json + report.md.
// Usage: node scripts/merge-reports.js <out dir> <run dir> [<run dir> ...]
const fs = require('fs');
const path = require('path');
const { writeReports } = require('../src/report');

function readRun(dir) {
  return JSON.parse(fs.readFileSync(path.join(dir, 'report.json'), 'utf8'));
}

// An element counts as exercised when any chunk exercised it, so the unexercised set is the intersection.
function mergeCoverage(runs) {
  const groups = new Map();
  for (const entry of runs.flatMap((r) => r.coverage || [])) {
    const seen = groups.get(entry.group);
    if (!seen) { groups.set(entry.group, { ...entry }); continue; }
    seen.unexercised = seen.unexercised.filter((id) => entry.unexercised.includes(id));
  }
  return [...groups.values()].map((g) => ({ ...g, exercised: g.elements - g.unexercised.length }));
}

function merge(runs) {
  const findings = new Map();
  const skipped = new Map();
  const compared = [];
  for (const run of runs) {
    run.findings.forEach((f) => findings.set(f.id, f));
    run.skipped.forEach((s) => skipped.set(`${s.what}|${s.reason}`, s));
    compared.push(...(run.compared || []));
  }
  const last = runs[runs.length - 1];
  return { meta: { ...last.meta, runs: runs.length }, findings: [...findings.values()], skipped: [...skipped.values()], referenceOnly: last.referenceOnly, compared, coverage: mergeCoverage(runs) };
}

const [outDir, ...runDirs] = process.argv.slice(2);
if (!outDir || !runDirs.length) throw new Error('usage: node scripts/merge-reports.js <out dir> <run dir> [...]');
const totals = writeReports(path.resolve(outDir), merge(runDirs.map(readRun)));
runDirs.forEach((dir) => {
  const diffs = path.join(dir, 'diffs');
  if (fs.existsSync(diffs)) fs.cpSync(diffs, path.join(path.resolve(outDir), 'diffs'), { recursive: true });
});
console.log(`${totals.findings} findings merged from ${runDirs.length} runs`);
