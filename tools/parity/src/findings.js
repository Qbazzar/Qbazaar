// Finding records: stable ids, severity and the category taxonomy of the report.
const crypto = require('crypto');
const { categoryOf, severityOf } = require('./props');

const CATEGORIES = ['typography', 'colour', 'spacing', 'states', 'motion', 'behaviour', 'icons', 'flows', 'pixels', 'missing', 'rtl'];

function stableId(parts) {
  return crypto.createHash('sha1').update(parts.join('|')).digest('hex').slice(0, 12);
}

class Findings {
  constructor() { this.list = []; this.seen = new Set(); }

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
}

module.exports = { Findings, CATEGORIES, stableId };
