// [stress test] Heavy Database Aggregation — PRISM version
// A distinct stress test from load-test.js: fewer VUs, but targeting a
// query that's expensive in itself (joins + GROUP BY + computed sort),
// to see how a heavy query holds up under sustained concurrent access —
// not about raising headcount, about raising query weight.
//
// Run:
//   k6 run -e BASE_URL=http://127.0.0.1:8000 -e EMAIL=YOUR_TEST_EMAIL -e PASSWORD=YOUR_TEST_PASSWORD tools/k6/heavy-aggregation-test.js
import http from 'k6/http';
import { check, sleep } from 'k6';
import { loginOnce, useSession } from './lib/auth.js';

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8000';
const EMAIL = __ENV.EMAIL;
const PASSWORD = __ENV.PASSWORD;
const TARGET_PATH = __ENV.TARGET_PATH || '/coordinator/risk-dashboard';

export const options = {
  stages: [
    { duration: '30s', target: 50 },  // ramp-up
    { duration: '2m', target: 50 },   // hold
    { duration: '30s', target: 0 },   // ramp-down
  ],
  thresholds: {
    http_req_duration: ['p(95)<3000'], // heavier query, more lenient bar
    http_req_failed: ['rate<0.01'],
  },
};

export function setup() {
  return loginOnce(BASE_URL, EMAIL, PASSWORD);
}

export default function (session) {
  useSession(BASE_URL, session);

  const res = http.get(`${BASE_URL}${TARGET_PATH}`, { redirects: 0 });
  check(res, { 'page loaded (200)': (r) => r.status === 200 });

  sleep(1);
}
