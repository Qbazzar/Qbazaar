// Image upload: a batch of photos to an ad, then removing them again
// (OPS-18.17).
//
//   k6 run -e BASE_URL=https://api.staging.example -e USERS_FILE=users.json \
//          -e IMAGE_FILE=/path/photo.jpg upload.js
//
// IMAGE_FILE is a real phone photo as the apps send it (resized, about
// 0.5-2 MB JPEG); it is not in the repo. Each arrival uploads IMAGES_PER_BATCH
// copies (default 5) to the user's draft ad, then deletes them, so the ad
// never reaches its 20-image cap. Every upload queues conversions on the
// `media` queue: watch Horizon's media wait time during the run, it is part
// of the result. Users need `ad_id`, a draft ad they own; each may upload 20
// batches a minute (UPLOAD_REQUESTS_PER_MINUTE).

import http from 'k6/http';
import { check } from 'k6';
import { BASE_URL, arrivalScenario, headers, nextUser, requireUsers } from './lib/config.js';

if (!__ENV.IMAGE_FILE) {
  throw new Error('Set IMAGE_FILE to a sample JPEG photo (not kept in the repo)');
}

const pool = requireUsers('ad_id');
const photo = open(__ENV.IMAGE_FILE, 'b');
const PER_BATCH = Math.min(10, Number(__ENV.IMAGES_PER_BATCH || 5));

export const options = {
  scenarios: {
    upload: arrivalScenario(
      [
        { target: 1, duration: '2m' },
        { target: 3, duration: '3m' },
        { target: 6, duration: '3m' },
        { target: 10, duration: '3m' },
        { target: 0, duration: '1m' },
      ],
      200,
    ),
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    'http_req_duration{name:upload}': ['p(95)<8000'],
  },
};

export default function () {
  const user = nextUser(pool);
  const body = {};
  for (let i = 0; i < PER_BATCH; i += 1) {
    body[`images[${i}]`] = http.file(photo, `load-${i}.jpg`, 'image/jpeg');
  }

  const upload = http.post(`${BASE_URL}/api/v1/ads/${user.ad_id}/images`, body, {
    headers: headers(user),
    tags: { name: 'upload' },
    timeout: '120s',
  });
  check(upload, { 'upload accepted': (r) => r.status === 201 });

  const ids = upload.status < 300 ? upload.json('data.images.#.id') || [] : [];
  for (const id of ids) {
    http.del(`${BASE_URL}/api/v1/media/${id}`, null, { headers: headers(user), tags: { name: 'delete' } });
  }
}
