<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

if (current_user()) {
  header('Location: ' . BASE_URL . '/index.php?page=home');
  exit;
}

$settings = db()->query("SELECT * FROM school_settings WHERE id=1")->fetch() ?: [
  'school_name' => 'EduTrack School',
  'logo_path' => null,
  'currency' => 'XAF',
  'timezone' => 'Africa/Douala',
];
$cssVer = (string)(@filemtime(__DIR__ . '/../../public/assets/css/app.css') ?: time());
$logo = $settings['logo_path']
  ? (BASE_URL . '/' . ltrim((string)$settings['logo_path'], '/'))
  : (BASE_URL . '/assets/img/logo.svg');

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $pass = $_POST['password'] ?? '';
  if (login_user($email, $pass)) {
    header('Location: ' . BASE_URL . '/index.php?page=home');
    exit;
  }
  $error = "Invalid credentials.";
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title><?= htmlspecialchars($settings['school_name'] ?? 'EduTrack') ?> • Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/app.css?v=<?= urlencode($cssVer) ?>" rel="stylesheet">
  <script>
    // Remove stale service workers/caches from previous local app versions.
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.getRegistrations().then(function(registrations) {
        registrations.forEach(function(reg) { reg.unregister(); });
      });
    }
    if (window.caches && caches.keys) {
      caches.keys().then(function(keys) {
        keys.forEach(function(k) { caches.delete(k); });
      });
    }
  </script>
</head>
<body class="bg-app">
  <div class="container" style="max-width: 560px; padding-top: 70px;">
    <div class="card card-soft shadow-lg">
      <div class="card-body p-4 p-md-5">
        <div class="d-flex align-items-center gap-3 mb-4">
          <img src="<?= htmlspecialchars($logo) ?>" alt="logo" style="width:54px;height:54px;border-radius:18px;">
          <div>
            <div class="h4 fw-bold m-0"><?= htmlspecialchars($settings['school_name'] ?? 'EduTrack School') ?></div>
            <div class="text-muted">Sign in to continue</div>
          </div>
        </div>

        <?php if ($error): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input class="form-control" name="email" type="email" required placeholder="you@school.com">
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input class="form-control" name="password" type="password" required placeholder="••••••••">
          </div>
          <button class="btn btn-primary w-100 py-2" type="submit">
            <i class="bi bi-box-arrow-in-right me-1"></i> Sign in
          </button>
        </form>

        <hr class="sep my-4">

        <div class="text-muted small">
          Default accounts (change after install):<br>
          <b>admin@edutrack.local</b> / Admin@2026!<br>
          <b>principal@edutrack.local</b> / Principal@2026!<br>
          <b>teacher@edutrack.local</b> / Teacher@2026!<br>
          <b>prefect@edutrack.local</b> / Prefect@2026!
        </div>
      </div>
    </div>
  </div>
</body>
</html>
