# Student-status CSV examples

Use these synthetic files only with disposable test accounts in User Management > Update Student Statuses. Confirmation changes account statuses and creates audit history.

- `preview-mixed.csv`: enrolled, graduated, and an unknown student number.
- `reenrolled.csv`: re-enroll a previously departed account.
- Remaining files exercise invalid headers, values, duplicates, or row structure.

Students absent from an upload remain unchanged. A stale preview must fail without partial changes; repeated confirmation must not duplicate the batch. Account and disciplinary records are not deleted by this workflow.

See [setup](../../SETUP.md) to create the test instance.
