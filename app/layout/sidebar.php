<?php
$u = current_user();
$role = $u['role'] ?? 'guest';
$page = $_GET['page'] ?? 'dashboard';

$menus = [
  'admin' => [
    ['Home','home','bi-house-door'],
    ['Dashboard','dashboard','bi-speedometer2'],
    ['Admin Dashboard','admin_dashboard','bi-grid-1x2'],
    ['Admin Users','admin_users','bi-people'],
    ['Admin Teachers','admin_teachers','bi-mortarboard'],
    ['Teacher Stamp Desk','teacher_stamps','bi-shield-lock'],
    ['Admin Classes','admin_classes','bi-easel2'],
    ['Admin Subjects','admin_subjects','bi-journals'],
    ['Admin Assignments','admin_assignments','bi-link-45deg'],
    ['Admin Availability','admin_availability','bi-calendar2-check'],
    ['Attendance','attendance','bi-check2-square'],
    ['Attendance Mgmt','attendance_management','bi-clipboard2-check'],
    ['Timetable','timetable','bi-calendar3-week'],
    ['Timetable Exports','timetable_exports','bi-download'],
    ['Reports','reports','bi-graph-up'],
    ['Branding','branding','bi-palette2'],
    ['Settings','settings','bi-gear'],
    ['Password Reset','password_reset','bi-key']
  ],
  'principal' => [
    ['Home','home','bi-house-door'],
    ['Dashboard','dashboard','bi-speedometer2'],
    ['Teachers','admin_teachers','bi-mortarboard'],
    ['Teacher Stamp Desk','teacher_stamps','bi-shield-lock'],
    ['Attendance Validation','attendance','bi-shield-check'],
    ['Attendance Mgmt','attendance_management','bi-clipboard2-check'],
    ['Timetable','timetable','bi-calendar3-week'],
    ['Reports','reports','bi-graph-up'],
  ],
  'teacher' => [
    ['Home','home','bi-house-door'],
    ['Dashboard','dashboard','bi-speedometer2'],
    ['Teacher Portal','teacher_portal','bi-person-lines-fill'],
    ['My Timetable','timetable','bi-calendar3-week'],
    ['My Reports','reports','bi-file-earmark-text'],
  ],
  'prefect' => [
    ['Home','home','bi-house-door'],
    ['Record Attendance','attendance','bi-check2-square'],
    ['Class Timetable','timetable','bi-calendar3-week'],
  ],
];

$links = $menus[$role] ?? [];
$mobileLinks = [];
foreach ($links as $link) {
  if (in_array($link[1], ['home','dashboard','teacher_stamps','attendance','timetable'], true)) {
    $mobileLinks[] = $link;
  }
}
if ($mobileLinks === []) {
  $mobileLinks = array_slice($links, 0, 4);
}
$settings = db()->query("SELECT * FROM school_settings WHERE id=1")->fetch() ?: [
  'school_name' => APP_NAME,
  'logo_path' => null,
  'currency' => 'XAF',
  'timezone' => 'Africa/Douala',
];
$logo = $settings['logo_path']
  ? (BASE_URL . '/' . ltrim((string)$settings['logo_path'], '/'))
  : (BASE_URL . '/assets/img/logo.svg');
?>
<nav class="sidebar shadow-sm">
  <div class="sidebar-brand d-flex align-items-center gap-2">
    <img src="<?= htmlspecialchars($logo) ?>" alt="logo" style="width:42px;height:42px;border-radius:14px;">
    <div>
      <div class="fw-bold"><?= htmlspecialchars($settings['school_name'] ?? APP_NAME) ?></div>
      <div class="text-muted small">Role: <?= htmlspecialchars($role) ?></div>
    </div>
  </div>

  <?php if ($u): ?>
  <div class="px-3 pb-2">
    <div class="card card-soft">
      <div class="card-body py-3">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="fw-semibold"><?= htmlspecialchars($u['full_name']) ?></div>
            <div class="text-muted small"><?= htmlspecialchars($u['email']) ?></div>
          </div>
          <span class="badge rounded-pill text-bg-dark"><?= strtoupper(htmlspecialchars($role)) ?></span>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="px-2">
    <div class="nav flex-column nav-pills gap-1">
      <?php foreach ($links as [$label,$p,$icon]): ?>
        <a class="nav-link <?= ($page === $p) ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/index.php?page=<?= $p ?>">
          <i class="bi <?= $icon ?> me-2"></i><?= htmlspecialchars($label) ?>
        </a>
      <?php endforeach; ?>
      <?php if ($u): ?>
        <a class="nav-link mt-2" href="<?= BASE_URL ?>/index.php?page=logout">
          <i class="bi bi-box-arrow-right me-2"></i>Logout
        </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="sidebar-footer px-3">
    <div id="netStatus" class="small text-muted d-flex align-items-center gap-2">
      <span class="status-dot"></span>
      <span>Checking connection...</span>
    </div>
  </div>
</nav>

<?php if ($u): ?>
<nav class="mobile-tabbar d-lg-none" aria-label="Mobile navigation">
  <?php foreach ($mobileLinks as [$label,$p,$icon]): ?>
    <a class="mobile-tabbar-link <?= ($page === $p) ? 'active' : '' ?>"
       href="<?= BASE_URL ?>/index.php?page=<?= $p ?>">
      <i class="bi <?= $icon ?>"></i>
      <span><?= htmlspecialchars($label) ?></span>
    </a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>

<main class="main">
  <header class="topbar">
    <div class="d-flex align-items-center justify-content-between gap-3">
      <div class="d-flex align-items-center gap-2">
        <button class="btn btn-icon d-lg-none" id="sidebarToggle"><i class="bi bi-list"></i></button>
        <div>
          <div class="fw-bold"><?= htmlspecialchars(ucfirst(str_replace('_',' ', $page))) ?></div>
          <div class="text-muted small"><?= APP_NAME ?> • School Management</div>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge rounded-pill text-bg-secondary d-none d-md-inline">Secure Login</span>
      </div>
    </div>
  </header>

  <section class="content container-fluid">
