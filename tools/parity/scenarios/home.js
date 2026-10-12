// Home page behaviours (assets/selects.js, assets/slider.js). Figma 489:16010 (category panel) and 492:17733 (distance).
const { scenario, click, hover, press, observe, motion, stay } = require('./dsl');

const CATEGORY_FIELD = { ref: '[data-qbsel=cat]', target: 'form[role=search] [data-field=category], form[role=search] a[href="/categories"]' };
const DISTANCE_FIELD = { ref: '[data-qbsel=dist]', target: 'form[role=search] [data-field=distance], form[role=search] [aria-label=Distance]' };
const PANEL = { ref: '.qb-ddpanel', target: '[role=listbox], [role=menu], [data-dropdown-panel]' };
const SUBMENU = { ref: '.qb-ddsub.open', target: '[role=listbox] [role=listbox], [role=menu] [role=menu], [data-dropdown-sub]' };
const RECOMMENDED = { ref: '#recTrack', target: 'ul[aria-label="Recommended for you"]' };
const SLIDER_NEXT = { ref: '#recTrack ~ .qb-slide-next, .qb-slide-host:has(#recTrack) .qb-slide-next', target: 'button[aria-label="Next"]' };
const SLIDER_PREV = { ref: '.qb-slide-host:has(#recTrack) .qb-slide-prev', target: 'button[aria-label="Previous"]' };

module.exports = [
  scenario({
    id: 'home-hero-category-cascade',
    page: 'index',
    viewports: [1440],
    steps: [
      click(CATEGORY_FIELD),
      observe('panel', PANEL, { crop: true, count: true }),
      stay('open-does-not-navigate'),
      hover({ ref: '.qb-dditem:has(.qb-ddchev)', target: '[role=option]:has([data-chevron])' }),
      observe('submenu', SUBMENU, { crop: true, count: true }),
      click({ ref: '.qb-ddsub.open .qb-dditem', target: '[role=listbox] [role=listbox] [role=option]' }),
      observe('field-after-choice', CATEGORY_FIELD, { crop: true }),
    ],
  }),
  scenario({
    id: 'home-hero-category-panel-motion',
    page: 'index',
    viewports: [1440],
    steps: [motion('panel-open', PANEL, click(CATEGORY_FIELD))],
  }),
  scenario({
    id: 'home-hero-distance',
    page: 'index',
    viewports: [1440],
    steps: [
      click(DISTANCE_FIELD),
      stay('open-does-not-navigate'),
      observe('panel', PANEL, { crop: true, count: true }),
      click({ ref: '.qb-ddpanel .qb-dditem:nth-child(3)', target: '[role=listbox] [role=option]:nth-child(3)' }),
      observe('field-after-choice', DISTANCE_FIELD, { crop: true }),
    ],
  }),
  scenario({
    id: 'home-hero-dropdown-close',
    page: 'index',
    viewports: [1440],
    steps: [
      click(CATEGORY_FIELD),
      press('Escape'),
      observe('closed-by-escape', PANEL, { text: 'skip' }),
      click(DISTANCE_FIELD),
      click({ ref: 'h1', target: 'h1' }),
      observe('closed-by-outside-click', PANEL, { text: 'skip' }),
    ],
  }),
  scenario({
    id: 'home-recommended-slider',
    page: 'index',
    viewports: [1440, 390],
    steps: [
      observe('prev-disabled-at-start', SLIDER_PREV, { anywhere: true, attributes: true, styles: false, text: 'skip' }),
      motion('next', RECOMMENDED, click(SLIDER_NEXT)),
      observe('prev-enabled-after-next', SLIDER_PREV, { attributes: true, crop: true, text: 'skip' }),
      motion('prev', RECOMMENDED, click(SLIDER_PREV)),
    ],
  }),
];
