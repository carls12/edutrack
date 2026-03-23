<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../timetable_helpers.php';

require_auth();
$u = current_user();
if (!in_array($u['role'], ['admin'], true)) {
  http_response_code(403);
  exit('Forbidden');
}

if (!class_exists('ZipArchive')) {
  http_response_code(500);
  exit('ZipArchive is not available');
}

$classes = db()->query("SELECT id, name FROM classes ORDER BY name")->fetchAll();
$days = timetable_days();
$periods = timetable_periods();
$branding = timetable_branding_assets();
$schoolName = (string)($branding['settings']['school_name'] ?? APP_NAME);
$logoHtml = timetable_logo_html((string)$branding['logo_file']);
$watermarkFile = (string)$branding['watermark_file'];
$signatoryData = timetable_signatory_data();
$signatureSlots = (int)$signatoryData['signature_slots'];
$signatories = $signatoryData['signatories'];

$zipPath = tempnam(sys_get_temp_dir(), 'class_tt_zip_');
if ($zipPath === false) {
  http_response_code(500);
  exit('Could not create temporary file');
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
  http_response_code(500);
  exit('Could not create zip archive');
}

foreach ($classes as $class) {
  $entries = timetable_fetch_class_entry_map((int)$class['id']);
  $html = timetable_header_html(
    $logoHtml,
    $schoolName,
    'Class Timetable - ' . (string)$class['name']
  );
  $html .= '<table width="100%" cellspacing="0" cellpadding="6" style="border-collapse:collapse;font-size:11px;">';
  $html .= '<tr style="background:#f3f4f6;"><th align="left" style="border:1px solid #ddd;width:105px;">Day</th>';
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
            . '<div style="color:#666;">' . htmlspecialchars((string)$entry['teacher_name']) . '</div>'
            . '</td>';
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

  $pdf = timetable_render_pdf_binary($html, 'A4', 'landscape', $watermarkFile);
  $zip->addFromString('classes/' . timetable_filename_safe((string)$class['name']) . '.pdf', $pdf);
}

$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="all_class_timetables.zip"');
header('Content-Length: ' . (string)filesize($zipPath));
readfile($zipPath);
@unlink($zipPath);
