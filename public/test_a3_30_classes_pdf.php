<?php
declare(strict_types=1);

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
  http_response_code(500);
  exit('dompdf is not installed.');
}

require_once $autoload;

if (!class_exists('Dompdf\\Dompdf')) {
  http_response_code(500);
  exit('dompdf is not available.');
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$periods = [
  ['label' => 'P1', 'time' => '07:30-08:10'],
  ['label' => 'P2', 'time' => '08:10-08:50'],
  ['label' => 'P3', 'time' => '08:50-09:30'],
  ['label' => 'P4', 'time' => '09:50-10:30'],
  ['label' => 'P5', 'time' => '10:30-11:10'],
  ['label' => 'P6', 'time' => '11:10-11:50'],
  ['label' => 'P7', 'time' => '12:10-12:50'],
  ['label' => 'P8', 'time' => '12:50-13:30'],
  ['label' => 'P9', 'time' => '13:30-14:10'],
];
$subjects = ['MATH', 'ENG', 'BIO', 'CHEM', 'PHY', 'GEO', 'HIST', 'FREN', 'ICT', 'A MATH', 'LIT', 'ECON'];
$teachers = ['Mr. Nfor', 'Mrs. Ndzi', 'Mr. Tabi', 'Mrs. Neba', 'Mr. Fai', 'Mrs. Ayuk', 'Mr. Neba', 'Mrs. Ngang', 'Mr. Taku', 'Mrs. Lyonga'];

$classes = [];
for ($i = 0; $i < 30; $i++) {
  $classes[] = 'Form ' . (int)floor($i / 5 + 1) . chr(65 + ($i % 5));
}

$html = '<html><head><meta charset="utf-8"><style>
  @page { size: A3 landscape; margin: 8mm; }
  body { font-family: DejaVu Sans, Arial, sans-serif; color: #172033; font-size: 8px; }
  .title { font-size: 20px; font-weight: bold; margin-bottom: 4px; }
  .subtitle { color: #5e6b85; margin-bottom: 10px; }
  table { width: 100%; border-collapse: collapse; table-layout: fixed; }
  th, td { border: 1px solid #cfd8e3; padding: 2px 2px; vertical-align: top; }
  th { background: #dfe8f3; text-align: center; font-weight: bold; }
  .class-col { width: 52px; font-weight: bold; background: #eef4fa; }
  .day-head { font-size: 9px; background: #cfdbe9; }
  .period-head { font-size: 7px; }
  .subject { font-weight: bold; font-size: 7px; line-height: 1.1; }
  .teacher { font-size: 6px; color: #5e6b85; line-height: 1.05; margin-top: 1px; }
  .break { text-align: center; color: #5e6b85; background: #f8fafc; font-weight: bold; font-size: 7px; }
  .foot { margin-top: 8px; color: #5e6b85; font-size: 8px; }
</style></head><body>';

$html .= '<div class="title">A3 Test Timetable Preview</div>';
$html .= '<div class="subtitle">Normale Stundenplan-Ansicht: eine Zeile pro Klasse, Montag bis Freitag in der Kopfzeile.</div>';

$html .= '<table><thead>';
$html .= '<tr><th class="class-col" rowspan="2">Class</th>';
foreach ($days as $day) {
  $html .= '<th class="day-head" colspan="' . count($periods) . '">' . htmlspecialchars($day) . '</th>';
}
$html .= '</tr><tr>';
foreach ($days as $day) {
  foreach ($periods as $period) {
    $html .= '<th class="period-head">' . htmlspecialchars($period['label']) . '</th>';
  }
}
$html .= '</tr></thead><tbody>';

foreach ($classes as $classIndex => $className) {
  $html .= '<tr><td class="class-col">' . htmlspecialchars($className) . '</td>';
  foreach ($days as $dayIndex => $day) {
    foreach ($periods as $periodIndex => $period) {
      $isBreak = $periodIndex === 2;
      if ($isBreak) {
        $html .= '<td class="break">BREAK</td>';
        continue;
      }

      $seed = strlen($className) + (($dayIndex + $classIndex) * 7) + ($periodIndex * 11);
      $subject = $subjects[$seed % count($subjects)];
      $teacher = $teachers[($seed + $dayIndex) % count($teachers)];

      $html .= '<td><div class="subject">' . htmlspecialchars($subject) . '</div><div class="teacher">' . htmlspecialchars($teacher) . '</div></td>';
    }
  }
  $html .= '</tr>';
}

$html .= '</tbody></table>';
$html .= '<div class="foot">Testdokument mit 30 Klassen und 45 Stundenplanfeldern pro Klasse, damit du das A3-Layout wie bei einem normalen Timetable prüfen kannst.</div>';
$html .= '</body></html>';

$dompdf = new \Dompdf\Dompdf();
$dompdf->getOptions()->set('isHtml5ParserEnabled', true);
$dompdf->loadHtml($html);
$dompdf->setPaper('A3', 'landscape');
$dompdf->render();

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="test_a3_30_classes.pdf"');
echo $dompdf->output();
