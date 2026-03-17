<?php
$u = current_user();
$role = $u['role'];

$navByRole = [
  'admin' => [
    ['Admin Dashboard', 'admin_dashboard', 'bi-grid-1x2', 'Manage teachers, classes, subjects, schedules'],
    ['Teacher Stamp Desk', 'teacher_stamps', 'bi-person-badge', 'View teacher codes and the live 1-minute office security codes'],
    ['Attendance', 'attendance', 'bi-check2-square', 'Record and validate attendance'],
    ['Timetable', 'timetable', 'bi-calendar3-week', 'Generate and edit class timetables'],
    ['Reports', 'reports', 'bi-graph-up', 'Salary and attendance analytics'],
  ],
  'principal' => [
    ['Teachers', 'admin_teachers', 'bi-mortarboard', 'View teacher codes and today stamp status without admin edit controls'],
    ['Attendance Validation', 'attendance', 'bi-shield-check', 'Approve/reject teacher records'],
    ['Timetable', 'timetable', 'bi-calendar3-week', 'View school timetable status'],
    ['Reports', 'reports', 'bi-graph-up', 'Monthly performance and salary reports'],
  ],
  'teacher' => [
    ['Teacher Portal', 'teacher_portal', 'bi-person-lines-fill', 'Your schedule and salary overview'],
    ['My Timetable', 'timetable', 'bi-calendar3-week', 'Your weekly timetable'],
    ['My Reports', 'reports', 'bi-file-earmark-text', 'Your monthly report exports'],
  ],
  'prefect' => [
    ['Record Attendance', 'attendance', 'bi-check2-square', 'Mark arrived, departed, absent'],
    ['Class Timetable', 'timetable', 'bi-calendar3-week', 'View class periods and teachers'],
  ],
];
$cards = $navByRole[$role] ?? [];

$todayDow = (int)date('N');
$isSchoolDay = $todayDow >= 1 && $todayDow <= 5;
$todayCards = [];

if ($isSchoolDay) {
  $params = [$todayDow];
  $sql = "
    SELECT
      u.full_name AS teacher_name,
      c.name AS class_name,
      s.code AS subject_code,
      s.name AS subject_name,
      p.label AS period_label,
      p.start_time,
      p.end_time
    FROM timetable_entries te
    JOIN users u ON u.id = te.teacher_user_id
    JOIN classes c ON c.id = te.class_id
    JOIN subjects s ON s.id = te.subject_id
    JOIN periods p ON p.id = te.period_id
    WHERE te.day_of_week = ?
  ";

  if ($role === 'prefect') {
    $sql .= " AND c.prefect_user_id = ? ";
    $params[] = (int)$u['id'];
  } elseif ($role === 'teacher') {
    $sql .= " AND te.teacher_user_id = ? ";
    $params[] = (int)$u['id'];
  }

  $sql .= " ORDER BY p.sort_order, c.name, u.full_name";
  $stmt = db()->prepare($sql);
  $stmt->execute($params);
  $todayCards = $stmt->fetchAll();
}
?>
<div class="row g-3">
  <div class="col-12">
    <div class="card card-soft">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
          <div>
            <div class="h4 fw-bold mb-1">EduTrack Home</div>
            <div class="text-muted">Welcome back, <?= htmlspecialchars($u['full_name']) ?>.</div>
          </div>
          <span class="badge rounded-pill text-bg-secondary px-3 py-2"><?= strtoupper(htmlspecialchars($role)) ?></span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card card-soft">
      <div class="card-body p-4">
        <div class="h5 fw-bold mb-1">Navigation</div>
        <div class="text-muted small">Role-based quick access.</div>
        <div class="row g-3 mt-1">
          <?php foreach ($cards as [$label, $pageName, $icon, $desc]): ?>
            <div class="col-md-6">
              <a class="card card-soft text-decoration-none p-3 d-block h-100" href="<?= BASE_URL ?>/index.php?page=<?= $pageName ?>">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <i class="bi <?= $icon ?>"></i>
                  <span class="fw-semibold"><?= htmlspecialchars($label) ?></span>
                </div>
                <div class="text-muted small"><?= htmlspecialchars($desc) ?></div>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card card-soft">
      <div class="card-body p-4">
        <div class="h5 fw-bold mb-1">Today Summary</div>
        <div class="text-muted small">
          <?php if ($role === 'admin' || $role === 'principal'): ?>
            All booked teacher periods for today.
          <?php elseif ($role === 'prefect'): ?>
            Booked periods for your assigned class.
          <?php else: ?>
            Your classes for today.
          <?php endif; ?>
        </div>
        <div class="mt-3">
          <?php if (!$isSchoolDay): ?>
            <div class="text-muted">No school periods today (weekend).</div>
          <?php else: ?>
            <div class="display-6 fw-bold"><?= count($todayCards) ?></div>
            <div class="text-muted small">Total cards for today</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card card-soft">
      <div class="card-body p-4">
        <div class="h5 fw-bold mb-1">Today's Teacher Cards</div>
        <div class="text-muted small">
          <?php if ($role === 'admin' || $role === 'principal'): ?>
            All teachers booked for today.
          <?php elseif ($role === 'prefect'): ?>
            Teachers booked for your class today.
          <?php else: ?>
            Your own booked classes for today.
          <?php endif; ?>
        </div>

        <?php if (!$isSchoolDay): ?>
          <div class="alert alert-secondary mt-3 mb-0">No classes today (weekend).</div>
        <?php elseif (!$todayCards): ?>
          <div class="alert alert-secondary mt-3 mb-0">No timetable cards found for today.</div>
        <?php else: ?>
          <div class="row g-3 mt-1">
            <?php foreach ($todayCards as $r): ?>
              <?php $isNow = (time() >= strtotime(date('Y-m-d ' . $r['start_time'])) && time() <= strtotime(date('Y-m-d ' . $r['end_time']))); ?>
              <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card card-soft h-100">
                  <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                      <div class="fw-semibold"><?= htmlspecialchars($r['teacher_name']) ?></div>
                      <span class="badge <?= $isNow ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $isNow ? 'Now' : 'Today' ?></span>
                    </div>
                    <div class="text-muted small"><?= htmlspecialchars($r['subject_code']) ?> - <?= htmlspecialchars($r['subject_name']) ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($r['class_name']) ?></div>
                    <div class="mt-2 small"><?= htmlspecialchars($r['period_label']) ?> (<?= htmlspecialchars($r['start_time']) ?> - <?= htmlspecialchars($r['end_time']) ?>)</div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>
