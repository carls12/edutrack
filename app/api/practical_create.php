<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);

$d          = json_input();
$code       = strtoupper(trim((string)($d['code']       ?? '')));
$name       = trim((string)($d['name']       ?? ''));
$parentId   = (int)($d['parent_subject_id']  ?? 0);
$classId    = (int)($d['class_id']           ?? 0);
$teacherId  = (int)($d['teacher_user_id']    ?? 0);
$hours      = (int)($d['hours_per_week']     ?? 2);
$active     = (int)($d['is_active']          ?? 1);

if ($code === '' || $name === '' || $parentId <= 0 || $classId <= 0 || $teacherId <= 0 || $hours <= 0) {
    fail('All fields are required.');
}

db()->beginTransaction();
try {
    $ins = db()->prepare(
        "INSERT INTO subjects(code, name, is_active, is_practical, parent_subject_id) VALUES(?,?,?,1,?)"
    );
    $ins->execute([$code, $name, $active, $parentId]);
    $subjectId = (int)db()->lastInsertId();

    $asgn = db()->prepare(
        "INSERT INTO teacher_assignments(teacher_user_id, subject_id, class_id, hours_per_week) VALUES(?,?,?,?)"
    );
    $asgn->execute([$teacherId, $subjectId, $classId, $hours]);

    db()->commit();
    ok(['subject_id' => $subjectId]);
} catch (Throwable $e) {
    db()->rollBack();
    $msg = $e->getMessage();
    if (str_contains($msg, '1062') || str_contains($msg, 'Duplicate')) {
        fail("A practical with code \"$code\" already exists. Use a different code.");
    }
    if (str_contains($msg, '1452') || str_contains($msg, 'foreign key')) {
        fail('Invalid class or teacher selected.');
    }
    fail('Could not save practical. Please try again.');
}
