<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);

$d       = json_input();
$class   = (int)($d['class_id']        ?? 0);
$day     = (int)($d['day_of_week']     ?? 0);
$period  = (int)($d['period_id']       ?? 0);
$subject = (int)($d['subject_id']      ?? 0);
$teacher = (int)($d['teacher_user_id'] ?? 0);
$locked  = (int)($d['is_locked']       ?? 1);

if ($class <= 0 || $day < 1 || $day > 7 || $period <= 0 || $subject <= 0 || $teacher <= 0)
    fail('Invalid data.');

// Prevent teacher double-booking in another class at the same slot
$chk = db()->prepare(
    "SELECT id FROM timetable_entries
     WHERE teacher_user_id=? AND day_of_week=? AND period_id=? AND class_id<>? LIMIT 1"
);
$chk->execute([$teacher, $day, $period, $class]);
if ($chk->fetch()) fail('This teacher is already booked at that time in another class.');

// Replace the class slot (delete any existing entries for this slot, then insert)
$del = db()->prepare(
    "DELETE FROM timetable_entries WHERE class_id=? AND day_of_week=? AND period_id=?"
);
$del->execute([$class, $day, $period]);

$ins = db()->prepare(
    "INSERT INTO timetable_entries
         (class_id, subject_id, teacher_user_id, day_of_week, period_id, source, is_locked)
     VALUES (?, ?, ?, ?, ?, 'manual', ?)"
);
$ins->execute([$class, $subject, $teacher, $day, $period, $locked]);

ok();
