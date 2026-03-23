<?php
require_once __DIR__ . '/../teacher_stamp.php';

teacher_stamp_ensure_schema();
$settings = db()->query("SELECT * FROM school_settings WHERE id=1")->fetch() ?: [
  'school_name' => APP_NAME,
  'logo_path' => null,
];
$cssVer = (string)(@filemtime(__DIR__ . '/../../public/assets/css/app.css') ?: time());
$logo = $settings['logo_path']
  ? (BASE_URL . '/' . ltrim((string)$settings['logo_path'], '/'))
  : (BASE_URL . '/assets/img/logo.svg');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title><?= htmlspecialchars($settings['school_name']) ?> | Teacher Stamp</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/app.css?v=<?= urlencode($cssVer) ?>" rel="stylesheet">
  <style>
    body.bg-app {
      background:
        radial-gradient(1200px 700px at 10% -10%, rgba(20, 184, 166, 0.16), transparent 55%),
        radial-gradient(900px 600px at 90% -5%, rgba(14, 165, 233, 0.16), transparent 60%),
        radial-gradient(1400px 700px at 50% 120%, rgba(8, 43, 68, 0.35), transparent 60%),
        linear-gradient(165deg, #03060b 0%, #070d16 52%, #0b1320 100%);
      color: #e6eefb;
      min-height: 100vh;
      font-family: 'Manrope', 'Segoe UI', Tahoma, sans-serif;
    }
    .card-soft,
    .card {
      background: linear-gradient(165deg, rgba(13, 20, 32, 0.92) 0%, rgba(16, 26, 41, 0.9) 100%);
      border: 1px solid rgba(150, 176, 210, 0.18);
      border-radius: 16px;
      box-shadow: 0 10px 35px rgba(0, 0, 0, 0.35);
      color: #e6eefb;
    }
    .form-control,
    .form-select {
      background: rgba(8, 15, 25, 0.72);
      border: 1px solid rgba(148, 163, 184, 0.24);
      color: #e6eefb;
      border-radius: 12px;
    }
    .form-control:focus,
    .form-select:focus {
      background: rgba(9, 17, 29, 0.92);
      color: #e6eefb;
      border-color: rgba(20, 184, 166, 0.62);
      box-shadow: 0 0 0 0.22rem rgba(20, 184, 166, 0.2);
    }
    .text-muted,
    .small.text-muted {
      color: #9aadc9 !important;
    }
    .btn-primary {
      background-image: linear-gradient(135deg, #14b8a6, #0891b2);
      border: 1px solid rgba(45, 212, 191, 0.45);
      color: #ecfeff;
    }
    .btn-soft {
      background: rgba(148, 163, 184, 0.1);
      border: 1px solid rgba(148, 163, 184, 0.2);
      color: #e6eefb;
    }
  </style>
</head>
<body class="bg-app">
  <main class="container py-4 py-lg-5 timestap-shell">
    <section class="timestap-hero card card-soft overflow-hidden mb-4">
      <div class="card-body p-4 p-lg-5">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
          <div class="d-flex align-items-center gap-3">
            <div class="timestap-logo-wrap">
              <img src="<?= htmlspecialchars($logo) ?>" alt="School logo" class="timestap-logo">
            </div>
            <div>
              <div class="text-uppercase small timestap-kicker">EduTrack Office Desk</div>
              <div class="display-6 fw-bold mb-1">Teacher Time Stamp</div>
              <div class="text-muted timestap-subtitle">Use your teacher code and your authenticator-app code. If your phone is unavailable, use a temporary office fallback code.</div>
            </div>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <span class="badge rounded-pill text-bg-secondary px-3 py-2">No Login Required</span>
            <span class="badge rounded-pill text-bg-dark px-3 py-2"><?= htmlspecialchars($settings['school_name'] ?? APP_NAME) ?></span>
          </div>
        </div>

        <div class="row g-3 mt-2">
          <div class="col-md-4">
            <div class="timestap-stat">
              <div class="text-muted small">Purpose</div>
              <div class="fw-semibold">Arrival and departure logging</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="timestap-stat">
              <div class="text-muted small">Security</div>
              <div class="fw-semibold">Teacher code + rotating 2FA</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="timestap-stat">
              <div class="text-muted small">Class rule</div>
              <div class="fw-semibold">Prefect cannot mark arrived first</div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card card-soft timestap-form-card">
          <div class="card-body p-4 p-lg-5">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
              <div>
                <div class="h4 fw-bold mb-1">Stamp Form</div>
                <div class="text-muted small">Enter the teacher code, 6-digit office code, then choose stamp in or stamp out.</div>
              </div>
              <i class="bi bi-shield-lock fs-2 timestap-icon"></i>
            </div>

            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Teacher Code</label>
                <input class="form-control form-control-lg timestap-input" id="stampCodeInput" placeholder="Example: TR102944" autocomplete="off" required>
              </div>
              <div class="col-12">
                <label class="form-label">Authenticator Or Temporary Code</label>
                <input class="form-control form-control-lg timestap-input" id="stampOtpInput" inputmode="numeric" pattern="\d{6}" maxlength="6" placeholder="000000" autocomplete="off" required>
              </div>
              <div class="col-12">
                <label class="form-label">Action</label>
                <div class="row g-2">
                  <div class="col-sm-6">
                    <button class="btn btn-primary timestap-choice w-100" id="stampInLabel" type="button" data-stamp-action="arrived">
                      <i class="bi bi-box-arrow-in-right me-1"></i>Stamp In
                    </button>
                  </div>
                  <div class="col-sm-6">
                    <button class="btn btn-soft timestap-choice w-100" id="stampOutLabel" type="button" data-stamp-action="departed">
                      <i class="bi bi-box-arrow-right me-1"></i>Stamp Out
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div id="stampAlert" class="alert d-none mt-4 mb-0"></div>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="card card-soft timestap-help-card h-100">
          <div class="card-body p-4 p-lg-5">
            <div class="h4 fw-bold mb-3">How It Works</div>
            <div class="timestap-steps">
              <div class="timestap-step">
                <span class="timestap-step-no">1</span>
                <div>Use the 6-digit code from your authenticator app or ask the office for a temporary fallback code if your phone is unavailable.</div>
              </div>
              <div class="timestap-step">
                <span class="timestap-step-no">2</span>
                <div>Enter your own teacher code and that 6-digit code here.</div>
              </div>
              <div class="timestap-step">
                <span class="timestap-step-no">3</span>
                <div>Stamp in before classes begin and stamp out when leaving school.</div>
              </div>
              <div class="timestap-step">
                <span class="timestap-step-no">4</span>
                <div>Prefects can only mark class arrival after the office stamp-in exists.</div>
              </div>
            </div>

            <div class="alert alert-secondary mt-4 mb-0">
              This page records school arrival and departure times only. Class attendance is still managed from the timetable cards.
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script>
    const alertBox = document.getElementById('stampAlert');
    const stampCodeInput = document.getElementById('stampCodeInput');
    const stampOtpInput = document.getElementById('stampOtpInput');
    const stampLabels = {
      arrived: document.getElementById('stampInLabel'),
      departed: document.getElementById('stampOutLabel'),
    };

    function showAlert(type, message) {
      alertBox.className = `alert alert-${type} mt-4 mb-0`;
      alertBox.textContent = message;
      alertBox.classList.remove('d-none');
    }

    async function submitStamp(action) {
      const stampCode = String(stampCodeInput.value || '').trim().toUpperCase();
      const otp = String(stampOtpInput.value || '').trim();
      if (stampCode === '' || otp === '') {
        showAlert('danger', 'Teacher code and 6-digit security code are required.');
        return;
      }
      try {
        const res = await fetch("<?= BASE_URL ?>/app/api/teacher_stamp.php", {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ stamp_code: stampCode, otp, action }),
        });
        const data = await res.json();
        if (!res.ok || data.ok === false) {
          throw new Error(data.error || `HTTP ${res.status}`);
        }

        const actionLabel = data.status === 'arrived' ? 'stamped in' : 'stamped out';
        showAlert('success', `${data.teacher_name} ${actionLabel} at ${data.event_time}.`);
        stampCodeInput.value = '';
        stampOtpInput.value = '';
        stampCodeInput.focus();
      } catch (error) {
        showAlert('danger', error.message || 'Could not save the stamp.');
      }
    }

    stampLabels.arrived.addEventListener('click', () => submitStamp('arrived'));
    stampLabels.departed.addEventListener('click', () => submitStamp('departed'));
  </script>
</body>
</html>
