// Opens one side (reference or target) of a comparison: context, page, CDP probe, navigation.
const browserTools = require('./browser');
const { Probe } = require('./capture');

async function openSide(env, side, pageDef, viewport) {
  const { browser, config } = env;
  const needsLogin = side === 'target' && pageDef.login;
  const context = await browserTools.newContext(browser, config, viewport, { side, storageState: needsLogin ? env.storageState : undefined, locale: config.locale });
  const page = await context.newPage();
  const base = side === 'reference' ? config.reference : config.target;
  const probe = await Probe.attach(context, page, side, config.reference);
  const url = side === 'reference' ? base + pageDef.reference : base + pageDef.route;
  const landed = await browserTools.open(page, url);
  return { context, page, probe, landed, loggedIn: Boolean(needsLogin && env.storageState) };
}

// The API rotates refresh tokens, so the next logged-in context must start from the tokens this one ended with.
async function closeSide(env, side) {
  if (side.loggedIn) env.storageState = await side.context.storageState().catch(() => env.storageState);
  await side.context.close();
}

module.exports = { openSide, closeSide };
