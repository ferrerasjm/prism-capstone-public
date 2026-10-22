// Shared login helper for PRISM k6 scripts.
//
// PRISM's real login flow is 3 steps (password -> privacy notice -> OTP),
// which is realistic to script only because local dev exposes the OTP in
// the page HTML (LOGIN_OTP_CONSOLE=true in .env). This walks that exact
// flow so the session cookie k6 ends up with is a real, fully authenticated
// PRISM session — not a shortcut.
//
// Login here is TEST SETUP, not the thing being measured: every script logs
// in once per virtual user, then measures the target page for the rest of
// the run. So this helper retries the setup on a transient failure (exactly
// as a real person would re-submit a form that hiccuped) and reports one
// check for whether the VU ended up authenticated. Intermediate retries are
// not counted as failed checks, because a retried login is not a defect in
// the page under test — it would otherwise show up as noise in the results
// while telling you nothing about the endpoint being benchmarked.
import http from 'k6/http';
import { check, fail, sleep } from 'k6';

// Think time between login steps. Without it, k6 fires all five steps
// back-to-back in milliseconds, which no real person does (they read the
// privacy notice, they type the OTP). Laravel persists the session in its
// terminate() phase — after the response is already sent — so a zero-delay
// next request can arrive before the previous step's session write has
// landed, producing 419s and lost OTP challenges that are an artifact of
// the harness, not of PRISM. Real think time removes that artifact.
const THINK = 1.0;

const MAX_LOGIN_ATTEMPTS = 3;

function extractCsrfToken(html) {
  if (!html) return null;
  const match = html.match(/name="_token" value="([^"]+)"/);
  return match ? match[1] : null;
}

function extractMetaCsrf(html) {
  if (!html) return null;
  const match = html.match(/name="csrf-token" content="([^"]+)"/);
  return match ? match[1] : null;
}

function extractDevOtp(html) {
  if (!html) return null;
  const match = html.match(/DEV OTP:\s*(\d{6})/);
  return match ? match[1] : null;
}

// One full pass at the login flow. Returns true only if the VU ends the
// flow with an authenticated session; returns false (without recording a
// failed check) on any transient problem so the caller can retry.
function attemptLogin(baseUrl, email, password) {
  if (!email || !password) fail('Set EMAIL and PASSWORD for a disposable test account.');
  // Step 1: GET the login page for a CSRF token.
  let res = http.get(`${baseUrl}/login`);
  const token = extractCsrfToken(res.body);
  if (!token) return false;

  // Step 2: submit credentials.
  //
  // redirects: 0 is deliberate and load-bearing. PRISM regenerates the
  // session ID during login, and Laravel only persists a session in its
  // terminate() phase - i.e. after the response has already been sent. If
  // k6 chases the redirect instantly, that next request arrives carrying a
  // session ID the database does not have yet, Laravel starts a fresh empty
  // session under it, and *that* empty session is what gets written - losing
  // the authenticated one. Not following the redirect, and pausing before
  // the next request, lets the write land first.
  res = http.post(
    `${baseUrl}/login`,
    { _token: token, email, password, 'g-recaptcha-response': '' },
    { redirects: 0 }
  );
  if (res.status !== 302) return false;
  sleep(THINK); // session write settles; user reads the privacy notice

  // Step 3: reload the login page - the privacy notice modal is shown there.
  res = http.get(`${baseUrl}/login`);
  const metaToken = extractMetaCsrf(res.body);
  if (!metaToken) return false;

  res = http.post(
    `${baseUrl}/privacy-notice`,
    JSON.stringify({ accepted: '1' }),
    {
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': metaToken,
      },
    }
  );
  if (res.status !== 200) return false;
  sleep(THINK); // page transitions to the OTP screen

  // Step 4: GET the OTP page, which prints the dev OTP straight into the HTML.
  res = http.get(`${baseUrl}/login/verify`);
  const otp = extractDevOtp(res.body);
  const otpToken = extractCsrfToken(res.body);
  if (!otp || !otpToken) return false;
  sleep(THINK); // user types the six-digit code

  // Step 5: submit the OTP to complete the login. Again redirects: 0 - this
  // is the step that calls session()->regenerate() after Auth::login(), so
  // it is the one most vulnerable to the lost-update race described above.
  res = http.post(
    `${baseUrl}/login/verify`,
    { _token: otpToken, otp },
    { redirects: 0 }
  );
  if (res.status !== 302) return false;
  sleep(THINK); // let terminate() persist the regenerated, authenticated session

  // Step 6: prove the VU is actually authenticated rather than assuming it.
  // A 200 here means the auth middleware let us through; a 302 means we are
  // still a guest and the login silently failed. Checking status alone after
  // following redirects would report a false success, because the redirect
  // target (/login) also returns 200.
  res = http.get(`${baseUrl}/notifications/unread-count`, { redirects: 0 });

  return res.status === 200;
}

// Logs in ONCE (in k6's setup(), before any VU starts) and returns the
// session cookie so every VU can reuse the same authenticated session.
//
// This exists because PRISM rate-limits OTP verification to 5 per minute per
// user+IP (AppServiceProvider: the 'login-otp' limiter). That is a real,
// correct security control - but it means N virtual users sharing one test
// account cannot all log in: only 5 per minute get through and the rest are
// throttled with a redirect. Logging in once and sharing the session avoids
// fighting a security feature that would never be exercised this way in
// production, and keeps the measurement on what we actually care about: how
// the target page behaves under concurrent authenticated traffic.
export function loginOnce(baseUrl, email, password) {
  const jar = http.cookieJar();
  let authenticated = false;
  for (let attempt = 1; attempt <= MAX_LOGIN_ATTEMPTS && !authenticated; attempt++) {
    authenticated = attemptLogin(baseUrl, email, password);
    if (!authenticated) sleep(2);
  }
  if (!authenticated) {
    fail(`loginOnce: could not authenticate ${email} after ${MAX_LOGIN_ATTEMPTS} attempts`);
  }

  const cookies = jar.cookiesForURL(`${baseUrl}/`);
  const name = Object.keys(cookies).find((k) => k.indexOf('session') !== -1);
  if (!name) fail('loginOnce: no session cookie found after successful login');

  return { name, value: cookies[name][0] };
}

// Applies the shared session cookie from setup() to this VU's cookie jar.
export function useSession(baseUrl, session) {
  http.cookieJar().set(`${baseUrl}/`, session.name, session.value);
}

// Logs in as the given user and returns the k6 http CookieJar.
// k6 keeps cookies per-VU automatically, so call this once per VU and reuse
// the same `baseUrl` for later requests.
export function login(baseUrl, email, password) {
  // Stagger VU starts so 100 virtual users don't all slam the login flow in
  // the same instant during ramp-up (real staff don't log in simultaneously
  // to the millisecond).
  sleep(Math.random() * 3);

  let authenticated = false;
  for (let attempt = 1; attempt <= MAX_LOGIN_ATTEMPTS && !authenticated; attempt++) {
    authenticated = attemptLogin(baseUrl, email, password);
    if (!authenticated) {
      sleep(1.5); // back off before retrying the setup
    }
  }

  check(authenticated, {
    'login: session authenticated': (ok) => ok === true,
  });

  if (!authenticated) {
    fail(`login: could not authenticate ${email} after ${MAX_LOGIN_ATTEMPTS} attempts`);
  }

  return http.cookieJar();
}
