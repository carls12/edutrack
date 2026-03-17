<?php
require_role(['admin']);
$settings = db()->query("SELECT * FROM school_settings WHERE id=1")->fetch() ?: [
  'school_name' => APP_NAME,
  'logo_path' => null,
  'currency' => 'XAF',
  'timezone' => 'Africa/Douala',
];
$logo = $settings['logo_path']
  ? (BASE_URL . '/' . ltrim((string)$settings['logo_path'], '/'))
  : (BASE_URL . '/assets/img/logo.svg');

$uploadError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logo'])) {
  if (!isset($_POST['csrf']) || !hash_equals(csrf_token(), $_POST['csrf'])) {
    $uploadError = "Invalid CSRF.";
  } else {
    $f = $_FILES['logo'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
      $uploadError = "Upload error.";
    } else {
      $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
      $allowed = ['png','jpg','jpeg','webp','svg'];
      if (!in_array($ext, $allowed, true)) {
        $uploadError = "Allowed: png, jpg, jpeg, webp, svg.";
      } else {
        $dir = __DIR__ . '/../../public/uploads';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $name = 'logo_' . time() . '.' . $ext;
        $dest = $dir . '/' . $name;
        move_uploaded_file($f['tmp_name'], $dest);

        $rel = 'uploads/' . $name;
        $stmt = db()->prepare("UPDATE school_settings SET logo_path=? WHERE id=1");
        $stmt->execute([$rel]);

        header("Location: " . BASE_URL . "/index.php?page=branding");
        exit;
      }
    }
  }
}
?>
<div class="row g-3">
  <div class="col-lg-6">
    <div class="card card-soft">
      <div class="card-body p-4">
        <div class="h5 fw-bold mb-1">School Branding</div>
        <div class="text-muted small">Upload logo and set your school name. The logo appears in sidebar and login.</div>

        <?php if ($uploadError): ?>
          <div class="alert alert-danger mt-3"><?= htmlspecialchars($uploadError) ?></div>
        <?php endif; ?>

        <form class="mt-3" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <div class="d-flex align-items-center gap-3">
            <img src="<?= htmlspecialchars($logo) ?>" alt="logo" style="width:84px;height:84px;border-radius:22px;">
            <div class="flex-grow-1">
              <label class="form-label">Upload new logo</label>
              <input class="form-control" type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,.svg" required>
              <div class="text-muted small mt-2">Recommended: square image 512×512.</div>
            </div>
          </div>
          <div class="d-flex justify-content-end mt-3">
            <button class="btn btn-primary" type="submit"><i class="bi bi-upload me-1"></i>Upload</button>
          </div>
        </form>

        <hr class="sep my-4">

        <form data-api="<?= BASE_URL ?>/app/api/settings_update.php">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">School name</label>
              <input class="form-control" name="school_name" value="<?= htmlspecialchars($settings['school_name'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Currency</label>
              <input class="form-control" name="currency" value="<?= htmlspecialchars($settings['currency'] ?? 'XAF') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Timezone</label>
              <input class="form-control" name="timezone" value="<?= htmlspecialchars($settings['timezone'] ?? 'Africa/Douala') ?>" required>
            </div>
          </div>
          <div class="d-flex justify-content-end mt-3">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card card-soft">
      <div class="card-body p-4">
        <div class="h5 fw-bold mb-1">Brand Preview</div>
        <div class="text-muted small">How your app will look to users.</div>

        <div class="mt-3 p-3 rounded-4" style="border:1px solid rgba(255,255,255,.1); background:rgba(255,255,255,.03);">
          <div class="d-flex align-items-center gap-3">
            <img src="<?= htmlspecialchars($logo) ?>" alt="logo" style="width:54px;height:54px;border-radius:18px;">
            <div>
              <div class="fw-bold"><?= htmlspecialchars($settings['school_name'] ?? '') ?></div>
              <div class="text-muted small">Teacher Timetable • Attendance • Reports</div>
            </div>
          </div>
          <div class="mt-3 row g-2">
            <div class="col-6"><div class="card card-soft"><div class="card-body">Dashboard</div></div></div>
            <div class="col-6"><div class="card card-soft"><div class="card-body">Attendance</div></div></div>
            <div class="col-6"><div class="card card-soft"><div class="card-body">Timetable</div></div></div>
            <div class="col-6"><div class="card card-soft"><div class="card-body">Reports</div></div></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
