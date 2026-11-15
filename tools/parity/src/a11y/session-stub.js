// A stand-in session for the signed-in pages when the demo account is not configured. It lives in the browser only:
// the local refresh route answers with a token the API never issued, the profile, summary and unread counts answer
// with fixtures, every other call carrying that token is forwarded without it (public data stays real) and, when the
// API insists on a session, answered with an empty page. Writes never leave the browser.
const STUB_TOKEN = 'a11y-audit-stub';

const EMPTY_PAGE = { success: true, data: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } };

function stubUser(locale) {
  return {
    id: '01a11yaudit0000000000000000',
    full_name: locale === 'ar' ? 'مستخدم التدقيق' : 'Audit User',
    email: 'audit@example.com',
    phone: '+97455555555',
    account_type: 'individual',
    status: 'active',
    email_verified: true,
    phone_verified: true,
    language: locale,
    avatar_url: null,
    bio: null,
    created_at: '2026-01-01T00:00:00Z',
  };
}

const STUB_SUMMARY = {
  my_ads: 0,
  drafts: 0,
  ads_by_status: { draft: 0, pending: 0, active: 0, sold: 0, expired: 0, rejected: 0, blocked: 0 },
  conversations: 0,
  unread_messages: 0,
  unread_notifications: 0,
  favorites: 0,
  saved_searches: 0,
};

/** The signed-in reads whose shape the pages rely on; null when the real API's public answer will do. */
function fixtureFor(pathname, locale) {
  if (pathname === '/api/v1/account/profile') return stubUser(locale);
  if (pathname === '/api/v1/account/summary') return STUB_SUMMARY;
  if (pathname.endsWith('/unread-count')) return { count: 0, unread_count: 0 };
  return null;
}

function corsHeaders(target) {
  return {
    'access-control-allow-origin': new URL(target).origin,
    'access-control-allow-credentials': 'true',
    'access-control-allow-headers': '*',
    'access-control-allow-methods': 'GET,POST,PUT,PATCH,DELETE,OPTIONS',
  };
}

const json = (body, headers, status = 200) => ({ status, headers: { ...headers, 'content-type': 'application/json' }, body: JSON.stringify(body) });

const carriesStubToken = (headers) => (headers.authorization || '') === `Bearer ${STUB_TOKEN}`;

/** Decides what the stub does with one API request: a canned answer, a forward without the token, or no say at all. */
function planApiRequest({ method, pathname, headers, locale }) {
  if (method === 'OPTIONS') return { action: 'preflight' };
  const fixture = method === 'GET' ? fixtureFor(pathname, locale) : null;
  if (fixture) return { action: 'fixture', fixture };
  if (!carriesStubToken(headers)) return { action: 'pass' };
  if (method !== 'GET') return { action: 'swallow' };
  return { action: 'forward' };
}

async function handleApiRoute(route, { target, locale }) {
  const request = route.request();
  const cors = corsHeaders(target);
  const plan = planApiRequest({ method: request.method(), pathname: new URL(request.url()).pathname, headers: request.headers(), locale });
  if (plan.action === 'pass') return route.fallback();
  if (plan.action === 'preflight') return route.fulfill({ status: 204, headers: cors });
  if (plan.action === 'fixture') return route.fulfill(json({ success: true, data: plan.fixture }, cors));
  if (plan.action === 'swallow') return route.fulfill(json({ success: true, data: null }, cors));

  const { authorization, ...headers } = request.headers();
  try {
    const response = await route.fetch({ headers });
    if (response.status() === 401 || response.status() === 403) return route.fulfill(json(EMPTY_PAGE, cors));
    return route.fulfill({ response, headers: { ...response.headers(), ...cors } });
  } catch {
    return route.abort().catch(() => {});
  }
}

/** Registers the stub on a target context. Call after the gate's CORS proxy so these routes run first. */
async function installSessionStub(context, config, locale) {
  const token = { access_token: STUB_TOKEN, refresh_token: '', token_type: 'Bearer', expires_in: 3600 };
  await context.route(`${config.target}/api/auth/refresh`, (route) => route.fulfill(json({ success: true, data: { token } }, {})));
  await context.route(`${config.apiOrigin}/**`, (route) => handleApiRoute(route, { target: config.target, locale }));
}

module.exports = { installSessionStub, planApiRequest, stubUser, STUB_TOKEN, EMPTY_PAGE };
