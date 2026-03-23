<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../timetable_helpers.php';

require_auth();
$u = current_user();
if (!in_array($u['role'], ['admin','principal','teacher','prefect'], true)) { http_response_code(403); exit('Forbidden'); }

$classId = (int)($_GET['class_id'] ?? 0);
if ($classId <= 0) { http_response_code(400); exit('class_id is required'); }

$classStmt = db()->prepare("SELECT name FROM classes WHERE id=? LIMIT 1");
$classStmt->execute([$classId]);
$class = $classStmt->fetch();
if (!$class) { http_response_code(404); exit('Class not found'); }

if ($u['role'] === 'teacher') {
  $chk = db()->prepare("SELECT 1 FROM teacher_assignments WHERE teacher_user_id=? AND class_id=? LIMIT 1");
  $chk->execute([(int)$u['id'], $classId]);
  if (!$chk->fetch()) { http_response_code(403); exit('Forbidden'); }
}
if ($u['role'] === 'prefect') {
  $chk = db()->prepare("SELECT 1 FROM classes WHERE id=? AND prefect_user_id=? LIMIT 1");
  $chk->execute([$classId, (int)$u['id']]);
  if (!$chk->fetch()) { http_response_code(403); exit('Forbidden'); }
}

$rows = timetable_fetch_class_rows($classId);
$days = timetable_days();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="timetable_' . preg_replace('/[^a-z0-9_-]+/i', '_', (string)$class['name']) . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Class', 'Day', 'Period', 'Start', 'End', 'Subject Code', 'Subject', 'Teacher']);
foreach ($rows as $r) {
  fputcsv($out, [
    $r['class_name'],
    $days[(int)$r['day_of_week']] ?? (string)$r['day_of_week'],
    $r['period_label'],
    $r['start_time'],
    $r['end_time'],
    $r['subject_code'],
    $r['subject_name'],
    $r['teacher_name'],
  ]);
}
fclose($out);
