<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);

$d = json_input();
$name = trim((string)($d['name'] ?? ''));
$title = trim((string)($d['title'] ?? ''));
$sort = (int)($d['sort_order'] ?? 10);
$active = (int)($d['is_active'] ?? 1) === 1 ? 1 : 0;

if ($title === '') fail('Title is required.');

db()->exec("
  CREATE TABLE IF NOT EXISTS timetable_signatories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    title VARCHAR(120) NOT NULL,
    sort_order INT NOT NULL DEFAULT 10,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )
");

$stmt = db()->prepare("INSERT INTO timetable_signatories(name,title,sort_order,is_active) VALUES(?,?,?,?)");
$stmt->execute([$name, $title, $sort, $active]);
ok(['id' => (int)db()->lastInsertId()]);
