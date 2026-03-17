<?php
require_role(['admin']);

$rows = db()->query("SELECT id, full_name, email, role, is_active, created_at FROM users ORDER BY created_at DESC")->fetchAll();
?>
<div class="row g-3">
  <div class="col-12">
    <div class="card card-soft">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between gap-2">
          <div>
            <div class="h5 fw-bold mb-1">Users</div>
            <div class="text-muted small">Create admins, principals, teachers, and prefects. Non-admin emails are generated automatically from the school name.</div>
          </div>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateUser">
            <i class="bi bi-plus-lg me-1"></i>Add user
          </button>
        </div>

        <div class="table-responsive mt-3">
          <table class="table table-dark table-hover align-middle">
            <thead class="text-muted">
              <tr>
                <th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
              <tr>
                <td class="fw-semibold"><?= htmlspecialchars($r['full_name']) ?></td>
                <td class="text-muted"><?= htmlspecialchars($r['email']) ?></td>
                <td><span class="badge text-bg-secondary"><?= htmlspecialchars($r['role']) ?></span></td>
                <td>
                  <?php if ((int)$r['is_active'] === 1): ?>
                    <span class="badge text-bg-success">Active</span>
                  <?php else: ?>
                    <span class="badge text-bg-danger">Disabled</span>
                  <?php endif; ?>
                </td>
                <td class="text-muted small"><?= htmlspecialchars($r['created_at']) ?></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-soft" data-bs-toggle="modal" data-bs-target="#modalEditUser"
                    data-id="<?= (int)$r['id'] ?>"
                    data-full_name="<?= htmlspecialchars($r['full_name'], ENT_QUOTES) ?>"
                    data-email="<?= htmlspecialchars($r['email'], ENT_QUOTES) ?>"
                    data-role="<?= htmlspecialchars($r['role'], ENT_QUOTES) ?>"
                    data-is_active="<?= (int)$r['is_active'] ?>">
                    <i class="bi bi-pencil me-1"></i>Edit
                  </button>
                  <button class="btn btn-sm btn-outline-danger" data-action="delete" data-api="<?= BASE_URL ?>/app/api/user_delete.php" data-id="<?= (int)$r['id'] ?>">
                    <i class="bi bi-trash me-1"></i>Delete
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="text-muted small mt-2">
          Note: You cannot delete your own currently logged-in account.
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalCreateUser" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Add User</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form class="modal-body" data-api="<?= BASE_URL ?>/app/api/user_create.php">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Full name</label>
            <input class="form-control" name="full_name" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input class="form-control" name="email" id="createUserEmail" type="email" required>
            <div class="form-text" id="createUserEmailHelp">Required for admin accounts. Other roles get school-based emails automatically.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Role</label>
            <select class="form-select" name="role" id="createUserRole" required>
              <option value="admin">Admin</option>
              <option value="principal">Principal</option>
              <option value="teacher">Teacher</option>
              <option value="prefect">Prefect</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Temporary password</label>
            <input class="form-control" name="password" type="text" required placeholder="e.g. Welcome@123">
          </div>
          <div class="col-md-6">
            <label class="form-label">Active</label>
            <select class="form-select" name="is_active">
              <option value="1" selected>Active</option>
              <option value="0">Disabled</option>
            </select>
          </div>

          <div class="col-12">
            <div class="alert alert-secondary mb-0">
              If role is <b>Teacher</b>, also go to <b>Admin Teachers</b> to configure salary type and rates.
            </div>
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

<div class="modal fade" id="modalEditUser" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Edit User</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form class="modal-body" data-api="<?= BASE_URL ?>/app/api/user_update.php">
        <input type="hidden" name="id" id="editUserId">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Full name</label>
            <input class="form-control" name="full_name" id="editUserName" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input class="form-control" name="email" id="editUserEmail" type="email" required>
            <div class="form-text" id="editUserEmailHelp">Required for admin accounts. Other roles get school-based emails automatically.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Role</label>
            <select class="form-select" name="role" id="editUserRole" required>
              <option value="admin">Admin</option>
              <option value="principal">Principal</option>
              <option value="teacher">Teacher</option>
              <option value="prefect">Prefect</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Active</label>
            <select class="form-select" name="is_active" id="editUserActive">
              <option value="1">Active</option>
              <option value="0">Disabled</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Reset password (optional)</label>
            <input class="form-control" name="password" type="text" placeholder="Leave blank to keep current password">
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
function syncUserEmailField(roleSelectId, emailInputId, helpId, preserveValue = false){
  const roleEl = document.getElementById(roleSelectId);
  const emailEl = document.getElementById(emailInputId);
  const helpEl = document.getElementById(helpId);
  if(!roleEl || !emailEl) return;

  const isAdmin = roleEl.value === 'admin';
  emailEl.readOnly = !isAdmin;
  emailEl.placeholder = isAdmin ? 'admin@example.com' : 'Auto-generated from school name';
  if (!isAdmin && !preserveValue) {
    emailEl.value = '';
  }
  if (helpEl) {
    helpEl.textContent = isAdmin
      ? 'Admin email is entered manually.'
      : 'This email is generated automatically from the school name and full name.';
  }
}

document.getElementById('createUserRole')?.addEventListener('change', ()=> syncUserEmailField('createUserRole', 'createUserEmail', 'createUserEmailHelp'));
syncUserEmailField('createUserRole', 'createUserEmail', 'createUserEmailHelp');

const editModal = document.getElementById('modalEditUser');
editModal?.addEventListener('show.bs.modal', (e)=>{
  const b = e.relatedTarget;
  document.getElementById('editUserId').value = b.dataset.id;
  document.getElementById('editUserName').value = b.dataset.full_name;
  document.getElementById('editUserEmail').value = b.dataset.email;
  document.getElementById('editUserRole').value = b.dataset.role;
  document.getElementById('editUserActive').value = b.dataset.is_active;
  syncUserEmailField('editUserRole', 'editUserEmail', 'editUserEmailHelp', true);
});

document.getElementById('editUserRole')?.addEventListener('change', ()=> syncUserEmailField('editUserRole', 'editUserEmail', 'editUserEmailHelp'));
</script>
