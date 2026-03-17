<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../teacher_stamp.php';
csrf_check_from_header();
$me = require_api_role(['admin','principal','prefect']);

teacher_stamp_ensure_schema();

$d = json_input();
$entryId = (int)($d['timetable_entry_id'] ?? 0);
$action = (string)($d['action'] ?? '');
$reason = trim((string)($d['reason'] ?? ''));

if ($entryId <= 0 || !in_array($action, ['arrived','left','absent'], true)) {
  fail('Invalid request.');
}

$entryStmt = db()->prepare("
  SELECT te.id, te.class_id, te.teacher_user_id, c.prefect_user_id
  FROM timetable_entries te
  JOIN classes c ON c.id = te.class_id
  WHERE te.id = ?
  LIMIT 1
");
$entryStmt->execute([$entryId]);
$entry = $entryStmt->fetch();
if (!$entry) fail('Timetable entry not found.', 404);

if ($me['role'] === 'prefect' && (int)$entry['prefect_user_id'] !== (int)$me['id']) {
  fail('You can only record attendance for your assigned class.', 403);
}

$teacherId = (int)$entry['teacher_user_id'];
$classId = (int)$entry['class_id'];
$teacherStampStatus = teacher_stamp_today_status($teacherId);

if ($me['role'] === 'prefect' && $action === 'arrived' && !$teacherStampStatus['is_in']) {
  fail('Teacher must stamp in at the office before the prefect can mark arrived.', 403);
}

$todayStmt = db()->prepare("
  SELECT status, reason, event_time
  FROM attendance
  WHERE teacher_user_id=? AND class_id=? AND DATE(event_time)=CURDATE()
  ORDER BY event_time ASC, id ASC
");
$todayStmt->execute([$teacherId, $classId]);
$logs = $todayStmt->fetchAll();

$arrivedAt = null;
$departedAt = null;
$absentAt = null;
$absentReason = null;
foreach ($logs as $r) {
  if ($r['status'] === 'arrived') $arrivedAt = $r['event_time'];
  if ($r['status'] === 'departed') $departedAt = $r['event_time'];
  if ($r['status'] === 'absent') {
    $absentAt = $r['event_time'];
    $absentReason = $r['reason'] ?? null;
  }
}

if ($action === 'arrived') {
  if ($absentAt) fail('Teacher already marked absent today.');
  if ($arrivedAt && (!$departedAt || strtotime($departedAt) < strtotime($arrivedAt))) {
    fail('Teacher already marked arrived.');
  }
}

if ($action === 'left') {
  if (!$arrivedAt) fail('Cannot mark left before arrived.');
  if ($departedAt && strtotime($departedAt) >= strtotime($arrivedAt)) {
    fail('Teacher already marked left.');
  }
}

if ($action === 'absent') {
  if ($arrivedAt || $departedAt || $absentAt) {
    fail('Cannot mark absent after attendance already started.');
  }
}

$status = $action === 'left' ? 'departed' : $action;
$eventAt = date('Y-m-d H:i:s');
$workedMinutes = 0;
$isAutoValidated = in_array($me['role'], ['admin','principal'], true);

if ($status === 'departed' && $arrivedAt) {
  $workedMinutes = max(0, (int)round((strtotime($eventAt) - strtotime($arrivedAt)) / 60));
}

$insert = db()->prepare("
  INSERT INTO attendance(teacher_user_id,class_id,status,reason,event_time,worked_minutes,source,created_by_user_id,validation_status,validated_by_user_id,validated_at)
  VALUES(?,?,?,?,?,?,?, ?, ?, ?, ?)
");
$insert->execute([
  $teacherId,
  $classId,
  $status,
  $status === 'absent' ? ($reason !== '' ? $reason : 'Absent') : null,
  $eventAt,
  $workedMinutes,
  $me['role'] === 'prefect' ? 'prefect_card' : 'manual',
  (int)$me['id']
  ,
  $isAutoValidated ? 'validated' : 'pending',
  $isAutoValidated ? (int)$me['id'] : null,
  $isAutoValidated ? date('Y-m-d H:i:s') : null
]);

ok(['id' => (int)db()->lastInsertId()]);
