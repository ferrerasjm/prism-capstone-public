# Security Testing — SQL Injection & Mobile API Stress (ISO 25010)

Your panel asked for two things: SQL injection testing, and an API
endpoint stress test for mobile sync. This covers both, mapped to ISO
25010's **Security** characteristic (Confidentiality & Integrity
sub-characteristic — same row your slide deck's "IT Experts" evaluation
table uses on page 55).

Like the k6 load tests, **you run everything here yourself** — this is
just the prepared material.

---

## Part 1 — SQL Injection

### 1.1 What a code review already found (do this part first, it's free)

Before firing any payloads, I searched the entire codebase for every place
raw SQL is used (`DB::raw`, `whereRaw`, `selectRaw`, `orderByRaw`, etc.) —
that's the only place a classic SQL injection could hide in a Laravel app,
because everywhere else (`->where('col', $value)`, `->orWhere(...)`, etc.)
Laravel's query builder automatically parameterizes values, making
injection structurally not possible.

**Result: only one spot in the whole app builds raw SQL from user input**
— `app/Http/Controllers/Officer/IncidentReportController.php:59`:

```php
->whereRaw("CONCAT(first_name,' ',last_name) LIKE ?", ["%{$search}%"])
```

This is **safe** — the `?` is a real bound parameter, not string
concatenation. The user's search text never touches the SQL string itself;
it's passed separately to MySQL as data, exactly like every other
Eloquent query in the app. Every other `Raw` usage found (about 30 of
them) is a hardcoded string like `CASE status WHEN 'pending' THEN 1...`
with **no user input in it at all** — nothing for an attacker to reach.

**This is a real, evidence-based finding**, not a guess: *"Code review of
all raw-SQL usage across the codebase found zero instances of unparameterized
user input reaching a SQL string; all data access goes through Laravel's
Eloquent ORM or bound parameters."* That's your Confidentiality &
Integrity write-up for the injection category — cite the file/line above
as your evidence.

### 1.2 Black-box confirmation (the empirical test your panel wants)

A code review is strong evidence, but your panel asked for *testing*, so
here's a hands-on confirmation: fire real SQL injection payloads at the
app's most exposed user-input fields and confirm none of them do anything
unusual. Run these yourself in your terminal (PowerShell) with the server
running (`php artisan serve`).

**Targets:** the login form (most classic SQLi target of all — auth
bypass), and the three search/typeahead boxes that take free-text user
input (`?q=`).

```powershell
# --- Login form: classic auth-bypass payloads ---
$loginPage = Invoke-WebRequest -Uri "http://127.0.0.1:8000/login" -Method Get -SessionVariable s -UseBasicParsing
$loginPage.Content -match 'name="_token" value="([^"]+)"' | Out-Null
$token = $Matches[1]

Invoke-WebRequest -Uri "http://127.0.0.1:8000/login" -Method Post -WebSession $s -UseBasicParsing -Body @{
    _token = $token
    email = "demo-user-001@nu-clark.edu.ph' OR '1'='1"
    password = "anything"
} | Select-Object StatusCode, StatusDescription

Invoke-WebRequest -Uri "http://127.0.0.1:8000/login" -Method Post -WebSession $s -UseBasicParsing -Body @{
    _token = $token
    email = "demo-user-001@nu-clark.edu.ph"
    password = "' OR 1=1 -- "
} | Select-Object StatusCode, StatusDescription
```

**Search boxes — do this one directly in the browser instead.** Testing
it through PowerShell would just get redirected to the login page before
ever reaching the search code (these routes require an authenticated
admin/officer/coordinator session) — not a very meaningful test, and not
much of a screenshot either. It's both a better test *and* better
evidence to log in normally through the browser (as you already have been
for the k6 tests) and type these directly into the real search box:

```
' OR '1'='1
'; DROP TABLE users; --
' UNION SELECT NULL,NULL,NULL -- 
admin' AND SLEEP(5) -- 
```

Try each one in: the admin user search (`/admin/users`), the officer
incident search (`/officer/incidents`), and the reporting-user student
lookup (`/reporter/reports/create`, the student lookup field). For the
`SLEEP(5)` one specifically, time it — if the search box takes about 5
seconds to respond, that's a real problem; if it responds normally, that
payload had no effect (which is what the code review predicts).

**Expected result (what "safe" looks like):**

| Payload type | Expected response | What would indicate a real vulnerability |
|---|---|---|
| `' OR '1'='1` in email/password | Normal "Invalid email or password" — login still fails | Successfully logging in without a real password |
| `'; DROP TABLE users; --` in search | Empty result set (`{"results": []}`) or normal 8-item list, HTTP 200 | An HTTP 500 with a MySQL syntax error visible, or the app breaking afterward |
| `' UNION SELECT NULL,NULL,NULL --` in search | Same as above — no extra/unexpected data | Data appearing that shouldn't be reachable by that query |
| `admin' AND SLEEP(5) --` in search | Response comes back in normal time (well under 5s) | Response deliberately takes ~5 seconds — that's the classic sign of a time-based blind SQL injection actually executing |

If every row in the "Expected response" column is what you see, that's
your empirical confirmation to pair with the code review finding above —
exactly the "Assessment Action → Expected Result" format your slide deck
uses for vulnerability tests (see the `.env` / server-banner examples on
pages 48-49).

### 1.3 (Optional) Automated scan

The slide deck also names OWASP ZAP as a tool for this. If you want a
second, tool-based opinion beyond the manual payloads above, and have
Docker installed:

```
docker run -v ${PWD}:/zap/wrk/:rw -t owasp/zap2docker-stable zap-baseline.py -t http://127.0.0.1:8000 -r zap-report.html
```

This is optional — the manual payload test above already gives you
real, citable evidence. Only do this if your panel specifically wants a
named-tool scan report.

---

## Part 2 — Mobile Sync API Stress Test

`tools/k6/mobile-sync-test.js` simulates PRISM's US Supervisor mobile app
syncing its data: on open (or pull-to-refresh), it pulls all three of its
endpoints in one burst — assignments, attendance history, and
notifications — the same way a real device would refresh its local cache.
This maps to ISO 25010's **Performance Efficiency (Time Behaviour)** and
**Reliability (Fault Tolerance)** rows.

### 2.1 One-time setup — mobile OTP

Mobile login is a real 2-step flow (password, then a 6-digit code) gated
by `MOBILE_OTP_ENABLED` in `.env` (currently `true`). Normally that code
is emailed for real via Gmail SMTP — a script can't read an inbox. The
backend has a built-in fallback for exactly this case though: set
`MOBILE_OTP_MAIL_ENABLED=false` in `.env`, and instead of emailing the
code, the server hands it back directly in the API response
(`debug_otp`) — so the script can still walk the **real** 2-step flow
end-to-end, it just doesn't need an inbox to do it.

```
MOBILE_OTP_MAIL_ENABLED=false
```

Then:
```
php artisan config:clear
```
Restart `php artisan serve --no-reload` after changing it.

⚠️ Revert `MOBILE_OTP_MAIL_ENABLED` back to `true` afterward — it controls
whether real users actually receive their mobile login code by email.

### 2.2 Run it

```
k6 run -e BASE_URL=http://127.0.0.1:8000 -e EMAIL=YOUR_TEST_EMAIL -e PASSWORD=YOUR_TEST_PASSWORD tools/k6/mobile-sync-test.js
```

This ramps to 30 simulated devices syncing at once, holds for a minute,
then ramps down — same p95 < 500ms / <1% error-rate bar as the web load
tests, for consistency across your report.

### 2.3 What "not fine" would look like

Same read as every other k6 result in this project: `checks_failed` /
`http_req_failed` above 0% means requests are actually breaking (auth
errors, 500s, timeouts) — that's a real problem. A red `✗` on the
`http_req_duration` threshold alone just means it's slower than the
500ms bar under this load, same "functionally fine, could be optimized"
finding as the coordinator/officer/reporting-user results already in your
report — not a new category of issue.
