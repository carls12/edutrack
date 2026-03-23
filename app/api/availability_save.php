<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);

$d = json_input();
$contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
if ($d === [] && stripos($contentType, 'application/json') === false && !empty($_POST)) {
  $d = $_POST;
}
$teacher = (int)($d['teacher_user_id'] ?? 0);
if ($teacher<=0) fail('Invalid teacher.');

$periods = db()->query("SELECT id, is_teaching_period FROM periods")->fetchAll();
$days = [1,2,3,4,5];

db()->beginTransaction();
try{
  // upsert each slot
  $stmt = db()->prepare("
    INSERT INTO teacher_availability(teacher_user_id,day_of_week,period_id,is_available)
    VALUES(?,?,?,?)
    ON DUPLICATE KEY UPDATE is_available=VALUES(is_available)
  ");

  foreach($days as $dow){
    foreach($periods as $p){
      if ((int)$p['is_teaching_period']===0) continue;
      $key = 'slot_'.$dow.'_'.$p['id'];
      $isAvail = isset($d[$key]) && ($d[$key]==='1' || $d[$key]===1 || $d[$key]===true) ? 1 : 0;
      $stmt->execute([$teacher,$dow,(int)$p['id'],$isAvail]);
    }
  }
  db()->commit();
  ok();
}catch(Exception $e){
  db()->rollBack();
  fail('Could not save availability.');
}
