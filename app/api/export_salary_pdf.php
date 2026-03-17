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

$settings = db()->query("SELECT * FROM school_settings WHERE id=1")->fetch();
$currency = $settings['currency'] ?? 'XAF';
$school = $settings['school_name'] ?? 'School';
$watermarkPath = (string)($settings['watermark_path'] ?? '');
function image_file_to_data_uri(string $file): string {
  if (!is_file($file)) return '';
  $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
  if ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
    $img = @imagecreatefromwebp($file);
    if ($img !== false) {
      ob_start();
      imagepng($img);
      $png = ob_get_clean();
      imagedestroy($img);
      if ($png !== false) return 'data:image/png;base64,' . base64_encode($png);
    }
  }
  $mimeMap = [
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml',
  ];
  $mime = $mimeMap[$ext] ?? 'application/octet-stream';
  $data = @file_get_contents($file);
  if ($data === false || $data === '') return '';
  return 'data:' . $mime . ';base64,' . base64_encode($data);
}
$watermarkFile = '';
if ($watermarkPath !== '') {
  $candidate = __DIR__ . '/../../public/' . ltrim($watermarkPath, '/');
  $watermarkFile = image_file_to_data_uri($candidate);
}

$html = '<h2 style="margin:0 0 8px 0;">'.htmlspecialchars($school).'</h2>';
$html .= '<div style="color:#555;margin-bottom:18px;">Salary Report • '.htmlspecialchars($month).'</div>';
$html .= '<table width="100%" cellspacing="0" cellpadding="6" style="border-collapse:collapse;font-size:12px;">';
$html .= '<tr style="background:#f3f4f6;">
  <th align="left" style="border:1px solid #ddd;">Teacher</th>
  <th align="left" style="border:1px solid #ddd;">Email</th>
  <th align="right" style="border:1px solid #ddd;">Worked Hours</th>
  <th align="right" style="border:1px solid #ddd;">Absent Days</th>
  <th align="right" style="border:1px solid #ddd;">Salary ('.$currency.')</th>
</tr>';

$total = 0;
foreach($rows as $r){
  $hours = ((int)$r['worked_minutes'])/60.0;
  $salary = ($r['salary_type']==='hourly') ? ($hours*(float)$r['hourly_rate']) : ((float)$r['fixed_salary']);
  $total += $salary;
  $html .= '<tr>
    <td style="border:1px solid #ddd;">'.htmlspecialchars($r['full_name']).'</td>
    <td style="border:1px solid #ddd;">'.htmlspecialchars($r['email']).'</td>
    <td align="right" style="border:1px solid #ddd;">'.number_format($hours,2).'</td>
    <td align="right" style="border:1px solid #ddd;">'.(int)$r['absent_days'].'</td>
    <td align="right" style="border:1px solid #ddd;font-weight:bold;">'.number_format($salary,2).'</td>
  </tr>';
}
$html .= '<tr>
  <td colspan="4" align="right" style="border:1px solid #ddd;font-weight:bold;">Total</td>
  <td align="right" style="border:1px solid #ddd;font-weight:bold;">'.number_format($total,2).'</td>
</tr>';
$html .= '</table>';

$dompdfPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($dompdfPath)) {
  header('Content-Type: text/html; charset=utf-8');
  echo '<div style="font-family:Arial;padding:18px;">
    <h3>PDF Export is not installed</h3>
    <p>This endpoint requires <b>dompdf</b> via Composer.</p>
    <ol>
      <li>Open terminal in project root</li>
      <li>Run: <code>composer require dompdf/dompdf</code></li>
      <li>Refresh this page</li>
    </ol>
    <hr><div>'.$html.'</div>
  </div>';
  exit;
}

require_once $dompdfPath;
if (!class_exists('Dompdf\\Dompdf')) {
  header('Content-Type: text/html; charset=utf-8');
  echo '<div style="font-family:Arial;padding:18px;">
    <h3>PDF Export is not installed</h3>
    <p><b>dompdf</b> is missing from your Composer dependencies.</p>
    <ol>
      <li>Open terminal in project root</li>
      <li>Run: <code>composer require dompdf/dompdf</code></li>
      <li>Refresh this page</li>
    </ol>
    <hr><div>'.$html.'</div>
  </div>';
  exit;
}

$dompdf = new \Dompdf\Dompdf();
$dompdf->getOptions()->set('isRemoteEnabled', true);
$dompdf->getOptions()->set('isHtml5ParserEnabled', true);
$wmHtml = '';
if ($watermarkFile !== '') {
  $wmHtml = '<div style="position:fixed;top:0;left:0;right:0;bottom:0;z-index:-100;">'
          . '<img src="' . htmlspecialchars($watermarkFile) . '" style="width:100%;height:100%;object-fit:cover;opacity:0.12;">'
          . '</div>';
}
$dompdf->loadHtml('<html><head><meta charset="utf-8"></head><body style="font-family:DejaVu Sans, Arial;position:relative;">'.$wmHtml.$html.'</body></html>');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="salary_' . $month . '.pdf"');
echo $dompdf->output();
