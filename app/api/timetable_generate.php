<?php
require_once __DIR__ . '/_common.php';
csrf_check_from_header();
require_api_role(['admin']);

/**
 * Greedy generator (safe):
 * - Keeps manual/locked entries
 * - Deletes only auto entries that are not locked
 * - Fills remaining empty slots based on assignments + availability
 */

$days = [1,2,3,4,5];

$periods = db()->query("SELECT id, sort_order FROM periods WHERE is_teaching_period=1 ORDER BY sort_order")->fetchAll();
if (!$periods) fail('No teaching periods configured.');

$assignments = db()->query("
  SELECT teacher_user_id, subject_id, class_id, hours_per_week
  FROM teacher_assignments
  ORDER BY class_id, subject_id
")->fetchAll();
if (!$assignments) fail('No assignments.');

$availMap = []; // teacher-day-period => bool
$stmt = db()->query("SELECT teacher_user_id, day_of_week, period_id, is_available FROM teacher_availability");
foreach($stmt->fetchAll() as $r){
  $availMap[$r['teacher_user_id'].'-'.$r['day_of_week'].'-'.$r['period_id']] = ((int)$r['is_available']===1);
}
function is_available(array $availMap, int $teacher, int $day, int $period): bool {
  $k = $teacher.'-'.$day.'-'.$period;
  // default available if not configured
  return $availMap[$k] ?? true;
}

db()->beginTransaction();
try{
  // Delete only non-locked auto entries
  db()->exec("DELETE FROM timetable_entries WHERE source='auto' AND is_locked=0");

  // Preload existing entries (manual + locked) so we don't overwrite/double-book
  $teacherBooked = [];
  $classBooked = [];
  $existing = db()->query("SELECT class_id, teacher_user_id, day_of_week, period_id FROM timetable_entries")->fetchAll();
  foreach($existing as $e){
    $classBooked[$e['class_id'].'-'.$e['day_of_week'].'-'.$e['period_id']] = true;
    $teacherBooked[$e['teacher_user_id'].'-'.$e['day_of_week'].'-'.$e['period_id']] = true;
  }

  $insert = db()->prepare("
    INSERT INTO timetable_entries(class_id, subject_id, teacher_user_id, day_of_week, period_id, source, is_locked)
    VALUES(?,?,?,?,?,'auto',0)
  ");

  foreach($assignments as $a){
    $teacher = (int)$a['teacher_user_id'];
    $subject = (int)$a['subject_id'];
    $classId = (int)$a['class_id'];
    $need = (int)$a['hours_per_week'];

    $startDayIndex = ($classId + $subject + $teacher) % count($days);
    $dayOrder = array_merge(array_slice($days, $startDayIndex), array_slice($days, 0, $startDayIndex));

    $placed = 0;

    for($pass=0; $pass<3 && $placed<$need; $pass++){
      foreach($dayOrder as $day){
        foreach($periods as $p){
          if($placed >= $need) break 2;
          $periodId = (int)$p['id'];

          $ck = $classId.'-'.$day.'-'.$periodId;
          $tk = $teacher.'-'.$day.'-'.$periodId;

          if(isset($classBooked[$ck])) continue;
          if(isset($teacherBooked[$tk])) continue;
          if(!is_available($availMap, $teacher, $day, $periodId)) continue;

          $insert->execute([$classId,$subject,$teacher,$day,$periodId]);
          $classBooked[$ck] = true;
          $teacherBooked[$tk] = true;
          $placed++;
        }
      }
    }
  }

  db()->commit();
  ok(['message'=>'Generated']);
}catch(Exception $e){
  db()->rollBack();
  fail('Generation failed: ' . $e->getMessage(), 500);
}
