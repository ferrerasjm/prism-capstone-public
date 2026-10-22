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

## Authentication and target selection

Browser scripts authenticate through password, privacy acknowledgement, and email OTP before measuring protected routes. The shared helper extracts the local development OTP from the page. A redirect to login or a 403 response is an authentication/permission problem; do not interpret it as successful page performance.

The standard load scenario defaults to `/admin/dashboard`; use an administrator account, or choose a permitted route with `TARGET_PATH`. It ramps to 100 virtual users over one minute, holds for three minutes, then ramps down for one minute. Each script defines its own profile; inspect `options` before running.

The mobile-sync scenario requires a US Supervisor account and a configured JWT secret. It authenticates once during setup and repeatedly requests assignments, history, and notifications. It ramps to 30 virtual users over 20 seconds, holds for one minute, then ramps down over 20 seconds. With mobile OTP enabled, its helper needs the local debug OTP response; keep that setting confined to disposable development instances.

## Interpreting results

Current load/mobile thresholds require p95 request duration below 500 ms and HTTP failure rate below 1%. Check explicit status assertions too: a fast error response is still a failure. These thresholds are project targets, not measured guarantees.

Record the commit, route/account role, dataset size, hardware, server/PHP configuration, background work, load profile, latency percentiles, failure counts, and failed checks with each result. Compare runs only under comparable conditions. A single-process development server can become the bottleneck; distinguish that result from production worker capacity.

Review route defaults and stages before running other scenarios. Authentication throttling and repeated setup requests can dominate a run; inspect setup failures before changing application limits. Avoid simultaneous scenarios unless their combined workload is the experiment being measured.
