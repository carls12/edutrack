<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);

$d = json_input();
$email = strtolower(trim((string)($d['email'] ?? '')));
if ($email === '') fail('Email is required.');

$stmt = db()->prepare("SELECT id, full_name, email, role, is_active FROM users WHERE email=? LIMIT 1");
$stmt->execute([$email]);
$u = $stmt->fetch();
if (!$u) fail('No user found with this email.', 404);
if ((int)$u['is_active'] !== 1) fail('User is disabled.');
if ($u['role'] === 'prefect') fail('Prefect resets are managed via prefect login workflow.');

ok([
  'id' => (int)$u['id'],
  'full_name' => $u['full_name'],
  'email' => $u['email'],
  'role' => $u['role']
]);
