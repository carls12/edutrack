# EduTrack (PHP + MySQL)

A modern admin dashboard for:
- Users & roles (Admin / Principal / Teacher / Prefect)
- Teachers, Classes, Subjects, Assignments
- Attendance (arrive / depart / absent) + validation + “currently teaching”
- Timetable generator (availability + hours/week + no double booking)
- Reports & analytics (Chart.js) + salary calc
- Export CSV + optional PDF (dompdf)

## 1) Requirements
- PHP 8.1+ (PDO MySQL enabled)
- MySQL 5.7+ or MariaDB 10+
- Apache with mod_rewrite enabled (or any server you prefer)

## 2) Install
1. Copy the folder `edutrack/` into your web root.
2. Create DB + tables:
   - Import `sql/schema.sql` into MySQL (phpMyAdmin or CLI).
3. Configure database:
   - Edit `app/config.php` (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`)
   - Set `BASE_URL` to your correct path.
4. Open:
   - `http://localhost/edutrack/public/index.php?page=login`

## 3) Default logins (change immediately)
- admin@edutrack.local / Admin@2026!
- principal@edutrack.local / Principal@2026!
- teacher@edutrack.local / Teacher@2026!
- prefect@edutrack.local / Prefect@2026!

## 4) Timetable workflow
1. Create teachers/users
2. Create classes + subjects
3. Define assignments (teacher + subject + class + hours/week)
4. Set availability (Admin → Availability)
5. Generate timetable (Timetable → Auto-generate)

## 5) PDF export (optional)
From the project root:
```bash
composer require dompdf/dompdf
```
Then “Export PDF” will work from Reports.

## Notes
This is a clean foundation. If you want the drag-drop timetable editor, I can extend the timetable page with a draggable grid UI and an API to save manual edits.
