// Loads pages.json and resolves routes, viewports and login needs for a run.
const pagesFile = require('../pages.json');

const ALL_VIEWPORTS = [1440, 744, 390];

function resolveRoute(route, ids) {
  return route.replace(/\{(\w+)\}/g, (_, key) => ids[key]);
}

function selectPages(config) {
  return pagesFile.pages
    .filter((p) => !config.pages || config.pages.includes(p.id))
    .map((p) => ({
      ...p,
      route: resolveRoute(p.route, pagesFile.ids),
      aliases: p.aliases || [],
      viewports: (p.viewports || ALL_VIEWPORTS).filter((w) => config.viewports.some((v) => v.width === w)),
    }));
}

const referenceOnly = () => pagesFile.referenceOnly;

/**
 * Inventory elements that exist only when the target page lists live data (pages.json liveData): the ads of a
 * category, the demo account's saved ads or searches. Returns the page's rule when it names this element, else null.
 */
function liveDataRule(pageId, elementId) {
  const page = pagesFile.pages.find((p) => p.id === pageId);
  return page && page.liveData && page.liveData.elements.includes(elementId) ? page.liveData : null;
}

/** True when the target page shows none of the items a live-data element needs (an empty result or account list). */
async function lacksLiveData(page, rule) {
  return !(await page.evaluate((items) => Boolean(document.querySelector(items)), rule.items).catch(() => true));
}

const liveDataReason = (rule) => `the target page shows no ${rule.what} to compare it with (live data, not a design difference)`;

module.exports = { selectPages, referenceOnly, liveDataRule, lacksLiveData, liveDataReason, ids: pagesFile.ids };
