"""Split prism_schema_core.sql into per-module files for MySQL Workbench ERDs.
Module boundaries match the project's official module list (Chapter 3 / SRS).

Run from the repo root:  python tools/erd/build_modules.py
"""
import re, os

SRC = 'tools/erd/prism_schema_core.sql'
OUT = 'tools/erd/modules'

# user-FK columns naming a real participant in the record; everything else is audit trail
KEEP_USER_FK = {'student_id', 'user_id', 'supervisor_id', 'guidance_officer_id',
                'assigned_to', 'referred_by', 'target_user_id', 'reported_by_user_id'}

MODULES = [
    ("1_user_management_authentication", "User Management and Authentication Module",
     ['users', 'student_profiles', 'academic_units', 'academic_programs',
      'discipline_officer_academic_unit', 'guidance_officer_academic_unit',
      'account_setup_tokens'], False),
    ("2_violation_case_documentation", "Violation and Case Documentation Module",
     ['incident_reports', 'incident_report_subjects', 'incident_attachments',
      'academic_periods', 'cases', 'case_offenses', 'case_explanations',
      'case_explanation_attachments', 'case_notices', 'case_resolutions',
      'student_handbooks'], False),
    ("3_offense_classification_escalation", "Offense Classification and Escalation Support Module",
     ['offense_catalog', 'sanction_matrix', 'offense_recommendation_audits'], False),
    ("4_behavioral_risk_scoring", "Behavioral Risk Scoring Module",
     ['risk_scores', 'risk_score_snapshots', 'risk_interventions',
      'risk_factor_weights', 'student_monitoring_flags'], False),
    ("5_sanction_referral_monitoring", "Sanction and Referral Monitoring Module",
     ['sanctions', 'counseling_referrals', 'discipline_education_attendances',
      'university_service_offices', 'upcc_referrals', 'upcc_appeal_attachments'], False),
    ("6_reporting_activity_log", "Reporting and Activity Log Module",
     ['audit_logs', 'notifications', 'announcements', 'csv_import_logs',
      'scheduled_reports', 'number_sequences'], True),
    ("7_mobile_university_service_tracking", "Mobile University Service Tracking Module",
     ['us_assignments', 'us_attendance_logs', 'mobile_otp_challenges'], False),
]

src = open(SRC, encoding='utf-8').read()
tbl = {n: s for s, n in re.findall(r'(CREATE TABLE `([^`]+)`.*?\n\) ENGINE[^;]*;)', src, re.S)}


def rebuild(stmt, keep_all):
    head, inner, foot = re.match(
        r'(CREATE TABLE `[^`]+` \()\n(.*)\n(\) ENGINE.*)', stmt, re.S).groups()
    out = []
    for ln in inner.split('\n'):
        s = ln.rstrip().rstrip(',')
        m = re.search(r'CONSTRAINT `[^`]+` FOREIGN KEY \(`([^`]+)`\) REFERENCES `([^`]+)`', s)
        if m and not keep_all:
            col, ref = m.groups()
            if ref == 'users' and col not in KEEP_USER_FK:
                continue
        out.append(s)
    return head + '\n' + ',\n'.join(out) + '\n' + foot


def stub(name):
    """Minimal reference box: PK + one identifying column, so FK lines still draw."""
    s = tbl[name]
    pk = re.search(r'PRIMARY KEY \(`([^`]+)`\)', s).group(1)
    pkdef = re.search(rf'^\s+(`{pk}`[^\n]*?),?$', s, re.M).group(1).replace(' AUTO_INCREMENT', '')
    ident = ''
    for c in ('name', 'title', 'case_number', 'report_number', 'code', 'email'):
        m = re.search(rf'^\s+(`{c}`[^\n]*?),?$', s, re.M)
        if m:
            ident = ',\n  ' + m.group(1)
            break
    return (f"-- [external reference - full definition lives in its own module]\n"
            f"CREATE TABLE `{name}` (\n  {pkdef}{ident},\n  PRIMARY KEY (`{pk}`)\n"
            f") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;")


os.makedirs(OUT, exist_ok=True)
for slug, title, tables, keep_all in MODULES:
    body = [rebuild(tbl[t], keep_all) for t in tables]
    ext = sorted({r for t in tables for _, r in
                  re.findall(r'FOREIGN KEY \(`([^`]+)`\) REFERENCES `([^`]+)`',
                             rebuild(tbl[t], keep_all)) if r not in tables})
    note = ("-- All FKs retained: for support tables the acting user is the domain relationship.\n"
            if keep_all else
            "-- Audit-only FKs to `users` (created_by/updated_by/...) omitted for readability.\n")
    hdr = (f"-- PRISM ERD - Module {slug.split('_')[0]}: {title}\n"
           f"-- {len(tables)} module tables"
           + (f" + {len(ext)} external reference stubs" if ext else "") + "\n"
           + note + "\nSET FOREIGN_KEY_CHECKS=0;\n\n")
    out = hdr + '\n\n'.join(body)
    if ext:
        out += '\n\n' + '\n\n'.join(stub(e) for e in ext)
    out += '\n\nSET FOREIGN_KEY_CHECKS=1;\n'
    open(f'{OUT}/{slug}.sql', 'w', encoding='utf-8', newline='\n').write(out)
    print(f"{slug:40} {len(tables)} tables" + (f" + stubs: {', '.join(ext)}" if ext else ""))
