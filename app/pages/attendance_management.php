<?php
require_once __DIR__ . '/../teacher_stamp.php';
require_role(['admin','principal']);
teacher_stamp_ensure_schema();

$month = $_GET['month'] ?? date('Y-m');
$tab = $_GET['tab'] ?? 'pending'; // pending|validated|all
$from = $month . '-01 00:00:00';
$to = date('Y-m-t', strtotime($month . '-01')) . ' 23:59:59';

$where = "WHERE a.event_time BETWEEN ? AND ?";
$params = [$from, $to];

if ($tab === 'pending') {
  $where .= " AND a.validation_status='pending'";
} elseif ($tab === 'validated') {
  $where .= " AND a.validation_status='validated'";
}

$rowsStmt = db()->prepare("
  SELECT a.*, u.full_name teacher_name, c.name class_name
  FROM attendance a
  JOIN users u ON u.id=a.teacher_user_id
  LEFT JOIN classes c ON c.id=a.class_id
  $where
  ORDER BY a.event_time DESC
  LIMIT 600
");
$rowsStmt->execute($params);
$rows = $rowsStmt->fetchAll();

$classes = db()->query("SELECT id, name FROM classes ORDER BY name")->fetchAll();
?>
<div class="card card-soft">
  <div class="card-body p-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>
        <div class="h5 fw-bold mb-1">Attendance Management</div>
        <div class="text-muted small">Edit teacher times and control validation status.</div>
      </div>
      <form method="get" action="<?= BASE_URL ?>/index.php" class="d-flex gap-2 align-items-center">
        <input type="hidden" name="page" value="attendance_management">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        <input class="form-control" type="month" name="month" value="<?= htmlspecialchars($month) ?>">
        <button class="btn btn-soft" type="submit"><i class="bi bi-funnel me-1"></i>Apply</button>
      </form>
    </div>

    <div class="nav nav-pills gap-2 mt-3">
      <a class="nav-link <?= $tab==='pending'?'active':'' ?>" href="<?= BASE_URL ?>/index.php?page=attendance_management&tab=pending&month=<?= urlencode($month) ?>">Pending</a>
      <a class="nav-link <?= $tab==='validated'?'active':'' ?>" href="<?= BASE_URL ?>/index.php?page=attendance_management&tab=validated&month=<?= urlencode($month) ?>">Validated</a>
      <a class="nav-link <?= $tab==='all'?'active':'' ?>" href="<?= BASE_URL ?>/index.php?page=attendance_management&tab=all&month=<?= urlencode($month) ?>">All</a>
    </div>

    <div class="table-responsive mt-3">
      <table class="table table-dark table-hover align-middle">
        <thead class="text-muted">
          <tr>
            <th>Teacher</th><th>Status</th><th>Source</th><th>Event Time</th><th>Class</th><th>Worked</th><th>Validation</th><th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($rows as $r): ?>
            <tr>
              <td class="fw-semibold"><?= htmlspecialchars($r['teacher_name']) ?></td>
              <td>
                <span class="badge text-bg-secondary"><?= htmlspecialchars($r['status']) ?></span>
                <?php if ($r['status'] === 'absent' && !empty($r['reason'])): ?>
                  <div class="text-muted small"><?= htmlspecialchars($r['reason']) ?></div>
                <?php endif; ?>
              </td>
              <td><span class="badge text-bg-dark"><?= htmlspecialchars($r['source'] ?? 'manual') ?></span></td>
              <td class="text-muted"><?= htmlspecialchars($r['event_time']) ?></td>
              <td class="text-muted"><?= htmlspecialchars($r['class_name'] ?? '-') ?></td>
              <td class="text-muted"><?= (int)$r['worked_minutes'] ?> min</td>
              <td>
                <?php
                  $vs = $r['validation_status'];
                  $vb = $vs==='validated'?'success':($vs==='rejected'?'danger':'warning');
                ?>
                <span class="badge text-bg-<?= $vb ?>"><?= htmlspecialchars($vs) ?></span>
              </td>
              <td class="text-end">
                <div class="d-flex gap-2 justify-content-end flex-wrap">
                  <button class="btn btn-sm btn-soft" data-bs-toggle="modal" data-bs-target="#modalEditAttendance"
                    data-id="<?= (int)$r['id'] ?>"
                    data-status="<?= htmlspecialchars($r['status'], ENT_QUOTES) ?>"
                    data-event_time="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime((string)$r['event_time'])), ENT_QUOTES) ?>"
                    data-worked_minutes="<?= (int)$r['worked_minutes'] ?>"
                    data-reason="<?= htmlspecialchars((string)($r['reason'] ?? ''), ENT_QUOTES) ?>"
                    data-class_id="<?= (int)($r['class_id'] ?? 0) ?>">
                    <i class="bi bi-pencil me-1"></i>Edit
                  </button>
                  <button class="btn btn-sm btn-success" data-action="validate_set" data-id="<?= (int)$r['id'] ?>" data-validation-status="validated">Validate</button>
                  <button class="btn btn-sm btn-outline-danger" data-action="validate_set" data-id="<?= (int)$r['id'] ?>" data-validation-status="rejected">Reject</button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?><tr><td colspan="8" class="text-muted">No records for this filter.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="modalEditAttendance" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Edit Attendance Record</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form class="modal-body" data-api="<?= BASE_URL ?>/app/api/attendance_update_admin.php">
        <input type="hidden" name="id" id="eaId">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select class="form-select" name="status" id="eaStatus" required>
              <option value="arrived">Arrived</option>
              <option value="departed">Departed</option>
              <option value="absent">Absent</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Event Time</label>
            <input class="form-control" type="datetime-local" name="event_time" id="eaEventTime" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Worked Minutes</label>
            <input class="form-control" type="number" min="0" name="worked_minutes" id="eaWorked">
            <div class="form-text">For departed entries, this is auto-calculated from arrival/departure time.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Class</label>
            <select class="form-select" name="class_id" id="eaClass">
              <option value="">-</option>
              <?php foreach($classes as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6" id="eaReasonWrap" style="display:none;">
            <label class="form-label">Absence Reason</label>
            <input class="form-control" name="reason" id="eaReason">
          </div>
        </div>
        <div class="modal-footer px-0 pb-0">
          <button class="btn btn-soft" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" type="submit">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('modalEditAttendance')?.addEventListener('show.bs.modal', (e)=>{
  const b = e.relatedTarget;
  document.getElementById('eaId').value = b.dataset.id;
  document.getElementById('eaStatus').value = b.dataset.status;
  document.getElementById('eaEventTime').value = b.dataset.event_time;
  document.getElementById('eaWorked').value = b.dataset.worked_minutes;
  document.getElementById('eaClass').value = (b.dataset.class_id && b.dataset.class_id !== '0') ? b.dataset.class_id : '';
  document.getElementById('eaReason').value = b.dataset.reason || '';
  toggleReasonField();
});

function toggleReasonField(){
  const s = document.getElementById('eaStatus');
  const wrap = document.getElementById('eaReasonWrap');
  wrap.style.display = s?.value === 'absent' ? 'block' : 'none';
}
document.getElementById('eaStatus')?.addEventListener('change', toggleReasonField);

document.addEventListener('click', async (e)=>{
  const btn = e.target.closest('[data-action=\"validate_set\"]');
  if(!btn) return;
  e.preventDefault();
  try{
    await api("<?= BASE_URL ?>/app/api/attendance_validation_set.php", {
      method: "POST",
      body: JSON.stringify({ id: btn.dataset.id, validation_status: btn.dataset.validationStatus })
    });
    toast("Validation updated", "success");
    window.location.reload();
  }catch(err){
    toast("Error: " + err.message, "error");
  }
});
</script>
