<?php
require_role(['admin']);
db()->exec("CREATE TABLE IF NOT EXISTS school_settings (
  id INT PRIMARY KEY DEFAULT 1,
  school_name VARCHAR(190) NOT NULL DEFAULT 'EduTrack School',
  logo_path VARCHAR(255) DEFAULT NULL,
  watermark_path VARCHAR(255) DEFAULT NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'XAF',
  timezone VARCHAR(60) NOT NULL DEFAULT 'Africa/Douala',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");
$wmCol = db()->prepare("
  SELECT COUNT(*) c
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'school_settings'
    AND COLUMN_NAME = 'watermark_path'
");
$wmCol->execute();
if ((int)$wmCol->fetch()['c'] === 0) {
  db()->exec("ALTER TABLE school_settings ADD COLUMN watermark_path VARCHAR(255) DEFAULT NULL AFTER logo_path");
}
db()->exec("
  CREATE TABLE IF NOT EXISTS timetable_signature_settings (
    id INT PRIMARY KEY DEFAULT 1,
    signature_slots INT NOT NULL DEFAULT 2,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  )
");
db()->exec("
  CREATE TABLE IF NOT EXISTS timetable_signatories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    title VARCHAR(120) NOT NULL,
    sort_order INT NOT NULL DEFAULT 10,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )
");
$periods = db()->query("SELECT * FROM periods ORDER BY sort_order")->fetchAll();
$sigSettings = db()->query("SELECT signature_slots FROM timetable_signature_settings WHERE id=1")->fetch() ?: ['signature_slots' => 2];
$signatories = db()->query("SELECT * FROM timetable_signatories ORDER BY sort_order, id")->fetchAll();
$settings = db()->query("SELECT * FROM school_settings WHERE id=1")->fetch() ?: ['watermark_path' => null];
$watermark = !empty($settings['watermark_path'])
  ? (BASE_URL . '/' . ltrim((string)$settings['watermark_path'], '/'))
  : null;

$uploadError = null;
function settings_redirect_self(): void {
  $url = BASE_URL . "/index.php?page=settings";
  if (!headers_sent()) {
    header("Location: " . $url);
  } else {
    echo '<script>window.location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';
  }
  exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wm_action'])) {
  if (!isset($_POST['csrf']) || !hash_equals(csrf_token(), (string)$_POST['csrf'])) {
    $uploadError = 'Invalid CSRF.';
  } else {
    $action = (string)$_POST['wm_action'];
    if ($action === 'delete') {
      db()->prepare("UPDATE school_settings SET watermark_path=NULL WHERE id=1")->execute();
      settings_redirect_self();
    }
    if ($action === 'upload') {
      if (!isset($_FILES['watermark']) || (int)$_FILES['watermark']['error'] !== UPLOAD_ERR_OK) {
        $uploadError = 'Upload error.';
      } else {
        $f = $_FILES['watermark'];
        $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
        $allowed = ['png','jpg','jpeg','webp'];
        if (!in_array($ext, $allowed, true)) {
          $uploadError = 'Allowed: png, jpg, jpeg, webp.';
        } else {
          $dir = __DIR__ . '/../../public/uploads';
          if (!is_dir($dir)) mkdir($dir, 0777, true);
          $name = 'watermark_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;
          $dest = $dir . '/' . $name;
          if (!move_uploaded_file((string)$f['tmp_name'], $dest)) {
            $uploadError = 'Could not move uploaded file.';
          } else {
            $rel = 'uploads/' . $name;
            db()->prepare("UPDATE school_settings SET watermark_path=? WHERE id=1")->execute([$rel]);
            settings_redirect_self();
          }
        }
      }
    }
  }
}
?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card card-soft">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="h5 fw-bold mb-1">School Periods</div>
            <div class="text-muted small">Timetable uses these periods. Mark breaks as non-teaching.</div>
          </div>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPeriodCreate"><i class="bi bi-plus-lg me-1"></i>Add period</button>
        </div>

        <div class="table-responsive mt-3">
          <table class="table table-dark table-hover align-middle">
            <thead class="text-muted">
              <tr><th>Label</th><th>Start</th><th>End</th><th>Teaching?</th><th class="text-end">Action</th></tr>
            </thead>
            <tbody>
              <?php foreach($periods as $p): ?>
              <tr>
                <td class="fw-semibold"><?= htmlspecialchars($p['label']) ?></td>
                <td class="text-muted"><?= htmlspecialchars($p['start_time']) ?></td>
                <td class="text-muted"><?= htmlspecialchars($p['end_time']) ?></td>
                <td><?= ((int)$p['is_teaching_period']===1) ? '<span class="badge text-bg-success">Yes</span>' : '<span class="badge text-bg-warning">No</span>' ?></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-soft" data-bs-toggle="modal" data-bs-target="#modalPeriodEdit"
                    data-id="<?= (int)$p['id'] ?>"
                    data-label="<?= htmlspecialchars($p['label'], ENT_QUOTES) ?>"
                    data-start_time="<?= htmlspecialchars($p['start_time'], ENT_QUOTES) ?>"
                    data-end_time="<?= htmlspecialchars($p['end_time'], ENT_QUOTES) ?>"
                    data-sort_order="<?= (int)$p['sort_order'] ?>"
                    data-is_teaching_period="<?= (int)$p['is_teaching_period'] ?>">
                    <i class="bi bi-pencil me-1"></i>Edit
                  </button>
                  <button class="btn btn-sm btn-outline-danger" data-action="delete" data-api="<?= BASE_URL ?>/app/api/period_delete.php" data-id="<?= (int)$p['id'] ?>">
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
  </div>

  <div class="col-lg-4">
    <div class="card card-soft">
      <div class="card-body p-4">
        <div class="h5 fw-bold mb-1">Document Watermark</div>
        <div class="text-muted small">This image is applied across all PDF exports as full-page background.</div>

        <?php if ($uploadError): ?>
          <div class="alert alert-danger mt-3 mb-0"><?= htmlspecialchars($uploadError) ?></div>
        <?php endif; ?>

        <form class="mt-3" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="wm_action" value="upload">
          <label class="form-label">Upload watermark image</label>
          <input class="form-control" type="file" name="watermark" accept=".png,.jpg,.jpeg,.webp" required>
          <div class="text-muted small mt-2">Best result: light image, landscape ratio (A4), high resolution.</div>
          <button class="btn btn-primary mt-3 w-100" type="submit">Upload Watermark</button>
        </form>

        <?php if ($watermark): ?>
          <div class="mt-3">
            <img src="<?= htmlspecialchars($watermark) ?>" alt="Watermark preview" style="width:100%;border-radius:12px;opacity:.7;">
          </div>
          <form class="mt-3" method="post">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="wm_action" value="delete">
            <button class="btn btn-outline-danger w-100" type="submit">Remove Watermark</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="card card-soft">
      <div class="card-body p-4">
        <div class="h5 fw-bold mb-1">Notes</div>
        <div class="text-muted small">
          After changing periods, regenerate the timetable.<br><br>
          Break periods should be <b>Teaching = No</b> so they won't be scheduled.
        </div>
      </div>
    </div>
    <div class="card card-soft mt-3">
      <div class="card-body p-4">
        <div class="h5 fw-bold mb-1">Timetable Signatures</div>
        <div class="text-muted small">Configure signature slots and signatories shown on timetable PDF.</div>

        <form class="mt-3" data-api="<?= BASE_URL ?>/app/api/timetable_signature_slots_update.php">
          <label class="form-label">Number of signature slots</label>
          <div class="d-flex gap-2">
            <input class="form-control" type="number" min="1" max="10" name="signature_slots" value="<?= (int)$sigSettings['signature_slots'] ?>" required>
            <button class="btn btn-primary" type="submit">Save</button>
          </div>
        </form>

        <hr class="sep my-3">

        <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#modalSignatoryCreate">
          <i class="bi bi-plus-lg me-1"></i>Add Signatory
        </button>

        <div class="mt-3 d-grid gap-2">
          <?php foreach($signatories as $s): ?>
            <div class="card card-soft">
              <div class="card-body py-2">
                <div class="fw-semibold"><?= htmlspecialchars($s['name']) ?></div>
                <div class="text-muted small"><?= htmlspecialchars($s['title']) ?> · Order <?= (int)$s['sort_order'] ?></div>
                <div class="d-flex gap-2 mt-2">
                  <button class="btn btn-sm btn-soft flex-fill" data-bs-toggle="modal" data-bs-target="#modalSignatoryEdit"
                    data-id="<?= (int)$s['id'] ?>"
                    data-name="<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>"
                    data-title="<?= htmlspecialchars($s['title'], ENT_QUOTES) ?>"
                    data-sort_order="<?= (int)$s['sort_order'] ?>"
                    data-is_active="<?= (int)$s['is_active'] ?>">
                    Edit
                  </button>
                  <button class="btn btn-sm btn-outline-danger flex-fill" data-action="delete" data-api="<?= BASE_URL ?>/app/api/timetable_signatory_delete.php" data-id="<?= (int)$s['id'] ?>">
                    Delete
                  </button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if(!$signatories): ?><div class="text-muted small">No signatories configured yet.</div><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Period create/edit -->
<div class="modal fade" id="modalPeriodCreate" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Add Period</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form class="modal-body" data-api="<?= BASE_URL ?>/app/api/period_create.php">
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Label</label><input class="form-control" name="label" required></div>
          <div class="col-md-4"><label class="form-label">Start</label><input class="form-control" name="start_time" type="time" required></div>
          <div class="col-md-4"><label class="form-label">End</label><input class="form-control" name="end_time" type="time" required></div>
          <div class="col-md-4"><label class="form-label">Sort order</label><input class="form-control" name="sort_order" type="number" value="10" required></div>
          <div class="col-md-4"><label class="form-label">Teaching period</label>
            <select class="form-select" name="is_teaching_period"><option value="1" selected>Yes</option><option value="0">No</option></select>
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

<div class="modal fade" id="modalPeriodEdit" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Edit Period</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form class="modal-body" data-api="<?= BASE_URL ?>/app/api/period_update.php">
        <input type="hidden" name="id" id="pId">
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Label</label><input class="form-control" name="label" id="pLabel" required></div>
          <div class="col-md-4"><label class="form-label">Start</label><input class="form-control" name="start_time" id="pStart" type="time" required></div>
          <div class="col-md-4"><label class="form-label">End</label><input class="form-control" name="end_time" id="pEnd" type="time" required></div>
          <div class="col-md-4"><label class="form-label">Sort order</label><input class="form-control" name="sort_order" id="pSort" type="number" required></div>
          <div class="col-md-4"><label class="form-label">Teaching period</label>
            <select class="form-select" name="is_teaching_period" id="pTeach"><option value="1">Yes</option><option value="0">No</option></select>
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
document.getElementById('modalPeriodEdit')?.addEventListener('show.bs.modal', (e)=>{
  const b = e.relatedTarget;
  document.getElementById('pId').value = b.dataset.id;
  document.getElementById('pLabel').value = b.dataset.label;
  document.getElementById('pStart').value = b.dataset.start_time;
  document.getElementById('pEnd').value = b.dataset.end_time;
  document.getElementById('pSort').value = b.dataset.sort_order;
  document.getElementById('pTeach').value = b.dataset.is_teaching_period;
});
</script>

<div class="modal fade" id="modalSignatoryCreate" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Add Signatory</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form class="modal-body" data-api="<?= BASE_URL ?>/app/api/timetable_signatory_create.php">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Name (optional)</label><input class="form-control" name="name" placeholder="Optional"></div>
          <div class="col-md-6"><label class="form-label">Title</label><input class="form-control" name="title" required placeholder="e.g. Principal"></div>
          <div class="col-md-6"><label class="form-label">Sort order</label><input class="form-control" name="sort_order" type="number" value="10" required></div>
          <div class="col-md-6"><label class="form-label">Active</label><select class="form-select" name="is_active"><option value="1" selected>Yes</option><option value="0">No</option></select></div>
        </div>
        <div class="modal-footer px-0 pb-0">
          <button class="btn btn-soft" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" type="submit">Create</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalSignatoryEdit" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Edit Signatory</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form class="modal-body" data-api="<?= BASE_URL ?>/app/api/timetable_signatory_update.php">
        <input type="hidden" name="id" id="sigId">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Name (optional)</label><input class="form-control" name="name" id="sigName" placeholder="Optional"></div>
          <div class="col-md-6"><label class="form-label">Title</label><input class="form-control" name="title" id="sigTitle" required></div>
          <div class="col-md-6"><label class="form-label">Sort order</label><input class="form-control" name="sort_order" id="sigSort" type="number" required></div>
          <div class="col-md-6"><label class="form-label">Active</label><select class="form-select" name="is_active" id="sigActive"><option value="1">Yes</option><option value="0">No</option></select></div>
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
document.getElementById('modalSignatoryEdit')?.addEventListener('show.bs.modal', (e)=>{
  const b = e.relatedTarget;
  document.getElementById('sigId').value = b.dataset.id;
  document.getElementById('sigName').value = b.dataset.name;
  document.getElementById('sigTitle').value = b.dataset.title;
  document.getElementById('sigSort').value = b.dataset.sort_order;
  document.getElementById('sigActive').value = b.dataset.is_active;
});
</script>
