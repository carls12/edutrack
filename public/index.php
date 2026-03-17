<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/permissions.php';

// Prevent stale HTML routing from browser caches/service-worker leftovers.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$page = $_GET['page'] ?? 'home';

$publicPages = ['login', 'timestap'];

if (!in_array($page, $publicPages, true)) {
  require_auth();

  $role = current_user()['role'];
  $allowed = PAGE_ROLES[$page] ?? null;
  if ($allowed && !in_array($role, $allowed, true)) {
    http_response_code(403);
    echo "Forbidden";
    exit;
  }
}

$allowedPages = [
  'login','logout','home','dashboard','admin_dashboard',
  'admin_users','admin_teachers','teacher_stamps','admin_classes','admin_subjects','admin_assignments','admin_availability',
  'attendance','attendance_management','timetable','reports','branding','settings','password_reset','teacher_portal','timestap'
];

if (!in_array($page, $allowedPages, true)) {
  http_response_code(404);
  echo "Not found";
  exit;
}

if ($page === 'logout') {
  logout_user();
  header('Location: ' . BASE_URL . '/index.php?page=login');
  exit;
}

if ($page === 'login') {
  include __DIR__ . '/../app/pages/login.php';
  exit;
}

if ($page === 'timestap') {
  include __DIR__ . '/../app/pages/timestap.php';
  exit;
}

if ($page === 'teacher_stamps') {
  include __DIR__ . '/../app/pages/teacher_stamps.php';
  exit;
}

include __DIR__ . '/../app/layout/header.php';
include __DIR__ . '/../app/layout/sidebar.php';
include __DIR__ . '/../app/pages/' . $page . '.php';
include __DIR__ . '/../app/layout/footer.php';
