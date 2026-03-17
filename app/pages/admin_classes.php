<?php
require_role(['admin']);
$teachers = db()->query("SELECT id, full_name FROM users WHERE role='teacher' AND is_active=1 ORDER BY full_name")->fetchAll();
$rows = db()->query("
  SELECT c.*, u.full_name class_master_name
  FROM classes c
  LEFT JOIN users u ON u.id = c.class_master_user_id
  ORDER BY c.name
")->fetchAll();
?>
<div class="card card-soft">
  <div class="card-body p-4">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <div class="h5 fw-bold mb-1">Classes</div>
        <div class="text-muted small">Manage classes (grade level, room), class master, and prefect assignment.</div>
      </div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalClassCreate"><i class="bi bi-plus-lg me-1"></i>Add class</button>
    </div>

    <div class="table-responsive mt-3">
      <table class="table table-dark table-hover align-middle">
        <thead class="text-muted">
          <tr><th>Name</th><th>Grade</th><th>Room</th><th>Class Master</th><th class="text-end">Action</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td class="fw-semibold"><?= htmlspecialchars($r['name']) ?></td>
            <td class="text-muted"><?= htmlspecialchars($r['grade_level'] ?? '') ?></td>
            <td class="text-muted"><?= htmlspecialchars($r['room_number'] ?? '') ?></td>
            <td class="text-muted"><?= htmlspecialchars($r['class_master_name'] ?? '-') ?></td>
            <td class="text-end">
              <button class="btn btn-sm btn-soft" data-bs-toggle="modal" data-bs-target="#modalClassEdit"
                data-id="<?= (int)$r['id'] ?>"
                data-name="<?= htmlspecialchars($r['name'], ENT_QUOTES) ?>"
                data-grade_level="<?= htmlspecialchars($r['grade_level'] ?? '', ENT_QUOTES) ?>"
                data-room_number="<?= htmlspecialchars($r['room_number'] ?? '', ENT_QUOTES) ?>"
                data-class_master_user_id="<?= (int)($r['class_master_user_id'] ?? 0) ?>">
                <i class="bi bi-pencil me-1"></i>Edit
              </button>
              <button class="btn btn-sm btn-outline-danger" data-action="delete" data-api="<?= BASE_URL ?>/app/api/class_delete.php" data-id="<?= (int)$r['id'] ?>">
                <i class="bi bi-trash me-1"></i>Delete
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Create -->
<div class="modal fade" id="modalClassCreate" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Add Class</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form class="modal-body" data-api="<?= BASE_URL ?>/app/api/class_create.php">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Class name</label>
            <input class="form-control" name="name" required placeholder="e.g. Form 1A">
          </div>
          <div class="col-md-3">
            <label class="form-label">Grade</label>
            <input class="form-control" name="grade_level" placeholder="e.g. Form 1">
          </div>
          <div class="col-md-3">
            <label class="form-label">Room</label>
            <input class="form-control" name="room_number" placeholder="e.g. A1">
          </div>
          <div class="col-md-6">
            <label class="form-label">Class Master</label>
            <select class="form-select" name="class_master_user_id">
              <option value="">-</option>
              <?php foreach ($teachers as $t): ?>
                <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer px-0 pb-0">
          <button class="btn btn-soft" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" type="submit">Create</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit -->
<div class="modal fade" id="modalClassEdit" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Edit Class</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form class="modal-body" data-api="<?= BASE_URL ?>/app/api/class_update.php">
        <input type="hidden" name="id" id="cId">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Class name</label>
            <input class="form-control" name="name" id="cName" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Grade</label>
            <input class="form-control" name="grade_level" id="cGrade">
          </div>
          <div class="col-md-3">
            <label class="form-label">Room</label>
            <input class="form-control" name="room_number" id="cRoom">
          </div>
          <div class="col-md-6">
            <label class="form-label">Class Master</label>
            <select class="form-select" name="class_master_user_id" id="cMaster">
              <option value="">-</option>
              <?php foreach ($teachers as $t): ?>
                <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
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

<script>
document.getElementById('modalClassEdit')?.addEventListener('show.bs.modal', (e)=>{
  const b = e.relatedTarget;
  document.getElementById('cId').value = b.dataset.id;
  document.getElementById('cName').value = b.dataset.name;
  document.getElementById('cGrade').value = b.dataset.grade_level || '';
  document.getElementById('cRoom').value = b.dataset.room_number || '';
  document.getElementById('cMaster').value = b.dataset.class_master_user_id || '';
});
</script>
