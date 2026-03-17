<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);

$d = json_input();
$email = strtolower(trim((string)($d['email'] ?? '')));
$newPassword = (string)($d['new_password'] ?? '');

if ($email === '' || strlen($newPassword) < 8) fail('Email and new password (min 8 chars) are required.');

$stmt = db()->prepare("SELECT id, role FROM users WHERE email=? LIMIT 1");
$stmt->execute([$email]);
$u = $stmt->fetch();
if (!$u) fail('User not found.', 404);
if ($u['role'] === 'prefect') fail('Use prefect credential flow for prefect accounts.');

$hash = password_hash($newPassword, PASSWORD_BCRYPT);
$upd = db()->prepare("UPDATE users SET password_hash=? WHERE id=?");
$upd->execute([$hash, (int)$u['id']]);

ok();
