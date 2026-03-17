<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

require_auth();
$u = current_user();
if (!in_array($u['role'], ['admin','principal','teacher','prefect'], true)) { http_response_code(403); exit('Forbidden'); }

$classId = (int)($_GET['class_id'] ?? 0);
if ($classId <= 0) { http_response_code(400); exit('class_id is required'); }

$classStmt = db()->prepare("SELECT name FROM classes WHERE id=? LIMIT 1");
$classStmt->execute([$classId]);
$class = $classStmt->fetch();
if (!$class) { http_response_code(404); exit('Class not found'); }

if ($u['role'] === 'teacher') {
  $chk = db()->prepare("SELECT 1 FROM teacher_assignments WHERE teacher_user_id=? AND class_id=? LIMIT 1");
  $chk->execute([(int)$u['id'], $classId]);
  if (!$chk->fetch()) { http_response_code(403); exit('Forbidden'); }
}
if ($u['role'] === 'prefect') {
  $chk = db()->prepare("SELECT 1 FROM classes WHERE id=? AND prefect_user_id=? LIMIT 1");
  $chk->execute([$classId, (int)$u['id']]);
  if (!$chk->fetch()) { http_response_code(403); exit('Forbidden'); }
}

$settings = db()->query("SELECT * FROM school_settings WHERE id=1")->fetch() ?: ['school_name'=>APP_NAME,'logo_path'=>null];
$schoolName = (string)($settings['school_name'] ?? APP_NAME);
$logoPath = (string)($settings['logo_path'] ?? '');
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
$logoFile = '';
if ($logoPath !== '') {
  $candidate = __DIR__ . '/../../public/' . ltrim($logoPath, '/');
  $logoFile = image_file_to_data_uri($candidate);
}
$watermarkFile = '';
if ($watermarkPath !== '') {
  $candidate = __DIR__ . '/../../public/' . ltrim($watermarkPath, '/');
  $watermarkFile = image_file_to_data_uri($candidate);
}

$periods = db()->query("SELECT id, label, start_time, end_time, sort_order, is_teaching_period FROM periods ORDER BY sort_order")->fetchAll();
$days = [1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday'];

$stmt = db()->prepare("
SELECT te.day_of_week, te.period_id, p.sort_order,
       s.code subject_code, s.name subject_name, u.full_name teacher_name
FROM timetable_entries te
JOIN periods p ON p.id=te.period_id
JOIN subjects s ON s.id=te.subject_id
JOIN users u ON u.id=te.teacher_user_id
WHERE te.class_id=?
ORDER BY te.day_of_week, p.sort_order
");
$stmt->execute([$classId]);
$rows = $stmt->fetchAll();

$entries = [];
foreach ($rows as $r) {
  $entries[$r['day_of_week'] . '-' . $r['period_id']] = $r;
}

$signatureSlots = 2;
$signatories = [];

db()->exec("CREATE TABLE IF NOT EXISTS timetable_signature_settings (
  id INT PRIMARY KEY DEFAULT 1,
  signature_slots INT NOT NULL DEFAULT 2,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");
db()->exec("CREATE TABLE IF NOT EXISTS timetable_signatories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  title VARCHAR(120) NOT NULL,
  sort_order INT NOT NULL DEFAULT 10,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$settingsRow = db()->query("SELECT signature_slots FROM timetable_signature_settings WHERE id=1")->fetch();
if ($settingsRow) $signatureSlots = max(1, min(10, (int)$settingsRow['signature_slots']));
$signatories = db()->query("SELECT name, title FROM timetable_signatories WHERE is_active=1 ORDER BY sort_order, id")->fetchAll();

$logoHtml = $logoFile !== '' ? '<img src="' . htmlspecialchars($logoFile) . '" style="height:55px;" />' : '';

$html = '<table width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:10px;"><tr>'
      . '<td style="width:70px;">' . $logoHtml . '</td>'
      . '<td>'
      . '<div style="font-size:20px;font-weight:bold;">' . htmlspecialchars($schoolName) . '</div>'
      . '<div style="color:#555;">Class Timetable - ' . htmlspecialchars((string)$class['name']) . '</div>'
      . '</td></tr></table>';

$html .= '<table width="100%" cellspacing="0" cellpadding="6" style="border-collapse:collapse;font-size:11px;">';
$html .= '<tr style="background:#f3f4f6;"><th align="left" style="border:1px solid #ddd;">Day</th>';
foreach ($periods as $p) {
  $html .= '<th align="center" style="border:1px solid #ddd;">'
        . htmlspecialchars((string)$p['label'])
        . '<div style="font-size:9px;color:#666;">' . htmlspecialchars((string)$p['start_time']) . '-' . htmlspecialchars((string)$p['end_time']) . '</div>'
        . '</th>';
}
$html .= '</tr>';

foreach ($days as $dow => $dayName) {
  $html .= '<tr><td style="border:1px solid #ddd;font-weight:bold;">' . htmlspecialchars($dayName) . '</td>';
  foreach ($periods as $p) {
    if ((int)$p['is_teaching_period'] === 0) {
      $html .= '<td style="border:1px solid #ddd;color:#999;text-align:center;">BREAK</td>';
      continue;
    }
    $key = $dow . '-' . $p['id'];
    $e = $entries[$key] ?? null;
    if (!$e) {
      $html .= '<td style="border:1px solid #ddd;color:#999;text-align:center;">-</td>';
    } else {
      $html .= '<td style="border:1px solid #ddd;">'
            . '<div style="font-weight:bold;">' . htmlspecialchars((string)$e['subject_code']) . '</div>'
            . '<div style="color:#666;">' . htmlspecialchars((string)$e['teacher_name']) . '</div>'
            . '</td>';
    }
  }
  $html .= '</tr>';
}
$html .= '</table>';

$html .= '<br><br><table width="100%" cellspacing="0" cellpadding="12" style="border-collapse:collapse;font-size:12px;"><tr>';
for ($i = 0; $i < $signatureSlots; $i++) {
  $sig = $signatories[$i] ?? null;
  $name = $sig['name'] ?? '';
  $title = $sig['title'] ?? 'Signatory';
  $html .= '<td style="width:' . floor(100 / $signatureSlots) . '%;vertical-align:top;">'
        . '<div style="border-top:1px solid #777; margin-top:10px; margin-bottom:8px;"></div>'
        . '<div style="font-weight:bold; min-height:16px;">' . htmlspecialchars((string)$name) . '</div>'
        . '<div style="color:#555;">' . htmlspecialchars((string)$title) . '</div>'
        . '</td>';
}
$html .= '</tr></table>';

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
    <hr><div>' . $html . '</div>
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
    <hr><div>' . $html . '</div>
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
$dompdf->loadHtml('<html><head><meta charset="utf-8"></head><body style="font-family:DejaVu Sans, Arial;position:relative;">' . $wmHtml . $html . '</body></html>');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="timetable_' . preg_replace('/[^a-z0-9_-]+/i', '_', (string)$class['name']) . '.pdf"');
echo $dompdf->output();
