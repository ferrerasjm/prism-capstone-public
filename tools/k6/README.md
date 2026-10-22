# Load & Stress Testing (K6) — PRISM

This walks you through running the same kind of Load/Stress test shown in
the "Writing Test Cases" slide deck (pp. 34–40), but against PRISM's real
login and real screens instead of a generic POS API. Everything in this
guide is meant for **you to run by hand** in your own terminal, so you get
real output on screen to screenshot as proof — nothing here runs itself.

---

## 0. Quick concept recap (so the screenshots make sense)

K6 doesn't click buttons in a browser — it fires raw HTTP requests very
fast, from many simulated "virtual users" (VUs) at once, and measures how
the server holds up. There are three flavors here, matching the slides:

- **Load test** (`load-test.js`) — a realistic, steady amount of traffic
  (like a normal busy period) held for a couple of minutes. Question it
  answers: *"does the system stay fast under normal peak usage?"*
- **Spike test** (`spike-test.js`) — traffic jumps instantly from low to
  high and back down. Question it answers: *"does the system survive a
  sudden burst, and recover afterward?"*
- **Breakpoint test** (`breakpoint-test.js`) — traffic keeps climbing in
  steps until something breaks. Question it answers: *"what's the maximum
  the system can actually handle before it starts failing?"*

All three log in as a real PRISM user first (the same 3-step flow you'd do
by hand: password → accept privacy notice → enter the OTP code), then
repeatedly hit real PRISM pages using that logged-in session.

---

## 1. One-time setup

Do these once, before running any test.

### 1.1 Check `.env` is ready

Open `.env` (you already have it open) and confirm these two lines:

```
RECAPTCHA_ENABLED=false
LOGIN_OTP_CONSOLE=true
```

Good news: on your machine **both are already set correctly** — `RECAPTCHA_ENABLED=false` and `LOGIN_OTP_CONSOLE=true` are already in your `.env`, so you don't need to change anything here. (Just know *why* these matter: reCAPTCHA can't be solved by a script, so it must be off for scripted logins to work at all; the OTP console flag makes PRISM print the one-time login code directly into the page instead of only emailing it, which is what lets the test script "read" the code the way you would from your inbox.)

⚠️ If you ever flip `RECAPTCHA_ENABLED` to `true` again to test something else, remember to set it back to `false` before running these scripts again — otherwise every login attempt in the test will fail at step 1.

### 1.2 Make sure the database is up and seeded

In a terminal, from the project root (`c:\laragon\www\prism-capstone`):

```
php artisan migrate --seed
```

This creates/updates the tables and inserts demo data, including the admin
account the test scripts log in as. If it finishes with no red error text,
you're good. (If MySQL isn't running yet, start it from the **Laragon**
control panel first — click "Start All".)

### 1.3 Start the PRISM server

```
php artisan serve
```

Leave this terminal window running — it's your live app server. You should
see output like:

```
INFO  Server running on [http://127.0.0.1:8000].
Press Ctrl+C to stop the server
```

Leave it open for the whole time you're running k6 tests. Open a **second,
separate terminal window** for the next steps — don't close this one.

### 1.4 Confirm k6 is on PATH

k6 was installed via winget (`GrafanaLabs.k6`, version 2.2.0) earlier. In
your **new** second terminal window, run:

```
k6 version
```

You should see something like `k6 v2.2.0 (...)`. If instead you get
`'k6' is not recognized...`, close and reopen your terminal (winget just
installed it, so your current terminal session may have started before
PATH was updated) and try again.

---

## 2. Run each test

Run these one at a time, in your second terminal (the one *not* running
`php artisan serve`), from the project root. Let each one finish completely
before starting the next — running two at once will skew both results.

### 2.1 Load test (steady peak traffic)

```
k6 run -e BASE_URL=http://127.0.0.1:8000 -e EMAIL=YOUR_TEST_EMAIL -e PASSWORD=YOUR_TEST_PASSWORD tools/k6/load-test.js
```

This ramps up to 20 simulated staff members browsing `/admin/dashboard` at
once, holds that for 2 minutes, then ramps back down. Takes about **3
minutes** total. Let it run to completion.

### 2.2 Spike test (sudden burst)

```
k6 run -e BASE_URL=http://127.0.0.1:8000 -e EMAIL=YOUR_TEST_EMAIL -e PASSWORD=YOUR_TEST_PASSWORD tools/k6/spike-test.js
```

This starts with 5 users, instantly jumps to 60 hitting the user
search-suggestions box (the typeahead), holds that spike for 30 seconds,
then drops back to 5. Takes about **1 minute**.

### 2.3 Breakpoint test (find the ceiling)

```
k6 run -e BASE_URL=http://127.0.0.1:8000 -e EMAIL=YOUR_TEST_EMAIL -e PASSWORD=YOUR_TEST_PASSWORD tools/k6/breakpoint-test.js
```

This climbs 25 → 50 → 100 → 150 → 200 concurrent users over 5 minutes,
mixing dashboard visits, the user list, and searches. Watch the terminal
as it runs — if `http_req_failed` (visible live during the run, or in the
end summary) starts climbing noticeably, that's the system's breaking
point. You can let it run the full 5 minutes, or press `Ctrl+C` early
once you've seen it fail — either way, note **at what VU count** things
started going wrong; that's the number you'd report as the capacity limit
(same as the slide's "System handles 350 concurrent cashiers safely..."
example).

---

## 3. Reading the output (what to screenshot)

When a run finishes, k6 prints a summary block that looks like this
(shortened example):

```
     ✓ dashboard loaded (200)

     checks.........................: 100.00% ✓ 120       ✗ 0
     http_req_duration..............: avg=142ms  p(95)=310ms
     http_req_failed.................: 0.00%   ✓ 0         ✗ 240
     vus.............................: 20      min=0       max=20
```

What each line means:

| Line | What it tells you |
|---|---|
| `✓ dashboard loaded (200)` | The check you defined in the script passed — the page actually returned HTTP 200 |
| `checks` | % of all your script's checks that passed. Should be close to 100% |
| `http_req_duration` | How long requests took. `p(95)` = 95% of requests were faster than this — the number the slide's "≤ 500ms" thresholds are comparing against |
| `http_req_failed` | % of requests that errored out (non-2xx/3xx, timeouts, etc.). Should be near 0% for the load and spike tests |
| `vus` | How many virtual users were active, and the max reached |

This whole block — plus a bit of the scrolled-up log above it — is exactly
what you screenshot as your test evidence, the same way the slide deck
shows a K6 terminal screenshot as its "proof."

If a threshold you set in the script fails (e.g. p95 over 500ms), k6 marks
that line with a red `✗` and exits with a non-zero status — that's not a
crash, that's the test telling you the system missed its target, which is
itself a valid (and screenshot-worthy) result.

---

## 4. What each script maps to (for your report)

| Script | Slide equivalent | What it hits | Simulates |
|---|---|---|---|
| `load-test.js` | Peak Rush Hour Concurrency (p. 35) | `GET /admin/dashboard`, 20 VUs, 2 min hold | Normal busy-hour staff traffic |
| `spike-test.js` | Sudden Flash Sale / Spike Test (p. 36) | `GET /admin/users/search-suggestions`, 5→60 VUs instantly | A sudden burst, e.g. everyone searching at once |
| `breakpoint-test.js` | Maximum Capacity / Breakpoint (p. 38) | Mixed dashboard/list/search traffic, 25→200 VUs in steps | Finding the system's real ceiling |

`tools/k6/lib/auth.js` is the shared login helper all three scripts use.
It walks PRISM's actual 3-step login exactly as a person would:

1. `GET /login` → reads the CSRF token off the login form.
2. `POST /login` with email + password → PRISM shows the privacy notice.
3. `POST /privacy-notice` accepting it → PRISM sends (and, locally, prints)
   a one-time login code.
4. `GET /login/verify` → reads the dev OTP straight out of the page HTML
   (only visible because `LOGIN_OTP_CONSOLE=true`).
5. `POST /login/verify` with that code → now fully logged in.

Each virtual user does this **once** and reuses that logged-in session for
every request after, the same way a real staff member logs in once and
then just keeps browsing.

---

## 5. Testing other roles

Create disposable accounts in a local or staging database through User Management.
Supply `EMAIL` and `PASSWORD` privately; the scripts have no default credentials.
`TARGET_PATH` selects a page the supplied account's role can access.
Never use production student or staff accounts for these examples.

### Load test as coordinator, against the risk dashboard (closest match to the slide's "EOD Report" case)

```
k6 run -e BASE_URL=http://127.0.0.1:8000 -e EMAIL=YOUR_TEST_EMAIL -e PASSWORD=YOUR_TEST_PASSWORD -e TARGET_PATH=/coordinator/risk-dashboard tools/k6/load-test.js
```

### Spike test as officer, against the incident search typeahead (closest match to the slide's "Stock Search" case)

```
k6 run -e BASE_URL=http://127.0.0.1:8000 -e EMAIL=YOUR_TEST_EMAIL -e PASSWORD=YOUR_TEST_PASSWORD -e TARGET_PATH=/officer/incidents/search-suggestions tools/k6/spike-test.js
```

You can substitute any other route that account's role can reach — e.g.
`/coordinator/reports/risk-analytics` (heavy report, like the slide's EOD
report), or `/officer/cases/search-suggestions`. `breakpoint-test.js`
stays admin-only for now, since its mixed-traffic pattern (dashboard +
list + search) is hardcoded to one role's screens — ask if you want a
role-parameterized version of that one too.

---

## 6. Troubleshooting

| Symptom | Likely cause |
|---|---|
| `login: could not read CSRF token from /login` | Server isn't running — check your `php artisan serve` window |
| `login: no DEV OTP found on /login/verify` | `LOGIN_OTP_CONSOLE` got set back to `false`, or `RECAPTCHA_ENABLED=true` silently blocked the login step before it — recheck `.env` |
| Every request in the summary shows `http_req_failed` near 100% | Wrong `BASE_URL`, or the server crashed partway through — check the `php artisan serve` terminal for errors |
| `'k6' is not recognized...` | Open a fresh terminal window so it picks up the updated PATH |
