<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../school_email.php';
require_once __DIR__ . '/../teacher_stamp.php';
csrf_check_from_header();
$me = require_api_role(['admin']);

$d = json_input();
$id = (int)($d['id'] ?? 0);
$full = trim((string)($d['full_name'] ?? ''));
$role = (string)($d['role'] ?? '');
$active = (int)($d['is_active'] ?? 1);
$pass = (string)($d['password'] ?? '');
$emailInput = strtolower(trim((string)($d['email'] ?? '')));

if ($id<=0 || $full==='' || !in_array($role, ['admin','principal','teacher','prefect'], true)) fail('Invalid fields.');
if ($id === (int)$me['id'] && $role !== 'admin') fail('You cannot remove your own admin role.');
$email = $role === 'admin' ? $emailInput : school_email_generate($full, $id);
if ($email === '') fail('Email is required.');

db()->beginTransaction();
try{
  if ($pass !== '') {
    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $stmt = db()->prepare("UPDATE users SET full_name=?, email=?, role=?, is_active=?, password_hash=? WHERE id=?");
    $stmt->execute([$full,$email,$role,$active,$hash,$id]);
  } else {
    $stmt = db()->prepare("UPDATE users SET full_name=?, email=?, role=?, is_active=? WHERE id=?");
    $stmt->execute([$full,$email,$role,$active,$id]);
  }
  if ($role === 'teacher') {
    teacher_stamp_ensure_schema();
  }
  db()->commit();
  ok();
}catch(Exception $e){
  db()->rollBack();
  fail('Could not update user (email must be unique).');
}
