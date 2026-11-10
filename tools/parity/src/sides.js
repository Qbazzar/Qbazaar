// Opens one side (reference or target) of a comparison: context, page, CDP probe, navigation.
const browserTools = require('./browser');
const { Probe } = require('./capture');

const onLoginPage = (url) => /^\/(?:[a-z]{2}\/)?login(?:\/|$)/.test(new URL(url).pathname);

async function openOnce(env, side, pageDef, viewport, needsLogin) {
  const { browser, config } = env;
  const context = await browserTools.newContext(browser, config, viewport, { side, storageState: needsLogin ? env.storageState : undefined, locale: config.locale });
  const page = await context.newPage();
  const base = side === 'reference' ? config.reference : config.target;
  const probe = await Probe.attach(context, page, side, config.reference);
  const url = side === 'reference' ? base + pageDef.reference : base + pageDef.route;
  const landed = await browserTools.open(page, url);
  return { context, page, probe, landed, loggedIn: Boolean(needsLogin && env.storageState) };
}

/**
 * A signed-in page that lands on /login lost the demo session mid-run (the API rotates refresh tokens and expires
 * idle ones), not the page: sign in again and reopen once. When the new sign-in fails too the side is marked
 * `sessionLost`, so the caller lists it under Skipped instead of comparing the login form with the page.
 */
async function openSide(env, side, pageDef, viewport) {
  const needsLogin = side === 'target' && Boolean(pageDef.login);
  const opened = await openOnce(env, side, pageDef, viewport, needsLogin);
  if (!needsLogin || !env.storageState || !onLoginPage(opened.landed.url) || /^login/.test(pageDef.id)) return opened;
  await opened.context.close();
  env.storageState = await browserTools.loginDemo(env.browser, env.config);
  if (!env.storageState) {
    const lost = await openOnce(env, side, pageDef, viewport, false);
    return { ...lost, sessionLost: true };
  }
  return openOnce(env, side, pageDef, viewport, true);
}

// The API rotates refresh tokens, so the next logged-in context must start from the tokens this one ended with.
async function closeSide(env, side) {
  if (side.loggedIn) env.storageState = await side.context.storageState().catch(() => env.storageState);
  await side.context.close();
}

const SESSION_LOST = 'skipped: the demo session ran out and signing in again did not leave /login, so the signed-in page could not be opened';

/** Thrown inside a comparison whose target side lost the session; runners list it under Skipped with this reason. */
class SessionLostError extends Error {
  constructor() {
    super(SESSION_LOST);
    this.skipReason = SESSION_LOST;
  }
}

module.exports = { openSide, closeSide, onLoginPage, SESSION_LOST, SessionLostError };
