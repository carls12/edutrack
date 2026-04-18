<?php
$u = current_user();
$role = $u['role'];

require_once __DIR__ . '/../timetable_helpers.php';

if ($role === 'admin') {
  $subjects = db()->query("SELECT id, code, name FROM subjects WHERE is_active=1 ORDER BY code")->fetchAll();
  $teachers = db()->query("SELECT id, full_name FROM users WHERE role='teacher' AND is_active=1 ORDER BY full_name")->fetchAll();
}

$classes = [];
if ($role === 'prefect') {
  $prefectClasses = db()->prepare("SELECT id, name FROM classes WHERE prefect_user_id=? ORDER BY name");
  $prefectClasses->execute([(int)$u['id']]);
  $classes = $prefectClasses->fetchAll();
} elseif ($role === 'teacher') {
  $teacherClasses = db()->prepare("
    SELECT DISTINCT c.id, c.name
    FROM classes c
    JOIN teacher_assignments a ON a.class_id = c.id
    WHERE a.teacher_user_id = ?
    ORDER BY c.name
  ");
  $teacherClasses->execute([(int)$u['id']]);
  $classes = $teacherClasses->fetchAll();
} else {
  $classes = db()->query("SELECT id, name FROM classes ORDER BY name")->fetchAll();
}
$classId = (int)($_GET['class_id'] ?? ($classes[0]['id'] ?? 0));
$allowedClassIds = array_map(static fn($x) => (int)$x['id'], $classes);
if (!in_array($classId, $allowedClassIds, true)) {
  $classId = (int)($classes[0]['id'] ?? 0);
}
$className = '';
foreach ($classes as $c) {
  if ((int)$c['id'] === $classId) { $className = (string)$c['name']; break; }
}

$settings = timetable_school_settings();
$logo = $settings['logo_path'] ? (BASE_URL . '/' . ltrim((string)$settings['logo_path'], '/')) : (BASE_URL . '/assets/img/logo.svg');

$days = timetable_days();
$periods = timetable_periods();

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
$signatureSlots = (int)(db()->query("SELECT signature_slots FROM timetable_signature_settings WHERE id=1")->fetch()['signature_slots'] ?? 2);
$signatureSlots = max(1, min(10, $signatureSlots));
$signatories = db()->query("SELECT name, title FROM timetable_signatories WHERE is_active=1 ORDER BY sort_order, id")->fetchAll();

$entries = $classId ? timetable_fetch_class_entry_map($classId) : [];
?>
<div class="card card-soft">
  <div class="card-body p-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>
        <div class="h5 fw-bold mb-1">Timetable</div>
        <div class="text-muted small">Format: Day x Periods (e.g. Monday | P1 | P2 | P3 | P4 | P5).</div>
      </div>
      <div class="d-flex gap-2 align-items-center">
        <form method="get" action="<?= BASE_URL ?>/index.php" class="d-flex gap-2 align-items-center">
          <input type="hidden" name="page" value="timetable">
          <select class="form-select" name="class_id" onchange="this.form.submit()">
            <?php foreach($classes as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= ($classId===(int)$c['id'])?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <?php if ($role === 'admin'): ?>
          <button class="btn btn-primary" data-action="generate_timetable" data-api="<?= BASE_URL ?>/app/api/timetable_generate.php">
            <i class="bi bi-magic me-1"></i>Auto-generate
          </button>
        <?php endif; ?>
        <?php if ($classId): ?>
          <a class="btn btn-soft" href="<?= BASE_URL ?>/app/api/export_timetable_csv.php?class_id=<?= (int)$classId ?>">
            <i class="bi bi-filetype-csv me-1"></i>Download CSV
          </a>
          <a class="btn btn-soft" href="<?= BASE_URL ?>/app/api/export_timetable_pdf.php?class_id=<?= (int)$classId ?>">
            <i class="bi bi-filetype-pdf me-1"></i>Download PDF
          </a>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!$classId): ?>
      <div class="alert alert-warning mt-3">No classes created yet.</div>
    <?php else: ?>
      <div class="d-flex align-items-center gap-3 mt-3 p-3 rounded-4" style="border:1px solid rgba(148,163,184,.25); background:rgba(148,163,184,.08);">
        <img src="<?= htmlspecialchars($logo) ?>" alt="logo" style="width:56px;height:56px;border-radius:16px;">
        <div>
          <div class="fw-bold"><?= htmlspecialchars($settings['school_name'] ?? APP_NAME) ?></div>
          <div class="text-muted small">Class: <?= htmlspecialchars($className) ?></div>
        </div>
      </div>

      <div class="table-responsive mt-3">
        <table class="table table-dark table-bordered align-middle">
          <thead class="text-muted">
            <tr>
              <th style="width:170px;">Day</th>
              <?php foreach($periods as $p): ?>
                <th class="text-center">
                  <?= htmlspecialchars($p['label']) ?>
                  <div class="small text-muted"><?= htmlspecialchars($p['start_time']) ?>-<?= htmlspecialchars($p['end_time']) ?></div>
                </th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach($days as $dow=>$dayLabel): ?>
              <tr>
                <td class="fw-semibold"><?= htmlspecialchars($dayLabel) ?></td>
                <?php foreach($periods as $p):
                  $k = $dow.'-'.$p['id'];
                  $e = $entries[$k] ?? null;
                  $isBreak = ((int)$p['is_teaching_period']===0);
                ?>
                  <td class="text-center <?= $isBreak ? 'text-muted' : '' ?>" style="min-width:170px;">
                    <?php if ($isBreak): ?>
                      <span class="badge text-bg-warning">BREAK</span>
                    <?php elseif ($role === 'admin'): ?>
                      <button class="btn btn-sm btn-soft w-100"
                        data-bs-toggle="modal"
                        data-bs-target="#modalSlot"
                        data-class_id="<?= (int)$classId ?>"
                        data-day="<?= (int)$dow ?>"
                        data-period_id="<?= (int)$p['id'] ?>"
                        data-subject_id="<?= (int)($e['subject_id'] ?? 0) ?>"
                        data-teacher_id="<?= (int)($e['teacher_user_id'] ?? 0) ?>"
                        data-locked="<?= (int)($e['is_locked'] ?? 0) ?>">
                        <?php if (!$e): ?>
                          <span class="text-muted small">Assign</span>
                        <?php elseif (!empty($e['is_paired'])): ?>
                          <span class="badge text-bg-info mb-1" style="font-size:.65rem;">Parallel</span>
                          <div class="fw-semibold" style="font-size:.8rem;"><?= htmlspecialchars($e['subject_code']) ?></div>
                          <div class="text-muted small" style="font-size:.72rem;"><?= htmlspecialchars($e['teacher_name']) ?></div>
                        <?php else: ?>
                          <?php if (!empty($e['is_practical'])): ?>
                            <span class="badge text-bg-info mb-1" style="font-size:.65rem;">Lab</span>
                          <?php endif; ?>
                          <div class="fw-semibold"><?= htmlspecialchars($e['subject_code']) ?></div>
                          <div class="text-muted small"><?= htmlspecialchars($e['teacher_name']) ?></div>
                          <?php if ((int)($e['is_locked'] ?? 0) === 1): ?>
                            <span class="badge text-bg-warning mt-1">Locked</span>
                          <?php endif; ?>
                        <?php endif; ?>
                      </button>
                    <?php elseif (!$e): ?>
                      <span class="text-muted small">-</span>
                    <?php elseif (!empty($e['is_paired'])): ?>
                      <span class="badge text-bg-info mb-1" style="font-size:.65rem;">Parallel</span>
                      <div class="fw-semibold" style="font-size:.8rem;"><?= htmlspecialchars($e['subject_code']) ?></div>
                      <div class="text-muted small" style="font-size:.72rem;"><?= htmlspecialchars($e['teacher_name']) ?></div>
                    <?php else: ?>
                      <?php if (!empty($e['is_practical'])): ?>
                        <span class="badge text-bg-info mb-1" style="font-size:.65rem;">Lab</span>
                      <?php endif; ?>
                      <div class="fw-semibold"><?= htmlspecialchars($e['subject_code']) ?></div>
                      <div class="text-muted small"><?= htmlspecialchars($e['teacher_name']) ?></div>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="mt-3 d-flex flex-wrap gap-3">
        <?php for ($i = 0; $i < $signatureSlots; $i++): $sig = $signatories[$i] ?? null; ?>
          <div class="p-3 rounded-3" style="min-width:220px; background:rgba(148,163,184,.06);">
            <div style="border-top:1px solid rgba(148,163,184,.55); margin-top:8px; margin-bottom:8px;"></div>
            <div class="fw-semibold"><?= htmlspecialchars((string)($sig['name'] ?? '')) ?></div>
            <div class="text-muted small"><?= htmlspecialchars((string)($sig['title'] ?? 'Signatory')) ?></div>
          </div>
        <?php endfor; ?>
      </div>

      <?php if ($role === 'admin'): ?>
        <div class="alert alert-secondary mt-3 mb-0">
          Auto-generate rules: teacher availability · hours/week per assignment · max 1 lesson per subject per day · even spread across weekdays · paired subjects share the same slot (shown as <span class="badge text-bg-info" style="font-size:.7rem;">Parallel</span>).
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php if ($role === 'admin' && $classId): ?>
<div class="modal fade" id="modalSlot" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Edit Timetable Slot</h5>
        <button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form class="modal-body" data-api="<?= BASE_URL ?>/app/api/timetable_slot_save.php">
        <input type="hidden" name="class_id" id="mClass">
        <input type="hidden" name="day_of_week" id="mDay">
        <input type="hidden" name="period_id" id="mPeriod">

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Subject</label>
            <select class="form-select" name="subject_id" id="mSubject" required>
              <?php foreach($subjects as $s): ?>
                <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['code']) ?> - <?= htmlspecialchars($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Teacher</label>
            <select class="form-select" name="teacher_user_id" id="mTeacher" required>
              <?php foreach($teachers as $t): ?>
                <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Lock slot (protected from auto-generate)</label>
            <select class="form-select" name="is_locked" id="mLocked">
              <option value="1">Yes (Locked)</option>
              <option value="0">No</option>
            </select>
          </div>

          <div class="col-12">
            <div class="alert alert-secondary mb-0">
              Locked slots are not overwritten by auto-generate.
            </div>
          </div>
        </div>

        <div class="modal-footer px-0 pb-0">
          <button type="button" class="btn btn-outline-danger" id="btnClearSlot">Clear Slot</button>
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const slotModal = document.getElementById('modalSlot');
slotModal?.addEventListener('show.bs.modal', (e) => {
  const b = e.relatedTarget;
  document.getElementById('mClass').value = b.dataset.class_id;
  document.getElementById('mDay').value = b.dataset.day;
  document.getElementById('mPeriod').value = b.dataset.period_id;

  const subj = b.dataset.subject_id || "0";
  const teacher = b.dataset.teacher_id || "0";
  document.getElementById('mLocked').value = (b.dataset.locked || "1");

  if (subj !== "0") document.getElementById('mSubject').value = subj;
  if (teacher !== "0") document.getElementById('mTeacher').value = teacher;
});

document.getElementById('btnClearSlot')?.addEventListener('click', async () => {
  try{
    await api("<?= BASE_URL ?>/app/api/timetable_slot_clear.php", {
      method: "POST",
      body: JSON.stringify({
        class_id: document.getElementById('mClass').value,
        day_of_week: document.getElementById('mDay').value,
        period_id: document.getElementById('mPeriod').value
      })
    });
    toast("Slot cleared", "success");
    window.location.reload();
  }catch(err){
    toast("Error: " + err.message, "error");
  }
});
</script>
<?php endif; ?>
