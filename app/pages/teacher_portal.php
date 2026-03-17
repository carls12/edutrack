<?php
require_role(['teacher']);
$u = current_user();

$month = $_GET['month'] ?? date('Y-m');
$from = $month . "-01 00:00:00";
$to   = date('Y-m-t', strtotime($month . "-01")) . " 23:59:59";

$stmt = db()->prepare("
SELECT 
  t.salary_type, t.hourly_rate, t.fixed_salary,
  SUM(CASE WHEN a.validation_status='validated' AND a.status='absent' THEN 1 ELSE 0 END) AS absent_days,
  SUM(CASE WHEN a.validation_status='validated' THEN a.worked_minutes ELSE 0 END) AS worked_minutes
FROM teachers t
LEFT JOIN attendance a ON a.teacher_user_id=t.user_id AND a.event_time BETWEEN ? AND ?
WHERE t.user_id=?
GROUP BY t.user_id
");
$stmt->execute([$from,$to,$u['id']]);
$r = $stmt->fetch() ?: ['salary_type'=>'hourly','hourly_rate'=>0,'fixed_salary'=>0,'absent_days'=>0,'worked_minutes'=>0];

$hours = ((int)$r['worked_minutes'])/60.0;
$salary = ($r['salary_type']==='hourly') ? ($hours*(float)$r['hourly_rate']) : ((float)$r['fixed_salary']);
$settings = db()->query("SELECT * FROM school_settings WHERE id=1")->fetch() ?: [
  'school_name' => APP_NAME,
  'logo_path' => null,
  'currency' => 'XAF',
  'timezone' => 'Africa/Douala',
];
$currency = $settings['currency'] ?? 'XAF';

$att = db()->prepare("
SELECT a.*, c.name class_name
FROM attendance a
LEFT JOIN classes c ON c.id=a.class_id
WHERE a.teacher_user_id=? AND a.event_time BETWEEN ? AND ?
ORDER BY a.event_time DESC
LIMIT 100
");
$att->execute([$u['id'],$from,$to]);
$attRows = $att->fetchAll();

$tt = db()->prepare("
SELECT te.*, s.code subject_code, c.name class_name, p.label period_label, p.start_time, p.end_time
FROM timetable_entries te
JOIN subjects s ON s.id=te.subject_id
JOIN classes c ON c.id=te.class_id
JOIN periods p ON p.id=te.period_id
WHERE te.teacher_user_id=?
ORDER BY te.day_of_week, p.sort_order
");
$tt->execute([$u['id']]);
$ttRows = $tt->fetchAll();

$days = [1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri'];
?>
<div class="row g-3">
  <div class="col-12">
    <div class="card card-soft">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <div class="h5 fw-bold mb-1">Teacher Portal</div>
            <div class="text-muted small">Your schedule, attendance, and earnings.</div>
          </div>
          <form method="get" action="<?= BASE_URL ?>/index.php" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="page" value="teacher_portal">
            <input class="form-control" type="month" name="month" value="<?= htmlspecialchars($month) ?>">
            <button class="btn btn-soft" type="submit"><i class="bi bi-funnel me-1"></i>Apply</button>
          </form>
        </div>

        <div class="row g-3 mt-1">
          <div class="col-md-4">
            <div class="card card-soft"><div class="card-body">
              <div class="text-muted small">Worked hours</div>
              <div class="display-6 fw-bold"><?= number_format($hours,2) ?></div>
            </div></div>
          </div>
          <div class="col-md-4">
            <div class="card card-soft"><div class="card-body">
              <div class="text-muted small">Absent days</div>
              <div class="display-6 fw-bold"><?= (int)$r['absent_days'] ?></div>
            </div></div>
          </div>
          <div class="col-md-4">
            <div class="card card-soft"><div class="card-body">
              <div class="text-muted small">Salary (<?= htmlspecialchars($currency) ?>)</div>
              <div class="display-6 fw-bold"><?= number_format($salary,2) ?></div>
            </div></div>
          </div>
        </div>

        <hr class="sep my-4">

        <div class="row g-3">
          <div class="col-lg-6">
            <div class="fw-bold mb-2">My timetable</div>
            <div class="table-responsive">
              <table class="table table-dark table-hover align-middle">
                <thead class="text-muted">
                  <tr><th>Day</th><th>Period</th><th>Class</th><th>Subject</th></tr>
                </thead>
                <tbody>
                  <?php foreach($ttRows as $x): ?>
                  <tr>
                    <td><?= htmlspecialchars($days[(int)$x['day_of_week']] ?? $x['day_of_week']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($x['period_label']) ?> (<?= htmlspecialchars($x['start_time']) ?>–<?= htmlspecialchars($x['end_time']) ?>)</td>
                    <td><?= htmlspecialchars($x['class_name']) ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($x['subject_code']) ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(!$ttRows): ?><tr><td colspan="4" class="text-muted">No timetable entries yet.</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="fw-bold mb-2">My attendance (<?= htmlspecialchars($month) ?>)</div>
            <div class="table-responsive">
              <table class="table table-dark table-hover align-middle">
                <thead class="text-muted">
                  <tr><th>Status</th><th>Time</th><th>Class</th><th>Validation</th></tr>
                </thead>
                <tbody>
                  <?php foreach($attRows as $a): ?>
                  <tr>
                    <td><?= htmlspecialchars($a['status']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($a['event_time']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($a['class_name'] ?? '-') ?></td>
                    <td><span class="badge text-bg-secondary"><?= htmlspecialchars($a['validation_status']) ?></span></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(!$attRows): ?><tr><td colspan="4" class="text-muted">No attendance records for this month.</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

        </div>

      </div>
    </div>
  </div>
</div>
