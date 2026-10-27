# User Manual — Holy Face AMGS

## Overview

The **Academic Monitoring & Grading System (AMGS)** connects three apps to one secure API and database:

| Role | App | Main tasks |
|------|-----|------------|
| Public / prospective students | **Web** | Read about strands, mission/vision, announcements |
| **Student** | **Mobile** | Digital ID, view grades, AI study tools |
| **Teacher** | **Desktop** | Class lists, encode WW/PT/QA, auto-computed grades |
| **Admin / Registrar** | Web login + API | Announcements (DB/API), audit review, user management (extend API as needed) |

## Demo accounts (seed data)

Password for all: **`HolyFace@2026`**

| Username | Role | Use |
|----------|------|-----|
| `registrar` | Admin | Web admin portal |
| `mreyes` | Teacher | Desktop grade encoding |
| `lcruz` | Teacher | Second teacher sample |
| `s001` … `s005` | Students | Mobile digital ID & study tools |

Change all passwords before any production use.

## Web app

1. Visit the home page for strands and announcements (loaded from API).
2. **Log In** uses CSRF-protected form and API authentication.
3. After login, role-based portal pages explain mobile/desktop next steps.

## Mobile app (students)

1. Log in with student username/password.
2. **Digital ID** — shows LRN, grade, strand, section, school year (from API).
3. **My Grades** — quarterly and final ratings when teachers have encoded scores.
4. **AI Study Tools** — pick a study material → Plan, Flashcards, or Quiz.
   - Consent for data privacy is recorded on first study visit.
   - **No name, LRN, photo, or contact** is sent to external AI; generation runs on-server from material text only.

## Desktop app (teachers)

1. Log in with teacher credentials (`mreyes` / `HolyFace@2026`) or Registrar (`registrar`).
2. Select **school year, quarter, strand, section, and subject**. Teachers see only their own assignments; Registrar sees all.
3. **Class list** — searchable/sortable roster with photo, LRN, name, and current grade.
4. **Grade grid** — spreadsheet (rows = students, columns = WW / PT / QA items). Keyboard arrows, Excel paste, autosave. Type `ABS` / `EXC` for absent / excused. Scores cannot exceed the item’s highest possible score. Every edit is written to `grade_audit_logs`. Locked quarters cannot be edited unless the Registrar unlocks them.
5. **Dashboard** — class average, highest, lowest, passing rate, distribution. **Early warning** flags students below 78 (configurable) or with a sharp quarter drop.
6. **Reports** — print/export class record, report of grades, missing-grades list. Registrar **All students** view exports Excel (CSV) and PDF.
7. **Settings** — Registrar edits component weights by subject category, passing mark, early-warning threshold, and lock/unlock grading periods. Work Immersion / Research / performance-heavy weights stay unconfirmed until the school sets them.
8. **Help** — computation rules plus a worked example.
9. Offline: grades are cached in **encrypted** local storage (Windows DPAPI via Electron `safeStorage`) with a visible sync queue. Auto-logout after inactivity (default 15 minutes). Confirm before **Submit final grades**.

## Grade computation (summary)

Source: **DepEd Order No. 8, s. 2015**. Weights and the transmutation table live in the database (Settings), not in code.

1. **Percentage Score** per component = (total score ÷ total highest possible score) × 100 (SUM method; absent/excused skipped).
2. **Weighted Score** = Percentage Score × component weight.
3. **Initial Grade** = sum of the three weighted scores.
4. **Quarterly Grade** = Initial Grade looked up on the school transmutation table (default DepEd Appendix 2 bands).
5. **Final Grade** (semester subject) = average of the two quarterly grades, rounded to the nearest whole number.
6. **General Average** = average of final grades across subjects. Passed/Failed uses the stored passing mark (default 75).

Default weight profiles (confirm with the school before live encoding): Languages/Humanities/Communication 25/50/25; Mathematics/Science and most academic 25/45/30; Work Immersion/Research/Exhibit/Performance is a placeholder until the Registrar confirms.

## Privacy (RA 10173)

- Collect only academic and enrollment fields needed for SHS operations.
- `consent_logs` records first-login (and version-change) acceptance of Terms, Privacy, and AI Use Policy, including guardian confirmation for minors. The Registrar can publish a new version in the desktop Settings screen; users must accept again.
- `ai_study_sessions` stores anonymous summaries only.
- Audit logs capture grade changes with actor, timestamp, old/new values.

## Support contacts

Configure your Registrar office contact on the web footer in `web/config.php` for production.
