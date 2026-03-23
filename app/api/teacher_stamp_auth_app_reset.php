<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../teacher_stamp.php';

csrf_check_from_header();
require_api_role(['admin','principal']);
teacher_stamp_ensure_schema();

$d = json_input();
$teacherUserId = (int)($d['teacher_user_id'] ?? 0);
if ($teacherUserId <= 0) {
  fail('Invalid teacher.');
}

$setup = teacher_stamp_reset_auth_app_secret($teacherUserId);
if (!$setup) {
  fail('Teacher not found.');
}

ok($setup);
