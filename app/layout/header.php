<?php
$u = current_user();
$settings = db()->query("SELECT * FROM school_settings WHERE id=1")->fetch() ?: ['school_name'=>APP_NAME,'logo_path'=>null,'currency'=>'XAF'];
$cssVer = (string)(@filemtime(__DIR__ . '/../../public/assets/css/app.css') ?: time());
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title><?= htmlspecialchars($settings['school_name']) ?> • <?= APP_NAME ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

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
