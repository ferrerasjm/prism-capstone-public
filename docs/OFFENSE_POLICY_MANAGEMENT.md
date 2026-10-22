# Offense and policy management

The Super Admin uses **Offense & Policy** in the Admin sidebar to maintain the offense catalog, categories and interventions, major-offense sanction recommendations, and habitual minor-offense thresholds. Every change requires a reason and is recorded in the audit log. The Discipline Coordinator can review cases but cannot change these policy settings.

The page places Offense Catalog, Minor Escalation, Categories & Interventions, and Sanction Matrix in tabs below the summary counts. The selected tab stays in the URL so pagination and validation return to the same section.

## Offense catalog

- Add minor or major offenses with a unique code, title, description, optional handbook reference, and search keywords. A code must begin with `MINOR-` or `MAJOR-` to match its type.
- A new minor offense is active immediately. A new major offense starts as a draft until its first-offense sanction recommendation is entered and the offense is activated.
- Edit an offense only before it is linked to a case. Once used, create a replacement code and archive the older offense to preserve historical case wording. Archived offenses stay on existing case records but cannot be selected for new charges or returned as classification suggestions.
- `MAJOR-19` cannot be archived or changed to a different code/type because the habitual-disregard workflow depends on it.
- New catalog entries can be selected manually and used by keyword matching. The ML classifier must be retrained separately before it can predict a new code.

## Sanction recommendations

The page lists recommendations with their occurrence count, category, and University Service hour range. Add or edit a row to change officer guidance. Archiving preserves the row and audit history while excluding it from new case recommendations; saving an archived row restores it. An active major offense must retain an active first-offense recommendation.

For major offenses, enter the intervention category by offense count, with optional University Service hour ranges and guidance. At a later repeat count without an exact row, the system uses the highest configured count **at or below** the actual count. These rows recommend sanctions to staff; they do not impose them automatically.

Officers and coordinators read active matrix rows for current case guidance. An open case without an assigned category uses the current recommendation; a category already assigned to a case remains a recorded decision. Archiving a repeat row makes later occurrences fall back to the nearest earlier active row.

## Offense categories

The five handbook category numbers remain fixed because case resolution uses their workflows. Admins can edit the intervention name and description, archive an unused category, or restore an archived category from its row. A category used by an active recommendation or open assigned case cannot be archived. Only active categories can be chosen for new recommendations and case decisions.

## Minor escalation

The same-type and different-type thresholds are stored in `discipline_policy_settings`, initially 3 and 4. Changes take effect on the next case evaluation and in student/staff progress displays. Existing decisions and audit records are not rewritten. Record a reason for each change; the existing policy reference remains stored.

## Deployment

Run `php artisan migrate --force` and refresh Laravel's configuration, route, and view caches. Existing catalog and matrix records are preserved. The catalog and matrix seeders now seed only empty tables, so rerunning `db:seed` cannot replace approved edits. Do not run policy seeders to update an established catalog; use the management page and record the reason.
