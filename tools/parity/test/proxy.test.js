const test = require('node:test');
const assert = require('node:assert/strict');
const { createProxyHandler, abortedCount, proxySummaryLine, resetProxyStats } = require('../src/proxy');

const cors = { 'access-control-allow-origin': 'http://localhost:3000' };
const makeRoute = ({ method = 'GET', fetch }) => {
  const calls = { fulfill: [], abort: 0 };
  return {
    calls,
    request: () => ({ method: () => method }),
    fetch,
    fulfill: async (arg) => { calls.fulfill.push(arg); },
    abort: async () => { calls.abort += 1; },
  };
};

test('forwarded requests are fulfilled with CORS headers and not counted', async () => {
  resetProxyStats();
  const route = makeRoute({ fetch: async () => ({ headers: () => ({ 'x-a': '1' }) }) });
  await createProxyHandler(cors)(route);
  assert.equal(route.calls.fulfill[0].headers['x-a'], '1');
  assert.equal(route.calls.fulfill[0].headers['access-control-allow-origin'], cors['access-control-allow-origin']);
  assert.equal(abortedCount(), 0);
});

test('preflight requests are answered without fetching', async () => {
  resetProxyStats();
  const route = makeRoute({ method: 'OPTIONS', fetch: async () => { throw new Error('must not fetch'); } });
  await createProxyHandler(cors)(route);
  assert.equal(route.calls.fulfill[0].status, 204);
  assert.equal(abortedCount(), 0);
});

test('a failing fetch aborts the route, is counted and summarised', async () => {
  resetProxyStats();
  const handler = createProxyHandler(cors);
  for (let i = 0; i < 2; i += 1) {
    const route = makeRoute({ fetch: async () => { throw new Error('closed'); } });
    await handler(route);
    assert.equal(route.calls.abort, 1);
  }
  assert.equal(abortedCount(), 2);
  assert.equal(proxySummaryLine(), 'proxy: 2 requests aborted');
});
