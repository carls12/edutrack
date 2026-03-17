<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../school_email.php';
require_once __DIR__ . '/../teacher_stamp.php';
csrf_check_from_header();
require_api_role(['admin']);

$d = json_input();
$full = trim((string)($d['full_name'] ?? ''));
$role = (string)($d['role'] ?? '');
$pass = (string)($d['password'] ?? '');
$active = (int)($d['is_active'] ?? 1);
$emailInput = strtolower(trim((string)($d['email'] ?? '')));

if ($full==='' || $pass==='' || !in_array($role, ['admin','principal','teacher','prefect'], true)) {
  fail('Missing/invalid fields.');
}
$email = $role === 'admin' ? $emailInput : school_email_generate($full);
if ($email === '') fail('Email is required.');
$hash = password_hash($pass, PASSWORD_BCRYPT);
try{
  $stmt = db()->prepare("INSERT INTO users(full_name,email,password_hash,role,is_active) VALUES(?,?,?,?,?)");
  $stmt->execute([$full,$email,$hash,$role,$active]);
  if ($role === 'teacher') {
    teacher_stamp_ensure_schema();
  }
  ok(['id'=>(int)db()->lastInsertId()]);
}catch(Exception $e){
  fail('Could not create user (email must be unique).');
}
