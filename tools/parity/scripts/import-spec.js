#!/usr/bin/env node
// Trims the reference behaviour inventories (one JSON per stream) into spec/<stream>.json and copies the
// reference icon library into reference-icons/. Usage: node scripts/import-spec.js <parity-spec dir>
const fs = require('fs');
const path = require('path');

const KEPT = ['page', 'id', 'component', 'refSelector', 'kind', 'interactive', 'viewports', 'figma', 'behaviour', 'source', 'notes'];

function trimElement(element) {
  return Object.fromEntries(KEPT.filter((k) => element[k] !== undefined).map((k) => [k, element[k]]));
}

// Streams describe flows in two shapes; both become { pages, element, trigger, to }.
function normaliseFlows(raw) {
  const list = Array.isArray(raw.flows) ? raw.flows : (raw.flows && raw.flows.html) || [];
  return list.map((f) => ({
    pages: String(f.page || f.from || '').match(/[\w-]+\.html/g) || [],
    element: f.element,
    trigger: String(f.event || f.trigger || 'click').split(' ')[0].toLowerCase(),
    to: f.target !== undefined ? f.target : f.to,
  }));
}

function importStream(file, outDir) {
  const raw = JSON.parse(fs.readFileSync(file, 'utf8'));
  const trimmed = { group: raw.group, pages: raw.pages, elements: raw.elements.map(trimElement), flows: normaliseFlows(raw) };
  const name = String(raw.group).replace(/[^a-z0-9]+/gi, '-').replace(/-$/, '').toLowerCase();
  fs.writeFileSync(path.join(outDir, `${name}.json`), JSON.stringify(trimmed, null, 1));
  return `${raw.group}: ${trimmed.elements.length} elements, ${trimmed.flows.length} flows`;
}

function copyIcons(sourceDir, destDir) {
  fs.mkdirSync(destDir, { recursive: true });
  for (const file of fs.readdirSync(sourceDir)) fs.copyFileSync(path.join(sourceDir, file), path.join(destDir, file));
}

const [source] = process.argv.slice(2);
if (!source) throw new Error('usage: node scripts/import-spec.js <parity-spec dir>');
const root = path.resolve(__dirname, '..');
fs.mkdirSync(path.join(root, 'spec'), { recursive: true });
for (const file of fs.readdirSync(source).filter((f) => f.endsWith('.json'))) console.log(importStream(path.join(source, file), path.join(root, 'spec')));
if (fs.existsSync(path.join(source, 'icons'))) copyIcons(path.join(source, 'icons'), path.join(root, 'reference-icons'));
