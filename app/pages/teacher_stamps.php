<?php
require_once __DIR__ . '/../teacher_stamp.php';
require_role(['admin','principal']);
teacher_stamp_ensure_schema();

$teachers = db()->query("
  SELECT
    u.id AS user_id,
    u.full_name,
    u.email,
    t.salary_type,
    t.hourly_rate,
    t.fixed_salary,
    t.phone,
    t.stamp_code,
    t.stamp_secret
  FROM users u
  LEFT JOIN teachers t ON t.user_id = u.id
  WHERE u.role = 'teacher'
  ORDER BY u.full_name
")->fetchAll();

$assignRows = db()->query("
  SELECT
    a.teacher_user_id,
    a.hours_per_week,
    s.code AS subject_code,
    c.name AS class_name
  FROM teacher_assignments a
  JOIN subjects s ON s.id = a.subject_id
  JOIN classes c ON c.id = a.class_id
  ORDER BY a.teacher_user_id, c.name, s.code
")->fetchAll();

$assignByTeacher = [];
foreach ($assignRows as $row) {
  $teacherId = (int)$row['teacher_user_id'];
  if (!isset($assignByTeacher[$teacherId])) {
    $assignByTeacher[$teacherId] = [];
  }
  $assignByTeacher[$teacherId][] = $row;
}
?>
<?php
$teachersTotal = count($teachers);
$stampedInCount = 0;
$assignedCount = 0;
foreach ($teachers as $teacher) {
  $teacherId = (int)$teacher['user_id'];
  $status = teacher_stamp_today_status($teacherId);
  if ($status['is_in']) {
    $stampedInCount++;
  }
  if (!empty($assignByTeacher[$teacherId])) {
    $assignedCount++;
  }
}
?>
<div class="row g-3">
  <div class="col-12">
    <div class="card card-soft">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
          <div>
            <div class="h4 fw-bold mb-1">Teacher Stamp Desk</div>
            <div class="text-muted small">Live teacher codes, rotating 1-minute 2FA, and today&apos;s office stamp activity.</div>
          </div>
          <a class="btn btn-primary" href="<?= BASE_URL ?>/timestap.php" target="_blank" rel="noopener">
            <i class="bi bi-box-arrow-up-right me-1"></i>Open Stamp Page
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card card-soft h-100">
      <div class="card-body p-4">
        <div class="text-muted small">Teachers</div>
        <div class="display-6 fw-bold"><?= $teachersTotal ?></div>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card card-soft h-100">
      <div class="card-body p-4">
        <div class="text-muted small">Stamped In Today</div>
        <div class="display-6 fw-bold"><?= $stampedInCount ?></div>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card card-soft h-100">
      <div class="card-body p-4">
        <div class="text-muted small">Teachers With Assignments</div>
        <div class="display-6 fw-bold"><?= $assignedCount ?></div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card card-soft">
      <div class="card-body p-4">
        <div class="alert alert-info mb-0">
          Teachers use the public stamp page with their personal teacher code plus the live 6-digit security code shown here.
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card card-soft">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
          <div>
            <div class="h5 fw-bold mb-1">Live Teacher Credentials</div>
            <div class="text-muted small">The 6-digit code refreshes automatically. No page reload required.</div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-dark table-hover align-middle">
        <thead class="text-muted">
          <tr>
            <th>Teacher</th>
            <th>Email</th>
            <th>Teacher Code</th>
            <th>2FA (1 min)</th>
            <th>Today</th>
            <th>Salary Type</th>
            <th>Hourly</th>
            <th>Fixed</th>
            <th>Phone</th>
            <th>Subjects / Classes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($teachers as $teacher): ?>
            <?php
              $teacherId = (int)$teacher['user_id'];
              $stampStatus = teacher_stamp_today_status($teacherId);
              $assignments = $assignByTeacher[$teacherId] ?? [];
            ?>
            <tr>
              <td class="fw-semibold"><?= htmlspecialchars($teacher['full_name']) ?></td>
              <td class="text-muted"><?= htmlspecialchars($teacher['email']) ?></td>
              <td>
                <div class="fw-semibold" data-stamp-code="<?= $teacherId ?>"><?= htmlspecialchars($teacher['stamp_code'] ?? '-') ?></div>
              </td>
              <td>
                <div class="fw-bold" data-stamp-otp="<?= $teacherId ?>"><?= htmlspecialchars(($teacher['stamp_secret'] ?? '') !== '' ? teacher_stamp_current_otp((string)$teacher['stamp_secret']) : '------') ?></div>
                <div class="text-muted small" data-stamp-expiry="<?= $teacherId ?>">refreshing...</div>
              </td>
              <td class="small">
                <div class="text-muted">In: <?= htmlspecialchars($stampStatus['arrived_at'] ?? '-') ?></div>
                <div class="text-muted">Out: <?= htmlspecialchars($stampStatus['departed_at'] ?? '-') ?></div>
              </td>
              <td><span class="badge text-bg-secondary"><?= htmlspecialchars($teacher['salary_type'] ?? 'hourly') ?></span></td>
              <td><?= htmlspecialchars($teacher['hourly_rate'] ?? '') ?></td>
              <td><?= htmlspecialchars($teacher['fixed_salary'] ?? '') ?></td>
              <td class="text-muted"><?= htmlspecialchars($teacher['phone'] ?? '') ?></td>
              <td>
                <?php if (!$assignments): ?>
                  <span class="text-muted small">No assignments</span>
                <?php else: ?>
                  <div class="d-grid gap-1">
                    <?php foreach ($assignments as $assignment): ?>
                      <div class="small"><b><?= htmlspecialchars($assignment['subject_code']) ?></b> in <?= htmlspecialchars($assignment['class_name']) ?> (<?= (int)$assignment['hours_per_week'] ?>/wk)</div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$teachers): ?>
            <tr><td colspan="10" class="text-muted">No teachers found.</td></tr>
          <?php endif; ?>
        </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
async function refreshTeacherStampCodes() {
  try {
    const out = await api("<?= BASE_URL ?>/app/api/teacher_stamp_codes.php");
    const teachers = Array.isArray(out.teachers) ? out.teachers : [];
    teachers.forEach((teacher) => {
      const codeEl = document.querySelector(`[data-stamp-code="${teacher.teacher_user_id}"]`);
      const otpEl = document.querySelector(`[data-stamp-otp="${teacher.teacher_user_id}"]`);
      const expiryEl = document.querySelector(`[data-stamp-expiry="${teacher.teacher_user_id}"]`);
      if (codeEl) codeEl.textContent = teacher.stamp_code || '-';
      if (otpEl) otpEl.textContent = teacher.current_otp || '------';
      if (expiryEl) expiryEl.textContent = `refreshing in ${teacher.expires_in || 0}s`;
    });
  } catch (err) {
    // Keep the last visible values.
  }
}

refreshTeacherStampCodes();
setInterval(refreshTeacherStampCodes, 5000);
</script>
