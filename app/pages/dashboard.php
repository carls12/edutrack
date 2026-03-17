<?php
$u = current_user();
$role = $u['role'];

$pending = (int)db()->query("SELECT COUNT(*) c FROM attendance WHERE validation_status='pending'")->fetch()['c'];
$teachersCount = (int)db()->query("SELECT COUNT(*) c FROM users WHERE role='teacher'")->fetch()['c'];
$classesCount = (int)db()->query("SELECT COUNT(*) c FROM classes")->fetch()['c'];
$liveNow = [];
if (in_array($role, ['admin', 'principal'], true)) {
  $todayDow = (int)date('N');
  if ($todayDow >= 1 && $todayDow <= 5) {
    $stmt = db()->prepare("
      SELECT
        u.full_name AS teacher_name,
        c.name AS class_name,
        s.code AS subject_code,
        p.label AS period_label
      FROM timetable_entries te
      JOIN users u ON u.id = te.teacher_user_id
      JOIN classes c ON c.id = te.class_id
      JOIN subjects s ON s.id = te.subject_id
      JOIN periods p ON p.id = te.period_id
      WHERE te.day_of_week = ?
        AND CURTIME() BETWEEN p.start_time AND p.end_time
      ORDER BY c.name, u.full_name
    ");
    $stmt->execute([$todayDow]);
    $liveNow = $stmt->fetchAll();
  }
}
?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card card-soft">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="h4 fw-bold mb-1">Hello, <?= htmlspecialchars($u['full_name']) ?> 👋</div>
            <div class="text-muted">Welcome to EduTrack — teacher time & school operations, simplified.</div>
          </div>
          <span class="badge rounded-pill text-bg-secondary px-3 py-2"><?= strtoupper(htmlspecialchars($role)) ?></span>
        </div>

        <div class="row g-3 mt-2">
          <div class="col-md-4">
            <div class="card card-soft">
              <div class="card-body">
                <div class="text-muted small">Pending validations</div>
                <div class="display-6 fw-bold"><?= $pending ?></div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card card-soft">
              <div class="card-body">
                <div class="text-muted small">Teachers</div>
                <div class="display-6 fw-bold"><?= $teachersCount ?></div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card card-soft">
              <div class="card-body">
                <div class="text-muted small">Classes</div>
                <div class="display-6 fw-bold"><?= $classesCount ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-3 text-muted small">
          Admin tip: set <b>Teacher Availability</b> and <b>Assignments</b>, then generate a timetable.
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card card-soft">
      <div class="card-body p-4">
        <div class="fw-bold mb-2">Quick actions</div>
        <div class="d-grid gap-2">
          <?php if ($role === 'admin'): ?>
          <a class="btn btn-soft" href="<?= BASE_URL ?>/index.php?page=admin_users"><i class="bi bi-people me-2"></i>Manage Users</a>
          <a class="btn btn-soft" href="<?= BASE_URL ?>/index.php?page=admin_assignments"><i class="bi bi-link-45deg me-2"></i>Teacher Assignments</a>
          <a class="btn btn-soft" href="<?= BASE_URL ?>/index.php?page=timetable"><i class="bi bi-calendar3-week me-2"></i>Generate Timetable</a>
          <?php endif; ?>
          <?php if ($role === 'teacher'): ?>
            <a class="btn btn-soft" href="<?= BASE_URL ?>/index.php?page=teacher_portal"><i class="bi bi-person-lines-fill me-2"></i>Teacher Portal</a>
          <?php else: ?>
            <a class="btn btn-soft" href="<?= BASE_URL ?>/index.php?page=attendance"><i class="bi bi-check2-square me-2"></i>Attendance</a>
          <?php endif; ?>
          <a class="btn btn-soft" href="<?= BASE_URL ?>/index.php?page=reports"><i class="bi bi-graph-up me-2"></i>Reports</a>
        </div>
      </div>
    </div>
    <div class="card card-soft mt-3">
      <div class="card-body p-4">
        <div class="h6 fw-bold mb-1">Currently In Class</div>
        <div class="text-muted small">Live view by timetable period.</div>
        <?php if (!in_array($role, ['admin', 'principal'], true)): ?>
          <div class="text-muted mt-2">Visible to Admin/Principal.</div>
        <?php elseif (!$liveNow): ?>
          <div class="text-muted mt-2">No active lessons now.</div>
        <?php else: ?>
          <div class="mt-2 d-grid gap-2">
            <?php foreach ($liveNow as $r): ?>
              <div class="card card-soft">
                <div class="card-body py-2">
                  <div class="fw-semibold"><?= htmlspecialchars($r['teacher_name']) ?></div>
                  <div class="text-muted small"><?= htmlspecialchars($r['class_name']) ?> · <?= htmlspecialchars($r['subject_code']) ?> · <?= htmlspecialchars($r['period_label']) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
