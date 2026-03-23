<?php
require_role(['admin']);

require_once __DIR__ . '/../timetable_helpers.php';

$classes = db()->query("SELECT id, name, grade_level, room_number FROM classes ORDER BY name")->fetchAll();
$teachers = db()->query("SELECT id, full_name, email FROM users WHERE role='teacher' AND is_active=1 ORDER BY full_name")->fetchAll();
$entryCount = (int)(db()->query("SELECT COUNT(*) AS c FROM timetable_entries")->fetch()['c'] ?? 0);
?>
<div class="row g-3">
  <div class="col-12">
    <div class="card card-soft">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
          <div>
            <div class="h5 fw-bold mb-1">Timetable Exports</div>
            <div class="text-muted small">Download class timetables, teacher timetables, and the full school timetable after generation.</div>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-soft" href="<?= BASE_URL ?>/index.php?page=timetable">
              <i class="bi bi-calendar3-week me-1"></i>Open Timetable
            </a>
            <a class="btn btn-primary" href="<?= BASE_URL ?>/app/api/export_school_timetable_pdf.php">
              <i class="bi bi-file-earmark-pdf me-1"></i>Download School A3 PDF
            </a>
            <a class="btn btn-soft" href="<?= BASE_URL ?>/app/api/export_all_class_timetables_zip.php">
              <i class="bi bi-file-earmark-zip me-1"></i>All Classes ZIP
            </a>
            <a class="btn btn-soft" href="<?= BASE_URL ?>/app/api/export_all_teacher_timetables_zip.php">
              <i class="bi bi-file-earmark-zip me-1"></i>All Teachers ZIP
            </a>
          </div>
        </div>

        <div class="row g-3 mt-1">
          <div class="col-md-4">
            <div class="card card-soft h-100">
              <div class="card-body">
                <div class="text-muted small">Classes</div>
                <div class="fw-bold fs-4"><?= count($classes) ?></div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card card-soft h-100">
              <div class="card-body">
                <div class="text-muted small">Teachers</div>
                <div class="fw-bold fs-4"><?= count($teachers) ?></div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card card-soft h-100">
              <div class="card-body">
                <div class="text-muted small">Scheduled Slots</div>
                <div class="fw-bold fs-4"><?= $entryCount ?></div>
              </div>
            </div>
          </div>
        </div>

        <?php if ($entryCount === 0): ?>
          <div class="alert alert-warning mt-3 mb-0">
            No timetable entries exist yet. Save availability and assignments, then generate the timetable first.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-xl-6">
    <div class="card card-soft h-100">
      <div class="card-body p-4">
        <div class="h5 fw-bold mb-1">Class Timetables</div>
        <div class="text-muted small">Each class can be downloaded as CSV or PDF.</div>

        <div class="table-responsive mt-3">
          <table class="table table-dark table-hover align-middle">
            <thead class="text-muted">
              <tr>
                <th>Class</th>
                <th>Level / Room</th>
                <th class="text-end">Downloads</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($classes as $class): ?>
                <tr>
                  <td class="fw-semibold"><?= htmlspecialchars($class['name']) ?></td>
                  <td class="text-muted">
                    <?= htmlspecialchars((string)($class['grade_level'] ?: '-')) ?>
                    /
                    <?= htmlspecialchars((string)($class['room_number'] ?: '-')) ?>
                  </td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-2">
                      <a class="btn btn-soft btn-sm" href="<?= BASE_URL ?>/app/api/export_timetable_csv.php?class_id=<?= (int)$class['id'] ?>">
                        <i class="bi bi-filetype-csv me-1"></i>CSV
                      </a>
                      <a class="btn btn-soft btn-sm" href="<?= BASE_URL ?>/app/api/export_timetable_pdf.php?class_id=<?= (int)$class['id'] ?>">
                        <i class="bi bi-filetype-pdf me-1"></i>PDF
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$classes): ?>
                <tr><td colspan="3" class="text-muted">No classes found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-6">
    <div class="card card-soft h-100">
      <div class="card-body p-4">
        <div class="h5 fw-bold mb-1">Teacher Timetables</div>
        <div class="text-muted small">Each teacher export lists the day, period, class, and subject they teach.</div>

        <div class="table-responsive mt-3">
          <table class="table table-dark table-hover align-middle">
            <thead class="text-muted">
              <tr>
                <th>Teacher</th>
                <th>Email</th>
                <th class="text-end">Downloads</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($teachers as $teacher): ?>
                <tr>
                  <td class="fw-semibold"><?= htmlspecialchars($teacher['full_name']) ?></td>
                  <td class="text-muted"><?= htmlspecialchars($teacher['email']) ?></td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-2">
                      <a class="btn btn-soft btn-sm" href="<?= BASE_URL ?>/app/api/export_teacher_timetable_csv.php?teacher_user_id=<?= (int)$teacher['id'] ?>">
                        <i class="bi bi-filetype-csv me-1"></i>CSV
                      </a>
                      <a class="btn btn-soft btn-sm" href="<?= BASE_URL ?>/app/api/export_teacher_timetable_pdf.php?teacher_user_id=<?= (int)$teacher['id'] ?>">
                        <i class="bi bi-filetype-pdf me-1"></i>PDF
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$teachers): ?>
                <tr><td colspan="3" class="text-muted">No teachers found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
