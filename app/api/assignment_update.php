<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);
$d=json_input();
$id=(int)($d['id']??0);
$teacher=(int)($d['teacher_user_id']??0);
$subject=(int)($d['subject_id']??0);
$class=(int)($d['class_id']??0);
$hours=(int)($d['hours_per_week']??2);
if($id<=0||$teacher<=0||$subject<=0||$class<=0||$hours<=0) fail('Invalid.');
try{
  $stmt=db()->prepare("UPDATE teacher_assignments SET teacher_user_id=?, subject_id=?, class_id=?, hours_per_week=? WHERE id=?");
  $stmt->execute([$teacher,$subject,$class,$hours,$id]);
  ok();
}catch(Exception $e){
  fail('Duplicate assignment already exists.');
}
