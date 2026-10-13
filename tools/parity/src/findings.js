// Finding records: stable ids, severity and the category taxonomy of the report.
const crypto = require('crypto');
const { categoryOf, severityOf } = require('./props');

const CATEGORIES = ['typography', 'colour', 'spacing', 'states', 'motion', 'behaviour', 'icons', 'flows', 'pixels', 'missing', 'rtl'];

// Differences the owner accepted once for the whole site: counted, never reported per element.
const ACCEPTED_REASONS = {
  'reference-demo-state': 'Texts the prototype shows only for its demo user or its current language (reference-text.json).',
  'reference-sample-data': 'Sample names in the prototype (cities, categories, sellers, chats); the target shows live data (reference-text.json).',
  'focus-ring': 'The reference declares no focus style of its own (the browser draws its default ring); the target keeps its accessible focus ring by the owner\'s rule. Focus differences in outline, ring shadow and border colour are accepted unless the reference itself changes that property on focus (the auth inputs\' border colour is still compared).',
};
const ACCEPTED_EXAMPLES = 5;

function stableId(parts) {
  return crypto.createHash('sha1').update(parts.join('|')).digest('hex').slice(0, 12);
}

class Findings {
  constructor() { this.list = []; this.seen = new Set(); this.acceptedByRule = new Map(); }

  add(f) {
    const record = {
      category: f.category || categoryOf(f.property || ''),
      severity: f.severity || severityOf(f.property || ''),
      state: 'default', ...f,
    };
    record.id = stableId([record.page, record.viewport, record.locale || 'en', record.component, record.state, record.property || record.note, record.category]);
    if (this.seen.has(record.id)) return null;
    this.seen.add(record.id);
    this.list.push(record);
    return record;
  }

  /** Counts one occurrence of an accepted difference. */
  accept(rule, { page, viewport, component, property, ref, target }, reason = ACCEPTED_REASONS[rule]) {
    const entry = this.acceptedByRule.get(rule) || { rule, reason, count: 0, pages: new Set(), examples: [] };
    entry.count++;
    entry.pages.add(page);
    if (entry.examples.length < ACCEPTED_EXAMPLES) entry.examples.push({ page, viewport, component, property, ref, target });
    this.acceptedByRule.set(rule, entry);
  }

  get accepted() {
    return [...this.acceptedByRule.values()].map((e) => ({ ...e, pages: [...e.pages] }));
  }
}

module.exports = { Findings, CATEGORIES, ACCEPTED_REASONS, stableId };
