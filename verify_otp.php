<?php
// verify_otp.php - OTP Verification Page
require_once 'config/db.php';

// If no pending mobile in session, redirect to login
$mobile = $_SESSION['pending_mobile'] ?? '';
if (!$mobile) {
    header('Location: index.php?login=1');
    exit;
}

$error   = $_GET['error']  ?? '';
$resent  = $_GET['resent'] ?? '';

// Mask mobile for display: show last 4 digits only
$masked_mobile = '+91 XXXXXX' . substr($mobile, -4);

// Time remaining for OTP expiry (session-based)
$otp_generated = $_SESSION['otp_generated'] ?? 0;
$seconds_elapsed = time() - $otp_generated;
$seconds_remaining = max(0, 600 - $seconds_elapsed);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify OTP - TicketNew</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/responsive.css">
  <link rel="icon" href="img/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="css/bootstrap-icons-1.13.1/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/bootstrap-form.css">
  <script src="js/bootstrap.bundle.min.js"></script>
  <style>
    /* ── OTP Page Layout ── */
    .otp-page {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #fafafa 0%, #f0f0f5 100%);
      padding: 20px;
    }

    .otp-card {
      background: #fff;
      border-radius: 20px;
      box-shadow: 0 8px 40px rgba(0, 0, 0, 0.12);
      padding: 44px 40px 36px;
      width: 100%;
      max-width: 420px;
      text-align: center;
    }

    /* ── Logo ── */
    .otp-logo {
      font-size: 1.6rem;
      font-weight: 900;
      letter-spacing: -0.5px;
      margin-bottom: 28px;
      display: block;
      text-decoration: none;
      color: inherit;
    }
    .otp-logo .logo-ticket { color: var(--red); }
    .otp-logo .logo-new    { color: var(--dark); }

    /* ── Shield Icon ── */
    .otp-icon {
      width: 68px;
      height: 68px;
      background: #fff0f3;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px;
      font-size: 2rem;
    }

    /* ── Heading ── */
    .otp-card h2 {
      font-size: 1.35rem;
      font-weight: 700;
      margin-bottom: 8px;
      color: var(--dark);
    }

    .otp-card p {
      font-size: 0.85rem;
      color: #666;
      margin-bottom: 30px;
      line-height: 1.6;
    }

    .otp-card p strong {
      color: var(--dark);
      font-weight: 600;
    }

    /* ── Alerts ── */
    .alert {
      padding: 12px 16px;
      border-radius: 10px;
      font-size: 0.82rem;
      font-weight: 500;
      margin-bottom: 20px;
      text-align: left;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .alert-error {
      background: #fff5f5;
      color: #c0392b;
      border: 1px solid #fecaca;
    }
    .alert-success {
      background: #f0fdf4;
      color: #15803d;
      border: 1px solid #bbf7d0;
    }

    /* ── OTP Input Boxes ── */
    .otp-inputs {
      display: flex;
      gap: 10px;
      justify-content: center;
      margin-bottom: 24px;
    }

    .otp-inputs input {
      width: 50px;
      height: 54px;
      border: 2px solid var(--border);
      border-radius: 12px;
      text-align: center;
      font-size: 1.4rem;
      font-weight: 700;
      font-family: inherit;
      color: var(--dark);
      background: #fafafa;
      outline: none;
      transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
      caret-color: transparent;
    }

    .otp-inputs input:focus {
      border-color: var(--red);
      background: #fff;
      box-shadow: 0 0 0 3px rgba(227, 24, 55, 0.1);
    }

    .otp-inputs input.filled {
      border-color: #aaa;
      background: #fff;
    }

    /* ── Hidden combined OTP field (submitted with form) ── */
    #otpHidden { display: none; }

    /* ── Submit Button ── */
    .btn-verify {
      width: 100%;
      padding: 14px;
      background: var(--red);
      color: #fff;
      font-family: inherit;
      font-size: 0.95rem;
      font-weight: 600;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      transition: background 0.2s, transform 0.1s;
      margin-bottom: 20px;
      letter-spacing: 0.3px;
    }

    .btn-verify:hover  { background: #c41230; }
    .btn-verify:active { transform: scale(0.98); }
    .btn-verify:disabled {
      background: #e0e0e0;
      color: #999;
      cursor: not-allowed;
      transform: none;
    }

    /* ── Resend / Timer ── */
    .resend-section {
      font-size: 0.82rem;
      color: #888;
      margin-bottom: 20px;
    }

    .resend-section #timer {
      font-weight: 600;
      color: var(--red);
    }

    .resend-section a.resend-link {
      color: var(--red);
      font-weight: 600;
      text-decoration: none;
      display: none; /* shown via JS when timer expires */
    }

    .resend-section a.resend-link:hover { text-decoration: underline; }

    /* ── Back link ── */
    .back-link {
      font-size: 0.8rem;
      color: #aaa;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      transition: color 0.2s;
    }
    .back-link:hover { color: var(--dark); }

    /* ── Demo OTP notice (remove in production) ── */
    .demo-notice {
      background: #fffbeb;
      border: 1px solid #fde68a;
      border-radius: 10px;
      padding: 10px 14px;
      font-size: 0.78rem;
      color: #92400e;
      margin-bottom: 20px;
      text-align: left;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .demo-notice strong { font-weight: 700; }

    /* ── Footer ── */
    .otp-footer {
      font-size: 0.72rem;
      color: #bbb;
      margin-top: 6px;
      line-height: 1.5;
    }

    /* ── Responsive ── */
    @media (max-width: 480px) {
      .otp-card { padding: 36px 24px 28px; }
      .otp-inputs input { width: 44px; height: 50px; font-size: 1.2rem; }
    }
  </style>
</head>
<body>

<div class="otp-page">
  <div class="otp-card">

    <!-- Logo -->
    <a href="index.php" class="otp-logo">
      <span class="logo-icon">🎫</span>
      <span class="logo-ticket">TICKE</span><span class="logo-new">NEW</span>
    </a>

    <!-- Icon -->
    <div class="otp-icon">🔐</div>

    <h2>Verify Your Number</h2>
    <p>
      We've sent a 6-digit OTP to<br>
      <strong><?= htmlspecialchars($masked_mobile) ?></strong>
    </p>

    <!-- Error / Success alerts -->
    <?php if ($error): ?>
      <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($resent): ?>
      <div class="alert alert-success">✅ OTP resent successfully!</div>
    <?php endif; ?>

    <!-- Demo notice: shows the OTP for testing. Remove in production. -->
    <?php if (!empty($_SESSION['demo_otp'])): ?>
      <div class="demo-notice">
        🧪 <span><strong>Demo mode:</strong> Your OTP is <strong><?= htmlspecialchars($_SESSION['demo_otp']) ?></strong></span>
      </div>
    <?php endif; ?>

    <!-- OTP Form -->
    <form method="POST" action="auth.php" id="otpForm">
      <input type="hidden" name="action" value="verify_otp">
      <input type="hidden" name="otp" id="otpHidden">

      <!-- 6 individual digit boxes -->
      <div class="otp-inputs" id="otpInputs">
        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" class="otp-box" data-index="0">
        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" class="otp-box" data-index="1">
        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" class="otp-box" data-index="2">
        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" class="otp-box" data-index="3">
        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" class="otp-box" data-index="4">
        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" class="otp-box" data-index="5">
      </div>

      <button type="submit" class="btn-verify" id="verifyBtn" disabled>Verify OTP</button>
    </form>

    <!-- Resend section -->
    <div class="resend-section">
      <span id="timerText">Resend OTP in <span id="timer"><?= $seconds_remaining ?>s</span></span>
      <a href="auth.php?action=resend" class="resend-link" id="resendLink">Resend OTP</a>
    </div>

    <!-- Back -->
    <a href="index.php" class="back-link">← Back to Home</a>

    <div class="otp-footer">By continuing, you agree to our Terms of Use &amp; Privacy Policy</div>

  </div><!-- /.otp-card -->
</div><!-- /.otp-page -->

<script>
(function () {
  const boxes     = document.querySelectorAll('.otp-box');
  const hidden    = document.getElementById('otpHidden');
  const verifyBtn = document.getElementById('verifyBtn');
  const form      = document.getElementById('otpForm');
  const timerText = document.getElementById('timerText');
  const timerEl   = document.getElementById('timer');
  const resendLink= document.getElementById('resendLink');

  // ── Focus first box on load ──
  boxes[0].focus();

  // ── OTP box keyboard handling ──
  boxes.forEach((box, i) => {
    box.addEventListener('keydown', (e) => {
      // Allow only digits, Backspace, Delete, Tab, Arrow keys
      const allowed = ['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight'];
      if (!allowed.includes(e.key) && !/^\d$/.test(e.key)) {
        e.preventDefault();
        return;
      }

      if (e.key === 'Backspace') {
        if (box.value) {
          box.value = '';
        } else if (i > 0) {
          boxes[i - 1].value = '';
          boxes[i - 1].focus();
        }
        updateState();
        e.preventDefault();
      }

      if (e.key === 'ArrowLeft'  && i > 0) boxes[i - 1].focus();
      if (e.key === 'ArrowRight' && i < 5) boxes[i + 1].focus();
    });

    box.addEventListener('input', (e) => {
      const val = e.target.value.replace(/[^0-9]/g, '');
      box.value = val ? val[val.length - 1] : '';  // Keep only last digit
      if (box.value && i < 5) boxes[i + 1].focus();
      updateState();
    });

    // Handle paste on any box
    box.addEventListener('paste', (e) => {
      e.preventDefault();
      const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '');
      if (!pasted) return;
      pasted.split('').slice(0, 6).forEach((digit, idx) => {
        if (boxes[i + idx]) boxes[i + idx].value = digit;
      });
      const nextFocus = Math.min(i + pasted.length, 5);
      boxes[nextFocus].focus();
      updateState();
    });
  });

  function updateState() {
    boxes.forEach(b => b.classList.toggle('filled', b.value !== ''));
    const otp = Array.from(boxes).map(b => b.value).join('');
    hidden.value = otp;
    verifyBtn.disabled = otp.length !== 6;
  }

  // ── Prevent double-submit ──
  form.addEventListener('submit', (e) => {
    const otp = Array.from(boxes).map(b => b.value).join('');
    if (otp.length !== 6) { e.preventDefault(); return; }
    hidden.value = otp;
    verifyBtn.disabled = true;
    verifyBtn.textContent = 'Verifying…';
  });

  // ── Countdown timer ──
  let remaining = <?= $seconds_remaining ?>;

  function tick() {
    if (remaining <= 0) {
      timerText.style.display  = 'none';
      resendLink.style.display = 'inline';
      return;
    }
    timerEl.textContent = remaining + 's';
    remaining--;
    setTimeout(tick, 1000);
  }
  tick();
})();
</script>

</body>
</html>