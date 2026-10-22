# Student status update samples

Use User Management > Update Student Statuses. Choose a term, today's effective
date, and source `Demo Registrar status update`. These are synthetic test
decisions, not Registrar confirmations. Preview is read-only. Confirmation
really changes the listed accounts: use a test database or substitute disposable
student numbers before confirming. Record initial status and access first.

- `preview-mixed.csv`: enrolled, graduated, and an unknown student number. Existing
  numbers are proposed for update; 2099-9999999 should be Needs Checking if absent.
  All students absent from the file stay unchanged. Confirm only on test data.
- `reenrolled.csv`: re-enroll the second student after the departure test. Restores
  Active or Pending Setup; a separate temporary/other access restriction stays.
- The other six CSV files must be rejected without saving a batch or changing users.
- Future-date test: use the valid sample and tomorrow's date in the form.
- Stale preview: preview the sample, separately deactivate one listed student, then
  confirm the old preview. The entire confirmation must fail with no partial updates.
- Confirming the same batch twice must not duplicate changes or audit events.

Account records and disciplinary records are never deleted by this workflow.
Re-enrollment is a new audited change, not an undo of the test's history.

See [student-status operations](../../OPERATIONS.md#student-status-updates) and [setup](../../SETUP.md).
