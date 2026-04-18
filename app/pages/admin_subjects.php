<?php
require_role(['admin']);

// Schema migrations
try { db()->exec("ALTER TABLE subjects ADD COLUMN is_practical TINYINT(1) NOT NULL DEFAULT 0"); } catch(Throwable $e){}
try { db()->exec("ALTER TABLE subjects ADD COLUMN parent_subject_id INT NULL"); } catch(Throwable $e){}
try { db()->exec("ALTER TABLE subjects ADD CONSTRAINT fk_subj_parent FOREIGN KEY (parent_subject_id) REFERENCES subjects(id) ON DELETE SET NULL"); } catch(Throwable $e){}

// Fetch: parents first, then their practicals grouped under them
$rows = db()->query("
    SELECT s.id, s.code, s.name, s.is_active, s.is_practical, s.parent_subject_id,
           p.name AS parent_name, p.code AS parent_code,
           (SELECT COUNT(*) FROM subjects ch WHERE ch.parent_subject_id = s.id AND ch.is_practical=1) AS practical_count
    FROM subjects s
    LEFT JOIN subjects p ON p.id = s.parent_subject_id
    ORDER BY COALESCE(p.code, s.code), s.is_practical, s.code
")->fetchAll();

$classes = db()->query("SELECT id, name FROM classes ORDER BY name")->fetchAll();

// For practical modal: teacher assignments map [subject_id][class_id] => [teacher_ids]
$assignmentMap = [];
foreach (db()->query("
    SELECT a.subject_id, a.class_id, a.teacher_user_id, u.full_name
    FROM teacher_assignments a
    JOIN users u ON u.id = a.teacher_user_id
")->fetchAll() as $a) {
    $assignmentMap[(int)$a['subject_id']][(int)$a['class_id']][] = [
        'id'   => (int)$a['teacher_user_id'],
        'name' => $a['full_name'],
    ];
}
db()->exec("
  CREATE TABLE IF NOT EXISTS subject_pairs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id_1 INT NOT NULL,
    subject_id_2 INT NOT NULL,
    class_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_subject_pair (class_id, subject_id_1, subject_id_2),
    FOREIGN KEY (subject_id_1) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id_2) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
  )
");
$hasClassCol = (int)db()->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='subject_pairs' AND COLUMN_NAME='class_id'")->fetchColumn();
if (!$hasClassCol) {
  db()->exec("ALTER TABLE subject_pairs ADD COLUMN class_id INT NULL");
  try { db()->exec("ALTER TABLE subject_pairs DROP INDEX uniq_subject_pair"); } catch(Throwable $e){}
  try { db()->exec("ALTER TABLE subject_pairs ADD UNIQUE KEY uniq_subject_pair (class_id, subject_id_1, subject_id_2)"); } catch(Throwable $e){}
  try { db()->exec("ALTER TABLE subject_pairs ADD CONSTRAINT fk_sp_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE"); } catch(Throwable $e){}
}
$subjectPairs = db()->query("
  SELECT sp.id, sp.subject_id_1, sp.subject_id_2, sp.class_id, sp.is_active,
         s1.code AS code_1, s1.name AS name_1,
         s2.code AS code_2, s2.name AS name_2,
         c.name AS class_name
  FROM subject_pairs sp
  JOIN subjects s1 ON s1.id = sp.subject_id_1
  JOIN subjects s2 ON s2.id = sp.subject_id_2
  LEFT JOIN classes c ON c.id = sp.class_id
  ORDER BY c.name, s1.code, s2.code
")->fetchAll();
?>
<div class="card card-soft">
  <div class="card-body p-4">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <div class="h5 fw-bold mb-1">Subjects</div>
        <div class="text-muted small">Create subjects with unique codes (e.g., MATH, ENG).</div>
      </div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalSubjectCreate"><i class="bi bi-plus-lg me-1"></i>Add subject</button>
    </div>

    <div class="table-responsive mt-3">
      <table class="table table-dark table-hover align-middle">
        <thead class="text-muted">
          <tr><th>Code</th><th>Name</th><th>Status</th><th class="text-end">Action</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            $isPractical = (int)$r['is_practical'] === 1;
          ?>
          <tr <?= $isPractical ? 'style="background:rgba(99,102,241,.08)"' : '' ?>>
            <td class="fw-semibold" <?= $isPractical ? 'style="padding-left:2rem"' : '' ?>>
              <?php if ($isPractical): ?>
                <span class="text-muted me-1" style="font-size:.75rem;">└</span>
              <?php endif; ?>
              <?= htmlspecialchars($r['code']) ?>
              <?php if ($isPractical): ?>
                <span class="badge text-bg-info ms-1" style="font-size:.65rem;">Lab</span>
              <?php endif; ?>
            </td>
            <td>
              <?= htmlspecialchars($r['name']) ?>
              <?php if (!$isPractical && (int)$r['practical_count'] > 0): ?>
                <span class="badge text-bg-secondary ms-2" title="Has practicals">
                  <i class="bi bi-eyedropper me-1"></i><?= (int)$r['practical_count'] ?> practical<?= (int)$r['practical_count'] > 1 ? 's' : '' ?>
                </span>
              <?php endif; ?>
            </td>
            <td><?= ((int)$r['is_active']===1) ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-danger">Disabled</span>' ?></td>
            <td class="text-end">
              <?php if (!$isPractical): ?>
              <button class="btn btn-sm btn-outline-info me-1" data-bs-toggle="modal" data-bs-target="#modalPracticalCreate"
                data-parent_id="<?= (int)$r['id'] ?>"
                data-parent_code="<?= htmlspecialchars($r['code'], ENT_QUOTES) ?>"
                data-parent_name="<?= htmlspecialchars($r['name'], ENT_QUOTES) ?>">
                <i class="bi bi-eyedropper me-1"></i><?= (int)$r['practical_count'] > 0 ? 'Add Another Practical' : 'Add Practical' ?>
              </button>
              <?php endif; ?>
              <button class="btn btn-sm btn-soft" data-bs-toggle="modal" data-bs-target="#modalSubjectEdit"
                data-id="<?= (int)$r['id'] ?>"
                data-code="<?= htmlspecialchars($r['code'], ENT_QUOTES) ?>"
                data-name="<?= htmlspecialchars($r['name'], ENT_QUOTES) ?>"
                data-is_active="<?= (int)$r['is_active'] ?>">
                <i class="bi bi-pencil me-1"></i>Edit
              </button>
              <button class="btn btn-sm btn-outline-danger" data-action="delete" data-api="<?= BASE_URL ?>/app/api/subject_delete.php" data-id="<?= (int)$r['id'] ?>">
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

<!-- Add Practical Modal -->
<div class="modal fade" id="modalPracticalCreate" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold"><i class="bi bi-eyedropper me-2"></i>Add Practical Session</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form class="modal-body" data-api="<?= BASE_URL ?>/app/api/practical_create.php">
        <input type="hidden" name="parent_subject_id" id="pcParentId">
        <div class="alert alert-secondary py-2 small mb-3" id="pcParentInfo"></div>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Code</label>
            <input class="form-control" name="code" id="pcCode" required placeholder="BIOP">
          </div>
          <div class="col-md-8">
            <label class="form-label">Name</label>
            <input class="form-control" name="name" id="pcName" required placeholder="Biology Practical">
          </div>
          <div class="col-md-6">
            <label class="form-label">Class</label>
            <select class="form-select" name="class_id" id="pcClass" required>
              <option value="">Select class…</option>
              <?php foreach ($classes as $cls): ?>
                <option value="<?= (int)$cls['id'] ?>"><?= htmlspecialchars($cls['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Teacher</label>
            <select class="form-select" name="teacher_user_id" id="pcTeacher" required>
              <option value="">Select a class first…</option>
            </select>
            <div class="form-text">Only teachers assigned to this subject in the selected class are shown.</div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Periods / week</label>
            <input class="form-control" type="number" name="hours_per_week" min="1" max="20" value="3" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select class="form-select" name="is_active">
              <option value="1" selected>Active</option>
              <option value="0">Disabled</option>
            </select>
          </div>
        </div>
        <div class="modal-footer px-0 pb-0">
          <button class="btn btn-soft" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-info" type="submit">Create Practical</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const _assignMap = <?= json_encode($assignmentMap, JSON_THROW_ON_ERROR) ?>;
let _pcParentSubjectId = null;

function pcUpdateTeachers() {
  const classId   = parseInt(document.getElementById('pcClass').value) || 0;
  const sel       = document.getElementById('pcTeacher');
  const prevVal   = sel.value;

  // Remove all existing options except the placeholder
  [...sel.options].forEach(o => { if (o.value) o.remove(); });

  if (!classId || !_pcParentSubjectId) return;

  const teachers = _assignMap[_pcParentSubjectId]?.[classId] ?? [];
  if (teachers.length === 0) {
    const opt = new Option('— No teacher assigned to this subject in this class —', '');
    opt.disabled = true;
    sel.add(opt);
  } else {
    teachers.forEach(t => {
      const opt = new Option(t.name, t.id);
      sel.add(opt);
    });
    // Restore previous selection if still valid
    if ([...sel.options].some(o => o.value == prevVal)) sel.value = prevVal;
    else if (teachers.length === 1) sel.value = teachers[0].id;
  }
}

document.getElementById('modalPracticalCreate')?.addEventListener('show.bs.modal', (e) => {
  const b = e.relatedTarget;
  _pcParentSubjectId = parseInt(b.dataset.parent_id);
  document.getElementById('pcParentId').value = b.dataset.parent_id;
  document.getElementById('pcCode').value     = b.dataset.parent_code + 'P';
  document.getElementById('pcName').value     = b.dataset.parent_name + ' Practical';
  document.getElementById('pcParentInfo').textContent = 'Practical for: ' + b.dataset.parent_code + ' — ' + b.dataset.parent_name;
  document.getElementById('pcClass').value    = '';
  // Reset teacher dropdown to placeholder only
  const sel = document.getElementById('pcTeacher');
  [...sel.options].forEach(o => { if (o.value) o.remove(); });
});

document.getElementById('pcClass')?.addEventListener('change', pcUpdateTeachers);
</script>

<div class="card card-soft mt-3">
  <div class="card-body p-4">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <div class="h5 fw-bold mb-1">Subject Pairing</div>
        <div class="text-muted small">Use this to define subjects that may run in parallel for the same class.</div>
      </div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalSubjectPairCreate"><i class="bi bi-plus-lg me-1"></i>Add Pair</button>
    </div>

    <div class="table-responsive mt-3">
      <table class="table table-dark table-hover align-middle">
        <thead class="text-muted">
          <tr><th>Class</th><th>Subject 1</th><th>Subject 2</th><th>Status</th><th class="text-end">Action</th></tr>
        </thead>
        <tbody>
          <?php foreach ($subjectPairs as $pair): ?>
          <tr>
            <td><?= $pair['class_name'] ? '<span class="badge text-bg-secondary">'.htmlspecialchars($pair['class_name']).'</span>' : '<span class="text-muted small">All classes</span>' ?></td>
            <td class="fw-semibold"><?= htmlspecialchars($pair['code_1']) ?> - <?= htmlspecialchars($pair['name_1']) ?></td>
            <td><?= htmlspecialchars($pair['code_2']) ?> - <?= htmlspecialchars($pair['name_2']) ?></td>
            <td><?= ((int)$pair['is_active'] === 1) ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-danger">Disabled</span>' ?></td>
            <td class="text-end">
              <button class="btn btn-sm btn-soft" data-bs-toggle="modal" data-bs-target="#modalSubjectPairEdit"
                data-id="<?= (int)$pair['id'] ?>"
                data-subject_id_1="<?= (int)$pair['subject_id_1'] ?>"
                data-subject_id_2="<?= (int)$pair['subject_id_2'] ?>"
                data-class_id="<?= (int)$pair['class_id'] ?>"
                data-is_active="<?= (int)$pair['is_active'] ?>">
                <i class="bi bi-pencil me-1"></i>Edit
              </button>
              <button class="btn btn-sm btn-outline-danger" data-action="delete" data-api="<?= BASE_URL ?>/app/api/subject_pair_delete.php" data-id="<?= (int)$pair['id'] ?>">
                <i class="bi bi-trash me-1"></i>Delete
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$subjectPairs): ?><tr><td colspan="5" class="text-muted">No subject pairs configured yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="modalSubjectCreate" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Add Subject</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form class="modal-body" data-api="<?= BASE_URL ?>/app/api/subject_create.php">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Code</label>
            <input class="form-control" name="code" required placeholder="MATH">
          </div>
          <div class="col-md-8">
            <label class="form-label">Name</label>
            <input class="form-control" name="name" required placeholder="Mathematics">
          </div>
          <div class="col-md-4">
            <label class="form-label">Active</label>
            <select class="form-select" name="is_active">
              <option value="1" selected>Active</option>
              <option value="0">Disabled</option>
            </select>
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

<div class="modal fade" id="modalSubjectEdit" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Edit Subject</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form class="modal-body" data-api="<?= BASE_URL ?>/app/api/subject_update.php">
        <input type="hidden" name="id" id="sId">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Code</label>
            <input class="form-control" name="code" id="sCode" required>
          </div>
          <div class="col-md-8">
            <label class="form-label">Name</label>
            <input class="form-control" name="name" id="sName" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Active</label>
            <select class="form-select" name="is_active" id="sActive">
              <option value="1">Active</option>
              <option value="0">Disabled</option>
            </select>
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
document.getElementById('modalSubjectEdit')?.addEventListener('show.bs.modal', (e)=>{
  const b = e.relatedTarget;
  document.getElementById('sId').value = b.dataset.id;
  document.getElementById('sCode').value = b.dataset.code;
  document.getElementById('sName').value = b.dataset.name;
  document.getElementById('sActive').value = b.dataset.is_active;
});
</script>

<div class="modal fade" id="modalSubjectPairCreate" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Add Subject Pair</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form class="modal-body" data-api="<?= BASE_URL ?>/app/api/subject_pair_create.php">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Class</label>
            <select class="form-select" name="class_id" required>
              <option value="">Select class</option>
              <?php foreach ($classes as $cls): ?>
                <option value="<?= (int)$cls['id'] ?>"><?= htmlspecialchars($cls['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Subject 1</label>
            <select class="form-select" name="subject_id_1" required>
              <option value="">Select subject</option>
              <?php foreach ($rows as $r): ?>
                <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['code']) ?> - <?= htmlspecialchars($r['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Subject 2</label>
            <select class="form-select" name="subject_id_2" required>
              <option value="">Select subject</option>
              <?php foreach ($rows as $r): ?>
                <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['code']) ?> - <?= htmlspecialchars($r['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Active</label>
            <select class="form-select" name="is_active">
              <option value="1" selected>Active</option>
              <option value="0">Disabled</option>
            </select>
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

<div class="modal fade" id="modalSubjectPairEdit" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Edit Subject Pair</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form class="modal-body" data-api="<?= BASE_URL ?>/app/api/subject_pair_update.php">
        <input type="hidden" name="id" id="spId">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Class</label>
            <select class="form-select" name="class_id" id="spClassId" required>
              <option value="">Select class</option>
              <?php foreach ($classes as $cls): ?>
                <option value="<?= (int)$cls['id'] ?>"><?= htmlspecialchars($cls['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Subject 1</label>
            <select class="form-select" name="subject_id_1" id="spSubject1" required>
              <option value="">Select subject</option>
              <?php foreach ($rows as $r): ?>
                <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['code']) ?> - <?= htmlspecialchars($r['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Subject 2</label>
            <select class="form-select" name="subject_id_2" id="spSubject2" required>
              <option value="">Select subject</option>
              <?php foreach ($rows as $r): ?>
                <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['code']) ?> - <?= htmlspecialchars($r['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Active</label>
            <select class="form-select" name="is_active" id="spActive">
              <option value="1">Active</option>
              <option value="0">Disabled</option>
            </select>
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
document.getElementById('modalSubjectPairEdit')?.addEventListener('show.bs.modal', (e)=>{
  const b = e.relatedTarget;
  document.getElementById('spId').value = b.dataset.id;
  document.getElementById('spClassId').value = b.dataset.class_id;
  document.getElementById('spSubject1').value = b.dataset.subject_id_1;
  document.getElementById('spSubject2').value = b.dataset.subject_id_2;
  document.getElementById('spActive').value = b.dataset.is_active;
});
</script>
