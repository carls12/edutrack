<?php
require_role(['admin']);
$teachers = db()->query("SELECT id, full_name FROM users WHERE role='teacher' ORDER BY full_name")->fetchAll();
$subjects = db()->query("SELECT id, code, name FROM subjects WHERE is_active=1 ORDER BY code")->fetchAll();
$classes  = db()->query("SELECT id, name FROM classes ORDER BY name")->fetchAll();

$rows = db()->query("
  SELECT a.id, a.hours_per_week, 
         u.full_name teacher_name, u.id teacher_id,
         s.code subject_code, s.name subject_name, s.id subject_id,
         c.name class_name, c.id class_id
  FROM teacher_assignments a
  JOIN users u ON u.id=a.teacher_user_id
  JOIN subjects s ON s.id=a.subject_id
  JOIN classes c ON c.id=a.class_id
  ORDER BY c.name, s.code, u.full_name
")->fetchAll();
?>
<div class="card card-soft">
  <div class="card-body p-4">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <div class="h5 fw-bold mb-1">Teacher Assignments</div>
        <div class="text-muted small">Define who teaches what, in which class, and how many periods per week.</div>
      </div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAssignCreate"><i class="bi bi-plus-lg me-1"></i>Add assignment</button>
    </div>

    <div class="table-responsive mt-3">
      <table class="table table-dark table-hover align-middle">
        <thead class="text-muted">
          <tr><th>Class</th><th>Subject</th><th>Teacher</th><th>Hours/Week</th><th class="text-end">Action</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td class="fw-semibold"><?= htmlspecialchars($r['class_name']) ?></td>
            <td><?= htmlspecialchars($r['subject_code']) ?> — <span class="text-muted"><?= htmlspecialchars($r['subject_name']) ?></span></td>
            <td><?= htmlspecialchars($r['teacher_name']) ?></td>
            <td><span class="badge text-bg-secondary"><?= (int)$r['hours_per_week'] ?></span></td>
            <td class="text-end">
              <button class="btn btn-sm btn-soft" data-bs-toggle="modal" data-bs-target="#modalAssignEdit"
                data-id="<?= (int)$r['id'] ?>"
                data-teacher_id="<?= (int)$r['teacher_id'] ?>"
                data-subject_id="<?= (int)$r['subject_id'] ?>"
                data-class_id="<?= (int)$r['class_id'] ?>"
                data-hours_per_week="<?= (int)$r['hours_per_week'] ?>">
                <i class="bi bi-pencil me-1"></i>Edit
              </button>
              <button class="btn btn-sm btn-outline-danger" data-action="delete" data-api="<?= BASE_URL ?>/app/api/assignment_delete.php" data-id="<?= (int)$r['id'] ?>">
                <i class="bi bi-trash me-1"></i>Delete
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="alert alert-secondary mt-3 mb-0">
      After setting assignments, go to <b>Admin • Availability</b> and then <b>Timetable</b> → Generate.
    </div>
  </div>
</div>

<div class="modal fade" id="modalAssignCreate" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Add Assignment</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form class="modal-body" data-api="<?= BASE_URL ?>/app/api/assignment_create.php">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Class</label>
            <select class="form-select" name="class_id" required>
              <?php foreach($classes as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Subject</label>
            <select class="form-select" name="subject_id" required>
              <?php foreach($subjects as $s): ?>
                <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['code']) ?> — <?= htmlspecialchars($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Teacher</label>
            <select class="form-select" name="teacher_user_id" required>
              <?php foreach($teachers as $t): ?>
                <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Hours per week</label>
            <input class="form-control" type="number" name="hours_per_week" min="1" value="2" required>
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

<div class="modal fade" id="modalAssignEdit" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Edit Assignment</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form class="modal-body" data-api="<?= BASE_URL ?>/app/api/assignment_update.php">
        <input type="hidden" name="id" id="aId">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Class</label>
            <select class="form-select" name="class_id" id="aClass" required>
              <?php foreach($classes as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Subject</label>
            <select class="form-select" name="subject_id" id="aSubject" required>
              <?php foreach($subjects as $s): ?>
                <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['code']) ?> — <?= htmlspecialchars($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Teacher</label>
            <select class="form-select" name="teacher_user_id" id="aTeacher" required>
              <?php foreach($teachers as $t): ?>
                <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Hours per week</label>
            <input class="form-control" type="number" name="hours_per_week" id="aHours" min="1" required>
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
document.getElementById('modalAssignEdit')?.addEventListener('show.bs.modal', (e)=>{
  const b = e.relatedTarget;
  document.getElementById('aId').value = b.dataset.id;
  document.getElementById('aClass').value = b.dataset.class_id;
  document.getElementById('aSubject').value = b.dataset.subject_id;
  document.getElementById('aTeacher').value = b.dataset.teacher_id;
  document.getElementById('aHours').value = b.dataset.hours_per_week;
});
</script>
