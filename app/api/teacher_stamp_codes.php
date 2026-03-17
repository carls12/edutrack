<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../teacher_stamp.php';

csrf_check_from_header();
require_api_role(['admin','principal']);

ok([
  'teachers' => teacher_stamp_codes_payload(),
  'server_time' => date('Y-m-d H:i:s'),
]);
