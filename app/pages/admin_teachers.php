<?php
require_once __DIR__ . '/../teacher_stamp.php';
require_role(['admin','principal']);
teacher_stamp_ensure_schema();
$canManageTeachers = current_user()['role'] === 'admin';
$teachers = db()->query("
  SELECT u.id user_id, u.full_name, u.email, u.is_active,
         t.salary_type, t.hourly_rate, t.fixed_salary, t.phone, t.active, t.stamp_code, t.stamp_secret
         , t.auth_app_secret
  FROM users u
  LEFT JOIN teachers t ON t.user_id=u.id
  WHERE u.role='teacher'
  ORDER BY u.full_name
")->fetchAll();
$subjects = db()->query("SELECT id, code, name FROM subjects WHERE is_active=1 ORDER BY code")->fetchAll();
$classes = db()->query("SELECT id, name FROM classes ORDER BY name")->fetchAll();
$periods = db()->query("SELECT id, label, start_time, end_time, is_teaching_period FROM periods ORDER BY sort_order")->fetchAll();
$dayLabels = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri'];

$assignRows = db()->query("
  SELECT a.teacher_user_id, a.subject_id, a.class_id, a.hours_per_week,
         s.code subject_code, s.name subject_name, c.name class_name
  FROM teacher_assignments a
  JOIN subjects s ON s.id=a.subject_id
  JOIN classes c ON c.id=a.class_id
  ORDER BY a.teacher_user_id, c.name, s.code
")->fetchAll();

$assignByTeacher = [];
foreach ($assignRows as $r) {
  $tid = (int)$r['teacher_user_id'];
  if (!isset($assignByTeacher[$tid])) $assignByTeacher[$tid] = [];
  $assignByTeacher[$tid][] = [
    'subject_id' => (int)$r['subject_id'],
    'class_id' => (int)$r['class_id'],
    'hours_per_week' => (int)$r['hours_per_week'],
    'subject_code' => $r['subject_code'],
    'subject_name' => $r['subject_name'],
    'class_name' => $r['class_name'],
  ];
}

$availabilityRows = db()->query("
  SELECT teacher_user_id, day_of_week, MAX(is_available) AS has_available
  FROM teacher_availability
  GROUP BY teacher_user_id, day_of_week
  ORDER BY teacher_user_id, day_of_week
")->fetchAll();

$availableDaysByTeacher = [];
$availabilityByTeacherSlot = [];
foreach ($availabilityRows as $row) {
  $teacherId = (int)$row['teacher_user_id'];
  if (!isset($availableDaysByTeacher[$teacherId])) {
    $availableDaysByTeacher[$teacherId] = [];
  }
  if ((int)$row['has_available'] === 1) {
    $availableDaysByTeacher[$teacherId][] = (int)$row['day_of_week'];
  }
}

$slotAvailabilityRows = db()->query("
  SELECT teacher_user_id, day_of_week, period_id, is_available
  FROM teacher_availability
  ORDER BY teacher_user_id, day_of_week, period_id
")->fetchAll();
foreach ($slotAvailabilityRows as $row) {
  $availabilityByTeacherSlot[(int)$row['teacher_user_id']][(int)$row['day_of_week'] . '-' . (int)$row['period_id']] = (int)$row['is_available'];
}
?>
<div class="card card-soft">
  <div class="card-body p-4">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <div class="h5 fw-bold mb-1">Teachers</div>
        <div class="text-muted small">Salary settings, assignments, and teacher self-stamp credentials.</div>
      </div>
      <a class="btn btn-soft btn-sm" href="<?= BASE_URL ?>/timestap.php" target="_blank" rel="noopener">
        <i class="bi bi-box-arrow-up-right me-1"></i>Open Stamp Page
      </a>
    </div>

    <div class="alert alert-info mt-3 mb-0">
      Teachers use the public stamp page with their teacher code plus their authenticator-app code. Temporary codes remain available as fallback.
    </div>

    <div class="table-responsive mt-3">
      <table class="table table-dark table-hover align-middle">
        <thead class="text-muted">
          <tr>
            <th>Teacher</th><th>Email</th><th>Teacher Code</th><th>Auth App</th><th>Temp Code</th><th>Today</th><th>Days</th><th>Salary Type</th><th>Hourly</th><th>Fixed</th><th>Phone</th><th>Subjects / Classes</th><?php if ($canManageTeachers): ?><th class="text-end">Action</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($teachers as $t): ?>
          <?php $rows = $assignByTeacher[(int)$t['user_id']] ?? []; ?>
          <?php $stampStatus = teacher_stamp_today_status((int)$t['user_id']); ?>
          <?php $teacherDays = $availableDaysByTeacher[(int)$t['user_id']] ?? [1,2,3,4,5]; ?>
          <tr>
            <td class="fw-semibold"><?= htmlspecialchars($t['full_name']) ?></td>
            <td class="text-muted"><?= htmlspecialchars($t['email']) ?></td>
            <td>
              <div class="fw-semibold" data-stamp-code="<?= (int)$t['user_id'] ?>"><?= htmlspecialchars($t['stamp_code'] ?? '-') ?></div>
            </td>
            <td>
              <div class="fw-bold" data-auth-app-status="<?= (int)$t['user_id'] ?>"><?= !empty($t['auth_app_secret']) ? 'Configured' : 'Not Set' ?></div>
              <div class="text-muted small" data-auth-app-info="<?= (int)$t['user_id'] ?>"><?= !empty($t['auth_app_secret']) ? 'Manage on Stamp Desk' : 'Setup on Stamp Desk' ?></div>
            </td>
            <td class="small">
              <div class="fw-bold" data-temp-code="<?= (int)$t['user_id'] ?>">-</div>
              <div class="text-muted" data-temp-expiry="<?= (int)$t['user_id'] ?>">No temp code</div>
              <?php if ($canManageTeachers): ?>
                <button class="btn btn-sm btn-soft mt-2" type="button" data-action="issue_temp_code" data-api="<?= BASE_URL ?>/app/api/teacher_stamp_temp_code_create.php" data-teacher-user-id="<?= (int)$t['user_id'] ?>">
                  Temp Code
                </button>
              <?php endif; ?>
            </td>
            <td class="small">
              <div class="text-muted">In: <?= htmlspecialchars($stampStatus['arrived_at'] ?? '-') ?></div>
              <div class="text-muted">Out: <?= htmlspecialchars($stampStatus['departed_at'] ?? '-') ?></div>
            </td>
            <td class="small text-muted">
              <?= htmlspecialchars(implode(', ', array_map(static fn($day) => $dayLabels[$day] ?? (string)$day, $teacherDays))) ?>
            </td>
            <td><span class="badge text-bg-secondary"><?= htmlspecialchars($t['salary_type'] ?? 'hourly') ?></span></td>
            <td><?= htmlspecialchars($t['hourly_rate'] ?? '') ?></td>
            <td><?= htmlspecialchars($t['fixed_salary'] ?? '') ?></td>
            <td class="text-muted"><?= htmlspecialchars($t['phone'] ?? '') ?></td>
            <td>
              <?php if (!$rows): ?>
                <span class="text-muted small">No assignments</span>
              <?php else: ?>
                <div class="d-grid gap-1">
                  <?php foreach ($rows as $a): ?>
                    <div class="small"><b><?= htmlspecialchars($a['subject_code']) ?></b> in <?= htmlspecialchars($a['class_name']) ?> (<?= (int)$a['hours_per_week'] ?>/wk)</div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </td>
            <?php if ($canManageTeachers): ?>
            <td class="text-end">
              <div class="d-flex gap-2 justify-content-end flex-wrap">
                <button class="btn btn-sm btn-soft" data-bs-toggle="modal" data-bs-target="#modalTeacher"
                  data-user_id="<?= (int)$t['user_id'] ?>"
                  data-full_name="<?= htmlspecialchars($t['full_name'], ENT_QUOTES) ?>"
                  data-salary_type="<?= htmlspecialchars($t['salary_type'] ?? 'hourly', ENT_QUOTES) ?>"
                  data-hourly_rate="<?= htmlspecialchars($t['hourly_rate'] ?? '', ENT_QUOTES) ?>"
                  data-fixed_salary="<?= htmlspecialchars($t['fixed_salary'] ?? '', ENT_QUOTES) ?>"
                  data-phone="<?= htmlspecialchars($t['phone'] ?? '', ENT_QUOTES) ?>">
                  <i class="bi bi-sliders me-1"></i>Salary
                </button>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalTeacherAssign"
                  data-user_id="<?= (int)$t['user_id'] ?>"
                  data-full_name="<?= htmlspecialchars($t['full_name'], ENT_QUOTES) ?>"
                  data-assignments="<?= htmlspecialchars(json_encode($rows), ENT_QUOTES) ?>"
                  data-available_days="<?= htmlspecialchars(json_encode($teacherDays), ENT_QUOTES) ?>"
                  data-availability_slots="<?= htmlspecialchars(json_encode($availabilityByTeacherSlot[(int)$t['user_id']] ?? new stdClass()), ENT_QUOTES) ?>">
                  <i class="bi bi-diagram-3 me-1"></i>Assign Subjects
                </button>
              </div>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if ($canManageTeachers): ?>
<div class="modal fade" id="modalTeacher" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Teacher Salary Settings</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form class="modal-body" data-api="<?= BASE_URL ?>/app/api/teacher_upsert.php">
        <input type="hidden" name="user_id" id="tUserId">
        <div class="row g-3">
          <div class="col-12">
            <div class="fw-semibold" id="tName"></div>
            <div class="text-muted small">Salary is calculated from <b>validated</b> attendance.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Salary type</label>
            <select class="form-select" name="salary_type" id="tSalaryType">
              <option value="hourly">Hourly</option>
              <option value="fixed">Fixed</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Phone</label>
            <input class="form-control" name="phone" id="tPhone" placeholder="+237 ...">
          </div>
          <div class="col-md-6">
            <label class="form-label">Hourly rate</label>
            <input class="form-control" name="hourly_rate" id="tHourly" type="number" step="0.01" placeholder="e.g. 1500">
          </div>
          <div class="col-md-6">
            <label class="form-label">Fixed salary (monthly)</label>
            <input class="form-control" name="fixed_salary" id="tFixed" type="number" step="0.01" placeholder="e.g. 200000">
          </div>
        </div>
        <div class="modal-footer px-0 pb-0">
          <button class="btn btn-soft" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" type="submit">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalTeacherAssign" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Assign Subjects & Classes</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form class="modal-body" id="teacherAssignForm">
        <input type="hidden" id="asTeacherId">
        <div class="fw-semibold" id="asTeacherName"></div>
        <div class="text-muted small mb-3">Set subject/class assignments and the exact periods this teacher is available.</div>

        <div class="card card-soft mb-3">
          <div class="card-body">
            <div class="fw-semibold mb-2">Availability By Day And Period</div>
            <div class="text-muted small mb-3">Check only the periods when this teacher is actually present in school.</div>
            <div class="table-responsive">
              <table class="table table-dark table-bordered align-middle mb-0">
                <thead class="text-muted">
                  <tr>
                    <th>Period</th>
                    <?php foreach ($dayLabels as $dayNumber => $dayLabel): ?>
                      <th class="text-center"><?= htmlspecialchars($dayLabel) ?></th>
                    <?php endforeach; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($periods as $period): ?>
                    <tr>
                      <td class="fw-semibold">
                        <?= htmlspecialchars($period['label']) ?>
                        <div class="text-muted small"><?= htmlspecialchars($period['start_time']) ?>-<?= htmlspecialchars($period['end_time']) ?></div>
                      </td>
                      <?php foreach ($dayLabels as $dayNumber => $dayLabel): ?>
                        <?php $disabled = ((int)$period['is_teaching_period'] !== 1) ? 'disabled' : ''; ?>
                        <td class="text-center">
                          <div class="form-check form-switch d-flex justify-content-center">
                            <input class="form-check-input" type="checkbox" data-availability-slot="<?= (int)$dayNumber ?>-<?= (int)$period['id'] ?>" <?= $disabled ?>>
                          </div>
                        </td>
                      <?php endforeach; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-dark table-hover align-middle" id="assignEditTable">
            <thead class="text-muted">
              <tr><th>Subject</th><th>Class</th><th>Hours/Week</th><th class="text-end">Remove</th></tr>
            </thead>
            <tbody id="assignEditBody"></tbody>
          </table>
        </div>

        <button class="btn btn-soft" type="button" id="btnAddAssignRow"><i class="bi bi-plus-lg me-1"></i>Add Row</button>

        <div class="modal-footer px-0 pb-0">
          <button class="btn btn-soft" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" type="submit">Save Assignments</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
const canManageTeachers = <?= $canManageTeachers ? 'true' : 'false' ?>;
if (canManageTeachers) {
  const m = document.getElementById('modalTeacher');
  m?.addEventListener('show.bs.modal', (e)=>{
    const b = e.relatedTarget;
    document.getElementById('tUserId').value = b.dataset.user_id;
    document.getElementById('tName').textContent = b.dataset.full_name;
    document.getElementById('tSalaryType').value = b.dataset.salary_type || 'hourly';
    document.getElementById('tHourly').value = b.dataset.hourly_rate || '';
    document.getElementById('tFixed').value = b.dataset.fixed_salary || '';
    document.getElementById('tPhone').value = b.dataset.phone || '';
  });
}

const subjectOptions = <?= json_encode(array_map(static fn($s) => ['id'=>(int)$s['id'],'label'=>$s['code'].' - '.$s['name']], $subjects)) ?>;
const classOptions = <?= json_encode(array_map(static fn($c) => ['id'=>(int)$c['id'],'label'=>$c['name']], $classes)) ?>;
const teachingPeriodIds = <?= json_encode(array_values(array_map(static fn($p) => (int)$p['id'], array_values(array_filter($periods, static fn($p) => (int)$p['is_teaching_period'] === 1))))) ?>;

function optionHtml(options, selectedId){
  return options.map(o => `<option value="${o.id}" ${String(o.id)===String(selectedId) ? 'selected' : ''}>${o.label}</option>`).join('');
}

function buildAssignRow(item = {subject_id:'', class_id:'', hours_per_week:2}){
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><select class="form-select" data-field="subject_id" required>
      <option value="">Select subject</option>${optionHtml(subjectOptions, item.subject_id)}
    </select></td>
    <td><select class="form-select" data-field="class_id" required>
      <option value="">Select class</option>${optionHtml(classOptions, item.class_id)}
    </select></td>
    <td><input class="form-control" type="number" min="1" data-field="hours_per_week" value="${item.hours_per_week || 2}" required></td>
    <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-row="1">Remove</button></td>
  `;
  tr.querySelector('[data-remove-row]')?.addEventListener('click', ()=> tr.remove());
  return tr;
}

function setAvailabilitySlots(slots){
  const map = (slots && typeof slots === 'object') ? slots : {};
  document.querySelectorAll('[data-availability-slot]').forEach((checkbox) => {
    const key = checkbox.dataset.availabilitySlot;
    const [dayStr, periodStr] = key.split('-');
    const periodId = Number(periodStr);
    if (!teachingPeriodIds.includes(periodId)) {
      checkbox.checked = false;
      return;
    }
    checkbox.checked = Object.prototype.hasOwnProperty.call(map, key) ? String(map[key]) === '1' : true;
  });
}

if (canManageTeachers) {
  const assignModal = document.getElementById('modalTeacherAssign');
  assignModal?.addEventListener('show.bs.modal', (e)=>{
    const b = e.relatedTarget;
    document.getElementById('asTeacherId').value = b.dataset.user_id;
    document.getElementById('asTeacherName').textContent = b.dataset.full_name;

    const body = document.getElementById('assignEditBody');
    body.innerHTML = '';
    let rows = [];
    try { rows = JSON.parse(b.dataset.assignments || '[]'); } catch(_) { rows = []; }
    let availabilitySlots = {};
    try { availabilitySlots = JSON.parse(b.dataset.availability_slots || '{}'); } catch(_) { availabilitySlots = {}; }
    setAvailabilitySlots(availabilitySlots);
    if (!Array.isArray(rows) || rows.length === 0) rows = [{subject_id:'', class_id:'', hours_per_week:2}];
    rows.forEach(r => body.appendChild(buildAssignRow(r)));
  });

  document.getElementById('btnAddAssignRow')?.addEventListener('click', ()=>{
    document.getElementById('assignEditBody')?.appendChild(buildAssignRow());
  });

  document.getElementById('teacherAssignForm')?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const teacherId = Number(document.getElementById('asTeacherId').value || 0);
    const rows = [...document.querySelectorAll('#assignEditBody tr')];
    const availabilitySlots = [...document.querySelectorAll('[data-availability-slot]:checked')].map((el) => String(el.dataset.availabilitySlot || ''));
    const assignments = rows.map(tr => ({
      subject_id: Number(tr.querySelector('[data-field="subject_id"]').value || 0),
      class_id: Number(tr.querySelector('[data-field="class_id"]').value || 0),
      hours_per_week: Number(tr.querySelector('[data-field="hours_per_week"]').value || 0),
    })).filter(x => x.subject_id > 0 && x.class_id > 0 && x.hours_per_week > 0);

    try{
      await api("<?= BASE_URL ?>/app/api/teacher_assignments_bulk_save.php", {
        method: 'POST',
        body: JSON.stringify({ teacher_user_id: teacherId, assignments, availability_slots: availabilitySlots })
      });
      toast('Teacher assignments saved', 'success');
      window.location.reload();
    }catch(err){
      toast('Error: ' + err.message, 'error');
    }
  });
}

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
      if (authAppInfoEl) authAppInfoEl.textContent = teacher.auth_app_enabled ? 'Manage on Stamp Desk' : 'Setup on Stamp Desk';
      if (tempCodeEl) tempCodeEl.textContent = teacher.temp_code || '-';
      if (tempExpiryEl) tempExpiryEl.textContent = teacher.temp_code ? `Temp ${teacher.temp_code_expires_in || 0}s` : 'No temp code';
    });
  } catch (err) {
    // Leave the last shown code in place; a failed refresh is non-blocking.
  }
}

refreshTeacherStampCodes();
setInterval(refreshTeacherStampCodes, 5000);

document.addEventListener('click', async (e) => {
  const btn = e.target.closest('[data-action="issue_temp_code"]');
  if (!btn) return;

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
});
</script>
