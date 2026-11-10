// Search: keyword queries with and without a category, through Meilisearch
// (OPS-18.17).
//
//   k6 run -e BASE_URL=https://api.staging.example -e USERS_FILE=users.json search.js
//
// SEARCH_TERMS (comma separated) replaces the default mix of Arabic and
// English queries, typos included (Meilisearch's typo tolerance is part of
// the cost). Each user may search once a second (`throttle:search`, 60 a
// minute), so USERS_FILE needs at least twice the peak arrival rate in users.

import http from 'k6/http';
import { check } from 'k6';
import { BASE_URL, arrivalScenario, headers, nextUser, pick, requireUsers } from './lib/config.js';

const pool = requireUsers();
const terms = (__ENV.SEARCH_TERMS || 'سيارة,تويوتا,شقة للايجار,ايفون,iphone 15,samsung,لابتوب,كنب,دراجة,iphnoe,toyta,ساعة')
  .split(',')
  .map((t) => t.trim())
  .filter(Boolean);
const categories = (__ENV.SEARCH_CATEGORIES || '').split(',').filter(Boolean);

export const options = {
  scenarios: {
    search: arrivalScenario([
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
    'http_req_duration{name:search}': ['p(95)<400'],
    'http_req_duration{name:suggestions}': ['p(95)<200'],
  },
};

export default function () {
  const user = nextUser(pool);
  const term = encodeURIComponent(pick(terms));
  const category = categories.length > 0 && Math.random() < 0.5 ? `&category_slug=${encodeURIComponent(pick(categories))}` : '';

  const suggestions = http.get(`${BASE_URL}/api/v1/search/suggestions?q=${term}`, {
    headers: headers(user),
    tags: { name: 'suggestions' },
  });
  check(suggestions, { 'suggestions 200': (r) => r.status === 200 });

  const results = http.get(`${BASE_URL}/api/v1/search?q=${term}${category}&page=1&per_page=20`, {
    headers: headers(user),
    tags: { name: 'search' },
  });
  check(results, { 'search 200': (r) => r.status === 200 });
}
