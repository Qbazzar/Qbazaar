// The pages FE-16.7 audits with axe and Lighthouse. IDs reuse the gate's sample ad and seller (pages.json).
const { ids } = require('../pages');

const AUDIT_PAGES = [
  { id: 'home', route: '/' },
  { id: 'category', route: '/c/cars' },
  { id: 'search', route: '/search?q=toyota' },
  { id: 'product', route: `/ads/${ids.ad}` },
  { id: 'seller', route: `/u/${ids.privateSeller}` },
  { id: 'companies', route: '/companies' },
  { id: 'login', route: '/login' },
  { id: 'account', route: '/account', login: true },
  { id: 'post-ad', route: '/post-ad', login: true },
];

// Menus, sheets and error messages only exist after a click, so axe sees them only in these opened states.
const AUDIT_STATES = [
  { id: 'home+menu', page: 'home', viewports: [390], click: 'header button[aria-haspopup="dialog"]', waitFor: '[role="dialog"]' },
  { id: 'category+filters', page: 'category', viewports: [390], click: 'main button[aria-haspopup="dialog"]', waitFor: '[role="dialog"]' },
  { id: 'login+errors', page: 'login', viewports: [390, 1440], click: 'main form button[type="submit"]', waitFor: '[aria-invalid="true"]' },
];

function selectAuditStates(only) {
  const pages = new Map(AUDIT_PAGES.map((page) => [page.id, page]));
  return AUDIT_STATES
    .filter((state) => !only || only.includes(state.page) || only.includes(state.id))
    .map((state) => ({ ...pages.get(state.page), ...state }));
}

function selectAuditPages(only) {
  return AUDIT_PAGES.filter((page) => !only || only.includes(page.id));
}

const listArg = (value, fallback) => (value ? String(value).split(',').map((item) => item.trim()).filter(Boolean) : fallback);

module.exports = { AUDIT_PAGES, AUDIT_STATES, selectAuditPages, selectAuditStates, listArg };
