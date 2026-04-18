<?php
declare(strict_types=1);

function timetable_days(): array {
  return [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday'];
}

function timetable_periods(): array {
  return db()->query("
    SELECT id, label, start_time, end_time, sort_order, is_teaching_period
    FROM periods
    ORDER BY sort_order
  ")->fetchAll();
}

function timetable_school_settings(): array {
  return db()->query("SELECT * FROM school_settings WHERE id=1")->fetch() ?: [
    'school_name' => APP_NAME,
    'logo_path' => null,
    'watermark_path' => null,
  ];
}

function timetable_image_file_to_data_uri(string $file): string {
  if (!is_file($file)) {
    return '';
  }

  $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
  if ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
    $img = @imagecreatefromwebp($file);
    if ($img !== false) {
      ob_start();
      imagepng($img);
      $png = ob_get_clean();
      imagedestroy($img);
      if ($png !== false) {
        return 'data:image/png;base64,' . base64_encode($png);
      }
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
  if ($data === false || $data === '') {
    return '';
  }

  return 'data:' . $mime . ';base64,' . base64_encode($data);
}

function timetable_branding_assets(): array {
  $settings = timetable_school_settings();

  $logoFile = '';
  if (!empty($settings['logo_path'])) {
    $logoFile = timetable_image_file_to_data_uri(__DIR__ . '/../public/' . ltrim((string)$settings['logo_path'], '/'));
  }

  $watermarkFile = '';
  if (!empty($settings['watermark_path'])) {
    $watermarkFile = timetable_image_file_to_data_uri(__DIR__ . '/../public/' . ltrim((string)$settings['watermark_path'], '/'));
  }

  return [
    'settings' => $settings,
    'logo_file' => $logoFile,
    'watermark_file' => $watermarkFile,
  ];
}

function timetable_signatory_data(): array {
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

  $signatureSlots = 2;
  $settingsRow = db()->query("SELECT signature_slots FROM timetable_signature_settings WHERE id=1")->fetch();
  if ($settingsRow) {
    $signatureSlots = max(1, min(10, (int)$settingsRow['signature_slots']));
  }

  return [
    'signature_slots' => $signatureSlots,
    'signatories' => db()->query("
      SELECT name, title
      FROM timetable_signatories
      WHERE is_active=1
      ORDER BY sort_order, id
    ")->fetchAll(),
  ];
}

function timetable_fetch_class_rows(int $classId): array {
  $stmt = db()->prepare("
    SELECT te.class_id, te.subject_id, te.teacher_user_id, te.day_of_week, te.period_id, te.source, te.is_locked,
           c.name class_name,
           s.code subject_code, s.name subject_name,
           u.full_name teacher_name,
           p.label period_label, p.start_time, p.end_time, p.sort_order
    FROM timetable_entries te
    JOIN classes c ON c.id = te.class_id
    JOIN subjects s ON s.id = te.subject_id
    JOIN users u ON u.id = te.teacher_user_id
    JOIN periods p ON p.id = te.period_id
    WHERE te.class_id = ?
    ORDER BY te.day_of_week, p.sort_order
  ");
  $stmt->execute([$classId]);
  return $stmt->fetchAll();
}

function timetable_fetch_class_entry_map(int $classId): array {
  $raw = [];
  foreach (timetable_fetch_class_rows($classId) as $row) {
    $key = $row['day_of_week'] . '-' . $row['period_id'];
    $raw[$key][] = $row;
  }

  $entries = [];
  foreach ($raw as $key => $rows) {
    if (count($rows) === 1) {
      $entries[$key] = $rows[0];
    } else {
      // Paired slot: merge subject codes and teacher names for display
      $first = $rows[0];
      $first['subject_code'] = implode(' / ', array_column($rows, 'subject_code'));
      $first['subject_name'] = implode(' / ', array_column($rows, 'subject_name'));
      $first['teacher_name'] = implode(' / ', array_column($rows, 'teacher_name'));
      $first['is_paired']    = true;
      $entries[$key] = $first;
    }
  }
  return $entries;
}

function timetable_fetch_teacher_rows(int $teacherId): array {
  $stmt = db()->prepare("
    SELECT te.teacher_user_id, te.class_id, te.subject_id, te.day_of_week, te.period_id,
           c.name class_name,
           s.code subject_code, s.name subject_name,
           u.full_name teacher_name,
           p.label period_label, p.start_time, p.end_time, p.sort_order
    FROM timetable_entries te
    JOIN classes c ON c.id = te.class_id
    JOIN subjects s ON s.id = te.subject_id
    JOIN users u ON u.id = te.teacher_user_id
    JOIN periods p ON p.id = te.period_id
    WHERE te.teacher_user_id = ?
    ORDER BY te.day_of_week, p.sort_order, c.name
  ");
  $stmt->execute([$teacherId]);
  return $stmt->fetchAll();
}

function timetable_fetch_teacher_entry_map(int $teacherId): array {
  $entries = [];
  foreach (timetable_fetch_teacher_rows($teacherId) as $row) {
    $entries[$row['day_of_week'] . '-' . $row['period_id']] = $row;
  }
  return $entries;
}

function timetable_fetch_school_rows(): array {
  return db()->query("
    SELECT te.class_id, te.subject_id, te.teacher_user_id, te.day_of_week, te.period_id,
           c.name class_name,
           s.code subject_code, s.name subject_name,
           u.full_name teacher_name,
           p.label period_label, p.start_time, p.end_time, p.sort_order
    FROM timetable_entries te
    JOIN classes c ON c.id = te.class_id
    JOIN subjects s ON s.id = te.subject_id
    JOIN users u ON u.id = te.teacher_user_id
    JOIN periods p ON p.id = te.period_id
    ORDER BY te.day_of_week, p.sort_order, c.name, s.code, u.full_name
  ")->fetchAll();
}

function timetable_filename_safe(string $value): string {
  $out = preg_replace('/[^a-z0-9_-]+/i', '_', $value) ?? 'timetable';
  $out = trim($out, '_');
  return $out !== '' ? $out : 'timetable';
}

function timetable_logo_html(string $logoFile): string {
  return $logoFile !== '' ? '<img src="' . htmlspecialchars($logoFile) . '" style="height:55px;" />' : '';
}

function timetable_header_html(string $logoHtml, string $schoolName, string $title, string $subtitle = ''): string {
  $html = '<table width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:10px;"><tr>'
        . '<td style="width:70px;">' . $logoHtml . '</td>'
        . '<td>'
        . '<div style="font-size:20px;font-weight:bold;">' . htmlspecialchars($schoolName) . '</div>'
        . '<div style="color:#555;">' . htmlspecialchars($title) . '</div>';
  if ($subtitle !== '') {
    $html .= '<div style="color:#777;font-size:11px;">' . htmlspecialchars($subtitle) . '</div>';
  }
  $html .= '</td></tr></table>';
  return $html;
}

function timetable_pdf_body_html(string $bodyHtml, string $watermarkFile = ''): string {
  $wmHtml = '';
  if ($watermarkFile !== '') {
    $wmHtml = '<div style="position:fixed;top:0;left:0;right:0;bottom:0;z-index:-100;">'
            . '<img src="' . htmlspecialchars($watermarkFile) . '" style="width:100%;height:100%;object-fit:cover;opacity:0.12;">'
            . '</div>';
  }

  return '<html><head><meta charset="utf-8"></head><body style="font-family:DejaVu Sans, Arial;position:relative;">'
       . $wmHtml
       . $bodyHtml
       . '</body></html>';
}

function timetable_require_dompdf(): void {
  $dompdfPath = __DIR__ . '/../vendor/autoload.php';
  if (!file_exists($dompdfPath)) {
    http_response_code(500);
    exit('PDF export is not installed');
  }

  require_once $dompdfPath;
  if (!class_exists('Dompdf\\Dompdf')) {
    http_response_code(500);
    exit('PDF export is not installed');
  }
}

function timetable_render_pdf_binary(string $bodyHtml, string $paper = 'A4', string $orientation = 'landscape', string $watermarkFile = ''): string {
  timetable_require_dompdf();
  $dompdf = new \Dompdf\Dompdf();
  $dompdf->getOptions()->set('isRemoteEnabled', true);
  $dompdf->getOptions()->set('isHtml5ParserEnabled', true);
  $dompdf->loadHtml(timetable_pdf_body_html($bodyHtml, $watermarkFile));
  $dompdf->setPaper($paper, $orientation);
  $dompdf->render();
  return $dompdf->output();
}
