<?php
require_once __DIR__ . '/../teacher_stamp.php';
$u = current_user();
$role = $u['role'];
teacher_stamp_ensure_schema();
$month = $_GET['month'] ?? date('Y-m');
$teacherIdFilter = (int)($_GET['teacher_user_id'] ?? 0);

$from = $month . "-01 00:00:00";
$to   = date('Y-m-t', strtotime($month . "-01")) . " 23:59:59";

$params = [$from, $to];
$teacherFilter = "";
if ($role === 'teacher') {
  $teacherFilter=" AND u.id=? ";
  $params[]=$u['id'];
} elseif ($teacherIdFilter > 0) {
  $teacherFilter=" AND u.id=? ";
  $params[]=$teacherIdFilter;
}

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

$settings = db()->query("SELECT * FROM school_settings WHERE id=1")->fetch() ?: [
  'school_name' => APP_NAME,
  'logo_path' => null,
  'currency' => 'XAF',
  'timezone' => 'Africa/Douala',
];
$currency = $settings['currency'] ?? 'XAF';

$chartLabels = [];
$chartValues = [];
$chartHours = [];
$totalSalary = 0;
$nameCounts = [];

foreach ($rows as $r0) {
  $n = (string)$r0['full_name'];
  $nameCounts[$n] = ($nameCounts[$n] ?? 0) + 1;
}

foreach($rows as &$r){
  $hours = ((int)$r['worked_minutes'])/60.0;
  $salary = ($r['salary_type']==='hourly') ? ($hours*(float)$r['hourly_rate']) : ((float)$r['fixed_salary']);
  $r['worked_hours'] = round($hours, 2);
  $r['salary'] = round($salary, 2);
  $r['label_name'] = (($nameCounts[(string)$r['full_name']] ?? 0) > 1)
    ? ($r['full_name'] . ' (' . $r['email'] . ')')
    : $r['full_name'];
  $totalSalary += $r['salary'];
  $chartLabels[] = $r['label_name'];
  $chartValues[] = $r['salary'];
  $chartHours[] = $r['worked_hours'];
}
$chartHeight = max(260, count($rows) * 44);
$teacherOptions = [];
if ($role !== 'teacher') {
  $teacherOptions = db()->query("SELECT id, full_name, email FROM users WHERE role='teacher' AND is_active=1 ORDER BY full_name")->fetchAll();
}

$stampSummaryParams = [$from, $to];
$stampSummaryFilter = '';
if ($role === 'teacher') {
  $stampSummaryFilter = ' AND u.id = ? ';
  $stampSummaryParams[] = (int)$u['id'];
} elseif ($teacherIdFilter > 0) {
  $stampSummaryFilter = ' AND u.id = ? ';
  $stampSummaryParams[] = $teacherIdFilter;
}

$stampSummaryStmt = db()->prepare("
SELECT
  DATE(a.event_time) AS stamp_day,
  u.full_name,
  u.email,
  MIN(CASE WHEN a.status='arrived' THEN a.event_time END) AS first_in,
  MAX(CASE WHEN a.status='departed' THEN a.event_time END) AS last_out
FROM attendance a
JOIN users u ON u.id = a.teacher_user_id
WHERE a.source='teacher_stamp'
  AND a.event_time BETWEEN ? AND ?
  $stampSummaryFilter
GROUP BY DATE(a.event_time), u.id, u.full_name, u.email
ORDER BY stamp_day DESC, u.full_name ASC
LIMIT 300
");
$stampSummaryStmt->execute($stampSummaryParams);
$stampRows = $stampSummaryStmt->fetchAll();
?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card card-soft">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <div class="h5 fw-bold mb-1">Reports & Analytics</div>
            <div class="text-muted small">Monthly attendance summary and salary calculations (validated records only).</div>
          </div>
          <form method="get" action="<?= BASE_URL ?>/index.php" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="page" value="reports">
            <input class="form-control" type="month" name="month" value="<?= htmlspecialchars($month) ?>">
            <?php if ($role !== 'teacher'): ?>
              <select class="form-select" name="teacher_user_id">
                <option value="0">All teachers</option>
                <?php foreach ($teacherOptions as $teacherOption): ?>
                  <option value="<?= (int)$teacherOption['id'] ?>" <?= $teacherIdFilter === (int)$teacherOption['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($teacherOption['full_name']) ?> (<?= htmlspecialchars($teacherOption['email']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
            <button class="btn btn-soft" type="submit"><i class="bi bi-funnel me-1"></i>Apply</button>
          </form>
        </div>

        <div class="mt-3" style="height: <?= (int)$chartHeight ?>px;">
          <canvas id="salaryChart"></canvas>
        </div>

        <div class="table-responsive mt-3">
          <table class="table table-dark table-hover align-middle">
            <thead class="text-muted">
              <tr><th>Teacher</th><th>Worked hours</th><th>Absent days</th><th class="text-end">Salary (<?= htmlspecialchars($currency) ?>)</th></tr>
            </thead>
            <tbody>
              <?php foreach($rows as $r): ?>
              <tr>
                <td class="fw-semibold"><?= htmlspecialchars($r['label_name']) ?></td>
                <td class="text-muted"><?= htmlspecialchars($r['worked_hours']) ?></td>
                <td class="text-muted"><?= (int)$r['absent_days'] ?></td>
                <td class="text-end fw-bold"><?= number_format((float)$r['salary'], 2) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="3" class="text-muted">Total</td>
                <td class="text-end fw-bold"><?= number_format((float)$totalSalary, 2) ?></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <div class="d-flex justify-content-end gap-2">
          <a class="btn btn-soft" href="<?= BASE_URL ?>/app/api/export_salary_csv.php?month=<?= urlencode($month) ?>"><i class="bi bi-filetype-csv me-1"></i>Export CSV</a>
          <a class="btn btn-soft" href="<?= BASE_URL ?>/app/api/export_salary_pdf.php?month=<?= urlencode($month) ?>"><i class="bi bi-filetype-pdf me-1"></i>Export PDF</a>
        </div>

      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card card-soft">
      <div class="card-body p-4">
        <div class="h5 fw-bold mb-1">Insights</div>
        <div class="text-muted small">This panel can be extended with more analytics.</div>

        <div class="mt-3 d-grid gap-2">
          <div class="card card-soft">
            <div class="card-body">
              <div class="text-muted small">Month</div>
              <div class="fw-bold"><?= htmlspecialchars($month) ?></div>
            </div>
          </div>
          <div class="card card-soft">
            <div class="card-body">
              <div class="text-muted small">Teachers in report</div>
              <div class="fw-bold"><?= count($rows) ?></div>
            </div>
          </div>
          <div class="card card-soft">
            <div class="card-body">
              <div class="text-muted small">Total salary</div>
              <div class="fw-bold"><?= number_format((float)$totalSalary, 2) ?> <?= htmlspecialchars($currency) ?></div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card card-soft">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <div class="h5 fw-bold mb-1">Teacher Stamp Log</div>
            <div class="text-muted small">Daily office stamp-in and stamp-out summary from the no-login teacher stamp page.</div>
          </div>
          <a class="btn btn-soft btn-sm" href="<?= BASE_URL ?>/timestap.php" target="_blank" rel="noopener">
            <i class="bi bi-box-arrow-up-right me-1"></i>Open Stamp Page
          </a>
        </div>

        <div class="table-responsive mt-3">
          <table class="table table-dark table-hover align-middle">
            <thead class="text-muted">
              <tr><th>Date</th><th>Teacher</th><th>First In</th><th>Last Out</th></tr>
            </thead>
            <tbody>
              <?php foreach ($stampRows as $stampRow): ?>
                <tr>
                  <td class="fw-semibold"><?= htmlspecialchars($stampRow['stamp_day']) ?></td>
                  <td><?= htmlspecialchars($stampRow['full_name']) ?> <span class="text-muted small"><?= htmlspecialchars($stampRow['email']) ?></span></td>
                  <td class="text-muted"><?= htmlspecialchars($stampRow['first_in'] ?? '-') ?></td>
                  <td class="text-muted"><?= htmlspecialchars($stampRow['last_out'] ?? '-') ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$stampRows): ?><tr><td colspan="4" class="text-muted">No teacher stamp records for this filter.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const labels = <?= json_encode($chartLabels) ?>;
const values = <?= json_encode($chartValues) ?>;
const workedHours = <?= json_encode($chartHours) ?>;
const ctx = document.getElementById('salaryChart');
if(ctx && typeof Chart !== 'undefined'){
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        { label: 'Worked Hours', data: workedHours, backgroundColor: 'rgba(56,189,248,0.75)', borderColor: 'rgba(56,189,248,1)', borderWidth: 1 },
        { label: 'Salary', data: values, backgroundColor: 'rgba(16,185,129,0.65)', borderColor: 'rgba(16,185,129,1)', borderWidth: 1 }
      ]
    },
    options: {
      indexAxis: 'y',
      maintainAspectRatio: false,
      plugins: { legend: { display: true, labels: { color: '#cbd5e1' } } },
      scales: {
        y: { ticks: { color: '#cbd5e1', autoSkip: false }, grid: { color: 'rgba(255,255,255,.07)' } },
        x: { ticks: { color: '#cbd5e1' }, grid: { color: 'rgba(255,255,255,.07)' } }
      }
    }
  });
} else if (ctx) {
  ctx.parentElement.innerHTML = '<div class="text-warning small">Chart.js is missing. Table data below is still accurate.</div>';
}
</script>
