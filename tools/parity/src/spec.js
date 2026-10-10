// Reads the reference behaviour inventories in spec/ (one file per stream).
const fs = require('fs');
const path = require('path');

const SPEC_DIR = path.join(__dirname, '..', 'spec');

function loadStreams() {
  if (!fs.existsSync(SPEC_DIR)) return [];
  return fs.readdirSync(SPEC_DIR).filter((f) => f.endsWith('.json')).map((f) => JSON.parse(fs.readFileSync(path.join(SPEC_DIR, f), 'utf8')));
}

const pageIdOf = (file) => file.replace(/\.html$/, '');

/** Every navigation the inventories describe: click an element, land on a reference page. */
function navigationFlows() {
  const streams = loadStreams();
  const elements = new Map(streams.flatMap((s) => s.elements).map((e) => [e.id, e]));
  const seen = new Set();
  const flows = [];
  for (const flow of streams.flatMap((s) => s.flows)) {
    const element = elements.get(flow.element);
    if (flow.trigger !== 'click' || !element || !/\.html$/.test(flow.to || '')) continue;
    const pages = element.id.startsWith('chrome.') ? flow.pages.slice(0, 1) : flow.pages;
    for (const page of pages) {
      const key = `${page}|${flow.element}`;
      if (seen.has(key)) continue;
      seen.add(key);
      flows.push({ page: pageIdOf(page), element: flow.element, refSelector: element.refSelector, to: pageIdOf(flow.to), viewports: element.viewports || [1440] });
    }
  }
  return flows;
}

const usedByScenarios = new Set();
const markUsed = (id) => usedByScenarios.add(id);
const usedIds = () => [...usedByScenarios];

/** Which inventory elements something in the gate exercises individually (a flow, a scenario step or a mapped component). */
function coverage(targets, swept = new Set()) {
  const flowed = new Set(navigationFlows().map((f) => f.element));
  return loadStreams().map((stream) => {
    const withBehaviour = stream.elements.filter((e) => e.behaviour && e.behaviour.length);
    const exercised = (e) => flowed.has(e.id) || usedByScenarios.has(e.id) || Boolean(targets[e.id]) || swept.has(e.id);
    return {
      group: stream.group, elements: stream.elements.length, withBehaviour: withBehaviour.length,
      exercised: stream.elements.filter(exercised).length,
      unexercised: stream.elements.filter((e) => !exercised(e)).map((e) => e.id),
    };
  });
}

module.exports = { loadStreams, navigationFlows, pageIdOf, markUsed, usedIds, coverage };
