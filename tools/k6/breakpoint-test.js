// [stress test] Maximum Capacity / Breakpoint Discovery — PRISM version
// Modeled on the "Maximum Capacity / Breakpoint Discovery" case: keep
// adding virtual users indefinitely (mixed traffic across a few real
// screens) until the server starts throwing errors, to find the ceiling.
//
// Run:
//   k6 run -e BASE_URL=http://127.0.0.1:8000 -e EMAIL=YOUR_TEST_EMAIL -e PASSWORD=YOUR_TEST_PASSWORD tools/k6/breakpoint-test.js
//
// Watch the terminal output as it climbs — stop it (Ctrl+C) once you see
// http_req_failed start rising, and note the VU count at that point.
import http from 'k6/http';
import { check, sleep } from 'k6';
import { loginOnce, useSession } from './lib/auth.js';

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8000';
const EMAIL = __ENV.EMAIL;
const PASSWORD = __ENV.PASSWORD;

export const options = {
  stages: [
    { duration: '1m', target: 50 },
    { duration: '1m', target: 125 },
    { duration: '1m', target: 250 },
    { duration: '1m', target: 375 },
    { duration: '1m', target: 500 },
  ],
};

export function setup() {
  return loginOnce(BASE_URL, EMAIL, PASSWORD);
}

export default function (session) {
  useSession(BASE_URL, session);

  // Mixed traffic, roughly matching the slide's 70/20/10 read/browse/search split.
  const roll = Math.random();
  let res;
  if (roll < 0.7) {
    res = http.get(`${BASE_URL}/admin/dashboard`, { redirects: 0 });
  } else if (roll < 0.9) {
    res = http.get(`${BASE_URL}/admin/users`, { redirects: 0 });
  } else {
    res = http.get(`${BASE_URL}/admin/users/search-suggestions?q=a`, { redirects: 0 });
  }

  check(res, { 'request ok (200)': (r) => r.status === 200 });
  sleep(0.5);
}
