// Shared settings for the QBazaar k6 scripts (OPS-18.17).
//
// Everything comes from environment variables (`k6 run -e KEY=value`), so no
// host, token or key is ever written into the repo. See ../README.md.

import { SharedArray } from 'k6/data';
import exec from 'k6/execution';

if (!__ENV.BASE_URL) {
  throw new Error('Set BASE_URL to the staging API, e.g. -e BASE_URL=https://api.staging.example');
}

export const BASE_URL = __ENV.BASE_URL.replace(/\/+$/, '');

// A load test against production would page the owner and skew real users'
// latency. It needs an explicit, deliberate override. (k6 has no URL class.)
const HOST = BASE_URL.replace(/^[a-z]+:\/\//i, '').replace(/^[^/@]*@/, '').split(/[/:?#]/)[0];
if (/(^|\.)qbazaar\.qa\.?$/i.test(HOST) && __ENV.ALLOW_PRODUCTION !== '1') {
  throw new Error(`${BASE_URL} looks like production; point BASE_URL at staging (or set ALLOW_PRODUCTION=1 on purpose).`);
}

// Pre-issued staging users (README "Test users"): a JSON array of
// { token, user_id, conversation_id?, ad_id? }. Loaded once, shared by all VUs.
export const users = new SharedArray('users', () => (__ENV.USERS_FILE ? JSON.parse(open(__ENV.USERS_FILE)) : []));

export const LOCALE = __ENV.LOCALE || 'ar';

// A plain copy of the users that have `field` (SharedArray has no filter()).
export function requireUsers(field) {
  const usable = [];
  for (const user of users) {
    if (!field || user[field]) {
      usable.push(user);
    }
  }
  if (usable.length === 0) {
    throw new Error(`USERS_FILE must list users${field ? ` with "${field}"` : ''}; see tools/load/k6/README.md`);
  }
  return usable;
}

// Spreads iterations evenly over the users, so each stays under the per-user
// rate limits (qbazaar.api.requests_per_minute and friends) while the total
// grows. A 429 in the results means too few users, not too little capacity.
export function nextUser(pool) {
  return pool[exec.scenario.iterationInTest % pool.length];
}

export function headers(user) {
  const base = { Accept: 'application/json', 'Accept-Language': LOCALE };
  return user ? { ...base, Authorization: `Bearer ${user.token}` } : base;
}

export function pick(list) {
  return list[Math.floor(Math.random() * list.length)];
}

// PROFILE=smoke checks a script end to end with one VU; the default ramp
// raises the arrival rate until latency or errors give out (the breaking
// point). RATE_STAGES overrides the ramp: "20:2m,50:3m,100:3m".
export function arrivalScenario(defaultStages, maxVUs = 1000) {
  if (__ENV.PROFILE === 'smoke') {
    return { executor: 'shared-iterations', vus: 1, iterations: 5, maxDuration: '2m' };
  }
  const stages = __ENV.RATE_STAGES
    ? __ENV.RATE_STAGES.split(',').map((s) => {
        const [target, duration] = s.split(':');
        return { target: Number(target), duration };
      })
    : defaultStages;
  return {
    executor: 'ramping-arrival-rate',
    startRate: 1,
    timeUnit: '1s',
    preAllocatedVUs: 50,
    maxVUs: Number(__ENV.MAX_VUS || maxVUs),
    stages,
  };
}
