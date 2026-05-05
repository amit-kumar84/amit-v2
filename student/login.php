<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/lang.php';

if (current_user() && current_user()['role'] === 'student') redirect(url('student/dashboard.php'));

$loginConflict = $_SESSION['student_login_conflict'] ?? null;
if ($loginConflict && (int)($loginConflict['expires'] ?? 0) < time()) {
  unset($_SESSION['student_login_conflict']);
  $loginConflict = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

  $sessionAction = $_POST['session_action'] ?? '';
  if ($sessionAction === 'cancel_conflict') {
    unset($_SESSION['student_login_conflict']);
    flash('Login canceled.', 'info');
    redirect(url('student/login.php'));
  }

  if ($sessionAction === 'end_old_session') {
    $token = (string)($_POST['conflict_token'] ?? '');
    $pending = $_SESSION['student_login_conflict'] ?? null;
    if (!$pending || (int)($pending['expires'] ?? 0) < time() || empty($pending['token']) || !hash_equals((string)$pending['token'], $token)) {
      unset($_SESSION['student_login_conflict']);
      flash('Session request expired. Please login again.', 'error');
      redirect(url('student/login.php'));
    }

    $sidUser = db()->prepare('SELECT * FROM users WHERE id=? AND role="student" LIMIT 1');
    $sidUser->execute([(int)$pending['user_id']]);
    $u = $sidUser->fetch();
    if (!$u) {
      unset($_SESSION['student_login_conflict']);
      flash('Student account not found.', 'error');
      redirect(url('student/login.php'));
    }

    unset($u['password_hash']);
    session_regenerate_id(true);
    $_SESSION['user'] = $u;
    set_active_user_session((int)$u['id'], 'student', session_id());
    unset($_SESSION['student_login_conflict']);
    redirect(url('student/dashboard.php'));
  }

    $ident = trim($_POST['identifier'] ?? '');
    $dob   = trim($_POST['dob'] ?? '');
    $pwd   = $_POST['password'] ?? '';
    $stmt = db()->prepare('SELECT * FROM users WHERE role="student" AND (email = ? OR roll_number = ?) LIMIT 1');
    $stmt->execute([strtolower($ident), $ident]);
    $u = $stmt->fetch();
    if (!$u || !password_verify($pwd, $u['password_hash']) || $u['dob'] !== $dob) {
        flash('Invalid credentials. Please check Roll/Staff ID, DOB and password.', 'error');
        redirect(url('student/login.php'));
    }

      $activeSid = get_active_user_session_id((int)$u['id'], 'student');
      if ($activeSid && !hash_equals($activeSid, session_id())) {
        $_SESSION['student_login_conflict'] = [
          'user_id' => (int)$u['id'],
          'token' => bin2hex(random_bytes(16)),
          'expires' => time() + 300,
        ];
        flash('Your old session is already running on another browser/device.', 'warning');
        redirect(url('student/login.php'));
      }

    unset($u['password_hash']);
    session_regenerate_id(true);
      $_SESSION['user'] = $u;
      set_active_user_session((int)$u['id'], 'student', session_id());
      unset($_SESSION['student_login_conflict']);
    redirect(url('student/dashboard.php'));
}
$BODY_CLASS = 'auth-page';
$PAGE_TITLE = t('sl_title') . ' — ' . t('brand');
require __DIR__ . '/../includes/header.php';
?>
<div class="login-split">
  <div class="left d-none d-md-flex">
    <div>
      <div class="d-flex align-items-center gap-3 mb-5">
        <img src="<?= url('assets/icons/BEL-Logo-Trnsprent.png') ?>" style="width:48px; height:48px; background:#fff; padding:6px; border-radius:3px; object-fit:contain" alt="BEL">
        <div>
          <div class="fw-bold"><?= t('brand') ?></div>
          <div class="small text-secondary"><?= t('brand_unit') ?></div>
          <div class="small" style="color:var(--saffron); letter-spacing:0.15em"><?= t('gov_in_hi') ?> · GOVT OF INDIA</div>
        </div>
      </div>
      <h2 class="display-6 fw-bold">Secure. Fair.<br>Government-grade testing.</h2>
      <p class="text-secondary mt-3">Lockdown environment, proctoring and instant evaluation — purpose-built for BEL recruitment.</p>
      <div class="tricolor mt-4" style="width:140px"><span></span><span></span><span></span></div>
    </div>
    <small class="text-secondary">© <?= date('Y') ?> <?= t('brand') ?></small>
  </div>
  <div class="right">
    <form method="post" style="width:100%; max-width:560px">
      <?= csrf_input() ?>
      <div class="text-center mb-4">
        <img src="<?= url('assets/icons/BEL-Logo-Trnsprent.png') ?>" alt="BEL" style="max-width:140px; height:auto; object-fit:contain;">
      </div>
      <div class="d-flex align-items-center gap-3 mb-3">
        <div style="width:42px; height:42px; background:var(--navy); color:#fff; display:flex; align-items:center; justify-content:center; border-radius:3px; border-bottom: 2px solid var(--saffron)"><i class="fas fa-user-graduate"></i></div>
        <h3 class="fw-bold mb-0"><?= t('sl_title') ?></h3>
      </div>
      <p class="text-secondary small mb-4"><?= t('sl_sub') ?></p>

      <label class="form-label-xs"><?= t('sl_id') ?></label>
      <input name="identifier" class="form-control mb-3" required placeholder="BEL2034 / 21EC045">

      <label class="form-label-xs"><?= t('sl_dob') ?></label>
      <input type="date" name="dob" class="form-control mb-3" required>

      <label class="form-label-xs"><?= t('sl_pwd') ?></label>
      <input type="password" name="password" class="form-control mb-4" required>

      <button class="btn btn-navy w-100" data-testid="student-login-submit"><?= t('sl_submit') ?></button>

      <?php if ($loginConflict): ?>
        <div class="alert alert-warning mt-3 mb-0" role="alert">
          <div class="fw-bold mb-1">Old session already running</div>
          <div class="small">This student account is already logged in on another browser/device.</div>
          <div class="d-flex gap-2 mt-3">
            <form method="post" class="m-0">
              <?= csrf_input() ?>
              <input type="hidden" name="session_action" value="end_old_session">
              <input type="hidden" name="conflict_token" value="<?= h($loginConflict['token'] ?? '') ?>">
              <button type="submit" class="btn btn-sm btn-danger">End old session and login</button>
            </form>
            <form method="post" class="m-0">
              <?= csrf_input() ?>
              <input type="hidden" name="session_action" value="cancel_conflict">
              <button type="submit" class="btn btn-sm btn-outline-secondary">Cancel</button>
            </form>
          </div>
        </div>
      <?php endif; ?>

      <div class="mt-3 small">
        <a href="<?= url('index.php') ?>" class="text-decoration-none text-secondary"><i class="fas fa-arrow-left me-1"></i><?= t('sl_back') ?></a>
      </div>
      <div class="mt-4 small text-secondary" style="border-top:1px solid #e2e8f0; padding-top:10px; font-size:11px; line-height:1.5">
        <i class="fas fa-info-circle me-1"></i> For lost credentials, please contact the <b>BEL Kotdwar Examination Controller</b>. Passwords are not resettable online.
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script src="/assets/js/admin-login.js"></script>
