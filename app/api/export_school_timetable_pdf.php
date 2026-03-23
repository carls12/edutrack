<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../timetable_helpers.php';

require_auth();
$u = current_user();
if (!in_array($u['role'], ['admin', 'principal'], true)) {
  http_response_code(403);
  exit('Forbidden');
}

$days = timetable_days();
$periods = timetable_periods();
$rows = timetable_fetch_school_rows();
$classes = db()->query("SELECT id, name FROM classes ORDER BY name")->fetchAll();
$branding = timetable_branding_assets();
$schoolName = (string)($branding['settings']['school_name'] ?? APP_NAME);
$logoHtml = timetable_logo_html((string)$branding['logo_file']);
$watermarkFile = (string)$branding['watermark_file'];

$slotMap = [];
foreach ($rows as $row) {
  $slotMap[$row['class_id'] . '-' . $row['day_of_week'] . '-' . $row['period_id']] = $row;
}

$html = timetable_header_html(
  $logoHtml,
  $schoolName,
  'General School Timetable',
  'A3 master timetable by day, class and period'
);

$periodCount = count($periods);
$classCount = count($classes);
if ($periodCount <= 6) {
  $classesPerPage = 10;
} elseif ($periodCount <= 8) {
  $classesPerPage = 8;
} elseif ($periodCount <= 10) {
  $classesPerPage = 6;
} else {
  $classesPerPage = 5;
}

$classChunks = array_chunk($classes, $classesPerPage);
foreach ($classChunks as $chunkIndex => $classChunk) {
  if ($chunkIndex > 0) {
    $html .= '<div style="page-break-before:always;"></div>';
  }

  if (count($classChunks) > 1) {
    $from = ($chunkIndex * $classesPerPage) + 1;
    $to = $from + count($classChunk) - 1;
    $html .= '<div style="font-size:11px;color:#666;margin-bottom:6px;">Classes ' . $from . ' to ' . $to . ' of ' . $classCount . '</div>';
  }

  foreach ($days as $dayOfWeek => $dayName) {
    $html .= '<div style="font-size:12px;font-weight:bold;margin:6px 0 4px 0;">' . htmlspecialchars($dayName) . '</div>';
    $html .= '<table width="100%" cellspacing="0" cellpadding="3" style="border-collapse:collapse;font-size:7.4px;table-layout:fixed;margin-bottom:6px;">';
    $html .= '<tr style="background:#f3f4f6;"><th align="left" style="border:1px solid #ddd;width:80px;">Class</th>';
    foreach ($periods as $period) {
      $html .= '<th align="center" style="border:1px solid #ddd;">'
            . htmlspecialchars((string)$period['label'])
            . '<div style="font-size:6.8px;color:#666;">' . htmlspecialchars((string)$period['start_time']) . '-' . htmlspecialchars((string)$period['end_time']) . '</div>'
            . '</th>';
    }
    $html .= '</tr>';

    foreach ($classChunk as $class) {
      $html .= '<tr><td style="border:1px solid #ddd;font-weight:bold;">' . htmlspecialchars((string)$class['name']) . '</td>';
      foreach ($periods as $period) {
        if ((int)$period['is_teaching_period'] === 0) {
          $html .= '<td style="border:1px solid #ddd;text-align:center;color:#888;">BRK</td>';
          continue;
        }

        $entry = $slotMap[$class['id'] . '-' . $dayOfWeek . '-' . $period['id']] ?? null;
        if (!$entry) {
          $html .= '<td style="border:1px solid #ddd;text-align:center;color:#999;">-</td>';
          continue;
        }

        $html .= '<td style="border:1px solid #ddd;vertical-align:top;">'
              . '<div style="font-weight:bold;">' . htmlspecialchars((string)$entry['subject_code']) . '</div>'
              . '<div style="color:#444;">' . htmlspecialchars((string)$entry['teacher_name']) . '</div>'
              . '</td>';
      }
      $html .= '</tr>';
    }

    $html .= '</table>';
  }
}

$pdf = timetable_render_pdf_binary($html, 'A3', 'landscape', $watermarkFile);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="school_timetable_a3.pdf"');
echo $pdf;
