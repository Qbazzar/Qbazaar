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

module.exports = { selectPages, referenceOnly, ids: pagesFile.ids };
