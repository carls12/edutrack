<?php
require_role(['admin']);

$rows = db()->query("
    SELECT u.full_name, u.email, c.name AS class_name,
           a.plain_password, a.created_at,
           creator.full_name AS created_by
    FROM   prefect_password_audit a
    JOIN   users  u       ON u.id  = a.prefect_user_id
    JOIN   classes c      ON c.id  = a.class_id
    JOIN   users  creator ON creator.id = a.created_by_user_id
    ORDER  BY a.created_at DESC
")->fetchAll();
?>

<div class="card card-soft">
  <div class="card-body p-4">

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
      <div>
        <div class="h5 fw-bold mb-1">Prefect Credentials</div>
        <div class="text-muted small">All prefect login details — visible to admins only. Keep this page confidential.</div>
      </div>
      <span class="badge text-bg-warning fs-6 px-3 py-2">
        <i class="bi bi-shield-lock me-1"></i>Admin eyes only
      </span>
    </div>

    <?php if (!$rows): ?>
      <div class="alert alert-info">No prefects have been created yet.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-dark table-hover align-middle">
          <thead>
            <tr>
              <th>Prefect Name</th>
              <th>Class</th>
              <th>Email</th>
              <th>Password</th>
              <th>Created</th>
              <th>Created by</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td class="fw-semibold"><?= htmlspecialchars($r['full_name']) ?></td>
                <td><?= htmlspecialchars($r['class_name']) ?></td>
                <td class="font-monospace small"><?= htmlspecialchars($r['email']) ?></td>
                <td>
                  <span class="font-monospace" id="pw-<?= md5($r['email'].$r['created_at']) ?>">
                    <span class="pw-hidden">••••••••••</span>
                    <span class="pw-plain d-none fw-bold text-warning"><?= htmlspecialchars($r['plain_password']) ?></span>
                  </span>
                </td>
                <td class="text-muted small"><?= date('d M Y, H:i', strtotime($r['created_at'])) ?></td>
                <td class="text-muted small"><?= htmlspecialchars($r['created_by']) ?></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-secondary me-1 btn-toggle-pw" title="Show/hide password">
                    <i class="bi bi-eye"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-primary btn-copy-pw"
                          data-pw="<?= htmlspecialchars($r['plain_password']) ?>"
                          title="Copy password">
                    <i class="bi bi-clipboard"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

  </div>
</div>

<script>
document.querySelectorAll('.btn-toggle-pw').forEach(btn => {
  btn.addEventListener('click', () => {
    const row  = btn.closest('tr');
    const hide = row.querySelector('.pw-hidden');
    const show = row.querySelector('.pw-plain');
    const icon = btn.querySelector('i');
    if (hide.classList.contains('d-none')) {
      hide.classList.remove('d-none');
      show.classList.add('d-none');
      icon.className = 'bi bi-eye';
    } else {
      hide.classList.add('d-none');
      show.classList.remove('d-none');
      icon.className = 'bi bi-eye-slash';
    }
  });
});

document.querySelectorAll('.btn-copy-pw').forEach(btn => {
  btn.addEventListener('click', () => {
    navigator.clipboard.writeText(btn.dataset.pw).then(() => {
      const icon = btn.querySelector('i');
      icon.className = 'bi bi-clipboard-check';
      btn.classList.replace('btn-outline-primary', 'btn-success');
      setTimeout(() => {
        icon.className = 'bi bi-clipboard';
        btn.classList.replace('btn-success', 'btn-outline-primary');
      }, 1800);
    });
  });
});
</script>
