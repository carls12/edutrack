<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);

$d = json_input();
$id = (int)($d['id'] ?? 0);
$name = trim((string)($d['name'] ?? ''));
$title = trim((string)($d['title'] ?? ''));
$sort = (int)($d['sort_order'] ?? 10);
$active = (int)($d['is_active'] ?? 1) === 1 ? 1 : 0;

if ($id <= 0 || $title === '') fail('Invalid fields.');

$stmt = db()->prepare("UPDATE timetable_signatories SET name=?, title=?, sort_order=?, is_active=? WHERE id=?");
$stmt->execute([$name, $title, $sort, $active, $id]);
ok();
