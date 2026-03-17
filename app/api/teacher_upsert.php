<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../teacher_stamp.php';
csrf_check_from_header();
require_api_role(['admin']);

teacher_stamp_ensure_schema();

$d = json_input();
$userId = (int)($d['user_id'] ?? 0);
$salaryType = (string)($d['salary_type'] ?? 'hourly');
$hourly = $d['hourly_rate'] === '' ? null : (float)($d['hourly_rate'] ?? 0);
$fixed = $d['fixed_salary'] === '' ? null : (float)($d['fixed_salary'] ?? 0);
$phone = trim((string)($d['phone'] ?? ''));

if ($userId<=0 || !in_array($salaryType, ['hourly','fixed'], true)) fail('Invalid data.');

$stmt = db()->prepare("SELECT id FROM users WHERE id=? AND role='teacher' LIMIT 1");
$stmt->execute([$userId]);
if (!$stmt->fetch()) fail('Teacher not found.');

$stmt = db()->prepare("
INSERT INTO teachers(user_id,salary_type,hourly_rate,fixed_salary,phone,active)
VALUES(?,?,?,?,?,1)
ON DUPLICATE KEY UPDATE salary_type=VALUES(salary_type), hourly_rate=VALUES(hourly_rate), fixed_salary=VALUES(fixed_salary), phone=VALUES(phone), active=1
");
$stmt->execute([$userId,$salaryType,$hourly,$fixed,$phone]);

teacher_stamp_seed_credentials();
ok();
