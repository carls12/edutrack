<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
$me = require_api_role(['admin']);

$d = json_input();
$id = (int)($d['id'] ?? 0);
if ($id<=0) fail('Invalid id.');
if ($id === (int)$me['id']) fail('You cannot delete your own account.');

$pdo = db();
$pdo->beginTransaction();

try {
  $stmt = $pdo->prepare("UPDATE classes SET class_master_user_id=NULL WHERE class_master_user_id=?");
  $stmt->execute([$id]);

  $stmt = $pdo->prepare("UPDATE classes SET prefect_user_id=NULL WHERE prefect_user_id=?");
  $stmt->execute([$id]);

  $stmt = $pdo->prepare("UPDATE subjects SET hod_user_id=NULL WHERE hod_user_id=?");
  $stmt->execute([$id]);

  $stmt = $pdo->prepare("DELETE FROM prefect_password_audit WHERE prefect_user_id=? OR created_by_user_id=?");
  $stmt->execute([$id, $id]);

  $stmt = $pdo->prepare("DELETE FROM attendance WHERE teacher_user_id=? OR created_by_user_id=? OR validated_by_user_id=?");
  $stmt->execute([$id, $id, $id]);

  $stmt = $pdo->prepare("DELETE FROM teacher_assignments WHERE teacher_user_id=?");
  $stmt->execute([$id]);

  $stmt = $pdo->prepare("DELETE FROM teacher_availability WHERE teacher_user_id=?");
  $stmt->execute([$id]);

  $stmt = $pdo->prepare("DELETE FROM timetable_entries WHERE teacher_user_id=?");
  $stmt->execute([$id]);

  $stmt = $pdo->prepare("DELETE FROM teachers WHERE user_id=?");
  $stmt->execute([$id]);

  $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
  $stmt->execute([$id]);

  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  throw $e;
}

ok();
