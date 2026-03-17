<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);

$d = json_input();
$class = (int)($d['class_id'] ?? 0);
$day   = (int)($d['day_of_week'] ?? 0);
$period= (int)($d['period_id'] ?? 0);
if($class<=0 || $day<1 || $day>7 || $period<=0) fail('Invalid data.');

$stmt = db()->prepare("DELETE FROM timetable_entries WHERE class_id=? AND day_of_week=? AND period_id=?");
$stmt->execute([$class,$day,$period]);

ok();
