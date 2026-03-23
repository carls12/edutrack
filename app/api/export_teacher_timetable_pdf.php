<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../timetable_helpers.php';

require_auth();
$u = current_user();
if (!in_array($u['role'], ['admin', 'principal', 'teacher'], true)) {
  http_response_code(403);
  exit('Forbidden');
}

$teacherId = (int)($_GET['teacher_user_id'] ?? 0);
if ($teacherId <= 0) {
  http_response_code(400);
  exit('teacher_user_id is required');
}

if ($u['role'] === 'teacher' && (int)$u['id'] !== $teacherId) {
  http_response_code(403);
  exit('Forbidden');
}

$teacherStmt = db()->prepare("SELECT full_name, email FROM users WHERE id=? AND role='teacher' LIMIT 1");
$teacherStmt->execute([$teacherId]);
$teacher = $teacherStmt->fetch();
if (!$teacher) {
  http_response_code(404);
  exit('Teacher not found');
}

$entries = timetable_fetch_teacher_entry_map($teacherId);
$days = timetable_days();
$periods = timetable_periods();
$branding = timetable_branding_assets();
$schoolName = (string)($branding['settings']['school_name'] ?? APP_NAME);
$logoHtml = timetable_logo_html((string)$branding['logo_file']);
$watermarkFile = (string)$branding['watermark_file'];

$html = timetable_header_html(
  $logoHtml,
  $schoolName,
  'Teacher Timetable - ' . (string)$teacher['full_name'],
  (string)$teacher['email']
);

$html .= '<table width="100%" cellspacing="0" cellpadding="6" style="border-collapse:collapse;font-size:10px;">';
$html .= '<tr style="background:#f3f4f6;"><th align="left" style="border:1px solid #ddd;width:100px;">Day</th>';
foreach ($periods as $period) {
  $html .= '<th align="center" style="border:1px solid #ddd;">'
        . htmlspecialchars((string)$period['label'])
        . '<div style="font-size:9px;color:#666;">' . htmlspecialchars((string)$period['start_time']) . '-' . htmlspecialchars((string)$period['end_time']) . '</div>'
        . '</th>';
}
$html .= '</tr>';

foreach ($days as $dayOfWeek => $dayName) {
  $html .= '<tr><td style="border:1px solid #ddd;font-weight:bold;">' . htmlspecialchars($dayName) . '</td>';
  foreach ($periods as $period) {
    if ((int)$period['is_teaching_period'] === 0) {
      $html .= '<td style="border:1px solid #ddd;text-align:center;color:#888;">BREAK</td>';
      continue;
    }

    $entry = $entries[$dayOfWeek . '-' . $period['id']] ?? null;
    if (!$entry) {
      $html .= '<td style="border:1px solid #ddd;text-align:center;color:#999;">-</td>';
      continue;
    }

    $html .= '<td style="border:1px solid #ddd;vertical-align:top;">'
          . '<div style="font-weight:bold;">' . htmlspecialchars((string)$entry['subject_code']) . '</div>'
          . '<div style="color:#444;">' . htmlspecialchars((string)$entry['subject_name']) . '</div>'
          . '<div style="color:#666;">' . htmlspecialchars((string)$entry['class_name']) . '</div>'
          . '</td>';
  }
  $html .= '</tr>';
}

$html .= '</table>';

$pdf = timetable_render_pdf_binary($html, 'A4', 'landscape', $watermarkFile);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="teacher_timetable_' . timetable_filename_safe((string)$teacher['full_name']) . '.pdf"');
echo $pdf;
