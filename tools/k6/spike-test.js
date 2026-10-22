// [stress test] Sudden Spike — PRISM version
// Modeled on the "Sudden Flash Sale / Stock Audit (Spike Test)" case:
// instantly jump from a handful of users to a burst of concurrent staff
// all hammering a typeahead/search endpoint at once, then drop back down.
//
// Run (admin, default — searches the user list):
//   k6 run -e BASE_URL=http://127.0.0.1:8000 -e EMAIL=YOUR_TEST_EMAIL -e PASSWORD=YOUR_TEST_PASSWORD tools/k6/spike-test.js
//
// Run as officer, against the incident-report search typeahead:
//   k6 run -e BASE_URL=http://127.0.0.1:8000 -e EMAIL=YOUR_TEST_EMAIL -e PASSWORD=YOUR_TEST_PASSWORD -e TARGET_PATH=/officer/incidents/search-suggestions tools/k6/spike-test.js
import http from 'k6/http';
import { check, sleep } from 'k6';
import { loginOnce, useSession } from './lib/auth.js';

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8000';
const EMAIL = __ENV.EMAIL;
const PASSWORD = __ENV.PASSWORD;
const TARGET_PATH = __ENV.TARGET_PATH || '/admin/users/search-suggestions';

export const options = {
  stages: [
    { duration: '10s', target: 10 },   // baseline
    { duration: '10s', target: 150 },  // instant spike
    { duration: '1m', target: 150 },   // hold the spike
    { duration: '20s', target: 10 },   // recovery back to baseline
  ],
};

export function setup() {
  return loginOnce(BASE_URL, EMAIL, PASSWORD);
}

export default function (session) {
  useSession(BASE_URL, session);

  const res = http.get(`${BASE_URL}${TARGET_PATH}?q=a`, { redirects: 0 });
  check(res, {
    'search responded (200)': (r) => r.status === 200,
  });

  sleep(0.3);
}
