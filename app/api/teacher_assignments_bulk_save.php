<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);

$d = json_input();
$teacherId = (int)($d['teacher_user_id'] ?? 0);
$items = $d['assignments'] ?? [];
$availabilitySlots = $d['availability_slots'] ?? [];

if ($teacherId <= 0 || !is_array($items)) fail('Invalid payload.');
if (!is_array($availabilitySlots)) $availabilitySlots = [];

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

  $teachingPeriods = db()->query("SELECT id FROM periods WHERE is_teaching_period=1 ORDER BY sort_order")->fetchAll();
  $teachingPeriodIds = array_map(static fn($period) => (int)$period['id'], $teachingPeriods);
  $normalizedSlots = [];
  foreach ($availabilitySlots as $slot) {
    if (!is_string($slot) || !preg_match('/^([1-5])-([0-9]+)$/', $slot, $m)) {
      continue;
    }
    $day = (int)$m[1];
    $periodId = (int)$m[2];
    if (in_array($periodId, $teachingPeriodIds, true)) {
      $normalizedSlots[$day . '-' . $periodId] = true;
    }
  }
  if ($normalizedSlots === []) {
    fail('Select at least one available period.');
  }

  $availabilityStmt = db()->prepare("
    INSERT INTO teacher_availability(teacher_user_id, day_of_week, period_id, is_available)
    VALUES(?,?,?,?)
    ON DUPLICATE KEY UPDATE is_available=VALUES(is_available)
  ");

  for ($day = 1; $day <= 5; $day++) {
    foreach ($teachingPeriods as $period) {
      $periodId = (int)$period['id'];
      $isAvailable = isset($normalizedSlots[$day . '-' . $periodId]) ? 1 : 0;
      $availabilityStmt->execute([$teacherId, $day, $periodId, $isAvailable]);
    }
  }

  db()->commit();
  ok(['count' => count($normalized), 'availability_slots' => array_keys($normalizedSlots)]);
} catch (Throwable $e) {
  db()->rollBack();
  fail('Could not save teacher assignments.', 500);
}
