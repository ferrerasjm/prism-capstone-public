# PRISM — ERD & Access Documentation

Generated from the project database schema export (49 tables). Laravel infrastructure tables
(`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`,
`sessions`, `password_reset_tokens`) are excluded — none carry foreign keys.

Module boundaries below match the project's seven official modules (Chapter 3 / SRS),
not an arbitrary schema-driven grouping.

## Files

| File | Contents |
|---|---|
| `prism_schema_full.sql` | All 49 tables, 93 FKs |
| `prism_schema_core.sql` | 41 domain tables, 93 FKs |
| `modules/1_user_management_authentication.sql` … `modules/7_mobile_university_service_tracking.sql` | One file per ERD diagram |

Module files omit audit-only FKs to `users` (`created_by`, `updated_by`,
`uploaded_by`, `verified_by`, …) and include small "external reference" stubs
for tables owned by another module, so every relationship still draws.
Module 6 (Reporting and Activity Log) is the exception — it keeps every
`users` FK, because for support/log tables the acting user *is* the domain
relationship.

Regenerate with `python tools/erd/build_modules.py` after any schema change.

**Import:** `File → New Model`, then
`File → Import → Reverse Engineer MySQL Create Script…`,
tick *Place imported objects on a diagram*.
Then set `Model → Relationship Notation → Crow's Foot (IE)`.

## Module map

| # | Module | Tables |
|---|---|---|
| 1 | User Management and Authentication | users, student_profiles, academic_units, academic_programs, discipline_officer_academic_unit, guidance_officer_academic_unit, account_setup_tokens |
| 2 | Violation and Case Documentation | incident_reports, incident_report_subjects, incident_attachments, academic_periods, cases, case_offenses, case_explanations, case_explanation_attachments, case_notices, case_resolutions, student_handbooks |
| 3 | Offense Classification and Escalation Support | offense_catalog, sanction_matrix, offense_recommendation_audits |
| 4 | Behavioral Risk Scoring | risk_scores, risk_score_snapshots, risk_interventions, risk_factor_weights, student_monitoring_flags |
| 5 | Sanction and Referral Monitoring | sanctions, counseling_referrals, discipline_education_attendances, university_service_offices, upcc_referrals, upcc_appeal_attachments |
| 6 | Reporting and Activity Log | audit_logs, notifications, announcements, csv_import_logs, scheduled_reports, number_sequences |
| 7 | Mobile University Service Tracking | us_assignments, us_attendance_logs, mobile_otp_challenges |

### Why `cases` sits in Module 2, not Module 3

"Offense Classification and Escalation Support" is a functional module, but its
escalation logic (`escalation_flagged`, `escalation_acknowledged_by`,
`escalation_acknowledged_at`) lives as columns inside the `cases` table itself,
not a separate table — an ERD groups whole tables, so `cases` has to live in one
diagram. It sits in Module 2 (Case Documentation) since that's where the bulk of
its columns and relationships belong; Module 3 references it as an external stub.

### Why `mobile_otp_challenges` sits in Module 7, not Module 1

It is the OTP table behind the mobile app's supervisor sign-in flow (Table 14,
Module 1: "Supervisor Sign-In, Email OTP Verification"), not the web account
system — `account_setup_tokens` (Module 1) is the equivalent for web accounts.

### Cross-module relationships

```
incident_reports ──> cases ──> case_resolutions ──> sanctions ──> counseling_referrals
      (2)             (2)           (2→5)              (5)          discipline_education_attendances
                       ^             │
                 upcc_referrals      └──> us_assignments (7) ──> us_attendance_logs (7)
                     (2→5)
```

`offense_catalog`, `sanction_matrix` and `offense_recommendation_audits` (Module 3)
all reference `cases` (Module 2). `case_offenses` (Module 2) also references
`offense_catalog` (Module 3) — the classification module and the documentation
module reference each other in both directions.

## Role × Module access matrix

Derived from the `role:` middleware groups in `routes/web.php`.
C = create, R = read, U = update, D = delete, — = no access.

| Role | 1 User Mgmt | 2 Case Doc | 3 Offense Class. | 4 Risk Scoring | 5 Sanction/Referral | 6 Reporting/Log | 7 Mobile US |
|---|---|---|---|---|---|---|---|
| `super_admin` | C R U | — | — | R U | — | C R | — |
| `discipline_coordinator` | R | R U | R | R | R U | R | — |
| `discipline_officer` | R | C R U D | C | R | C | R | — |
| `reporting_user` | R¹ | C R | — | — | C | R | — |
| `guidance_officer` | R | R | — | — | R U² | R | — |
| `us_supervisor` | R¹ | — | — | — | — | — | R U³ |
| `student` | R¹ | R + C⁴ | — | — | R + C U⁵ | R⁶ | — |

1. Own record only (`/dashboard/profile`).
2. `counseling_referrals` only — acknowledge / complete / incomplete.
3. `us_assignments` + `us_attendance_logs` — log session, record no-show (via the mobile app).
4. Student complaints are `incident_reports` rows with `source_type = 'student_complaint'`.
5. Submit or waive an appeal (`upcc_referrals`) on own case.
6. `notifications` and `student_handbooks` (read + download); `student_handbooks` is Module 2, listed under Reporting here only for the notification feed.

### Notes for the defence

- **`super_admin` has no case access.** The `/admin` route group covers accounts,
  academic structure, CSV import and risk weights only — deliberate separation
  of duties between account administration and case handling.
- **Students are `users`.** There is no separate student entity; `student_profiles`
  extends `users`, and every `student_id` across the schema references `users.id`.
- **`reporting_user` can file appeals too** (`/reporter/cases/{case}/appeal`), not
  only students.

## Verification

Checked after generation — every FK target resolves inside its own file, every
one of the 41 domain tables appears in exactly one module, and no relationship
is silently dropped on import.

| File | Boxes | Relationship lines | Unconnected |
|---|---|---|---|
| `1_user_management_authentication.sql` | 7 | 8 | — |
| `2_violation_case_documentation.sql` | 14 | 19 | — |
| `3_offense_classification_escalation.sql` | 4 | 3 | — |
| `4_behavioral_risk_scoring.sql` | 6 | 6 | `risk_factor_weights` |
| `5_sanction_referral_monitoring.sql` | 9 | 10 | `university_service_offices` |
| `6_reporting_activity_log.sql` | 8 | 6 | `notifications`, `number_sequences` |
| `7_mobile_university_service_tracking.sql` | 5 | 6 | — |

The three remaining unconnected tables are facts about the schema, not diagram
artefacts:

- `university_service_offices` — `us_assignments.office_name` is validated at the
  application layer (`Rule::exists`) rather than a database FK. Documented, not fixed.
- `risk_factor_weights` — configuration/weights table, read by code, never joined.
- `notifications` — Laravel's polymorphic store (`notifiable_type` + `notifiable_id`);
  a polymorphic relation cannot have a foreign key.
- `number_sequences` — counter table for generating case/report numbers.
