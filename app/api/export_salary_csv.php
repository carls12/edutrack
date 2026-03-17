<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

require_auth();
$u = current_user();
if (!in_array($u['role'], ['admin','principal','teacher'], true)) { http_response_code(403); exit('Forbidden'); }

$month = $_GET['month'] ?? date('Y-m');
$from = $month . "-01 00:00:00";
$to   = date('Y-m-t', strtotime($month . "-01")) . " 23:59:59";

$params = [$from,$to];
$teacherFilter = "";
if ($u['role']==='teacher') { $teacherFilter=" AND u.id=? "; $params[]=$u['id']; }

$stmt = db()->prepare("
SELECT 
  u.id teacher_user_id,
  u.full_name,
  u.email,
  COALESCE(t.salary_type, 'hourly') AS salary_type,
  COALESCE(t.hourly_rate, 0) AS hourly_rate,
  COALESCE(t.fixed_salary, 0) AS fixed_salary,
  SUM(CASE WHEN a.validation_status='validated' AND a.status='absent' THEN 1 ELSE 0 END) AS absent_days,
  SUM(CASE WHEN a.validation_status='validated' THEN a.worked_minutes ELSE 0 END) AS worked_minutes
FROM users u
LEFT JOIN teachers t ON t.user_id=u.id
LEFT JOIN attendance a ON a.teacher_user_id=u.id AND a.event_time BETWEEN ? AND ?
WHERE u.role='teacher' AND u.is_active=1 $teacherFilter
GROUP BY u.id, u.full_name, u.email
ORDER BY u.full_name
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="salary_' . $month . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['TeacherId','Teacher','Email','WorkedHours','AbsentDays','SalaryType','HourlyRate','FixedSalary','Salary']);
foreach($rows as $r){
  $hours = ((int)$r['worked_minutes'])/60.0;
  $salary = ($r['salary_type']==='hourly') ? ($hours*(float)$r['hourly_rate']) : ((float)$r['fixed_salary']);
  fputcsv($out, [
    (int)$r['teacher_user_id'],
    $r['full_name'],
    $r['email'],
    round($hours,2),
    (int)$r['absent_days'],
    $r['salary_type'],
    $r['hourly_rate'],
    $r['fixed_salary'],
    round($salary,2)
  ]);
}
fclose($out);
