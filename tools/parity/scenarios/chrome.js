// Site chrome behaviours: language menus, mobile drawer, footer accordion, account menu
// (assets/mobilemenu.js and the engine header; inventory ids chrome.*).
const { scenario, click, clickAt, hover, press, scrollTo, observe, motion } = require('./dsl');

const LANG_BUTTON_DESKTOP = { ref: 'header button[title="Language"]', target: 'button[aria-label="Language"]' };
const LANG_PANEL_DESKTOP = { ref: 'header button[title="Language"] + div', target: 'div.absolute.top-full.z-50' };
const LANG_BUTTON_MOBILE = { ref: '#qb-mglobe', target: 'button[aria-label="Language"]' };
const LANG_PANEL_MOBILE = { ref: '.qb-langpop', target: 'div.absolute.top-full.z-50' };
const BURGER = { ref: '#qb-burger', target: 'button[aria-label="Open menu"]' };
const DRAWER = { ref: '.qb-mdrawer', target: '[role=dialog]' };
const DRAWER_CLOSE = { ref: '.qb-mdrawer-x', target: '[role=dialog] button[aria-label="Close"]' };
const AVATAR = { ref: 'header button[title="Account"]', target: 'header a[href="/account"]' };
const AVATAR_MENU = { ref: 'header button[title="Account"] + div', target: 'header div.absolute.top-full' };
const FOOTER_TITLE = { ref: 'footer .qb-facc > h3', target: 'footer summary' };
const FOOTER_LINKS = { ref: 'footer .qb-facc .qb-flinks', target: 'footer details[open] ul' };

module.exports = [
  scenario({
    id: 'chrome-language-menu-desktop',
    page: 'index',
    viewports: [1440],
    steps: [
      hover(LANG_BUTTON_DESKTOP),
      observe('opens-on-hover', LANG_PANEL_DESKTOP, { crop: true, count: true }),
      click(LANG_BUTTON_DESKTOP),
      observe('panel-after-click', LANG_PANEL_DESKTOP, { crop: true }),
      observe('option-rows', { ref: 'header button[title="Language"] + div .qb-btn', target: 'div.absolute.top-full.z-50 [role=menuitemradio], div.absolute.top-full.z-50 button' }, { count: true, text: 'skip', styles: false }),
    ],
  }),
  scenario({
    id: 'chrome-language-menu-motion',
    page: 'index',
    viewports: [1440],
    steps: [motion('panel-open', LANG_PANEL_DESKTOP, hover(LANG_BUTTON_DESKTOP))],
  }),
  scenario({
    id: 'chrome-language-popover-mobile',
    page: 'index',
    viewports: [390],
    steps: [
      click(LANG_BUTTON_MOBILE),
      observe('popover', LANG_PANEL_MOBILE, { crop: true, count: true }),
      click({ ref: '.qb-langpop button[data-lang="Arabic"]', target: 'div.absolute.top-full.z-50 button:has-text("العربية")' }),
      observe('closed-after-choice', LANG_PANEL_MOBILE, { text: 'skip' }),
    ],
  }),
  scenario({
    id: 'chrome-drawer',
    page: 'index',
    viewports: [390],
    steps: [
      click(BURGER),
      observe('drawer-open', DRAWER, { crop: true }),
      observe('drawer-links', { ref: '.qb-mnav a', target: '[role=dialog] nav a' }, { count: true, text: 'skip', styles: false }),
      click(DRAWER_CLOSE),
      observe('closed-by-x', DRAWER, { text: 'skip', styles: false }),
    ],
  }),
  scenario({
    id: 'chrome-drawer-escape',
    page: 'index',
    viewports: [390],
    steps: [click(BURGER), press('Escape'), observe('stays-open-on-escape', DRAWER, { text: 'skip', styles: false })],
  }),
  scenario({
    id: 'chrome-drawer-backdrop',
    page: 'index',
    viewports: [390],
    steps: [click(BURGER), clickAt(370, 400), observe('closed-by-backdrop', DRAWER, { text: 'skip', styles: false })],
  }),
  scenario({
    id: 'chrome-drawer-motion',
    page: 'index',
    viewports: [390],
    steps: [motion('drawer-open', DRAWER, click(BURGER), 800)],
  }),
  scenario({
    id: 'chrome-footer-accordion',
    page: 'index',
    viewports: [744, 390],
    steps: [
      scrollTo('footer'),
      click(FOOTER_TITLE),
      observe('column-open', FOOTER_LINKS, { crop: true, text: 'skip' }),
      click(FOOTER_TITLE),
      observe('column-closed', FOOTER_LINKS, { text: 'skip', styles: false }),
    ],
  }),
  scenario({
    id: 'chrome-account-menu',
    page: 'index',
    viewports: [1440],
    login: true,
    steps: [
      hover(AVATAR),
      observe('opens-on-hover', AVATAR_MENU, { crop: true }),
      observe('menu-items', { ref: 'header button[title="Account"] + div .qb-btn', target: 'header div.absolute.top-full a, header div.absolute.top-full button' }, { count: true, text: 'skip', styles: false }),
    ],
  }),
];
