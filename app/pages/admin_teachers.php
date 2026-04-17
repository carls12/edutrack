<?php
require_once __DIR__ . '/../teacher_stamp.php';
require_role(['admin','principal']);
teacher_stamp_ensure_schema();
$canManageTeachers = current_user()['role'] === 'admin';
$teachers = db()->query("
  SELECT u.id user_id, u.full_name, u.email, u.is_active,
         t.salary_type, t.hourly_rate, t.fixed_salary, t.phone, t.active, t.stamp_code, t.stamp_secret
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
      Teachers use the public stamp page with their teacher code plus the live 6-digit security code shown here.
    </div>

    <div class="table-responsive mt-3">
      <table class="table table-dark table-hover align-middle">
        <thead class="text-muted">
          <tr>
            <th>Teacher</th><th>Email</th><th>Teacher Code</th><th>2FA (1 min)</th><th>Today</th><th>Days</th><th>Salary Type</th><th>Hourly</th><th>Fixed</th><th>Phone</th><th>Subjects / Classes</th><?php if ($canManageTeachers): ?><th class="text-end">Action</th><?php endif; ?>
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
              <div class="fw-bold" data-stamp-otp="<?= (int)$t['user_id'] ?>"><?= htmlspecialchars(($t['stamp_secret'] ?? '') !== '' ? teacher_stamp_current_otp((string)$t['stamp_secret']) : '------') ?></div>
              <div class="text-muted small" data-stamp-expiry="<?= (int)$t['user_id'] ?>">refreshing...</div>
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

        <div id="assignGroups"></div>

        <button class="btn btn-soft" type="button" id="btnAddSubjectGroup"><i class="bi bi-plus-lg me-1"></i>Add Subject</button>

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

function buildSubjectGroup(subjectId, classAssignments){
  subjectId = subjectId || '';
  classAssignments = Array.isArray(classAssignments) ? classAssignments : [];
  const assignedMap = {};
  classAssignments.forEach(a => { assignedMap[String(a.class_id)] = a.hours_per_week || 2; });

  const uid = Math.random().toString(36).slice(2);
  const classRows = classOptions.map(cls => {
    const isChecked = Object.prototype.hasOwnProperty.call(assignedMap, String(cls.id));
    const hours = isChecked ? assignedMap[String(cls.id)] : 2;
    return `<div class="col-xl-3 col-md-4 col-sm-6 col-12">
      <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:rgba(255,255,255,0.06)">
        <div class="form-check mb-0 flex-grow-1 overflow-hidden">
          <input class="form-check-input class-cb" type="checkbox"
            data-class-id="${cls.id}" id="cb_${uid}_${cls.id}" ${isChecked ? 'checked' : ''}>
          <label class="form-check-label text-truncate d-block" for="cb_${uid}_${cls.id}">${cls.label}</label>
        </div>
        <input class="form-control form-control-sm hours-inp" type="number" min="1" max="99"
          data-hours-for="${cls.id}" value="${hours}" placeholder="hrs"
          style="width:60px;${isChecked ? '' : 'display:none'}" title="Hours per week">
      </div>
    </div>`;
  }).join('');

  const group = document.createElement('div');
  group.className = 'assign-group card card-soft mb-3';
  group.innerHTML = `<div class="card-body">
    <div class="d-flex align-items-center gap-2 mb-3">
      <div class="fw-semibold text-muted small me-1">Subject</div>
      <select class="form-select" data-field="subject_id" style="max-width:300px" required>
        <option value="">Select subject…</option>${optionHtml(subjectOptions, subjectId)}
      </select>
      <button type="button" class="btn btn-sm btn-outline-danger ms-auto" data-remove-group>
        <i class="bi bi-trash"></i>
      </button>
    </div>
    <div class="text-muted small mb-2">Tick each class this teacher teaches for the selected subject, then set hours/week.</div>
    <div class="row g-2">${classRows}</div>
  </div>`;

  group.querySelector('[data-remove-group]').addEventListener('click', () => group.remove());
  group.querySelectorAll('.class-cb').forEach(cb => {
    const hoursInp = group.querySelector(`[data-hours-for="${cb.dataset.classId}"]`);
    cb.addEventListener('change', () => {
      if (hoursInp) hoursInp.style.display = cb.checked ? '' : 'none';
    });
  });
  return group;
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

    const container = document.getElementById('assignGroups');
    container.innerHTML = '';
    let rows = [];
    try { rows = JSON.parse(b.dataset.assignments || '[]'); } catch(_) { rows = []; }
    let availabilitySlots = {};
    try { availabilitySlots = JSON.parse(b.dataset.availability_slots || '{}'); } catch(_) { availabilitySlots = {}; }
    setAvailabilitySlots(availabilitySlots);

    // Group assignments by subject_id
    const subjectMap = {};
    rows.forEach(r => {
      const sid = String(r.subject_id);
      if (!subjectMap[sid]) subjectMap[sid] = [];
      subjectMap[sid].push({ class_id: r.class_id, hours_per_week: r.hours_per_week });
    });
    const keys = Object.keys(subjectMap);
    if (keys.length === 0) {
      container.appendChild(buildSubjectGroup());
    } else {
      keys.forEach(sid => container.appendChild(buildSubjectGroup(Number(sid), subjectMap[sid])));
    }
  });

  document.getElementById('btnAddSubjectGroup')?.addEventListener('click', ()=>{
    document.getElementById('assignGroups')?.appendChild(buildSubjectGroup());
  });

  document.getElementById('teacherAssignForm')?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const teacherId = Number(document.getElementById('asTeacherId').value || 0);
    const availabilitySlots = [...document.querySelectorAll('[data-availability-slot]:checked')].map((el) => String(el.dataset.availabilitySlot || ''));
    const assignments = [];
    document.querySelectorAll('.assign-group').forEach(group => {
      const subjectId = Number(group.querySelector('[data-field="subject_id"]').value || 0);
      if (subjectId <= 0) return;
      group.querySelectorAll('.class-cb:checked').forEach(cb => {
        const classId = Number(cb.dataset.classId || 0);
        const hoursInp = group.querySelector(`[data-hours-for="${classId}"]`);
        const hours = Number(hoursInp?.value || 0);
        if (classId > 0 && hours > 0) assignments.push({ subject_id: subjectId, class_id: classId, hours_per_week: hours });
      });
    });

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
      const otpEl = document.querySelector(`[data-stamp-otp="${teacher.teacher_user_id}"]`);
      const expiryEl = document.querySelector(`[data-stamp-expiry="${teacher.teacher_user_id}"]`);
      if (codeEl) codeEl.textContent = teacher.stamp_code || '-';
      if (otpEl) otpEl.textContent = teacher.current_otp || '------';
      if (expiryEl) expiryEl.textContent = `Expires in ${teacher.expires_in || 0}s`;
    });
  } catch (err) {
    // Leave the last shown code in place; a failed refresh is non-blocking.
  }
}

refreshTeacherStampCodes();
setInterval(refreshTeacherStampCodes, 5000);

</script>
