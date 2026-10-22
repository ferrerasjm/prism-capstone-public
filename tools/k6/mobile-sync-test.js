// [stress test] Mobile Sync API — PRISM version
// For the panel's request to include API-endpoint stress testing for
// mobile sync as part of ISO 25010 (Performance Efficiency / Time Behaviour
// and Resource Utilization, and Reliability / Fault Tolerance).
//
// Simulates the US Supervisor mobile app's sync pattern: on open (or a
// pull-to-refresh), it pulls all three of its data sources in one burst —
// assignments, attendance history, and notifications — the same way a real
// mobile client would sync its local cache.
//
// Run:
//   k6 run -e BASE_URL=http://127.0.0.1:8000 -e EMAIL=YOUR_TEST_EMAIL -e PASSWORD=YOUR_TEST_PASSWORD tools/k6/mobile-sync-test.js
import http from 'k6/http';
import { check, sleep } from 'k6';
import { mobileLogin } from './lib/mobile-auth.js';

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8000';
const EMAIL = __ENV.EMAIL;
const PASSWORD = __ENV.PASSWORD;

export const options = {
  stages: [
    { duration: '20s', target: 30 },  // ramp-up
    { duration: '1m', target: 30 },   // hold
    { duration: '20s', target: 0 },   // ramp-down
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'],
    http_req_failed: ['rate<0.01'],
  },
};

// Logging in once here (setup() runs a single time, before any VU starts)
// instead of once per VU. 30 VUs each logging in independently would fire
// 30 login attempts in ~30-60s and trip Laravel's own anti-brute-force
// throttle on /api/auth/login (25 attempts/minute/IP) - which also isn't a
// realistic thing to stress-test anyway, since one login rate limit
// existing to stop exactly that pattern is by design. What we actually want
// to load-test is the sync endpoints themselves, as one authenticated
// device would hit them on every refresh.
export function setup() {
  return { token: mobileLogin(BASE_URL, EMAIL, PASSWORD) };
}

export default function ({ token }) {
  const headers = { Authorization: `Bearer ${token}`, Accept: 'application/json' };

  // A device syncing all three of its data sources in one refresh.
  const assignments = http.get(`${BASE_URL}/api/mobile/us/assignments`, { headers });
  const history = http.get(`${BASE_URL}/api/mobile/us/history`, { headers });
  const notifications = http.get(`${BASE_URL}/api/mobile/us/notifications`, { headers });

  check(assignments, { 'assignments synced (200)': (r) => r.status === 200 });
  check(history, { 'history synced (200)': (r) => r.status === 200 });
  check(notifications, { 'notifications synced (200)': (r) => r.status === 200 });

  sleep(1);
}
