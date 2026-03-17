<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);
$d = json_input();
$id=(int)($d['id']??0);
$name=trim((string)($d['name']??''));
$grade=trim((string)($d['grade_level']??''));
$room=trim((string)($d['room_number']??''));
$master = ($d['class_master_user_id'] ?? '') === '' ? null : (int)$d['class_master_user_id'];
if($id<=0||$name==='') fail('Invalid.');
if ($master !== null) {
  $chk = db()->prepare("SELECT id FROM users WHERE id=? AND role='teacher' LIMIT 1");
  $chk->execute([$master]);
  if (!$chk->fetch()) fail('Class master must be an existing teacher.');
}
$stmt=db()->prepare("UPDATE classes SET name=?, grade_level=?, room_number=?, class_master_user_id=? WHERE id=?");
$stmt->execute([$name,$grade?:null,$room?:null,$master,$id]);
ok();
