# Database diagrams

- `prism_schema_full.sql`: complete schema export for inspection.
- `prism_schema_core.sql`: domain schema used by the module splitter.
- `modules/`: seven module SQL files and MySQL Workbench `.mwb` diagrams.

Open `.mwb` files in MySQL Workbench or reverse-engineer the corresponding SQL into a model. The diagram files are documentation; install the application using Laravel migrations in the [setup guide](../../docs/SETUP.md).

To regenerate module SQL after reviewing the core schema:

```bash
python tools/erd/build_modules.py
```

The script writes files under `tools/erd/modules/`. Review generated differences before committing. Cross-module relationships may appear as context; Laravel migrations and application rules remain authoritative.

## Module boundaries


| # | Module | Tables |
|---|---|---|
| 1 | User Management and Authentication | users, student_profiles, academic_units, academic_programs, discipline_officer_academic_unit, guidance_officer_academic_unit, account_setup_tokens |
| 2 | Violation and Case Documentation | incident_reports, incident_report_subjects, incident_attachments, academic_periods, cases, case_offenses, case_explanations, case_explanation_attachments, case_notices, case_resolutions, student_handbooks |
| 3 | Offense Classification and Escalation Support | offense_catalog, sanction_matrix, offense_recommendation_audits |
| 4 | Behavioral Risk Scoring | risk_scores, risk_score_snapshots, risk_interventions, risk_factor_weights, student_monitoring_flags |
| 5 | Sanction and Referral Monitoring | sanctions, counseling_referrals, discipline_education_attendances, university_service_offices, upcc_referrals, upcc_appeal_attachments |
| 6 | Reporting and Activity Log | audit_logs, notifications, announcements, csv_import_logs, scheduled_reports, number_sequences |
| 7 | Mobile University Service Tracking | us_assignments, us_attendance_logs, mobile_otp_challenges |


## Import and interpretation

In MySQL Workbench, create a new model, choose File > Import > Reverse Engineer MySQL Create Script, select the module SQL, and enable placing objects on a diagram. Use Crow's Foot (IE) notation where appropriate.

Module SQL includes small external-reference stubs so cross-module relationships can be drawn. Audit-only references to `users` are generally omitted; reporting/activity-log diagrams retain them because the acting user is part of their meaning. `cases` belongs to case documentation even though classification/escalation logic also updates it.

The exported schema is a point-in-time documentation snapshot. Regenerating module SQL reads `prism_schema_core.sql`; it does not introspect current Laravel migrations or a live database. Update and review that input before regenerating. Polymorphic notifications and application-validated office names may have no database foreign key; an unconnected diagram box is not proof that a relationship is missing from the application.
