# Registrar roster and records review

PRISM's Admin obtains the official Registrar roster each term. Registrar confirmation is the source for enrollment, graduation, transfer and withdrawal decisions. Account access status alone does not establish academic standing.

## Student status updates

1. Request a Registrar-confirmed CSV containing `student_number,student_status`. Supported statuses: enrolled, graduated, transferred, withdrawn, not_enrolled. Missing students are never inferred to have left.
2. Open User Management > Update Student Statuses. Select the period, effective date and Registrar source. Upload the file to preview only.
3. Review Will Update, Unchanged and Needs Checking counts. Unknown accounts and outdated effective dates are skipped. Malformed files are rejected.
4. Confirm once to apply valid listed students. Access becomes inactive for departures; re-enrollment restores Active or Pending Setup. Separate inactive access restrictions are preserved, even across departure and re-enrollment.
5. For one student, open their account > Update Student Status and use the same preview and confirmation process.
6. Create new accounts through Bulk Student Import; status updates do not create accounts.

Existing school statuses start as Not Yet Confirmed; access states are not backfilled into school statuses. Every changed account and applied batch is audited with the source and effective date. Confirmation is transactional and idempotent; changed accounts invalidate a stale preview without partial application. A newer confirmed effective date cannot be overwritten by an older list.

The old roster comparison pages and routes have been removed. Existing database records are preserved; they are no longer exposed in the application. The comparison CSV fixtures have been replaced by docs/test-data/student-statuses. Raw uploaded CSV files are not retained; proposed/applied rows are retained with the batch.

## Retention and disposal procedure

The supplied four-page CRK-ACD-SDO-D-PO-001 policy was visually reviewed. Its footer states an effective date of August 1, 2023. The policy defines an inactive record as no longer active and potentially unused for six months or more (page 1, III.3.b). This is separate from an inactive login account. Page 2, V assigns disposal approval to the Student Discipline Coordinator. Page 3, 3.b requires SDO approval and satisfaction of the minimum retention periods in Annex A of the QMO Control of Records; 3.c calls for review of inactive records every three years. Page 3, 4.a allows disposal of violation slips, apology letters and other records after three years, while 4.b requires cases with or without resolutions to be archived properly.

Annex A is not included in the supplied PDF, and the policy does not establish a graduation-based countdown for every record category. The 7/5-year configuration values remain application baselines, not a verified implementation of that institutional schedule. Do not replace every period with three years or automatically delete cases based on a student leaving.

The SDO should obtain Annex A from QMO and record, for each record category: policy/version and clause, retention period, start event, responsible reviewer, and disposal authority. The Student Discipline Assistant monitors/files records and the Coordinator approves disposal under V. Admin implements approved technical actions but does not infer disposal authority from account-maintenance access. Maintain a review register for inactive records and review it every three years as specified in 3.c; document earlier reviews of damaged/missing/altered records under 3.a.

For each proposed disposal batch, document record identifiers and linked evidence, applicable policy clause, trigger dates, retention calculation, unresolved case/appeal or preservation requirements, approver, method, execution date and verification result. Include generated copies and backup retention in the approved procedure; keeping an indefinite extra backup is not disposal. Maintain the authorized disposal evidence according to its own approved schedule.

Graduation and account deactivation do not trigger deletion. PRISM currently provides no general disposal executor or automated case-retention enforcement. `prism:privacy-check` only flags old audit logs for review. Do not claim full policy compliance or automated disposal during defense.

## Deployment

Run `php artisan migrate` and `php artisan view:cache`. The additive migration creates student status fields and batch history; it does not change existing access or disciplinary records. Student status update routes are restricted to Super Admin.
