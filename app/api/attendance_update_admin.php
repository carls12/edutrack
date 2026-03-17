<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
$me = require_api_role(['admin','principal']);

$d = json_input();
$id = (int)($d['id'] ?? 0);
$status = (string)($d['status'] ?? '');
$event = trim((string)($d['event_time'] ?? ''));
$workedRaw = $d['worked_minutes'] ?? null;
$worked = is_numeric($workedRaw) ? (int)$workedRaw : null;
$reason = trim((string)($d['reason'] ?? ''));
$classId = ($d['class_id'] ?? '') === '' ? null : (int)$d['class_id'];

if ($id <= 0 || !in_array($status, ['arrived','departed','absent'], true) || $event === '' || ($worked !== null && $worked < 0)) {
  fail('Invalid fields.');
}

if ($status !== 'absent') $reason = '';

// datetime-local can come as "YYYY-MM-DDTHH:MM"
$event = str_replace('T', ' ', $event);
if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $event)) {
  fail('Invalid event_time format.');
}
if (strlen($event) === 16) $event .= ':00';

$currStmt = db()->prepare("SELECT teacher_user_id, class_id FROM attendance WHERE id=? LIMIT 1");
$currStmt->execute([$id]);
$curr = $currStmt->fetch();
if (!$curr) {
  fail('Attendance record not found.', 404);
}

$teacherId = (int)$curr['teacher_user_id'];

if ($status === 'arrived' || $status === 'absent') {
  $worked = 0;
}

if ($status === 'departed') {
  if ($classId === null) {
    $arrivedStmt = db()->prepare("
      SELECT event_time
      FROM attendance
      WHERE teacher_user_id=?
        AND class_id IS NULL
        AND status='arrived'
        AND DATE(event_time)=DATE(?)
        AND event_time <= ?
        AND id<>?
      ORDER BY event_time DESC, id DESC
      LIMIT 1
    ");
    $arrivedStmt->execute([$teacherId, $event, $event, $id]);
  } else {
    $arrivedStmt = db()->prepare("
      SELECT event_time
      FROM attendance
      WHERE teacher_user_id=?
        AND class_id=?
        AND status='arrived'
        AND DATE(event_time)=DATE(?)
        AND event_time <= ?
        AND id<>?
      ORDER BY event_time DESC, id DESC
      LIMIT 1
    ");
    $arrivedStmt->execute([$teacherId, $classId, $event, $event, $id]);
  }
  $arrived = $arrivedStmt->fetch();
  if ($arrived) {
    $worked = max(0, (int)round((strtotime($event) - strtotime((string)$arrived['event_time'])) / 60));
  } elseif ($worked === null) {
    $worked = 0;
  }
}

if ($worked === null) {
  $worked = 0;
}

$stmt = db()->prepare("
  UPDATE attendance
  SET status=?, reason=?, event_time=?, worked_minutes=?, class_id=?,
      validation_status='validated', validated_by_user_id=?, validated_at=NOW()
  WHERE id=?
");
$stmt->execute([$status, $reason !== '' ? $reason : null, $event, $worked, $classId, (int)$me['id'], $id]);

if ($classId === null) {
  $depStmt = db()->prepare("
    SELECT id, event_time
    FROM attendance
    WHERE teacher_user_id=?
      AND class_id IS NULL
      AND status='departed'
      AND DATE(event_time)=DATE(?)
    ORDER BY event_time ASC, id ASC
  ");
  $depStmt->execute([$teacherId, $event]);
} else {
  $depStmt = db()->prepare("
    SELECT id, event_time
    FROM attendance
    WHERE teacher_user_id=?
      AND class_id=?
      AND status='departed'
      AND DATE(event_time)=DATE(?)
    ORDER BY event_time ASC, id ASC
  ");
  $depStmt->execute([$teacherId, $classId, $event]);
}
$departedRows = $depStmt->fetchAll();

foreach ($departedRows as $dep) {
  $depId = (int)$dep['id'];
  $depTime = (string)$dep['event_time'];

  if ($classId === null) {
    $arrStmt = db()->prepare("
      SELECT event_time
      FROM attendance
      WHERE teacher_user_id=?
        AND class_id IS NULL
        AND status='arrived'
        AND DATE(event_time)=DATE(?)
        AND event_time <= ?
      ORDER BY event_time DESC, id DESC
      LIMIT 1
    ");
    $arrStmt->execute([$teacherId, $depTime, $depTime]);
  } else {
    $arrStmt = db()->prepare("
      SELECT event_time
      FROM attendance
      WHERE teacher_user_id=?
        AND class_id=?
        AND status='arrived'
        AND DATE(event_time)=DATE(?)
        AND event_time <= ?
      ORDER BY event_time DESC, id DESC
      LIMIT 1
    ");
    $arrStmt->execute([$teacherId, $classId, $depTime, $depTime]);
  }
  $arr = $arrStmt->fetch();
  $calcWorked = 0;
  if ($arr) {
    $calcWorked = max(0, (int)round((strtotime($depTime) - strtotime((string)$arr['event_time'])) / 60));
  }

  $updDep = db()->prepare("
    UPDATE attendance
    SET worked_minutes=?, validation_status='validated', validated_by_user_id=?, validated_at=NOW()
    WHERE id=?
  ");
  $updDep->execute([$calcWorked, (int)$me['id'], $depId]);
}

ok(['worked_minutes' => $worked]);
