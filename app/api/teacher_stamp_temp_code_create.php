<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../teacher_stamp.php';

csrf_check_from_header();
$user = require_api_role(['admin','principal']);
teacher_stamp_ensure_schema();

$d = json_input();
$teacherUserId = (int)($d['teacher_user_id'] ?? 0);
if ($teacherUserId <= 0) {
  fail('Invalid teacher.');
}

$teacherStmt = db()->prepare("
  SELECT u.id, u.full_name
  FROM users u
  JOIN teachers t ON t.user_id = u.id
  WHERE u.id = ? AND u.role = 'teacher' AND u.is_active = 1 AND t.active = 1
  LIMIT 1
");
$teacherStmt->execute([$teacherUserId]);
$teacher = $teacherStmt->fetch();
if (!$teacher) {
  fail('Teacher not found.');
}

$tempCode = teacher_stamp_issue_temp_code($teacherUserId, (int)$user['id']);

ok([
  'teacher_user_id' => $teacherUserId,
  'teacher_name' => $teacher['full_name'],
  'temp_code' => $tempCode['code_value'],
  'expires_at' => $tempCode['expires_at'],
  'expires_in_seconds' => $tempCode['expires_in_seconds'],
]);
