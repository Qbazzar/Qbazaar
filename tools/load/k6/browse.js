// Browsing: home, the ads feed and an ad's page (OPS-18.17).
//
//   k6 run -e BASE_URL=https://api.staging.example -e USERS_FILE=users.json browse.js
//
// Each arrival is one visitor step: the home screen, a feed page and one ad
// from it. The ramp raises arrivals per second until the thresholds break;
// the stage where they first fail is the breaking point for the report.

import http from 'k6/http';
import { check } from 'k6';
import { BASE_URL, arrivalScenario, headers, nextUser, pick, requireUsers } from './lib/config.js';

const pool = requireUsers();

export const options = {
  scenarios: {
    browse: arrivalScenario([
      { target: 10, duration: '2m' },
      { target: 30, duration: '3m' },
      { target: 60, duration: '3m' },
      { target: 100, duration: '3m' },
      { target: 150, duration: '3m' },
      { target: 0, duration: '1m' },
    ]),
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    'http_req_duration{name:home}': ['p(95)<500'],
    'http_req_duration{name:feed}': ['p(95)<500'],
    'http_req_duration{name:ad}': ['p(95)<400'],
  },
};

export default function () {
  const params = (name) => ({ headers: headers(nextUser(pool)), tags: { name } });

  const home = http.get(`${BASE_URL}/api/v1/home`, params('home'));
  check(home, { 'home 200': (r) => r.status === 200 });

  const page = 1 + Math.floor(Math.random() * Number(__ENV.FEED_PAGES || 50));
  const feed = http.get(`${BASE_URL}/api/v1/ads?page=${page}&per_page=20`, params('feed'));
  check(feed, { 'feed 200': (r) => r.status === 200 });

  const ids = feed.status === 200 ? feed.json('data.#.id') || [] : [];
  if (ids.length > 0) {
    const ad = http.get(`${BASE_URL}/api/v1/ads/${pick(ids)}`, params('ad'));
    check(ad, { 'ad 200': (r) => r.status === 200 });
  }
}
