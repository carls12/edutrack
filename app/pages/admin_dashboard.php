<?php
require_role(['admin']);

$tab = (string)($_GET['tab'] ?? 'teachers');
$validTabs = ['teachers', 'classes', 'schedules', 'prefects', 'subjects'];
if (!in_array($tab, $validTabs, true)) {
  $tab = 'teachers';
}

$stats = [
  'teachers' => (int)db()->query("SELECT COUNT(*) c FROM users WHERE role='teacher'")->fetch()['c'],
  'classes' => (int)db()->query("SELECT COUNT(*) c FROM classes")->fetch()['c'],
  'subjects' => (int)db()->query("SELECT COUNT(*) c FROM subjects WHERE is_active=1")->fetch()['c'],
  'schedules' => (int)db()->query("SELECT COUNT(*) c FROM timetable_entries")->fetch()['c'],
  'prefects' => (int)db()->query("SELECT COUNT(*) c FROM users WHERE role='prefect'")->fetch()['c'],
];
?>
<div class="row g-3">
  <div class="col-12">
    <div class="card card-soft">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <div class="h4 fw-bold mb-1">Admin Dashboard</div>
            <div class="text-muted small">Central management interface.</div>
          </div>
          <a class="btn btn-soft" href="<?= BASE_URL ?>/index.php?page=home"><i class="bi bi-house-door me-1"></i>Home</a>
        </div>

        <div class="row g-3 mt-1">
          <div class="col-md-2"><div class="card card-soft"><div class="card-body py-3"><div class="text-muted small">Teachers</div><div class="fw-bold fs-4"><?= $stats['teachers'] ?></div></div></div></div>
          <div class="col-md-2"><div class="card card-soft"><div class="card-body py-3"><div class="text-muted small">Classes</div><div class="fw-bold fs-4"><?= $stats['classes'] ?></div></div></div></div>
          <div class="col-md-2"><div class="card card-soft"><div class="card-body py-3"><div class="text-muted small">Subjects</div><div class="fw-bold fs-4"><?= $stats['subjects'] ?></div></div></div></div>
          <div class="col-md-3"><div class="card card-soft"><div class="card-body py-3"><div class="text-muted small">Schedules</div><div class="fw-bold fs-4"><?= $stats['schedules'] ?></div></div></div></div>
          <div class="col-md-3"><div class="card card-soft"><div class="card-body py-3"><div class="text-muted small">Prefects</div><div class="fw-bold fs-4"><?= $stats['prefects'] ?></div></div></div></div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card card-soft">
      <div class="card-body p-3">
        <div class="nav nav-pills gap-2">
          <a class="nav-link <?= $tab === 'teachers' ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php?page=admin_dashboard&tab=teachers">Teachers</a>
          <a class="nav-link <?= $tab === 'classes' ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php?page=admin_dashboard&tab=classes">Classes</a>
          <a class="nav-link <?= $tab === 'schedules' ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php?page=admin_dashboard&tab=schedules">Schedules</a>
          <a class="nav-link <?= $tab === 'prefects' ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php?page=admin_dashboard&tab=prefects">Prefects</a>
          <a class="nav-link <?= $tab === 'subjects' ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php?page=admin_dashboard&tab=subjects">Subjects</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <?php if ($tab === 'teachers'): ?>
      <?php include __DIR__ . '/admin_teachers.php'; ?>
      <div class="mt-3">
        <?php include __DIR__ . '/admin_assignments.php'; ?>
      </div>
    <?php elseif ($tab === 'classes'): ?>
      <?php include __DIR__ . '/admin_classes.php'; ?>
      <div class="d-flex gap-2 flex-wrap mt-3">
        <a class="btn btn-soft" href="<?= BASE_URL ?>/index.php?page=settings"><i class="bi bi-clock-history me-1"></i>Periods Settings</a>
        <a class="btn btn-soft" href="<?= BASE_URL ?>/index.php?page=branding"><i class="bi bi-palette2 me-1"></i>Branding</a>
      </div>
    <?php elseif ($tab === 'schedules'): ?>
      <?php include __DIR__ . '/admin_availability.php'; ?>
      <div class="mt-3">
        <?php include __DIR__ . '/timetable.php'; ?>
      </div>
    <?php elseif ($tab === 'prefects'): ?>
      <?php
        db()->exec("
          CREATE TABLE IF NOT EXISTS prefect_password_audit (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            prefect_user_id INT NOT NULL,
            class_id INT NOT NULL,
            email VARCHAR(190) NOT NULL,
            plain_password VARCHAR(120) NOT NULL,
            created_by_user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_prefect_user (prefect_user_id),
            INDEX idx_class (class_id)
          )
        ");
        $prefects = db()->query("SELECT id, full_name FROM users WHERE role='prefect' AND is_active=1 ORDER BY full_name")->fetchAll();
        $classes = db()->query("
          SELECT c.id, c.name, c.grade_level, c.room_number, c.prefect_user_id, u.full_name AS prefect_name, u.email AS prefect_email
          FROM classes c
          LEFT JOIN users u ON u.id = c.prefect_user_id
          ORDER BY c.name
        ")->fetchAll();
        $auditRows = db()->query("
          SELECT a.created_at, a.email, a.plain_password, c.name class_name, u.full_name prefect_name
          FROM prefect_password_audit a
          LEFT JOIN classes c ON c.id = a.class_id
          LEFT JOIN users u ON u.id = a.prefect_user_id
          ORDER BY a.id DESC
          LIMIT 20
        ")->fetchAll();
      ?>
      <div class="card card-soft">
        <div class="card-body p-4">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
              <div class="h5 fw-bold mb-1">Assign Prefects To Classes</div>
              <div class="text-muted small">Create prefect accounts with auto login and assign class.</div>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreatePrefect">
              <i class="bi bi-person-plus me-1"></i>Create Prefect + Assign
            </button>
          </div>
          <div class="text-muted small">One prefect per class.</div>
          <div id="prefectCredentialsBox" class="alert alert-info mt-3 d-none"></div>
          <div class="table-responsive mt-3">
            <table class="table table-dark table-hover align-middle">
              <thead class="text-muted">
                <tr><th>Class</th><th>Grade</th><th>Room</th><th>Current Prefect</th><th>Login Email</th><th class="text-end">Assign</th></tr>
              </thead>
              <tbody>
                <?php foreach ($classes as $c): ?>
                <tr>
                  <td class="fw-semibold"><?= htmlspecialchars($c['name']) ?></td>
                  <td class="text-muted"><?= htmlspecialchars($c['grade_level'] ?? '-') ?></td>
                  <td class="text-muted"><?= htmlspecialchars($c['room_number'] ?? '-') ?></td>
                  <td><?= htmlspecialchars($c['prefect_name'] ?? 'Not assigned') ?></td>
                  <td class="text-muted"><?= htmlspecialchars($c['prefect_email'] ?? '-') ?></td>
                  <td class="text-end">
                    <form class="d-flex gap-2 justify-content-end" data-api="<?= BASE_URL ?>/app/api/prefect_assign.php">
                      <input type="hidden" name="class_id" value="<?= (int)$c['id'] ?>">
                      <select class="form-select" style="max-width:260px" name="prefect_user_id">
                        <option value="">No prefect</option>
                        <?php foreach ($prefects as $p): ?>
                          <option value="<?= (int)$p['id'] ?>" <?= ((int)$c['prefect_user_id'] === (int)$p['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['full_name']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <button class="btn btn-primary" type="submit">Save</button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <hr class="sep my-4">
          <div class="h6 fw-bold mb-2">Prefect Initial Login Audit (Admin Only)</div>
          <div class="text-muted small mb-2">Only prefect generated passwords are stored here, as requested.</div>
          <div class="table-responsive">
            <table class="table table-dark table-hover align-middle">
              <thead class="text-muted">
                <tr><th>Created</th><th>Prefect</th><th>Class</th><th>Email</th><th>Password</th></tr>
              </thead>
              <tbody>
                <?php foreach ($auditRows as $a): ?>
                  <tr>
                    <td class="text-muted small"><?= htmlspecialchars($a['created_at']) ?></td>
                    <td><?= htmlspecialchars($a['prefect_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($a['class_name'] ?? '-') ?></td>
                    <td><code><?= htmlspecialchars($a['email']) ?></code></td>
                    <td><code><?= htmlspecialchars($a['plain_password']) ?></code></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$auditRows): ?><tr><td colspan="5" class="text-muted">No records yet.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="modal fade" id="modalCreatePrefect" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title fw-bold">Create Prefect & Assign Class</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form class="modal-body" data-api="<?= BASE_URL ?>/app/api/prefect_create_assign.php" data-on-success="onPrefectCreated">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Prefect full name</label>
                  <input class="form-control" name="full_name" required placeholder="e.g. Anita Nfor">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Assign class</label>
                  <select class="form-select" name="class_id" required>
                    <?php foreach ($classes as $c): ?>
                      <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12">
                  <div class="alert alert-secondary mb-0">
                    Email and password will be generated automatically and shown after creation.
                  </div>
                </div>
              </div>
              <div class="modal-footer px-0 pb-0">
                <button class="btn btn-soft" type="button" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" type="submit">Create Prefect</button>
              </div>
            </form>
          </div>
        </div>
      </div>
      <script>
      function onPrefectCreated(out){
        const box = document.getElementById('prefectCredentialsBox');
        if (!box) {
          window.location.reload();
          return;
        }
        box.classList.remove('d-none');
        box.innerHTML =
          `<div class="fw-bold mb-1">Prefect Created Successfully</div>
           <div>Name: <b>${out.full_name}</b></div>
           <div>Class: <b>${out.class_name}</b></div>
           <div>Login Email: <code>${out.email}</code></div>
           <div>Temporary Password: <code>${out.password}</code></div>
           <div class="small mt-2">Please share this login and ask the prefect to change password later.</div>`;
        const modalEl = document.getElementById('modalCreatePrefect');
        const modal = bootstrap.Modal.getInstance(modalEl);
        modal?.hide();
        setTimeout(() => window.location.reload(), 2500);
      }
      </script>
    <?php else: ?>
      <?php include __DIR__ . '/admin_subjects.php'; ?>
    <?php endif; ?>
  </div>
</div>
