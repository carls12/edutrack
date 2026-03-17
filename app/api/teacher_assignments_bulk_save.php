<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);

$d = json_input();
$teacherId = (int)($d['teacher_user_id'] ?? 0);
$items = $d['assignments'] ?? [];

if ($teacherId <= 0 || !is_array($items)) fail('Invalid payload.');

$chk = db()->prepare("SELECT id FROM users WHERE id=? AND role='teacher' LIMIT 1");
$chk->execute([$teacherId]);
if (!$chk->fetch()) fail('Teacher not found.');

$normalized = [];
foreach ($items as $it) {
  if (!is_array($it)) continue;
  $subjectId = (int)($it['subject_id'] ?? 0);
  $classId = (int)($it['class_id'] ?? 0);
  $hours = (int)($it['hours_per_week'] ?? 0);
  if ($subjectId <= 0 || $classId <= 0 || $hours <= 0) continue;
  $key = $subjectId . '-' . $classId;
  // Deduplicate subject-class pairs per teacher in payload.
  $normalized[$key] = [
    'subject_id' => $subjectId,
    'class_id' => $classId,
    'hours_per_week' => $hours,
  ];
}

db()->beginTransaction();
try {
  $del = db()->prepare("DELETE FROM teacher_assignments WHERE teacher_user_id=?");
  $del->execute([$teacherId]);

  if (!empty($normalized)) {
    $ins = db()->prepare("
      INSERT INTO teacher_assignments(teacher_user_id,subject_id,class_id,hours_per_week)
      VALUES(?,?,?,?)
    ");
    foreach ($normalized as $row) {
      $ins->execute([$teacherId, $row['subject_id'], $row['class_id'], $row['hours_per_week']]);
    }
  }

  db()->commit();
  ok(['count' => count($normalized)]);
} catch (Throwable $e) {
  db()->rollBack();
  fail('Could not save teacher assignments.', 500);
}
