<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);
$d=json_input();
$teacher=(int)($d['teacher_user_id']??0);
$subject=(int)($d['subject_id']??0);
$class=(int)($d['class_id']??0);
$hours=(int)($d['hours_per_week']??2);
if($teacher<=0||$subject<=0||$class<=0||$hours<=0) fail('Invalid.');
try{
  $stmt=db()->prepare("INSERT INTO teacher_assignments(teacher_user_id,subject_id,class_id,hours_per_week) VALUES(?,?,?,?)");
  $stmt->execute([$teacher,$subject,$class,$hours]);
  ok(['id'=>(int)db()->lastInsertId()]);
}catch(Exception $e){
  fail('Duplicate assignment already exists.');
}
