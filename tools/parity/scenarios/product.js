// Product page behaviours: gallery (assets/photos.js), share and report modals, toasts.
// Inventory ids product.* in spec/product.json.
const { scenario, click, press, clickAt, observe, motion } = require('./dsl');

const COUNTER_PATTERN = '"[0-9]+ ?/ ?[0-9]+"';
const GALLERY_NEXT = { ref: '.qb-ph[data-gallery] > button:nth-of-type(2)', target: 'button[aria-label="Next"]' };
const GALLERY_PREV = { ref: '.qb-ph[data-gallery] > button:nth-of-type(1)', target: 'button[aria-label="Previous"]' };
const COUNTER = { ref: `.qb-ph[data-gallery] span:text-matches(${COUNTER_PATTERN})`, target: `span:text-matches(${COUNTER_PATTERN})` };
const SHARE = { ref: 'button:text-is("Share Ad")', target: 'button:text-is("Share Ad")' };
const REPORT = { ref: 'button:text-is("Report Ad")', target: 'button:text-is("Report Ad")' };
const MODAL = { ref: 'div[style*="z-index: 90"] > div', target: '[role=dialog]' };
const TOAST = { ref: 'div[style*="rgb(234, 255, 247)"]', target: '[role=status]:not(.sr-only)' };
const REASON_SPAM = { ref: 'div[style*="z-index: 90"] :text-is("Spam")', target: '[role=dialog] label:has-text("Spam")' };

module.exports = [
  scenario({
    id: 'product-gallery',
    page: 'product',
    viewports: [1440, 390],
    steps: [
      observe('counter-at-start', COUNTER, { styles: false }),
      click(GALLERY_NEXT),
      observe('counter-after-next', COUNTER, { styles: false }),
      click(GALLERY_PREV),
      click(GALLERY_PREV, { force: true }),
      observe('counter-wraps-to-last', COUNTER, { styles: false }),
      observe('next-button', GALLERY_NEXT, { text: 'skip', crop: true }),
    ],
  }),
  scenario({
    id: 'product-share-modal',
    page: 'product',
    viewports: [1440, 390],
    steps: [
      click(SHARE),
      observe('modal-open', MODAL, { crop: true }),
      observe('feedback-toast', TOAST, { text: 'skip', styles: false }),
      press('Escape'),
      observe('stays-open-on-escape', MODAL, { text: 'skip', styles: false }),
    ],
  }),
  scenario({
    id: 'product-report-modal',
    page: 'product',
    viewports: [1440, 390],
    login: true,
    steps: [
      click(REPORT),
      observe('modal-open', MODAL, { crop: true }),
      click(REASON_SPAM),
      observe('reason-selected', REASON_SPAM, { text: 'skip', crop: true }),
      click({ ref: 'div[style*="z-index: 90"] button:text-is("Cancel")', target: '[role=dialog] button[aria-label="Close"]' }),
      observe('closed-by-cancel', MODAL, { text: 'skip', styles: false }),
    ],
  }),
  scenario({
    id: 'product-report-modal-escape',
    page: 'product',
    viewports: [1440],
    login: true,
    steps: [click(REPORT), press('Escape'), observe('stays-open-on-escape', MODAL, { text: 'skip', styles: false })],
  }),
  scenario({
    id: 'product-report-modal-overlay',
    page: 'product',
    viewports: [1440],
    login: true,
    steps: [click(REPORT), clickAt(10, 10), observe('closed-by-overlay', MODAL, { text: 'skip', styles: false })],
  }),
  scenario({
    id: 'product-report-submit',
    page: 'product',
    viewports: [1440],
    writes: true,
    login: true,
    steps: [
      click(REPORT),
      click({ ref: 'div[style*="z-index: 90"] button:text-is("Submit Report")', target: '[role=dialog] button:text-is("Submit report")' }),
      observe('toast', TOAST, { text: true, crop: true }),
      observe('modal-closed', MODAL, { text: 'skip', styles: false }),
    ],
  }),
  scenario({
    id: 'product-modal-motion',
    page: 'product',
    viewports: [1440],
    steps: [motion('report-open', MODAL, click(REPORT), 600)],
  }),
];
