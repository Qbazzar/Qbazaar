// Finding records: stable ids, severity and the category taxonomy of the report.
const crypto = require('crypto');
const { categoryOf, severityOf } = require('./props');

const CATEGORIES = ['typography', 'colour', 'spacing', 'states', 'motion', 'behaviour', 'icons', 'flows', 'pixels', 'missing', 'rtl'];

// Differences the owner accepted once for the whole site: counted, never reported per element.
const ACCEPTED_REASONS = {
  'reference-demo-state': 'Texts the prototype shows only for its demo user or its current language (reference-text.json).',
  'reference-sample-data': 'Sample names in the prototype (cities, categories, sellers, chats); the target shows live data (reference-text.json).',
  'focus-ring': 'The reference declares no focus style of its own (the browser draws its default ring); the target keeps its accessible focus ring by the owner\'s rule. Focus differences in outline, ring shadow and border colour are accepted unless the reference itself changes that property on focus (the auth inputs\' border colour is still compared).',
  'arabic-font': 'Arabic text is drawn in Noto Kufi Arabic on the target (owner pick 2026-10-09); Latin text and digits stay Poppins, Montserrat and Acme and are still compared.',
  'svg-flags': 'The target draws SVG flags where the prototype uses emoji flags, and its language list holds the languages the site ships (English, Arabic), each named in its own script.',
  'auth-static-pill': 'forgot-password, send-code and signup-verify do not load assets/auth.js, so their language pill keeps a static fallback (a 15px rect in place of the flag); the target shows the working pill on every auth page.',
  'copy-5': 'Five copy strings kept on purpose by the owner in the phase-3 copy pass (PR #274): "My orders" (not "Sales Overview"), the purchase and sale captions (not "Transaction History" twice), the offer hint (no 70% claim), the cash-at-handover buy step and the description placeholder (reference-text.json, rule copy-5).',
  'escape-key': 'Dialogs, drawers and panels close on Escape on the target (keyboard access); the prototype ignores Escape.',
  'signup-extra-fields': 'Sign-up asks for the full name and the phone number (POST /auth/register requires both), so the sign-up card is taller than the reference card.',
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
