# PRISM cleanup review — 2026-09-09

Historical review record. [Documentation index](README.md)

At the end of this review, changes were uncommitted and unstaged. The results
below describe that work-tree snapshot, not a continuing guarantee about future
releases or current Git status. No database schema, migrations,
routes, environment settings, model artifacts, user uploads, or installed dependency
directories were removed.

## Scope and evidence

The baseline inventory contained 766 tracked files: 736 decodable text files
(334,598 lines) and 30 binary files. Automated content/reference scanning read
the text files, including application code, all Blade templates, route files,
configuration, migrations, seeders, tests, deployment scripts, documentation,
training data, and dependency manifests. PHP imports were also checked with
Pint's `no_unused_imports` rule alone; Python imports were checked using the AST.
Candidate removals received targeted manual review and follow-up reference searches.

This is a repository-wide static audit with targeted manual review, **not a claim
that every line received manual semantic review** or that static search proves
the absence of all external/dynamic callers. Third-party implementations in
`vendor/`, `node_modules/`, and `.venv-ml/`, Git internals, binary contents,
runtime caches, private database contents, and historical model experiments were
not subjected to a line-by-line dead-code audit. A wider hidden/ignored-file
reference search found the generated frontend manifest; it also encountered
access-denied pytest cache directories. These directories were retained.

Reference evidence and test logs were retained locally in `storage/logs/cleanup-*`.
They are ignored diagnostic artifacts and may not be available in another checkout.
Retain this report with the cleanup change for review history; use the current
maintenance guides for operational instructions.

## Final removals

| File / symbol | Removal | Reason and reference verification |
| --- | --- | --- |
| `vite.config.js` | Entire unused configuration file (25 lines) | No project reference to the configuration filename, no Blade `@vite()`/`Vite::` consumer, no npm build/dev script, and no declared Vite dependency. README and deployment scripts explicitly describe a frontend without a build step. |
| `tests/Feature/CaseCorrectionActionsTest.php` | `use App\Models\CaseOffense;` | No use of the imported alias anywhere else in the file; independently confirmed by Pint. The model itself remains in use elsewhere and is retained. |
| `ml/scripts/train_baseline.py` | `import json` | AST finds no reference to the binding. Full script review shows JSON output uses the imported `pilot_common.write_json`; no dynamic lookup or external import of this binding was found. |
| `app/Models/Sanction.php` | `isAwaitingAssessment()` and its attached docblock | No caller or string reference outside its declaration in repository-wide and wider work-tree searches. Ordinary boolean helper, not an Eloquent scope, relation, accessor, override, or framework hook. Existing assessment workflow remains covered by the suite. |
| `package-lock.json` | Four stale root declarations and 74 obsolete package records | Reconciled offline with the existing `package.json` using npm's lockfile-only mode. Removed roots: `@tailwindcss/vite`, `laravel-vite-plugin`, `tailwindcss`, `vite`. Their dependency records are no longer reachable from the sole declared root, `concurrently`. All 25 retained package records, including versions and integrity hashes, are unchanged. |

No orphaned route, public asset, migration, model relationship, or commented-out
executable block met the conservative removal threshold. Explanatory comments
were retained. No directory deletion remains in the final diff.

## Validation by batch

| Stage | Check | Result |
| --- | --- | --- |
| Baseline | Complete PHPUnit suite | 744 passed; 3,958 assertions |
| Baseline Python | Complete `ml/tests` suite | Initial sandbox run: 121 passed, 16 fixture setup errors caused by temp-directory permissions. Rerun with required access: 137 passed, 22 dependency warnings. |
| Batch 1: Vite config and PHP import | Complete PHPUnit suite | 744 passed; 3,958 assertions |
| Batch 2: lockfile reconciliation and frontend entry-point review | Complete PHPUnit suite | 744 passed; 3,958 assertions |
| Final batch: Python import and sanction helper | Complete PHPUnit suite | 744 passed; 3,958 assertions |
| Final batch | Complete Python suite, including real-model tests | 137 passed; 22 dependency warnings |
| Frontend/framework | Blade compilation (`php artisan view:cache`) | Passed |
| Routing | Parsed route-list snapshots compared | All 251 routes identical |
| PHP imports | Pint with only `no_unused_imports` enabled | Passed after PHP import removal |
| Syntax | Changed PHP files and all seven `public/js/*.js` files | Passed |
| Python training entry point | `train_baseline.py --help` | Passed |
| Frozen dataset integrity | `build_training_splits.py --check` | Committed splits match the reviewed CSV |
| Development runner | Local `concurrently --version` | Passed |
| Lockfile | Compare retained package records and root declarations | 25 retained package records unchanged; roots match package.json |
| Patch hygiene | `git diff --check` | Passed |

Batch 2 initially removed `resources/css/app.css` and the empty
`resources/js/app.js` after their tracked references disappeared. The wider
search subsequently found both names in ignored `public/build/manifest.json`.
Both source files were restored byte-for-byte from HEAD before final validation,
and their directories and generated assets are retained. They are **not removals**
in the proposed change.

## Retained candidates and rationale

- All 13 Artisan command classes: Laravel discovers them by directory; schedules,
  signatures, tests, and operational use do not require class-name references.
- Error views: framework-selected by HTTP status. Pagination override: consumed
  through the `pagination::prism` view namespace.
- Model accessors, query scopes, relationships, JWT methods, and authentication
  hooks: dynamic/framework consumers make raw method-name counts insufficient.
  Public relationships are retained even when no present caller was found.
- All routes, including legacy QR and mobile API endpoints: external clients and
  bookmarks are valid consumers without a local route-name reference.
- Public PNG/WebP pairs and fonts: references exist in templates, CSS, email,
  reports, tests, or font manifests. No image was removed by guessing duplicates.
- `public/favicon.ico`, `public/robots.txt`, web-server configuration, and storage
  placeholders: browser/server conventions or directory-layout contracts apply.
- Migrations, seeders, research datasets, ML scripts/artifacts, ERD modules,
  deployment scripts, and the ignored SQL backup: operational, historical,
  reproducibility, or user-data value; not disposable solely for lack of imports.
- Blade/CSS comment candidates: manual inspection found explanatory prose,
  not disabled executable markup.

## Removed lockfile package records

Each record below was part of the obsolete frontend build dependency graph and
is absent from npm's reconciled graph rooted at `concurrently`. This removes
lockfile metadata only; it does not delete local `node_modules` files.

| Package record | Reason |
| --- | --- |
| `node_modules/@emnapi/core` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@emnapi/runtime` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@emnapi/wasi-threads` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@jridgewell/gen-mapping` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@jridgewell/remapping` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@jridgewell/resolve-uri` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@jridgewell/sourcemap-codec` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@jridgewell/trace-mapping` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@napi-rs/wasm-runtime` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@oxc-project/types` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@rolldown/binding-android-arm64` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@rolldown/binding-darwin-arm64` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@rolldown/binding-darwin-x64` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@rolldown/binding-freebsd-x64` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@rolldown/binding-linux-arm-gnueabihf` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@rolldown/binding-linux-arm64-gnu` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@rolldown/binding-linux-arm64-musl` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@rolldown/binding-linux-ppc64-gnu` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@rolldown/binding-linux-s390x-gnu` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@rolldown/binding-linux-x64-gnu` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@rolldown/binding-linux-x64-musl` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@rolldown/binding-openharmony-arm64` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@rolldown/binding-wasm32-wasi` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@rolldown/binding-win32-arm64-msvc` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@rolldown/binding-win32-x64-msvc` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@rolldown/pluginutils` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@tailwindcss/node` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@tailwindcss/oxide` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@tailwindcss/oxide-android-arm64` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@tailwindcss/oxide-darwin-arm64` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@tailwindcss/oxide-darwin-x64` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@tailwindcss/oxide-freebsd-x64` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@tailwindcss/oxide-linux-arm-gnueabihf` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@tailwindcss/oxide-linux-arm64-gnu` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@tailwindcss/oxide-linux-arm64-musl` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@tailwindcss/oxide-linux-x64-gnu` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@tailwindcss/oxide-linux-x64-musl` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@tailwindcss/oxide-wasm32-wasi` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@tailwindcss/oxide-win32-arm64-msvc` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@tailwindcss/oxide-win32-x64-msvc` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@tailwindcss/vite` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/@tybys/wasm-util` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/detect-libc` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/enhanced-resolve` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/fdir` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/fsevents` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/graceful-fs` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/jiti` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/laravel-vite-plugin` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/lightningcss` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/lightningcss-android-arm64` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/lightningcss-darwin-arm64` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/lightningcss-darwin-x64` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/lightningcss-freebsd-x64` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/lightningcss-linux-arm-gnueabihf` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/lightningcss-linux-arm64-gnu` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/lightningcss-linux-arm64-musl` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/lightningcss-linux-x64-gnu` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/lightningcss-linux-x64-musl` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/lightningcss-win32-arm64-msvc` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/lightningcss-win32-x64-msvc` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/magic-string` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/nanoid` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/picocolors` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/picomatch` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/postcss` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/rolldown` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/source-map-js` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/tailwindcss` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/tapable` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/tinyglobby` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/vite` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/vite-plugin-full-reload` | Unreachable from the declared dependency graph after reconciliation. |
| `node_modules/vite-plugin-full-reload/node_modules/picomatch` | Unreachable from the declared dependency graph after reconciliation. |

## Baseline tracked inventory by area

| Area | Files |
| --- | ---: |
| `(root)` | 15 |
| `app` | 160 |
| `bootstrap` | 3 |
| `config` | 17 |
| `database` | 114 |
| `deploy` | 10 |
| `docs` | 5 |
| `ml` | 56 |
| `public` | 39 |
| `resources` | 148 |
| `routes` | 3 |
| `storage` | 28 |
| `tests` | 141 |
| `tools` | 27 |

## Conservative method-review inventory

The following low-reference model methods were flagged for review and retained
(except the explicitly removed sanction helper above). This list is not a
declaration that these methods are dead.

- `app/Models/AcademicPeriod.php::incidentReports()`
- `app/Models/AcademicPeriod.php::scopeContaining()`
- `app/Models/AcademicPeriod.php::getLabelAttribute()`
- `app/Models/AcademicUnit.php::disciplineOfficers()`
- `app/Models/AcademicUnit.php::scopeOfficerAssignable()`
- `app/Models/Announcement.php::targetUser()`
- `app/Models/Announcement.php::targetAcademicUnit()`
- `app/Models/Announcement.php::scopeForUser()`
- `app/Models/DisciplinaryCase.php::offenseRecommendationAudits()`
- `app/Models/DisciplinaryCase.php::activeNotices()`
- `app/Models/DisciplinaryCase.php::scopeForwardedToUpcc()`
- `app/Models/DisciplinaryCase.php::scopeResolved()`
- `app/Models/DisciplinaryCase.php::scopeEscalationFlagged()`
- `app/Models/IncidentReport.php::scopePendingReview()`
- `app/Models/IncidentReport.php::scopeBySourceType()`
- `app/Models/OffenseCatalog.php::sanctionMatrix()`
- `app/Models/OffenseCatalog.php::suggestedCases()`
- `app/Models/OffenseCatalog.php::chargedCases()`
- `app/Models/OffenseRecommendationAudit.php::selectedOffense()`
- `app/Models/RiskIntervention.php::riskScoreSnapshot()`
- `app/Models/StudentHandbook.php::scopePublished()`
- `app/Models/StudentHandbook.php::scopePublishedHistory()`
- `app/Models/StudentMonitoringFlag.php::flaggedBy()`
- `app/Models/StudentMonitoringFlag.php::deactivatedBy()`
- `app/Models/StudentProfile.php::scopeForAcademicUnit()`
- `app/Models/UpccReferral.php::appealWaivedBy()`
- `app/Models/UsAssignment.php::getSupervisorDisplayNameAttribute()`
- `app/Models/UsAssignment.php::scopeVisibleToSupervisor()`
- `app/Models/User.php::getJWTIdentifier()`
- `app/Models/User.php::getJWTCustomClaims()`
- `app/Models/User.php::sendPasswordResetNotification()`
- `app/Models/User.php::getFullNameAttribute()`
- `app/Models/User.php::getListNameAttribute()`
- `app/Models/User.php::riskScoreSnapshots()`
- `app/Models/User.php::assignedRiskInterventions()`
- `app/Models/User.php::monitoringFlags()`
- `app/Models/User.php::activeMonitoringFlag()`
- `app/Models/User.php::assignedCases()`
- `app/Models/User.php::supervisedAssignments()`
- `app/Models/User.php::counselingReferrals()`
- `app/Models/User.php::assignedReferrals()`
- `app/Models/User.php::csvImportLogs()`
- `app/Models/User.php::scopeStudents()`
- `app/Models/User.php::scopePendingSetup()`
