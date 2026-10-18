// Proxy handler for the localhost CORS harness. Kept free of Playwright so it can be unit tested.
const stats = { aborted: 0 };

/** Builds the route handler: forwards the request with CORS headers, counts every request it had to abort. */
function createProxyHandler(cors) {
  return async (route) => {
    if (route.request().method() === 'OPTIONS') return route.fulfill({ status: 204, headers: cors });
    // A page that closes while its request is in flight makes fetch() reject; that must not end the run,
    // and the error is not printed because Playwright's call log carries the session's Authorization header.
    // The count is reported at the end of the run, so a real API outage is not lost in the silence.
    try {
      const response = await route.fetch();
      await route.fulfill({ response, headers: { ...response.headers(), ...cors } });
    } catch {
      stats.aborted += 1;
      await route.abort().catch(() => {});
    }
  };
}

const abortedCount = () => stats.aborted;
const proxySummaryLine = () => `proxy: ${stats.aborted} requests aborted`;
const resetProxyStats = () => { stats.aborted = 0; };

module.exports = { createProxyHandler, abortedCount, proxySummaryLine, resetProxyStats };
