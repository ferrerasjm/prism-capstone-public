// Login helper for PRISM's MOBILE API (JWT-based), used by the mobile sync
// stress test. Different, simpler flow than lib/auth.js (the web session
// login): the mobile API returns a Bearer token directly, and a Bearer
// token bypasses the CSRF-cookie middleware entirely (see
// VerifyJwtCookieCsrf::handle — it skips the check whenever the
// Authorization header starts with "bearer "), so no CSRF dance is needed.
//
// Mobile login is a real 2-step flow when MOBILE_OTP_ENABLED=true (it is,
// by default): password first, then a 6-digit OTP. Normally that OTP is
// emailed via real Gmail SMTP (MOBILE_OTP_MAIL_ENABLED=true), which a
// script can't read. But the backend has a built-in fallback for exactly
// this: when MOBILE_OTP_MAIL_ENABLED=false, it skips sending the email and
// returns the code directly in the response as `debug_otp` instead
// (AuthController::issueMobileOtp). Set that flag to false in .env before
// running this script so it can complete the real OTP step end-to-end.
import http from 'k6/http';
import { check, fail } from 'k6';

export function mobileLogin(baseUrl, email, password) {
  if (!email || !password) fail('Set EMAIL and PASSWORD for a disposable test account.');
  let res = http.post(
    `${baseUrl}/api/auth/login`,
    JSON.stringify({ email, password }),
    { headers: { 'Content-Type': 'application/json', Accept: 'application/json' } }
  );

  if (res.status !== 200) {
    fail(`mobile login (password step): unexpected status ${res.status} - ${res.body}`);
  }

  let body = res.json();

  // MOBILE_OTP_ENABLED=false path: token comes back immediately.
  if (body.access_token) {
    return body.access_token;
  }

  // MOBILE_OTP_ENABLED=true path: password step just opened an OTP challenge.
  if (!body.otp_required) {
    fail(`mobile login: unexpected response shape - ${res.body}`);
  }

  if (!body.debug_otp) {
    fail(
      'mobile login: server wants an OTP but is emailing it for real ' +
      '(MOBILE_OTP_MAIL_ENABLED=true), which this script cannot read. ' +
      'Set MOBILE_OTP_MAIL_ENABLED=false in .env, php artisan config:clear, ' +
      'restart the server, and re-run.'
    );
  }

  res = http.post(
    `${baseUrl}/api/auth/otp/verify`,
    JSON.stringify({ email, otp_token: body.otp_token, code: body.debug_otp }),
    { headers: { 'Content-Type': 'application/json', Accept: 'application/json' } }
  );

  check(res, { 'mobile login: OTP step ok': (r) => r.status === 200 });

  if (res.status !== 200) {
    fail(`mobile login (OTP step): unexpected status ${res.status} - ${res.body}`);
  }

  const finalBody = res.json();
  check(res, { 'mobile login: got access_token': () => !!finalBody.access_token });

  return finalBody.access_token;
}
