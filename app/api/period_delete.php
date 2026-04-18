<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);

$d  = json_input();
$id = (int)($d['id'] ?? 0);
if ($id <= 0) fail('Invalid.');

db()->beginTransaction();
try {
    // Remove child rows that reference this period before deleting it
    db()->prepare("DELETE FROM timetable_entries    WHERE period_id=?")->execute([$id]);
    db()->prepare("DELETE FROM teacher_availability WHERE period_id=?")->execute([$id]);
    db()->prepare("DELETE FROM periods              WHERE id=?")->execute([$id]);
    db()->commit();
    ok();
} catch (Throwable $e) {
    db()->rollBack();
    fail('Could not delete period: ' . $e->getMessage());
}
