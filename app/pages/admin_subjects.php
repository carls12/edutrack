<?php
require_role(['admin']);
$rows = db()->query("SELECT id, code, name, is_active FROM subjects ORDER BY code")->fetchAll();
?>
<div class="card card-soft">
  <div class="card-body p-4">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <div class="h5 fw-bold mb-1">Subjects</div>
        <div class="text-muted small">Create subjects with unique codes (e.g., MATH, ENG).</div>
      </div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalSubjectCreate"><i class="bi bi-plus-lg me-1"></i>Add subject</button>
    </div>

    <div class="table-responsive mt-3">
      <table class="table table-dark table-hover align-middle">
        <thead class="text-muted">
          <tr><th>Code</th><th>Name</th><th>Status</th><th class="text-end">Action</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td class="fw-semibold"><?= htmlspecialchars($r['code']) ?></td>
            <td><?= htmlspecialchars($r['name']) ?></td>
            <td><?= ((int)$r['is_active']===1) ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-danger">Disabled</span>' ?></td>
            <td class="text-end">
              <button class="btn btn-sm btn-soft" data-bs-toggle="modal" data-bs-target="#modalSubjectEdit"
                data-id="<?= (int)$r['id'] ?>"
                data-code="<?= htmlspecialchars($r['code'], ENT_QUOTES) ?>"
                data-name="<?= htmlspecialchars($r['name'], ENT_QUOTES) ?>"
                data-is_active="<?= (int)$r['is_active'] ?>">
                <i class="bi bi-pencil me-1"></i>Edit
              </button>
              <button class="btn btn-sm btn-outline-danger" data-action="delete" data-api="<?= BASE_URL ?>/app/api/subject_delete.php" data-id="<?= (int)$r['id'] ?>">
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

<div class="modal fade" id="modalSubjectCreate" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Add Subject</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form class="modal-body" data-api="<?= BASE_URL ?>/app/api/subject_create.php">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Code</label>
            <input class="form-control" name="code" required placeholder="MATH">
          </div>
          <div class="col-md-8">
            <label class="form-label">Name</label>
            <input class="form-control" name="name" required placeholder="Mathematics">
          </div>
          <div class="col-md-4">
            <label class="form-label">Active</label>
            <select class="form-select" name="is_active">
              <option value="1" selected>Active</option>
              <option value="0">Disabled</option>
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

<div class="modal fade" id="modalSubjectEdit" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Edit Subject</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form class="modal-body" data-api="<?= BASE_URL ?>/app/api/subject_update.php">
        <input type="hidden" name="id" id="sId">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Code</label>
            <input class="form-control" name="code" id="sCode" required>
          </div>
          <div class="col-md-8">
            <label class="form-label">Name</label>
            <input class="form-control" name="name" id="sName" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Active</label>
            <select class="form-select" name="is_active" id="sActive">
              <option value="1">Active</option>
              <option value="0">Disabled</option>
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
document.getElementById('modalSubjectEdit')?.addEventListener('show.bs.modal', (e)=>{
  const b = e.relatedTarget;
  document.getElementById('sId').value = b.dataset.id;
  document.getElementById('sCode').value = b.dataset.code;
  document.getElementById('sName').value = b.dataset.name;
  document.getElementById('sActive').value = b.dataset.is_active;
});
</script>
