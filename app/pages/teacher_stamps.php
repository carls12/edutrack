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
    t.auth_app_secret
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
            <div class="text-muted small">Teacher code, authenticator-app setup, temporary fallback codes, and today&apos;s office stamp activity.</div>
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
          Teachers use the public stamp page with their teacher code plus their authenticator-app code. If the phone is unavailable, issue a temporary fallback code here.
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
            <th>Authenticator App</th>
            <th>Temp Code</th>
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
                <div class="fw-bold" data-auth-app-status="<?= $teacherId ?>"><?= !empty($teacher['auth_app_secret']) ? 'Configured' : 'Not Set' ?></div>
                <div class="text-muted small" data-auth-app-info="<?= $teacherId ?>"><?= !empty($teacher['auth_app_secret']) ? 'Use Reset App to re-enroll' : 'Setup required' ?></div>
                <button class="btn btn-sm btn-soft mt-2" type="button" data-action="reset_auth_app" data-api="<?= BASE_URL ?>/app/api/teacher_stamp_auth_app_reset.php" data-teacher-user-id="<?= $teacherId ?>">
                  <?= !empty($teacher['auth_app_secret']) ? 'Reset App' : 'Setup App' ?>
                </button>
              </td>
              <td>
                <div class="fw-bold" data-temp-code="<?= $teacherId ?>">-</div>
                <div class="text-muted small" data-temp-expiry="<?= $teacherId ?>">No temp code</div>
                <button class="btn btn-sm btn-soft mt-2" type="button" data-action="issue_temp_code" data-api="<?= BASE_URL ?>/app/api/teacher_stamp_temp_code_create.php" data-teacher-user-id="<?= $teacherId ?>">
                  Issue Temp Code
                </button>
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
            <tr><td colspan="11" class="text-muted">No teachers found.</td></tr>
          <?php endif; ?>
        </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="authAppSetupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Authenticator App Setup</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-4 align-items-start">
          <div class="col-lg-5 text-center">
            <div id="authQrWrap" class="p-3 rounded-4 d-inline-flex align-items-center justify-content-center" style="background:#fff; min-width:260px; min-height:260px;">
              <div class="text-muted small">QR code will appear here</div>
            </div>
          </div>
          <div class="col-lg-7">
            <div class="fw-semibold mb-1" id="authTeacherName">Teacher</div>
            <div class="text-muted small mb-3">Scan this QR code in Google Authenticator, Microsoft Authenticator, 2FAS or Aegis. Standard TOTP refresh is every 30 seconds.</div>
            <div class="mb-3">
              <label class="form-label">Secret Key</label>
              <input class="form-control" id="authSecretKey" readonly>
            </div>
            <div class="mb-3">
              <label class="form-label">OTP URI</label>
              <textarea class="form-control" id="authOtpUri" rows="4" readonly></textarea>
            </div>
            <div class="alert alert-secondary mb-0">
              If the teacher forgets the phone, use <b>Issue Temp Code</b>. The temp code stays valid for 15 minutes and is consumed after one successful use.
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-soft" type="button" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
<script>
async function refreshTeacherStampCodes() {
  try {
    const out = await api("<?= BASE_URL ?>/app/api/teacher_stamp_codes.php");
    const teachers = Array.isArray(out.teachers) ? out.teachers : [];
    teachers.forEach((teacher) => {
      const codeEl = document.querySelector(`[data-stamp-code="${teacher.teacher_user_id}"]`);
      const authAppStatusEl = document.querySelector(`[data-auth-app-status="${teacher.teacher_user_id}"]`);
      const authAppInfoEl = document.querySelector(`[data-auth-app-info="${teacher.teacher_user_id}"]`);
      const tempCodeEl = document.querySelector(`[data-temp-code="${teacher.teacher_user_id}"]`);
      const tempExpiryEl = document.querySelector(`[data-temp-expiry="${teacher.teacher_user_id}"]`);
      if (codeEl) codeEl.textContent = teacher.stamp_code || '-';
      if (authAppStatusEl) authAppStatusEl.textContent = teacher.auth_app_enabled ? 'Configured' : 'Not Set';
      if (authAppInfoEl) authAppInfoEl.textContent = teacher.auth_app_enabled ? 'Use Reset App to re-enroll' : 'Setup required';
      if (tempCodeEl) tempCodeEl.textContent = teacher.temp_code || '-';
      if (tempExpiryEl) tempExpiryEl.textContent = teacher.temp_code ? `expires in ${teacher.temp_code_expires_in || 0}s` : 'No temp code';
    });
  } catch (err) {
    // Keep the last visible values.
  }
}

refreshTeacherStampCodes();
setInterval(refreshTeacherStampCodes, 5000);

document.addEventListener('click', async (e) => {
  const btn = e.target.closest('[data-action="issue_temp_code"]');
  const resetBtn = e.target.closest('[data-action="reset_auth_app"]');

  if (btn) {
    try {
      const out = await api(btn.dataset.api, {
        method: 'POST',
        body: JSON.stringify({ teacher_user_id: Number(btn.dataset.teacherUserId || 0) })
      });
      toast(`Temp code ${out.temp_code} issued for ${out.teacher_name}`, 'success');
      refreshTeacherStampCodes();
    } catch (err) {
      toast('Error: ' + err.message, 'error');
    }
    return;
  }

  if (resetBtn) {
    try {
      const out = await api(resetBtn.dataset.api, {
        method: 'POST',
        body: JSON.stringify({ teacher_user_id: Number(resetBtn.dataset.teacherUserId || 0) })
      });
      const nameEl = document.getElementById('authTeacherName');
      const secretEl = document.getElementById('authSecretKey');
      const uriEl = document.getElementById('authOtpUri');
      const qrWrap = document.getElementById('authQrWrap');
      if (nameEl) nameEl.textContent = `${out.teacher_name} (${out.teacher_email})`;
      if (secretEl) secretEl.value = out.secret || '';
      if (uriEl) uriEl.value = out.otpauth_uri || '';
      if (qrWrap) {
        qrWrap.innerHTML = '';
        if (window.QRCode && out.otpauth_uri) {
          const canvas = document.createElement('canvas');
          qrWrap.appendChild(canvas);
          await QRCode.toCanvas(canvas, out.otpauth_uri, { width: 240, margin: 1 });
        } else {
          qrWrap.textContent = out.otpauth_uri || 'QR code could not be generated.';
        }
      }
      const modalEl = document.getElementById('authAppSetupModal');
      if (modalEl && window.bootstrap?.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
      }
      toast(`Authenticator app secret reset for ${out.teacher_name}`, 'success');
      refreshTeacherStampCodes();
    } catch (err) {
      toast('Error: ' + err.message, 'error');
    }
  }
});
</script>
