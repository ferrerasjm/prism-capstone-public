"""Build an isolated PRISM interview database from a HeidiSQL export.

Usage: python tools/sanitize_demo_sql.py SOURCE.sql OUTPUT.sql CREDENTIALS.txt
The source is read only. The output always targets prism_demo_sanitized.
"""

from __future__ import annotations

import re
import secrets
import sys
from collections import Counter
from pathlib import Path

import bcrypt


DATABASE = "prism_demo_sanitized"
DROP_INSERTS = {"cache", "sessions", "jobs", "failed_jobs", "password_reset_tokens", "account_setup_tokens", "mobile_otp_challenges", "mobile_attendance_receipts"}
INSERT = re.compile(r"INSERT INTO `(?P<table>\w+)` \((?P<columns>.*?)\) VALUES\s*", re.S)


def statements(sql: str):
    pos = 0
    while match := INSERT.search(sql, pos):
        yield None, sql[pos:match.start()]
        i, quoted, escaped = match.end(), False, False
        while i < len(sql):
            char = sql[i]
            if escaped:
                escaped = False
            elif quoted and char == "\\":
                escaped = True
            elif char == "'":
                if quoted and i + 1 < len(sql) and sql[i + 1] == "'":
                    i += 1
                else:
                    quoted = not quoted
            elif char == ";" and not quoted:
                i += 1
                break
            i += 1
        if quoted or i > len(sql):
            raise ValueError(f"Unclosed INSERT for {match.group('table')}")
        yield match, sql[match.start():i]
        pos = i
    yield None, sql[pos:]


def split_values(text: str):
    """Return raw SQL tuple fields; preserve MySQL escaping on untouched data."""
    rows, row, token = [], [], []
    depth, quoted, escaped = 0, False, False
    for char in text:
        if escaped:
            token.append(char)
            escaped = False
        elif quoted and char == "\\":
            token.append(char)
            escaped = True
        elif char == "'":
            quoted = not quoted
            token.append(char)
        elif char == "(" and not quoted:
            if depth == 0:
                row, token = [], []
            else:
                token.append(char)
            depth += 1
        elif char == ")" and not quoted:
            depth -= 1
            if depth == 0:
                row.append("".join(token).strip())
                rows.append(row)
                token = []
            else:
                token.append(char)
        elif char == "," and not quoted and depth == 1:
            row.append("".join(token).strip())
            token = []
        elif depth:
            token.append(char)
    if depth or quoted:
        raise ValueError("Malformed SQL VALUES")
    return rows


def value(raw: str) -> str | None:
    if raw == "NULL":
        return None
    if raw.startswith("'") and raw.endswith("'"):
        return raw[1:-1].replace("''", "'").replace("\\'", "'").replace("\\\\", "\\")
    return raw


def literal(text: str | None) -> str:
    if text is None:
        return "NULL"
    return "'" + text.replace("\\", "\\\\").replace("'", "\\'").replace("\x00", "") + "'"


def table_rows(sql: str):
    for match, statement in statements(sql):
        if match is None:
            continue
        columns = re.findall(r"`([^`]+)`", match.group("columns"))
        rows = split_values(statement[match.end() - match.start():-1])
        for row in rows:
            if len(row) != len(columns):
                raise ValueError(f"Column count mismatch in {match.group('table')}")
        yield match.group("table"), columns, rows


def main(source: Path, output: Path, credential_file: Path) -> None:
    sql = source.read_text(encoding="utf-8-sig")
    data = {table: (columns, rows) for table, columns, rows in table_rows(sql)}
    users_columns, users = data["users"]
    profiles_columns, profiles = data["student_profiles"]
    u = {column: n for n, column in enumerate(users_columns)}
    p = {column: n for n, column in enumerate(profiles_columns)}
    existing = credential_file.read_text(encoding="utf-8") if credential_file.exists() else ""
    saved = re.search(r"^All demo account passwords: (.+)$", existing, re.M)
    password = saved.group(1) if saved else secrets.token_urlsafe(18)
    # PHP's password_get_info() recognizes $2y$, while it reports Python
    # bcrypt's otherwise compatible $2b$ hashes as an unknown algorithm.
    password_hash = bcrypt.hashpw(password.encode(), bcrypt.gensalt()).decode().replace("$2b$", "$2y$", 1)
    replacements: dict[str, str] = {}
    role_counts: Counter[str] = Counter()
    user_aliases: dict[str, tuple[str, str, str]] = {}

    def remember(original: str | None, fake: str) -> None:
        if original and original != fake and len(original) >= 5:
            replacements.setdefault(original, fake)

    for row in users:
        role = value(row[u["role"]]) or "user"
        role_counts[role] += 1
        ordinal = role_counts[role]
        label = role.replace("_", " ").title()
        first, last = "Demo", f"{label} {ordinal:03d}"
        email = f"demo.{role}.{ordinal:03d}@example.test"
        institutional_id = f"DEMO-{role.upper().replace('_', '-')}-{ordinal:03d}"
        contact = f"+639990{len(user_aliases)+1:06d}"
        old_first, old_middle, old_last = (value(row[u[c]]) for c in ("first_name", "middle_name", "last_name"))
        for name in filter(None, [
            " ".join(filter(None, [old_first, old_last])),
            " ".join(filter(None, [old_first, old_middle, old_last])),
            f"{old_last}, {old_first}" if old_first and old_last else None,
        ]):
            remember(name, f"{first} {last}")
        for column, fake in (("email", email), ("institutional_id", institutional_id), ("contact_number", contact)):
            remember(value(row[u[column]]), fake)
            row[u[column]] = literal(fake) if value(row[u[column]]) is not None else "NULL"
        row[u["first_name"]] = literal(first)
        row[u["middle_name"]] = "NULL"
        row[u["last_name"]] = literal(last)
        row[u["password"]] = literal(password_hash)
        row[u["profile_photo"]] = "NULL"
        user_aliases[value(row[u["id"]])] = (first, last, contact)

    for n, row in enumerate(profiles, 1):
        user_id = value(row[p["user_id"]])
        alias = user_aliases.get(user_id)
        if not alias:
            raise ValueError(f"Profile points to missing user {user_id}")
        student_number = f"DEMO-STU-{n:04d}"
        remember(value(row[p["student_number"]]), student_number)
        row[p["student_number"]] = literal(student_number)
        remember(value(row[p["contact_number"]]), alias[2])
        row[p["contact_number"]] = literal(alias[2]) if value(row[p["contact_number"]]) is not None else "NULL"
        guardian = f"Demo Guardian {n:03d}"
        remember(value(row[p["guardian_name"]]), guardian)
        remember(value(row[p["guardian_contact"]]), f"+639980{n:06d}")
        row[p["guardian_name"]] = literal(guardian) if value(row[p["guardian_name"]]) is not None else "NULL"
        row[p["guardian_contact"]] = literal(f"+639980{n:06d}") if value(row[p["guardian_contact"]]) is not None else "NULL"

    # Replace a free-text reporter name only if it cannot be linked to a user.
    reports_columns, reports = data["incident_reports"]
    r = {column: n for n, column in enumerate(reports_columns)}
    for n, row in enumerate(reports, 1):
        reporter_id = value(row[r["reported_by_user_id"]])
        old_name = value(row[r["reported_by_name"]])
        if old_name:
            alias = user_aliases.get(reporter_id)
            fake_name = f"{alias[0]} {alias[1]}" if alias else f"Demo Reporter {n:03d}"
            remember(old_name, fake_name)
            row[r["reported_by_name"]] = literal(fake_name)

    # Longer matches first to prevent a short identifier from fragmenting a longer one.
    ordered = sorted(replacements.items(), key=lambda item: len(item[0]), reverse=True)
    generated = []
    for match, statement in statements(sql):
        if match is None:
            generated.append(statement)
            continue
        table = match.group("table")
        if table in DROP_INSERTS:
            generated.append(f"-- Omitted transient {table} rows from interview copy")
            continue
        columns, rows = data[table]
        rewritten = []
        for row in rows:
            for i, raw in enumerate(row):
                if raw.startswith("'"):
                    for old, fake in ordered:
                        raw = raw.replace(old, fake)
                    row[i] = raw
            if table == "audit_logs" and "ip_address" in columns:
                ip = columns.index("ip_address")
                if row[ip] != "NULL":
                    row[ip] = literal("192.0.2.1")
            rewritten.append("(" + ", ".join(row) + ")")
        generated.append(statement[:match.end() - match.start()] + ",\n".join(rewritten) + ";")

    result = "".join(generated)
    result = result.replace("CREATE DATABASE IF NOT EXISTS `prism`", f"CREATE DATABASE IF NOT EXISTS `{DATABASE}`", 1)
    result = result.replace("USE `prism`;", f"USE `{DATABASE}`;", 1)
    result = result.replace("-- Dumping database structure for prism", f"-- Sanitized interview copy; database {DATABASE}")
    result = result.replace("-- Dumping structure for table prism.", f"-- Dumping structure for table {DATABASE}.")
    result = result.replace("-- Dumping data for table prism.", f"-- Dumping data for table {DATABASE}.")
    if "USE `prism`;" in result or "CREATE DATABASE IF NOT EXISTS `prism`" in result:
        raise ValueError("Original database target remains")
    for old, _ in ordered:
        if old in result:
            raise ValueError("A source identifier remains in output")
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(result, encoding="utf-8", newline="\n")
    credential_file.write_text(
        f"Database: {DATABASE}\nAll demo account passwords: {password}\n"
        "Use only in an isolated local environment. Delete this file after the interview.\n",
        encoding="utf-8",
    )
    print(f"Wrote {output}; users={len(users)}, profiles={len(profiles)}, reports={len(reports)}, cases={len(data['cases'][1])}")


if __name__ == "__main__":
    if len(sys.argv) != 4:
        raise SystemExit(__doc__)
    main(*(Path(arg) for arg in sys.argv[1:]))
