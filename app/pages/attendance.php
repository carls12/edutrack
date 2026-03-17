<?php
require_once __DIR__ . '/../teacher_stamp.php';
$u = current_user();
$role = $u['role'];
teacher_stamp_ensure_schema();

$todayDow = (int)date('N');
$isSchoolDay = $todayDow >= 1 && $todayDow <= 5;

if ($role === 'prefect') {
  $prefectClasses = db()->prepare("SELECT id, name FROM classes WHERE prefect_user_id=? ORDER BY name");
  $prefectClasses->execute([(int)$u['id']]);
  $classOptions = $prefectClasses->fetchAll();
  $selectedClass = (int)($_GET['class_id'] ?? ($classOptions[0]['id'] ?? 0));
} else {
  $classOptions = db()->query("SELECT id, name FROM classes ORDER BY name")->fetchAll();
  $selectedClass = (int)($_GET['class_id'] ?? 0); // 0 = all
}

$cards = [];
if ($isSchoolDay) {
  $params = [$todayDow];
  $sql = "
    SELECT te.id timetable_entry_id, te.class_id, te.teacher_user_id,
           u.full_name teacher_name, s.code subject_code, s.name subject_name,
           c.name class_name, p.label period_label, p.start_time, p.end_time
    FROM timetable_entries te
    JOIN users u ON u.id=te.teacher_user_id
    JOIN subjects s ON s.id=te.subject_id
    JOIN classes c ON c.id=te.class_id
    JOIN periods p ON p.id=te.period_id
    WHERE te.day_of_week=?
  ";

  if ($role === 'prefect') {
    $sql .= " AND te.class_id=? ";
    $params[] = $selectedClass;
  } elseif ($selectedClass > 0) {
    $sql .= " AND te.class_id=? ";
    $params[] = $selectedClass;
  }
  $sql .= " ORDER BY p.sort_order, c.name, u.full_name";

  $st = db()->prepare($sql);
  $st->execute($params);
  $cards = $st->fetchAll();
}

$attendanceMap = [];
$teacherStampMap = [];
if ($cards) {
  $pairKeys = [];
  $teacherIds = [];
  foreach ($cards as $c) {
    $pairKeys[$c['teacher_user_id'] . '-' . $c['class_id']] = true;
    $teacherIds[(int)$c['teacher_user_id']] = true;
  }
  $logs = db()->query("
    SELECT teacher_user_id, class_id, status, reason, event_time
    FROM attendance
    WHERE DATE(event_time)=CURDATE()
    ORDER BY event_time ASC, id ASC
  ")->fetchAll();

  foreach ($logs as $r) {
    $k = $r['teacher_user_id'] . '-' . $r['class_id'];
    if (!isset($pairKeys[$k])) continue;
    if (!isset($attendanceMap[$k])) {
      $attendanceMap[$k] = ['arrived_at' => null, 'departed_at' => null, 'absent_at' => null, 'absent_reason' => null];
    }
    if ($r['status'] === 'arrived') $attendanceMap[$k]['arrived_at'] = $r['event_time'];
    if ($r['status'] === 'departed') $attendanceMap[$k]['departed_at'] = $r['event_time'];
    if ($r['status'] === 'absent') {
      $attendanceMap[$k]['absent_at'] = $r['event_time'];
      $attendanceMap[$k]['absent_reason'] = $r['reason'];
    }
  }

  foreach (array_keys($teacherIds) as $teacherId) {
    $teacherStampMap[$teacherId] = teacher_stamp_today_status((int)$teacherId);
  }
}

function attendance_state(array $m): string {
  $arrived = $m['arrived_at'] ?? null;
  $departed = $m['departed_at'] ?? null;
  $absent = $m['absent_at'] ?? null;
  if ($absent && (!$arrived || strtotime((string)$absent) >= strtotime((string)$arrived))) return 'absent';
  if ($arrived && (!$departed || strtotime((string)$departed) < strtotime((string)$arrived))) return 'in_class';
  if ($arrived && $departed && strtotime((string)$departed) >= strtotime((string)$arrived)) return 'completed';
  return 'pending';
}
?>
<div class="row g-3">
  <div class="col-12">
    <div class="card card-soft">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <div class="h5 fw-bold mb-1">Teacher Attendance Cards</div>
            <div class="text-muted small">Arrived → Left, or mark Absent. Teachers are not allowed on this page.</div>
          </div>
          <form method="get" action="<?= BASE_URL ?>/index.php" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="page" value="attendance">
            <?php if ($role === 'prefect'): ?>
              <select class="form-select" name="class_id" onchange="this.form.submit()">
                <?php foreach($classOptions as $c): ?>
                  <option value="<?= (int)$c['id'] ?>" <?= ($selectedClass === (int)$c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <select class="form-select" name="class_id" onchange="this.form.submit()">
                <option value="0" <?= $selectedClass === 0 ? 'selected' : '' ?>>All classes</option>
                <?php foreach($classOptions as $c): ?>
                  <option value="<?= (int)$c['id'] ?>" <?= ($selectedClass === (int)$c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
          </form>
        </div>
        <div id="offlineQueueInfo" class="alert alert-warning mt-3 d-none mb-0"></div>
      </div>
    </div>
  </div>

  <?php if (!$isSchoolDay): ?>
    <div class="col-12"><div class="alert alert-secondary">No timetable cards today (weekend).</div></div>
  <?php elseif (!$cards): ?>
    <div class="col-12"><div class="alert alert-secondary">No schedules found for today and selected class scope.</div></div>
  <?php else: ?>
    <?php foreach ($cards as $c): ?>
      <?php
        $k = $c['teacher_user_id'] . '-' . $c['class_id'];
        $meta = $attendanceMap[$k] ?? ['arrived_at' => null, 'departed_at' => null, 'absent_at' => null, 'absent_reason' => null];
        $state = attendance_state($meta);
        $stampMeta = $teacherStampMap[(int)$c['teacher_user_id']] ?? ['arrived_at' => null, 'departed_at' => null, 'is_in' => false];
        $isPending = $state === 'pending';
        $isInClass = $state === 'in_class';
        $isCompleted = $state === 'completed';
        $isAbsent = $state === 'absent';
        $prefectBlocked = $role === 'prefect' && !$stampMeta['is_in'];
      ?>
      <div class="col-xl-4 col-lg-6">
        <div class="card card-soft h-100 <?= $isAbsent ? 'border-warning' : '' ?>">
          <div class="card-body p-3">
            <div class="d-flex align-items-start justify-content-between gap-2">
              <div>
                <div class="fw-bold"><?= htmlspecialchars($c['teacher_name']) ?></div>
                <div class="text-muted small"><?= htmlspecialchars($c['subject_code']) ?> · <?= htmlspecialchars($c['subject_name']) ?></div>
              </div>
              <?php if ($isPending): ?><span class="badge text-bg-secondary">Pending</span><?php endif; ?>
              <?php if ($isInClass): ?><span class="badge text-bg-success">In Class</span><?php endif; ?>
              <?php if ($isCompleted): ?><span class="badge text-bg-primary">Completed</span><?php endif; ?>
              <?php if ($isAbsent): ?><span class="badge text-bg-warning text-dark"><?= htmlspecialchars($meta['absent_reason'] ?: 'Absent') ?></span><?php endif; ?>
            </div>

            <div class="mt-2 p-2 rounded-3" style="background: rgba(148,163,184,.10); border:1px solid rgba(148,163,184,.2);">
              <div class="small text-muted"><?= htmlspecialchars($c['period_label']) ?> · <?= htmlspecialchars($c['start_time']) ?> - <?= htmlspecialchars($c['end_time']) ?></div>
              <div class="small text-muted"><?= htmlspecialchars($c['class_name']) ?></div>
            </div>

            <div class="mt-2 small text-muted">
              Office stamp in: <?= htmlspecialchars($stampMeta['arrived_at'] ?? 'Not yet stamped in') ?><br>
              Office stamp out: <?= htmlspecialchars($stampMeta['departed_at'] ?? '-') ?><br>
              <?php if ($meta['arrived_at']): ?>Arrived: <?= htmlspecialchars($meta['arrived_at']) ?><br><?php endif; ?>
              <?php if ($meta['departed_at']): ?>Left: <?= htmlspecialchars($meta['departed_at']) ?><?php endif; ?>
            </div>

            <?php if ($isAbsent): ?>
              <div class="alert alert-warning mt-2 mb-0 py-2">
                Teacher marked absent<?= $meta['absent_reason'] ? ': ' . htmlspecialchars($meta['absent_reason']) : '' ?>.
              </div>
            <?php else: ?>
              <?php if ($prefectBlocked): ?>
                <div class="alert alert-warning mt-2 mb-0 py-2">
                  Teacher must first stamp in at the office before the prefect can mark class arrival.
                </div>
              <?php endif; ?>
              <div class="d-flex gap-2 mt-3">
                <button class="btn btn-sm <?= $isPending ? 'btn-success' : 'btn-soft' ?> flex-fill"
                        data-action="attendance_card_action"
                        data-api="<?= BASE_URL ?>/app/api/attendance_card_action.php"
                        data-mode="arrived"
                        data-timetable-entry-id="<?= (int)$c['timetable_entry_id'] ?>"
                        <?= ($isPending && !$prefectBlocked) ? '' : 'disabled' ?>>
                  <?= $isPending ? 'Arrived' : 'Arrived ✓' ?>
                </button>
                <button class="btn btn-sm <?= $isInClass ? 'btn-primary' : 'btn-soft' ?> flex-fill"
                        data-action="attendance_card_action"
                        data-api="<?= BASE_URL ?>/app/api/attendance_card_action.php"
                        data-mode="left"
                        data-timetable-entry-id="<?= (int)$c['timetable_entry_id'] ?>"
                        <?= $isInClass ? '' : 'disabled' ?>>
                  <?= $isCompleted ? 'Left ✓' : 'Left' ?>
                </button>
                <button class="btn btn-sm <?= $isPending ? 'btn-outline-danger' : 'btn-soft' ?> flex-fill"
                        data-action="attendance_card_action"
                        data-api="<?= BASE_URL ?>/app/api/attendance_card_action.php"
                        data-mode="absent"
                        data-timetable-entry-id="<?= (int)$c['timetable_entry_id'] ?>"
                        <?= $isPending ? '' : 'disabled' ?>>
                  <?= $isAbsent ? 'Absent ✓' : 'Absent' ?>
                </button>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
