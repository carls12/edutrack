<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../teacher_stamp.php';
csrf_check_from_header();
$me = require_api_role(['admin','prefect']);

teacher_stamp_ensure_schema();

$d=json_input();
$teacher=(int)($d['teacher_user_id']??0);
$class = ($d['class_id']??'')==='' ? null : (int)$d['class_id'];
$status=(string)($d['status']??'arrived');
$reason=trim((string)($d['reason']??''));
$event=(string)($d['event_time']??'');
$worked=(int)($d['worked_minutes']??0);
if($teacher<=0||!in_array($status,['arrived','departed','absent'],true)||$event==='') fail('Invalid.');

if($status!=='absent') $reason=null;
$isAutoValidated = ($me['role'] === 'admin');

$stmt=db()->prepare("
  INSERT INTO attendance(teacher_user_id,class_id,status,reason,event_time,worked_minutes,source,created_by_user_id,validation_status,validated_by_user_id,validated_at)
  VALUES(?,?,?,?,?,?,?, ?,?,?,?)
");
$stmt->execute([
  $teacher,
  $class,
  $status,
  $reason,
  $event,
  $worked,
  $me['role'] === 'prefect' ? 'prefect_card' : 'manual',
  (int)$me['id'],
  $isAutoValidated ? 'validated' : 'pending',
  $isAutoValidated ? (int)$me['id'] : null,
  $isAutoValidated ? date('Y-m-d H:i:s') : null
]);
ok(['id'=>(int)db()->lastInsertId()]);
