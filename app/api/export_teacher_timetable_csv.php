<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../timetable_helpers.php';

require_auth();
$u = current_user();
if (!in_array($u['role'], ['admin', 'principal', 'teacher'], true)) {
  http_response_code(403);
  exit('Forbidden');
}

$teacherId = (int)($_GET['teacher_user_id'] ?? 0);
if ($teacherId <= 0) {
  http_response_code(400);
  exit('teacher_user_id is required');
}

if ($u['role'] === 'teacher' && (int)$u['id'] !== $teacherId) {
  http_response_code(403);
  exit('Forbidden');
}

$teacherStmt = db()->prepare("SELECT full_name FROM users WHERE id=? AND role='teacher' LIMIT 1");
$teacherStmt->execute([$teacherId]);
$teacher = $teacherStmt->fetch();
if (!$teacher) {
  http_response_code(404);
  exit('Teacher not found');
}

$rows = timetable_fetch_teacher_rows($teacherId);
$days = timetable_days();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="teacher_timetable_' . preg_replace('/[^a-z0-9_-]+/i', '_', (string)$teacher['full_name']) . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Teacher', 'Day', 'Period', 'Start', 'End', 'Class', 'Subject Code', 'Subject']);
foreach ($rows as $row) {
  fputcsv($out, [
    $row['teacher_name'],
    $days[(int)$row['day_of_week']] ?? (string)$row['day_of_week'],
    $row['period_label'],
    $row['start_time'],
    $row['end_time'],
    $row['class_name'],
    $row['subject_code'],
    $row['subject_name'],
  ]);
}
fclose($out);
