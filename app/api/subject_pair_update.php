<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);

$d = json_input();
$id = (int)($d['id'] ?? 0);
$subject1 = (int)($d['subject_id_1'] ?? 0);
$subject2 = (int)($d['subject_id_2'] ?? 0);
$active = (int)($d['is_active'] ?? 1) === 1 ? 1 : 0;

if ($id <= 0 || $subject1 <= 0 || $subject2 <= 0 || $subject1 === $subject2) fail('Choose two different subjects.');
if ($subject1 > $subject2) {
  [$subject1, $subject2] = [$subject2, $subject1];
}

$stmt = db()->prepare("UPDATE subject_pairs SET subject_id_1=?, subject_id_2=?, is_active=? WHERE id=?");
try {
  $stmt->execute([$subject1, $subject2, $active, $id]);
  ok();
} catch (Throwable $e) {
  fail('Subject pair already exists.');
}
