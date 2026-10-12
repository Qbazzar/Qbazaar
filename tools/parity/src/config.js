// CLI arguments, environment and constants shared by every module.
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const DEFAULT_REFERENCE = 'file:///D:/Doc/q-bazaar/';
const VIEWPORT_HEIGHTS = { 1440: 900, 744: 1133, 390: 844 };

function parseArgs(argv) {
  const args = {};
  for (let i = 0; i < argv.length; i++) {
    if (!argv[i].startsWith('--')) continue;
    const key = argv[i].slice(2);
    const next = argv[i + 1];
    args[key] = next && !next.startsWith('--') ? (i++, next) : true;
  }
  return args;
}

function toReferenceBase(value) {
  if (/^https?:\/\//.test(value) || value.startsWith('file://')) return value.replace(/\/?$/, '/');
  return 'file:///' + path.resolve(value).replaceAll('\\', '/').replace(/^\/+/, '').replace(/\/?$/, '/');
}

function buildConfig(argv, env = process.env) {
  const args = parseArgs(argv);
  if (!args.target) throw new Error('--target <url> is required');
  const viewports = String(args.viewports || '1440,744,390').split(',').map(Number);
  const target = String(args.target).replace(/\/$/, '');
  return {
    target,
    reference: toReferenceBase(String(args.reference || DEFAULT_REFERENCE)),
    pages: args.pages ? String(args.pages).split(',') : null,
    viewports: viewports.map((width) => ({ width, height: VIEWPORT_HEIGHTS[width] || 900 })),
    locale: String(args.locale || 'en'),
    out: path.resolve(String(args.out || path.join(ROOT, 'out', new Date().toISOString().replace(/[:.]/g, '-')))),
    localTarget: /^https?:\/\/(localhost|127\.0\.0\.1)(:|\/|$)/.test(target),
    only: args.only ? String(args.only).split(',') : null,
    verbose: Boolean(args.verbose),
    skipPages: Boolean(args['skip-pages']),
    skipInventory: Boolean(args['skip-inventory']),
    skipFlows: Boolean(args['skip-flows']),
    skipScenarios: Boolean(args['skip-scenarios']),
    demo: { email: env.QB_DEMO_EMAIL, password: env.QB_DEMO_PASSWORD },
    apiOrigin: String(args.api || 'https://api.qbazaar.qa'),
    chromeChannel: String(args.channel || 'chrome'),
  };
}

module.exports = { ROOT, buildConfig, parseArgs, toReferenceBase };
