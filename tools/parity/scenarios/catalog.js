// Category and listing behaviours (category.html, parent-category.html, assets/*.js sheets).
// Inventory ids ca.*, pc.*, ac.* in spec/catalog.json.
const { scenario, click, hover, press, clickAt, observe, motion, stay } = require('./dsl');

const CAT = '[data-screen-label="Category"]';
const GRID_TOGGLE = { ref: `${CAT} [data-dc-tpl="422"]`, target: 'button[aria-label="Grid view"]' };
const LIST_TOGGLE = { ref: `${CAT} [data-dc-tpl="419"]`, target: 'button[aria-label="List view"]' };
const FILTER_TRIGGER = { ref: '.qb-filter-inject', target: 'button:has-text("Filter")' };
const FILTER_SHEET = { ref: 'aside', target: '[role=dialog]' };
const FAVOURITE = { ref: `${CAT} [data-dc-tpl="456"]`, target: 'button[aria-label^="Save"]' };

module.exports = [
  scenario({
    id: 'category-view-toggle',
    page: 'category',
    viewports: [1440],
    steps: [
      observe('list-toggle-selected', LIST_TOGGLE, { text: 'skip', attributes: true }),
      click(GRID_TOGGLE),
      observe('grid-toggle-selected', GRID_TOGGLE, { text: 'skip', attributes: true }),
      observe('list-toggle-unselected', LIST_TOGGLE, { text: 'skip', attributes: true }),
      click(LIST_TOGGLE),
      observe('list-toggle-reselected', LIST_TOGGLE, { text: 'skip', attributes: true }),
    ],
  }),
  scenario({
    id: 'category-favourite-toggle',
    page: 'category',
    viewports: [1440, 390],
    steps: [
      click(FAVOURITE),
      stay('toggle-does-not-navigate'),
      observe('heart-filled', FAVOURITE, { text: 'skip', attributes: true, crop: true }),
      click(FAVOURITE),
      observe('heart-empty', FAVOURITE, { text: 'skip', attributes: true }),
    ],
  }),
  scenario({
    id: 'category-filter-sheet',
    page: 'category',
    viewports: [744, 390],
    steps: [
      hover(FILTER_TRIGGER),
      observe('trigger-hover', FILTER_TRIGGER, { text: 'skip' }),
      click(FILTER_TRIGGER),
      observe('sheet-open', FILTER_SHEET, { text: 'skip', crop: true }),
      press('Escape'),
      observe('closed-by-escape', FILTER_SHEET, { text: 'skip', styles: false }),
      click(FILTER_TRIGGER),
      clickAt(200, 40),
      observe('closed-by-backdrop', FILTER_SHEET, { text: 'skip', styles: false }),
    ],
  }),
  scenario({
    id: 'category-filter-sheet-motion',
    page: 'category',
    viewports: [390],
    steps: [motion('sheet-open', FILTER_SHEET, click(FILTER_TRIGGER), 700)],
  }),
  scenario({
    id: 'category-sort',
    page: 'category',
    viewports: [1440],
    steps: [observe('sort-control', { ref: `${CAT} [data-dc-tpl="415"]`, target: 'select' }, { text: 'skip' })],
  }),
];
