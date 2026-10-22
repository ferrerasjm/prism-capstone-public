# Load testing

Use k6 against a disposable local/staging database with explicit test-account credentials. Scripts authenticate as real accounts; they do not provision accounts.

## Setup

Follow [application setup](../../docs/SETUP.md), create the account/role needed by the target route, and start the app. For scripted browser login, use local-only `RECAPTCHA_ENABLED=false` and `LOGIN_OTP_CONSOLE=true`, then clear Laravel configuration.

```bash
k6 run -e BASE_URL=http://127.0.0.1:8000 -e EMAIL=YOUR_TEST_EMAIL -e PASSWORD=YOUR_TEST_PASSWORD tools/k6/load-test.js
```

Other entry points:

| Script | Purpose |
| --- | --- |
| `spike-test.js` | Sudden traffic burst |
| `breakpoint-test.js` | Increasing concurrency |
| `heavy-aggregation-test.js` | Expensive report/aggregation routes |
| `mobile-sync-test.js` | Mobile API login and synchronization |

Select a permitted target with `TARGET_PATH` where supported; inspect each script's options for its load profile. Mobile OTP automation requires local-only `MOBILE_OTP_MAIL_ENABLED=false` when mobile OTP is enabled. Restore OTP delivery and console settings before exposing a deployment.

Review request failures, authentication checks, and latency percentiles together. Passing a threshold in one environment does not establish production capacity. Run one scenario at a time; protect credentials and private response data when retaining output.
