<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/lang.php';
$u = require_login('student');
$eid = (int)($_GET['exam_id'] ?? 0);
$stmt = db()->prepare('SELECT *, (SELECT COUNT(*) FROM questions WHERE exam_id=exams.id AND deleted_at IS NULL) AS qcount FROM exams WHERE id=?');
$stmt->execute([$eid]);
$exam = $stmt->fetch();
if (!$exam) { flash('Exam not found', 'error'); redirect(url('student/dashboard.php')); }
if (!is_exam_assigned((int)$u['id'], $eid)) { flash('This examination is not assigned to your account.', 'error'); redirect(url('student/dashboard.php')); }
$used = db()->prepare('SELECT COUNT(*) FROM attempts WHERE user_id=? AND exam_id=? AND status="submitted"');
$used->execute([$u['id'], $eid]);
$attempts_left = max(0, (int)$exam['max_attempts'] - (int)$used->fetchColumn());
$PAGE_TITLE = t('in_title');
require __DIR__ . '/../includes/header.php';
?>
<div class="tricolor"><span></span><span></span><span></span></div>
<header style="background:var(--navy); color:#fff; border-bottom: 2px solid var(--saffron); padding: 14px 0">
  <div class="container d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-2">
      <img src="<?= url('assets/icons/BEL-Logo-Trnsprent.png') ?>" style="width:40px; height:40px; background:#fff; padding:5px; border-radius:3px; object-fit:contain" alt="BEL">
      <span class="fw-bold">BEL Kotdwar · <?= t('in_title') ?></span>
    </div>
    <a href="<?= url('student/dashboard.php') ?>" class="btn btn-sm btn-outline-light"><i class="fas fa-arrow-left"></i> <?= t('sl_back') ?></a>
  </div>
</header>

<main class="container py-4" style="max-width:900px">
  <div class="exam-card">
    <div class="border-bottom pb-3 mb-4">
      <h2 class="fw-bold"><?= h($exam['exam_name']) ?></h2>
      <p class="text-secondary mb-0">Duration: <b><?= (int)$exam['duration_minutes'] ?> min</b> · Questions: <b><?= (int)$exam['qcount'] ?></b> · Attempts left: <b><?= $attempts_left ?></b></p>
    </div>
    <?php foreach ([
      ['1', 'Lockdown Mode (mandatory)', 'As soon as you click <b>Start Exam</b>, the browser will switch to <b>fullscreen</b>. Exiting fullscreen, switching tabs / applications, or right-click / copy / paste will be logged as violations. After '.MAX_VIOLATIONS.' violations the exam will <b>auto-submit</b>.'],
      ['2', 'Webcam Proctoring', 'Your webcam preview will be visible during the exam. Ensure your face is clearly visible.'],
      ['3', 'Question Navigation', 'Use the question palette to jump to any question, mark for review, or revisit. Green=answered, red=not answered, blue=marked, orange=answered & marked.'],
      ['4', 'Submission', 'Click <b>Submit Exam</b> when done. The exam will auto-submit when the timer reaches zero.'],
    ] as $s): ?>
      <div class="mb-3">
        <h5 class="d-flex align-items-center gap-2"><span style="width:28px; height:28px; background:var(--navy); color:#fff; border-radius:3px; display:inline-flex; align-items:center; justify-content:center; font-size:13px"><?= $s[0] ?></span><?= $s[1] ?></h5>
        <p class="text-secondary small mb-0"><?= $s[2] ?></p>
      </div>
    <?php endforeach; ?>
    <?php if (!empty($exam['instructions'])): ?>
      <div class="bg-light p-3 small border"><?= nl2br(h($exam['instructions'])) ?></div>
    <?php endif; ?>

    <form id="beginForm" method="get" action="<?= url('student/take-exam.php') ?>" class="mt-4">
      <input type="hidden" name="exam_id" value="<?= $eid ?>">
      <div class="form-check mb-3">
        <input type="checkbox" class="form-check-input" id="agree" required>
        <label class="form-check-label small" for="agree"><?= t('in_agree') ?></label>
      </div>
      <button id="confirmBeginBtn" type="button" class="btn btn-success btn-lg"><i class="fas fa-shield-alt me-2"></i><?= t('in_begin') ?></button>
      <a href="<?= url('student/dashboard.php') ?>" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
    </form>

    <!-- Fullscreen start overlay -->
    <div id="startOverlay" style="display:none; position:fixed; inset:0; background:radial-gradient(circle at 20% 10%, rgba(255,215,0,0.08), rgba(0,0,0,0.9)); z-index:1050; align-items:center; justify-content:center;">
      <div style="text-align:center; color:#fff; width:100%;">
        <div style="max-width:980px; margin:0 auto;">
          <h2 id="overlayTitle" class="mb-3" style="font-size:28px; font-weight:700; text-shadow:0 2px 6px rgba(0,0,0,0.6)">Preparing to start</h2>
          <div id="bigTimer" style="font-size:120px; font-weight:800; letter-spacing:2px; margin:18px 0; color:var(--saffron); text-shadow:0 6px 20px rgba(0,0,0,0.6)">--:--</div>
          <p id="overlayMsg" class="mb-4" style="opacity:0.9">The exam will start when the timer reaches zero. Your screen will switch to fullscreen when you begin.</p>
          <div>
            <button id="startNowBtn" class="btn btn-lg btn-primary me-2" disabled>Start Exam</button>
            <button id="overlayCancelBtn" class="btn btn-lg btn-outline-light">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <?php
    // expose exam timestamps to JS
    $start_ts = strtotime($exam['start_time']);
    ?>
    <script>
    (function(){
      const startTs = <?= (int)$start_ts ?> * 1000;
      const confirmBtn = document.getElementById('confirmBeginBtn');
      const overlay = document.getElementById('startOverlay');
      const bigTimer = document.getElementById('bigTimer');
      const startNowBtn = document.getElementById('startNowBtn');
      const overlayCancelBtn = document.getElementById('overlayCancelBtn');
      const beginForm = document.getElementById('beginForm');
      const agree = document.getElementById('agree');

      let timerId = null;

      function formatMS(ms){
        if (ms <= 0) return '00:00';
        const s = Math.floor(ms/1000);
        const mm = String(Math.floor(s/60)).padStart(2,'0');
        const ss = String(s%60).padStart(2,'0');
        return `${mm}:${ss}`;
      }

      function updateTimer(){
        const now = Date.now();
        const rem = startTs - now;
        if (rem <= 0){
          bigTimer.textContent = '00:00';
          startNowBtn.disabled = false;
          startNowBtn.classList.remove('btn-secondary');
          startNowBtn.classList.add('btn-success');
          // stop interval but keep overlay visible until user clicks start
          clearInterval(timerId);
          timerId = null;
          return;
        }
        bigTimer.textContent = formatMS(rem);
      }

      function openOverlay(){
        overlay.style.display = 'flex';
        updateTimer();
        if (!timerId) timerId = setInterval(updateTimer, 500);
      }

      function closeOverlay(){
        overlay.style.display = 'none';
        if (timerId){ clearInterval(timerId); timerId = null; }
      }

      confirmBtn.addEventListener('click', function(){
        // validate checkbox
        if (!agree.checkValidity()){
          agree.reportValidity();
          return;
        }
        openOverlay();
      });

      overlayCancelBtn.addEventListener('click', function(e){
        e.preventDefault();
        closeOverlay();
      });

      startNowBtn.addEventListener('click', function(e){
        // when enabled, request fullscreen then submit
        if (startNowBtn.disabled) return;
        // try to enter fullscreen on documentElement
        const el = document.documentElement;
        const fs = el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen || el.msRequestFullscreen;
        if (fs) {
          try { fs.call(el); } catch (err) { /* ignore */ }
        }
        // submit the form
        beginForm.submit();
      });

      // keyboard: Esc closes overlay
      document.addEventListener('keydown', function(ev){ if (ev.key === 'Escape' && overlay.style.display === 'flex'){ closeOverlay(); } });
    })();
    </script>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
