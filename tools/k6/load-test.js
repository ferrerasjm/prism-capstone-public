// [stress test] Peak Rush Hour Concurrency — PRISM version
// Modeled on the "Peak Rush Hour Concurrency (Standard Load Test)" case:
// ramp up to a steady number of concurrent logged-in staff hitting the
// heaviest everyday screen (the dashboard) and hold it for a few minutes.
//
// Run (admin, default):
//   k6 run -e BASE_URL=http://127.0.0.1:8000 -e EMAIL=YOUR_TEST_EMAIL -e PASSWORD=YOUR_TEST_PASSWORD tools/k6/load-test.js
//
// Run as coordinator, against the heavier risk dashboard:
//   k6 run -e BASE_URL=http://127.0.0.1:8000 -e EMAIL=YOUR_TEST_EMAIL -e PASSWORD=YOUR_TEST_PASSWORD -e TARGET_PATH=/coordinator/risk-dashboard tools/k6/load-test.js
import http from 'k6/http';
import { check, sleep } from 'k6';
import { loginOnce, useSession } from './lib/auth.js';

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8000';
const EMAIL = __ENV.EMAIL;
const PASSWORD = __ENV.PASSWORD;
const TARGET_PATH = __ENV.TARGET_PATH || '/admin/dashboard';

export const options = {
  stages: [
    { duration: '1m', target: 100 },   // ramp-up
    { duration: '3m', target: 100 },   // hold at steady peak load
    { duration: '1m', target: 0 },     // ramp-down
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'], // p95 under 500ms, same bar as the slide
    http_req_failed: ['rate<0.01'],   // <1% errors
  },
};

export function setup() {
  return loginOnce(BASE_URL, EMAIL, PASSWORD);
}

export default function (session) {
  useSession(BASE_URL, session);

  const res = http.get(`${BASE_URL}${TARGET_PATH}`, { redirects: 0 });
  check(res, {
    'page loaded (200)': (r) => r.status === 200,
  });

  sleep(1);
}
