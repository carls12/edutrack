<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
$me = require_api_role(['admin','principal']);

$d = json_input();
$id = (int)($d['id'] ?? 0);
$status = (string)($d['validation_status'] ?? '');

if ($id <= 0 || !in_array($status, ['pending','validated','rejected'], true)) {
  fail('Invalid fields.');
}

if ($status === 'pending') {
  $stmt = db()->prepare("UPDATE attendance SET validation_status='pending', validated_by_user_id=NULL, validated_at=NULL WHERE id=?");
  $stmt->execute([$id]);
  ok();
}

$stmt = db()->prepare("UPDATE attendance SET validation_status=?, validated_by_user_id=?, validated_at=NOW() WHERE id=?");
$stmt->execute([$status, (int)$me['id'], $id]);
ok();
