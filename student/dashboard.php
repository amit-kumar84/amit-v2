<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/lang.php';
$u = require_login('student');

$assigned = assigned_exam_ids((int)$u['id']);
if ($assigned) {
    $in = str_repeat('?,', count($assigned) - 1) . '?';
    $stmt = db()->prepare("SELECT e.*, (SELECT COUNT(*) FROM questions WHERE exam_id=e.id AND deleted_at IS NULL) AS qcount FROM exams e WHERE e.id IN ($in) ORDER BY e.start_time DESC");
    $stmt->execute($assigned);
    $exams = $stmt->fetchAll();
} else {
    $exams = [];
}
foreach ($exams as &$e) {
    $e['status'] = exam_status($e);
    $used = db()->prepare('SELECT COUNT(*) FROM attempts WHERE user_id=? AND exam_id=? AND status="submitted"');
    $used->execute([$u['id'], $e['id']]);
    $e['attempts_used'] = (int)$used->fetchColumn();
    $e['attempts_left'] = max(0, (int)$e['max_attempts'] - $e['attempts_used']);
}
unset($e);
$PAGE_TITLE = t('sd_title');
require __DIR__ . '/../includes/header.php';
$labels = [
  'statuses' => [
    'active' => t('sd_active'),
    'upcoming' => t('sd_upcoming'),
    'closed' => t('sd_closed'),
  ],
  'noExams' => t('sd_no_exams'),
  'duration' => t('sd_duration'),
  'questions' => t('sd_questions'),
  'attemptsLeft' => t('sd_attempts_left'),
  'viewInstructions' => t('sd_view_instructions'),
  'closedText' => t('sd_closed'),
  'upcomingText' => t('sd_upcoming'),
  'maxAttempts' => 'Max Attempts',
  'noQuestions' => 'No Questions',
  'syncing' => lang() === 'hi' ? 'परीक्षा अपडेट हर 10 सेकंड में जाँची जा रही है' : 'Checking for exam updates every 10 seconds',
  'live' => lang() === 'hi' ? 'लाइव अपडेट' : 'Live update',
];
?>
<div class="tricolor"><span></span><span></span><span></span></div>
<header class="student-topbar">
  <div class="container-fluid px-3 px-md-4">
    <div class="student-topbar-inner">
      <a href="<?= url('student/dashboard.php') ?>" class="student-topbar-brand" aria-label="<?= h(t('brand')) ?>">
        <img src="<?= url('assets/icons/BEL-Logo-Trnsprent.png') ?>" alt="BEL">
      </a>
      <div class="student-topbar-copy">
        <div class="student-topbar-title">Candidate Portal · <?= t('brand') ?></div>
        <div class="student-topbar-subtitle"><?= t('sd_welcome') ?>, <?= h($u['name']) ?> · Kotdwar Unit</div>
      </div>
      <div class="student-topbar-actions">
        <a href="?lang=<?= lang()==='en'?'hi':'en' ?>" class="btn btn-sm btn-outline-light"><?= t('lang_toggle') ?></a>
        <a href="<?= url('logout.php') ?>" class="btn btn-sm btn-danger"><i class="fas fa-sign-out-alt me-1"></i><?= t('sd_logout') ?></a>
      </div>
    </div>
  </div>
</header>

<main class="container py-4">
  <h2 class="fw-bold"><?= t('sd_title') ?></h2>
  <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <p class="text-secondary mb-0"><?= t('sd_sub') ?></p>
    <span class="badge bg-light text-dark border"><i class="fas fa-satellite-dish me-1"></i><?= h($labels['live']) ?></span>
  </div>
  <div class="small text-muted mb-3" id="exam-live-status"><?= h($labels['syncing']) ?></div>

  <div class="row g-3 mt-2" id="student-exams-grid">
    <?php foreach ($exams as $e): ?>
      <?php $canStart = $e['status'] === 'active' && $e['attempts_left'] > 0 && $e['qcount'] > 0; ?>
      <div class="col-md-6 col-lg-4">
        <div class="exam-card h-100 d-flex flex-column">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <h5 class="fw-bold mb-0"><i class="fas fa-book-open text-secondary me-2"></i><?= h($e['exam_name']) ?></h5>
            <span class="pill status-<?= $e['status'] ?>"><?= t('sd_' . $e['status']) ?></span>
          </div>
          <div class="small text-secondary">
            <div><i class="far fa-clock"></i> <?= t('sd_duration') ?>: <b><?= (int)$e['duration_minutes'] ?> min</b></div>
            <div><?= t('sd_questions') ?>: <b><?= (int)$e['qcount'] ?></b> · <?= t('sd_attempts_left') ?>: <b><?= $e['attempts_left'] ?></b></div>
            <div class="text-muted mt-1" style="font-size:11px">Window: <?= fmt_dt($e['start_time']) ?> → <?= fmt_dt($e['end_time']) ?></div>
          </div>
          <div class="mt-auto pt-3">
            <?php if ($canStart): ?>
              <a href="<?= url('student/instructions.php?exam_id=' . $e['id']) ?>" class="btn btn-navy w-100"><?= t('sd_view_instructions') ?> →</a>
            <?php else: ?>
              <button disabled class="btn btn-secondary w-100">
                <?php if ($e['status']==='closed') echo t('sd_closed');
                elseif ($e['status']==='upcoming') echo t('sd_upcoming');
                elseif ($e['attempts_left']<=0) echo 'Max Attempts';
                else echo 'No Questions'; ?>
              </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (empty($exams)): ?>
      <div class="col-12 exam-empty"><div class="exam-card text-center text-secondary py-5">
        <i class="fas fa-lock fa-2x mb-3 text-muted"></i>
        <h5 class="fw-bold"><?= t('sd_no_exams') ?></h5>
        <p class="small mb-0">No examinations have been assigned to your account yet. Please contact the BEL Kotdwar Examination Controller.</p>
      </div></div>
    <?php endif; ?>
  </div>
</main>
<script>
(function() {
  const grid = document.getElementById('student-exams-grid');
  const statusEl = document.getElementById('exam-live-status');
  const pollUrl = <?= json_encode(url('api/student-dashboard-exams.php')) ?>;
  const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
  let lastSignature = '';
  let refreshTimer = null;

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function statusLabel(status) {
    return labels.statuses[status] || status;
  }

  function buttonText(exam) {
    if (exam.can_start) return `${escapeHtml(labels.viewInstructions)} →`;
    if (exam.status === 'closed') return labels.closedText;
    if (exam.status === 'upcoming') return labels.upcomingText;
    if (exam.attempts_left <= 0) return labels.maxAttempts;
    return labels.noQuestions;
  }

  function examCard(exam) {
    const disabled = exam.can_start ? '' : ' disabled';
    const btnClass = exam.can_start ? 'btn btn-navy w-100' : 'btn btn-secondary w-100';
    const href = <?= json_encode(url('student/instructions.php?exam_id=')) ?> + encodeURIComponent(exam.id);
    return `
      <div class="col-md-6 col-lg-4" data-exam-id="${escapeHtml(exam.id)}">
        <div class="exam-card h-100 d-flex flex-column">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <h5 class="fw-bold mb-0"><i class="fas fa-book-open text-secondary me-2"></i>${escapeHtml(exam.exam_name)}</h5>
            <span class="pill status-${escapeHtml(exam.status)}">${escapeHtml(statusLabel(exam.status))}</span>
          </div>
          <div class="small text-secondary">
            <div><i class="far fa-clock"></i> ${escapeHtml(labels.duration)}: <b>${escapeHtml(exam.duration_minutes)} min</b></div>
            <div>${escapeHtml(labels.questions)}: <b>${escapeHtml(exam.qcount)}</b> · ${escapeHtml(labels.attemptsLeft)}: <b>${escapeHtml(exam.attempts_left)}</b></div>
            <div class="text-muted mt-1" style="font-size:11px">Window: ${escapeHtml(exam.start_time_label)} → ${escapeHtml(exam.end_time_label)}</div>
          </div>
          <div class="mt-auto pt-3">
            ${exam.can_start ? `<a href="${href}" class="${btnClass}">${escapeHtml(labels.viewInstructions)} →</a>` : `<button class="${btnClass}"${disabled}>${escapeHtml(buttonText(exam))}</button>`}
          </div>
        </div>
      </div>`;
  }

  function renderExams(exams) {
    if (!Array.isArray(exams) || exams.length === 0) {
      grid.innerHTML = `
        <div class="col-12 exam-empty">
          <div class="exam-card text-center text-secondary py-5">
            <i class="fas fa-lock fa-2x mb-3 text-muted"></i>
            <h5 class="fw-bold">${escapeHtml(labels.noExams)}</h5>
            <p class="small mb-0">No examinations have been assigned to your account yet. Please contact the BEL Kotdwar Examination Controller.</p>
          </div>
        </div>`;
      return;
    }
    grid.innerHTML = exams.map(examCard).join('');
    // Set animation delays for each card
    document.querySelectorAll('#student-exams-grid [data-exam-id]').forEach((el, index) => {
      el.style.setProperty('--card-index', index);
    });
  }

  async function syncExams() {
    try {
      const response = await fetch(pollUrl, { cache: 'no-store', credentials: 'same-origin' });
      if (!response.ok) return;
      const data = await response.json();
      if (!data || !data.success) return;
      const signature = JSON.stringify(data.exams || []);
      if (signature === lastSignature) return;
      lastSignature = signature;
      renderExams(data.exams || []);
      if (statusEl) {
        const now = new Date();
        statusEl.textContent = `${labels.live} · ${now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`;
      }
    } catch (err) {
      // keep existing content on transient network failures
    }
  }

  syncExams();
  refreshTimer = window.setInterval(() => {
    if (!document.hidden) syncExams();
  }, 10000);

  // Set initial animation delays
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#student-exams-grid [data-exam-id]').forEach((el, index) => {
      el.style.setProperty('--card-index', index);
    });
  });
  // Also run immediately in case DOMContentLoaded already fired
  document.querySelectorAll('#student-exams-grid [data-exam-id]').forEach((el, index) => {
    el.style.setProperty('--card-index', index);
  });

  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) syncExams();
  });
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
