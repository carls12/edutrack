<?php
require_role(['admin']);

$teachers = db()->query("SELECT id, full_name FROM users WHERE role='teacher' AND is_active=1 ORDER BY full_name")->fetchAll();
$periods  = db()->query("SELECT * FROM periods ORDER BY sort_order")->fetchAll();

$teacherId = (int)($_GET['teacher_id'] ?? ($teachers[0]['id'] ?? 0));
$days      = [1=>'Mon', 2=>'Tue', 3=>'Wed', 4=>'Thu', 5=>'Fri'];

$existing = [];
if ($teacherId) {
  $stmt = db()->prepare("SELECT day_of_week, period_id, is_available FROM teacher_availability WHERE teacher_user_id=?");
  $stmt->execute([$teacherId]);
  foreach ($stmt->fetchAll() as $r) {
    $existing[$r['day_of_week'] . '-' . $r['period_id']] = (int)$r['is_available'];
  }
}

// Count total available teaching slots for the summary badge
$teachingPeriods = array_filter($periods, fn($p) => (int)$p['is_teaching_period'] === 1);
$totalSlots = count($days) * count($teachingPeriods);
$blockedSlots = 0;
foreach ($teachingPeriods as $p) {
  foreach ($days as $dow => $label) {
    $val = $existing[$dow . '-' . $p['id']] ?? 1;
    if ($val === 0) $blockedSlots++;
  }
}
$availableSlots = $totalSlots - $blockedSlots;
?>

<div class="card card-soft">
  <div class="card-body p-4">

    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
      <div>
        <div class="h5 fw-bold mb-1">Teacher Availability</div>
        <div class="text-muted small">Click any cell to toggle. Green = available, red = blocked. Used by the timetable generator.</div>
      </div>
      <form class="d-flex gap-2 align-items-center" method="get" action="<?= BASE_URL ?>/index.php">
        <input type="hidden" name="page" value="admin_availability">
        <select class="form-select" name="teacher_id" onchange="this.form.submit()" style="min-width:220px;">
          <?php foreach ($teachers as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= ($teacherId === (int)$t['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($t['full_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <?php if (!$teacherId): ?>
      <div class="alert alert-warning">No teachers found. Create teacher users first.</div>
    <?php else: ?>

      <!-- Summary bar -->
      <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
        <span class="badge text-bg-success fs-6 px-3 py-2">
          <i class="bi bi-check-circle me-1"></i><?= $availableSlots ?> available slots / week
        </span>
        <?php if ($blockedSlots > 0): ?>
          <span class="badge text-bg-danger fs-6 px-3 py-2">
            <i class="bi bi-x-circle me-1"></i><?= $blockedSlots ?> blocked
          </span>
        <?php endif; ?>
        <div class="ms-auto d-flex gap-2">
          <button type="button" class="btn btn-sm btn-outline-success" onclick="setAll(1)">
            <i class="bi bi-check2-all me-1"></i>All available
          </button>
          <button type="button" class="btn btn-sm btn-outline-danger" onclick="setAll(0)">
            <i class="bi bi-x-lg me-1"></i>Block all
          </button>
        </div>
      </div>

      <form id="availForm" data-api="<?= BASE_URL ?>/app/api/availability_save.php">
        <input type="hidden" name="teacher_user_id" value="<?= (int)$teacherId ?>">

        <div class="table-responsive">
          <table class="table table-dark table-bordered align-middle text-center" id="availTable">
            <thead>
              <tr>
                <th class="text-start" style="min-width:130px;">Period</th>
                <?php foreach ($days as $dow => $label): ?>
                  <th style="min-width:90px;">
                    <div><?= $label ?></div>
                    <div class="d-flex gap-1 justify-content-center mt-1">
                      <button type="button" class="btn btn-xs px-1 py-0" style="font-size:.65rem;background:rgba(34,197,94,.18);color:#4ade80;border:1px solid rgba(34,197,94,.3);"
                        onclick="setCol(<?= $dow ?>, 1)" title="Unblock <?= $label ?>">✓</button>
                      <button type="button" class="btn btn-xs px-1 py-0" style="font-size:.65rem;background:rgba(239,68,68,.18);color:#f87171;border:1px solid rgba(239,68,68,.3);"
                        onclick="setCol(<?= $dow ?>, 0)" title="Block <?= $label ?>">✕</button>
                    </div>
                  </th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($periods as $p):
                $isBreak = (int)$p['is_teaching_period'] === 0;
              ?>
                <tr>
                  <td class="text-start">
                    <span class="fw-semibold"><?= htmlspecialchars($p['label']) ?></span>
                    <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars(substr($p['start_time'], 0, 5)) ?>–<?= htmlspecialchars(substr($p['end_time'], 0, 5)) ?></div>
                    <?php if (!$isBreak): ?>
                      <div class="d-flex gap-1 mt-1">
                        <button type="button" class="btn btn-xs px-1 py-0" style="font-size:.65rem;background:rgba(34,197,94,.18);color:#4ade80;border:1px solid rgba(34,197,94,.3);"
                          onclick="setRow(<?= (int)$p['id'] ?>, 1)" title="Unblock row">✓ row</button>
                        <button type="button" class="btn btn-xs px-1 py-0" style="font-size:.65rem;background:rgba(239,68,68,.18);color:#f87171;border:1px solid rgba(239,68,68,.3);"
                          onclick="setRow(<?= (int)$p['id'] ?>, 0)" title="Block row">✕ row</button>
                      </div>
                    <?php endif; ?>
                  </td>

                  <?php foreach ($days as $dow => $label):
                    if ($isBreak): ?>
                      <td><span class="badge text-bg-warning">Break</span></td>
                    <?php else:
                      $val     = $existing[$dow . '-' . $p['id']] ?? 1;
                      $inputId = "slot_{$dow}_{$p['id']}";
                    ?>
                      <td class="p-1">
                        <input type="hidden" name="<?= $inputId ?>" id="<?= $inputId ?>" value="<?= $val ?>">
                        <button type="button"
                          class="avail-cell w-100 rounded-3 border-0 py-2 px-1"
                          data-input="<?= $inputId ?>"
                          data-dow="<?= $dow ?>"
                          data-pid="<?= (int)$p['id'] ?>"
                          style="cursor:pointer;transition:background .15s;">
                        </button>
                      </td>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
          <div class="text-muted small"><i class="bi bi-info-circle me-1"></i>Changes are not saved until you click Save.</div>
          <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Save availability</button>
        </div>
      </form>

      <!-- Legend -->
      <div class="d-flex gap-3 mt-3 flex-wrap">
        <div class="d-flex align-items-center gap-2">
          <div style="width:24px;height:24px;border-radius:6px;background:rgba(34,197,94,.25);border:1px solid rgba(34,197,94,.5);"></div>
          <span class="text-muted small">Available</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <div style="width:24px;height:24px;border-radius:6px;background:rgba(239,68,68,.25);border:1px solid rgba(239,68,68,.5);"></div>
          <span class="text-muted small">Blocked</span>
        </div>
      </div>

    <?php endif; ?>
  </div>
</div>

<style>
.avail-cell[data-val="1"] { background: rgba(34,197,94,.22); border: 1px solid rgba(34,197,94,.45) !important; }
.avail-cell[data-val="0"] { background: rgba(239,68,68,.22); border: 1px solid rgba(239,68,68,.45) !important; }
.avail-cell[data-val="1"]:hover { background: rgba(34,197,94,.38); }
.avail-cell[data-val="0"]:hover { background: rgba(239,68,68,.38); }
#availSummaryAvail, #availSummaryBlocked { transition: all .2s; }
</style>

<script>
// Initialise cell colours from hidden inputs
document.querySelectorAll('.avail-cell').forEach(btn => {
  const inp = document.getElementById(btn.dataset.input);
  btn.setAttribute('data-val', inp.value);
  btn.title = inp.value === '1' ? 'Available – click to block' : 'Blocked – click to unblock';
});

// Toggle on click
document.querySelectorAll('.avail-cell').forEach(btn => {
  btn.addEventListener('click', () => toggle(btn));
});

function toggle(btn) {
  const inp = document.getElementById(btn.dataset.input);
  const newVal = inp.value === '1' ? '0' : '1';
  inp.value = newVal;
  btn.setAttribute('data-val', newVal);
  btn.title = newVal === '1' ? 'Available – click to block' : 'Blocked – click to unblock';
  updateSummary();
}

function setAll(val) {
  document.querySelectorAll('.avail-cell').forEach(btn => {
    const inp = document.getElementById(btn.dataset.input);
    inp.value = val;
    btn.setAttribute('data-val', String(val));
  });
  updateSummary();
}

function setCol(dow, val) {
  document.querySelectorAll(`.avail-cell[data-dow="${dow}"]`).forEach(btn => {
    const inp = document.getElementById(btn.dataset.input);
    inp.value = val;
    btn.setAttribute('data-val', String(val));
  });
  updateSummary();
}

function setRow(pid, val) {
  document.querySelectorAll(`.avail-cell[data-pid="${pid}"]`).forEach(btn => {
    const inp = document.getElementById(btn.dataset.input);
    inp.value = val;
    btn.setAttribute('data-val', String(val));
  });
  updateSummary();
}

function updateSummary() {
  const all     = document.querySelectorAll('.avail-cell').length;
  const blocked = document.querySelectorAll('.avail-cell[data-val="0"]').length;
  const avail   = all - blocked;
  const bAvail   = document.querySelector('.badge.text-bg-success');
  const bBlocked = document.querySelector('.badge.text-bg-danger');
  if (bAvail)   bAvail.innerHTML   = `<i class="bi bi-check-circle me-1"></i>${avail} available slots / week`;
  if (bBlocked) bBlocked.innerHTML = `<i class="bi bi-x-circle me-1"></i>${blocked} blocked`;
}
</script>
