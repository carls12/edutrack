<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);

$d = json_input();
$slots = (int)($d['signature_slots'] ?? 2);
if ($slots < 1 || $slots > 10) fail('signature_slots must be between 1 and 10.');

db()->exec("
  CREATE TABLE IF NOT EXISTS timetable_signature_settings (
    id INT PRIMARY KEY DEFAULT 1,
    signature_slots INT NOT NULL DEFAULT 2,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  )
");

$stmt = db()->prepare("
  INSERT INTO timetable_signature_settings(id, signature_slots)
  VALUES(1, ?)
  ON DUPLICATE KEY UPDATE signature_slots=VALUES(signature_slots)
");
$stmt->execute([$slots]);

ok();
