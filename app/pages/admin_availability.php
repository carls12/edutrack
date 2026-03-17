<?php
require_role(['admin']);

$teachers = db()->query("SELECT id, full_name FROM users WHERE role='teacher' ORDER BY full_name")->fetchAll();
$periods = db()->query("SELECT * FROM periods ORDER BY sort_order")->fetchAll();

$teacherId = (int)($_GET['teacher_id'] ?? ($teachers[0]['id'] ?? 0));
$days = [
  1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri'
];

$existing = [];
if ($teacherId) {
  $stmt = db()->prepare("SELECT day_of_week, period_id, is_available FROM teacher_availability WHERE teacher_user_id=?");
  $stmt->execute([$teacherId]);
  foreach ($stmt->fetchAll() as $r) {
    $existing[$r['day_of_week'] . '-' . $r['period_id']] = (int)$r['is_available'];
  }
}
?>
<div class="card card-soft">
  <div class="card-body p-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>
        <div class="h5 fw-bold mb-1">Teacher Availability</div>
        <div class="text-muted small">Used by the timetable generator. Uncheck a slot if the teacher is not available.</div>
      </div>

      <form class="d-flex gap-2 align-items-center" method="get" action="<?= BASE_URL ?>/index.php">
        <input type="hidden" name="page" value="admin_availability">
        <select class="form-select" name="teacher_id" onchange="this.form.submit()">
          <?php foreach($teachers as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= ($teacherId===(int)$t['id'])?'selected':'' ?>><?= htmlspecialchars($t['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <?php if (!$teacherId): ?>
      <div class="alert alert-warning mt-3">No teachers found. Create teacher users first.</div>
    <?php else: ?>
      <form class="mt-3" data-api="<?= BASE_URL ?>/app/api/availability_save.php">
        <input type="hidden" name="teacher_user_id" value="<?= (int)$teacherId ?>">
        <div class="table-responsive">
          <table class="table table-dark table-bordered align-middle">
            <thead class="text-muted">
              <tr>
                <th>Period</th>
                <?php foreach($days as $d): ?><th class="text-center"><?= $d ?></th><?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach($periods as $p): ?>
                <tr>
                  <td class="fw-semibold">
                    <?= htmlspecialchars($p['label']) ?>
                    <div class="text-muted small"><?= htmlspecialchars($p['start_time']) ?>–<?= htmlspecialchars($p['end_time']) ?></div>
                  </td>
                  <?php foreach($days as $dow=>$label): 
                    $key = $dow . '-' . $p['id'];
                    $val = $existing[$key] ?? 1;
                    $disabled = ((int)$p['is_teaching_period']===0) ? 'disabled' : '';
                    $checked = ((int)$p['is_teaching_period']===0) ? '' : (($val===1)?'checked':'');
                  ?>
                    <td class="text-center">
                      <div class="form-check form-switch d-flex justify-content-center">
                        <input class="form-check-input" type="checkbox" name="slot_<?= $dow ?>_<?= (int)$p['id'] ?>" <?= $checked ?> <?= $disabled ?>>
                      </div>
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-end">
          <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Save availability</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>
