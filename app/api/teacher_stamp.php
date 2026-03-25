<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../teacher_stamp.php';

$d = json_input();
$stampCode = strtoupper(trim((string)($d['stamp_code'] ?? '')));
$otp = trim((string)($d['otp'] ?? ''));
$action = (string)($d['action'] ?? '');

if ($stampCode === '' || $otp === '' || !in_array($action, ['arrived', 'departed'], true)) {
  fail('Teacher code, security code, and action are required.');
}

$teacher = teacher_stamp_find_teacher_by_code($stampCode);
if (!$teacher) {
  fail('Teacher code not found.', 404);
}
if ((int)$teacher['is_active'] !== 1 || (int)$teacher['teacher_active'] !== 1) {
  fail('This teacher is inactive.');
}
if (!teacher_stamp_verify_otp((string)$teacher['stamp_secret'], $otp)) {
  fail('Security code is invalid or expired.');
}

try {
  $record = teacher_stamp_record((int)$teacher['teacher_user_id'], $action);
} catch (RuntimeException $e) {
  fail($e->getMessage());
} catch (Throwable $e) {
  fail('Could not save the stamp right now.', 500);
}

ok([
  'teacher_name' => $teacher['full_name'],
  'status' => $record['status'],
  'event_time' => $record['event_time'],
]);
