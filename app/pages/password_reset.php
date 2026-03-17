<?php
require_role(['admin']);
?>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card card-soft">
      <div class="card-body p-4">
        <div class="h5 fw-bold mb-1">Password Reset (Non-Prefect Users)</div>
        <div class="text-muted small">Step 1: validate email. Step 2: set new password.</div>

        <form id="lookupForm" class="mt-3" data-api="<?= BASE_URL ?>/app/api/password_reset_lookup.php" data-on-success="onLookupUser">
          <div class="row g-2">
            <div class="col-md-8">
              <label class="form-label">User Email</label>
              <input class="form-control" type="email" name="email" required placeholder="user@edutrack.local">
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search me-1"></i>Validate Email</button>
            </div>
          </div>
        </form>

        <div id="lookupResult" class="alert alert-secondary mt-3 d-none"></div>

        <form id="resetForm" class="mt-3 d-none" data-api="<?= BASE_URL ?>/app/api/password_reset_update.php" data-on-success="onResetDone">
          <input type="hidden" name="email" id="resetEmail">
          <div class="row g-2">
            <div class="col-md-8">
              <label class="form-label">New Password</label>
              <input class="form-control" type="text" name="new_password" required minlength="8" placeholder="New temporary password">
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <button class="btn btn-primary w-100" type="submit"><i class="bi bi-key me-1"></i>Reset Password</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function onLookupUser(out){
  const box = document.getElementById('lookupResult');
  const form = document.getElementById('resetForm');
  const emailInput = document.getElementById('resetEmail');
  box.classList.remove('d-none');
  box.innerHTML = `Validated: <b>${out.full_name}</b> (${out.role}) · <code>${out.email}</code>`;
  form.classList.remove('d-none');
  emailInput.value = out.email;
}
function onResetDone(){
  toast("Password reset successfully", "success");
  document.getElementById('resetForm')?.classList.add('d-none');
}
</script>
