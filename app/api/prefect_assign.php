<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);

$d = json_input();
$classId = (int)($d['class_id'] ?? 0);
$prefectId = ($d['prefect_user_id'] ?? '') === '' ? null : (int)$d['prefect_user_id'];

if ($classId <= 0) fail('Invalid class.');

$chk = db()->prepare("SELECT id FROM classes WHERE id=? LIMIT 1");
$chk->execute([$classId]);
if (!$chk->fetch()) fail('Class not found.', 404);

if ($prefectId !== null) {
  $u = db()->prepare("SELECT id FROM users WHERE id=? AND role='prefect' AND is_active=1 LIMIT 1");
  $u->execute([$prefectId]);
  if (!$u->fetch()) fail('Prefect not found or inactive.');
}

$stmt = db()->prepare("UPDATE classes SET prefect_user_id=? WHERE id=?");
$stmt->execute([$prefectId, $classId]);

ok();
