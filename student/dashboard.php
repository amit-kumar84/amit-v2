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
    $e['join_window_minutes'] = max(0, (int)($e['join_window_minutes'] ?? 0));
    $e['join_window_start'] = $e['join_window_minutes'] > 0 ? date('Y-m-d H:i:s', strtotime($e['start_time']) - $e['join_window_minutes'] * 60) : null;
    $e['join_window_start_label'] = $e['join_window_start'] ? fmt_dt($e['join_window_start']) : null;
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
  'joinText' => t('sd_join'),
  'joinNowText' => t('sd_join_now'),
  'startExamText' => t('sd_start_exam'),
  'joinWindowClosedText' => t('sd_join_window_closed'),
  'joinWindowClosesInText' => t('sd_join_window_closes_in'),
  'joinWindowOpensInText' => lang() === 'hi' ? 'जॉइन विंडो शुरू होने में' : 'Join window opens in',
  'currentlyOngoingText' => t('sd_currently_ongoing'),
  'maxAttempts' => t('sd_max_attempts'),
  'noQuestions' => t('sd_no_questions'),
  'candidatePortal' => t('sd_candidate_portal'),
  'kotdwarUnit' => t('sd_kotdwar_unit'),
  'modeJoinWindow' => t('sd_mode_join_window'),
  'modeDirectStart' => t('sd_mode_direct_start'),
  'visibilityJoin' => t('sd_visibility_join'),
  'visibilityDirect' => t('sd_visibility_direct'),
  'joinWindowEnabled' => t('sd_join_window_enabled'),
  'joinWindowDisabled' => t('sd_join_window_disabled'),
  'windowLabel' => t('sd_window_label'),
  'emptyMessage' => t('sd_exam_controller_note'),
  'syncing' => lang() === 'hi' ? 'परीक्षा अपडेट हर 10 सेकंड में जाँची जा रही है' : 'Checking for exam updates every 10 seconds',
  'live' => lang() === 'hi' ? 'लाइव अपडेट' : 'Live update',
];
?>
<style>
  .exam-card {
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(0, 169, 224, 0.12);
    background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(245,250,255,0.94));
    box-shadow: 0 12px 28px rgba(14, 42, 71, 0.08);
    transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    border-radius: 18px;
  }
  .exam-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 18px 36px rgba(14, 42, 71, 0.14);
    border-color: rgba(0, 169, 224, 0.28);
  }
  .exam-card::before {
    content: '';
    position: absolute;
    inset: 0 0 auto 0;
    height: 5px;
    background: linear-gradient(90deg, #0E2A47 0%, #00A9E0 42%, #FF9933 100%);
  }
  .exam-card h5 {
    background: linear-gradient(90deg, #0E2A47, #00A9E0, #FF9933);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    text-fill-color: transparent;
  }
  .exam-card .exam-title {
    font-size: 1rem;
    line-height: 1.25;
  }
  .exam-card .exam-code {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 9px;
    border-radius: 999px;
    background: rgba(14, 42, 71, 0.06);
    color: #0E2A47;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.04em;
  }
  .exam-card .exam-subline {
    color: #47627e;
    font-size: 12px;
    line-height: 1.45;
  }
  .exam-card .exam-panel {
    background: linear-gradient(180deg, rgba(255,255,255,0.75), rgba(241,248,255,0.92));
    border: 1px solid rgba(0, 169, 224, 0.10);
    border-radius: 14px;
    padding: 12px;
  }
  .exam-card .exam-metrics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
  }
  .exam-card .metric {
    border-radius: 12px;
    padding: 10px 8px;
    text-align: center;
    background: rgba(255,255,255,0.72);
    border: 1px solid rgba(148, 163, 184, 0.16);
  }
  .exam-card .metric-label {
    display: block;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #6b7d95;
    margin-bottom: 2px;
    font-weight: 800;
  }
  .exam-card .metric-value {
    font-size: 15px;
    font-weight: 900;
    color: #0E2A47;
  }
  .exam-card .exam-detail-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
  }
  .exam-card .exam-detail-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 999px;
    background: rgba(0, 169, 224, 0.08);
    color: #0E2A47;
    border: 1px solid rgba(0, 169, 224, 0.12);
    font-size: 11px;
    font-weight: 700;
  }
  .exam-card .exam-note {
    margin-top: 10px;
    color: #5a6d83;
    font-size: 12px;
    line-height: 1.5;
  }
  .exam-card .small.text-secondary > div {
    color: #1f3b57;
  }
  .join-window-info {
    color: #0E2A47;
  }
  .join-window-countdown {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    font-weight: 800;
    letter-spacing: 0.02em;
    background: linear-gradient(90deg, rgba(255,153,51,0.12), rgba(0,169,224,0.12), rgba(16,185,129,0.12));
    background-size: 200% 100%;
    color: #0E2A47;
    animation: joinShimmer 3s linear infinite, joinPulse 1.4s ease-in-out infinite;
    box-shadow: 0 6px 16px rgba(0,169,224,0.12);
  }
  .join-window-countdown .join-window-time {
    display: inline-block;
    min-width: 54px;
    text-align: center;
    color: #fff;
    background: linear-gradient(135deg, #FF9933, #00A9E0, #10b981);
    background-size: 180% 180%;
    border-radius: 999px;
    padding: 2px 8px;
    box-shadow: 0 6px 14px rgba(0, 169, 224, 0.22);
    animation: timerGlow 1.6s ease-in-out infinite, timerWave 2.8s ease-in-out infinite;
  }
  .join-window-action.btn-navy {
    background: linear-gradient(135deg, #0E2A47, #00A9E0, #FF9933);
    background-size: 200% 200%;
    border: none;
    box-shadow: 0 10px 22px rgba(0, 169, 224, 0.22);
    animation: buttonShift 4s ease infinite;
  }
  .join-window-action.btn-secondary {
    opacity: 0.85;
  }
  .status-join, .status-active, .status-upcoming, .status-closed {
    animation: statusBreath 2.4s ease-in-out infinite;
  }
  .status-join { background: linear-gradient(135deg, #10b981, #00A9E0) !important; color: #fff !important; }
  .status-active { background: linear-gradient(135deg, #00A9E0, #0E2A47) !important; color: #fff !important; }
  .status-upcoming { background: linear-gradient(135deg, #FF9933, #f59e0b) !important; color: #fff !important; }
  .status-closed { background: linear-gradient(135deg, #64748b, #334155) !important; color: #fff !important; }
  @keyframes joinShimmer {
    0% { background-position: 0% 50%; }
    100% { background-position: 200% 50%; }
  }
  @keyframes joinPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.03); }
  }
  @keyframes timerGlow {
    0%, 100% { box-shadow: 0 6px 14px rgba(0, 169, 224, 0.18); }
    50% { box-shadow: 0 8px 20px rgba(255, 153, 51, 0.30); }
  }
  @keyframes timerWave {
    0%, 100% { filter: saturate(1); }
    50% { filter: saturate(1.25); }
  }
  @keyframes buttonShift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
  }
  @keyframes statusBreath {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-1px); }
  }
</style>
<div class="tricolor"><span></span><span></span><span></span></div>
<header class="student-topbar">
  <div class="container-fluid px-3 px-md-4">
    <div class="student-topbar-inner">
      <a href="<?= url('student/dashboard.php') ?>" class="student-topbar-brand" aria-label="<?= h(t('brand')) ?>">
        <img src="<?= url('assets/icons/BEL-Logo-Trnsprent.png') ?>" alt="BEL">
      </a>
      <div class="student-topbar-copy">
        <div class="student-topbar-title"><?= t('sd_candidate_portal') ?> · <?= t('brand') ?></div>
        <div class="student-topbar-subtitle"><?= t('sd_welcome') ?>, <?= h($u['name']) ?> · <?= t('sd_kotdwar_unit') ?></div>
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
      <?php $canStart = exam_can_start_now($e) && $e['attempts_left'] > 0 && $e['qcount'] > 0; ?>
      <?php $joinWindowEndsAt = !empty($e['join_window_minutes']) ? strtotime($e['start_time']) : null; ?>
      <?php $joinWindowStartsAt = !empty($e['join_window_start']) ? strtotime($e['join_window_start']) : null; ?>
      <?php $modeLabel = $e['join_window_minutes'] > 0 ? sprintf(t('sd_mode_join_window'), (int)$e['join_window_minutes']) : t('sd_mode_direct_start'); ?>
      <?php $visibilityNote = $e['join_window_minutes'] > 0 ? t('sd_visibility_join') : t('sd_visibility_direct'); ?>
      <div class="col-md-6 col-lg-4">
        <div class="exam-card h-100 d-flex flex-column" data-exam-card data-status="<?= h($e['status']) ?>" data-can-start="<?= $canStart ? '1' : '0' ?>" data-join-window-minutes="<?= (int)$e['join_window_minutes'] ?>" data-join-window-end-ts="<?= $joinWindowEndsAt ? ((int)$joinWindowEndsAt * 1000) : '' ?>" data-join-window-start-ts="<?= $joinWindowStartsAt ? ((int)$joinWindowStartsAt * 1000) : '' ?>">
          <div class="p-3 pb-2">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
              <div class="flex-grow-1">
                <div class="exam-code mb-2"><i class="fas fa-barcode"></i><?= h($e['exam_code'] ?: 'EXAM') ?></div>
                <h5 class="fw-bold mb-1 exam-title"><i class="fas fa-book-open text-secondary me-2"></i><?= h($e['exam_name']) ?></h5>
                <div class="exam-subline"><?= h($visibilityNote) ?></div>
              </div>
              <span class="pill status-<?= $e['status'] ?>"><?= t('sd_' . $e['status']) ?></span>
            </div>

            <div class="exam-panel mt-3">
              <div class="exam-metrics">
                <div class="metric">
                  <span class="metric-label"><?= h($labels['duration']) ?></span>
                  <span class="metric-value"><?= (int)$e['duration_minutes'] ?>m</span>
                </div>
                <div class="metric">
                  <span class="metric-label"><?= h($labels['questions']) ?></span>
                  <span class="metric-value"><?= (int)$e['qcount'] ?></span>
                </div>
                <div class="metric">
                  <span class="metric-label"><?= h($labels['attemptsLeft']) ?></span>
                  <span class="metric-value"><?= $e['attempts_left'] ?></span>
                </div>
              </div>
              <div class="exam-detail-row">
                <span class="exam-detail-chip"><i class="fas fa-clipboard-list"></i><?= h($modeLabel) ?></span>
                <span class="exam-detail-chip"><i class="far fa-calendar-alt"></i><?= fmt_dt($e['start_time']) ?></span>
              </div>
              <div class="text-muted mt-2 join-window-info" style="font-size:11px">
              <?php if ($e['status'] === 'join'): ?>
                <span class="join-window-countdown" data-join-countdown><?= h(t('sd_join_window_closes_in')) ?> <span class="join-window-time">--:--</span></span>
              <?php elseif ($e['status'] === 'upcoming' && $e['join_window_start_label']): ?>
                <span class="join-window-countdown" data-join-open-countdown><?= h($labels['joinWindowOpensInText']) ?> <span class="join-window-time">--:--</span></span>
              <?php else: ?>
                <?= h($labels['windowLabel']) ?>: <?= fmt_dt($e['start_time']) ?> → <?= fmt_dt($e['end_time']) ?>
              <?php endif; ?>
              </div>
              <div class="exam-note">
                <i class="fas fa-circle-info me-1 text-info"></i>
                <?= h($e['join_window_minutes'] > 0 ? $labels['joinWindowEnabled'] : $labels['joinWindowDisabled']) ?>
              </div>
            </div>
          </div>
          <div class="mt-auto pt-3">
            <?php if ($canStart): ?>
              <a href="<?= url('student/instructions.php?exam_id=' . $e['id']) ?>" class="btn btn-navy w-100 join-window-action" data-join-action data-join-href="<?= url('student/instructions.php?exam_id=' . $e['id']) ?>"><?= $e['status'] === 'join' ? t('sd_join_now') : t('sd_start_exam') ?> →</a>
            <?php else: ?>
              <button disabled class="btn btn-secondary w-100 join-window-action" data-join-action>
                <?php if ($e['status']==='closed') echo t('sd_closed');
                elseif ($e['status']==='upcoming') echo t('sd_upcoming');
                elseif ($e['attempts_left']<=0) echo $labels['maxAttempts'];
                else echo $labels['noQuestions']; ?>
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
        <p class="small mb-0"><?= h($labels['emptyMessage']) ?></p>
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
    if (exam.can_start) return `${escapeHtml(labels.startExamText)} →`;
    if (exam.status === 'join') return `${escapeHtml(labels.joinNowText)} →`;
    if (exam.status === 'active') return labels.joinWindowClosedText;
    if (exam.status === 'closed') return labels.closedText;
    if (exam.status === 'upcoming') return labels.upcomingText;
    if (exam.attempts_left <= 0) return labels.maxAttempts;
    return labels.noQuestions;
  }

  function examCard(exam) {
    const isStartable = !!exam.can_start;
    const disabled = isStartable ? '' : ' disabled';
    const btnClass = isStartable ? 'btn btn-navy w-100' : 'btn btn-secondary w-100';
    const href = <?= json_encode(url('student/instructions.php?exam_id=')) ?> + encodeURIComponent(exam.id);
    const modeLabel = parseInt(exam.join_window_minutes || 0, 10) > 0
      ? labels.modeJoinWindow.replace('%d', escapeHtml(exam.join_window_minutes))
      : labels.modeDirectStart;
    const visibilityNote = parseInt(exam.join_window_minutes || 0, 10) > 0
      ? labels.visibilityJoin
      : labels.visibilityDirect;
    const windowInfo = exam.status === 'join'
      ? `<span class="join-window-countdown" data-join-countdown>${escapeHtml(labels.joinWindowClosesInText)} <span class="join-window-time">--:--</span></span>`
      : (exam.status === 'upcoming' && exam.join_window_start_label)
        ? `<span class="join-window-countdown" data-join-open-countdown>${escapeHtml(labels.joinWindowOpensInText)} <span class="join-window-time">--:--</span></span>`
        : `${escapeHtml(labels.windowLabel)}: ${escapeHtml(exam.start_time_label)} → ${escapeHtml(exam.end_time_label)}`;
    return `
      <div class="col-md-6 col-lg-4" data-exam-id="${escapeHtml(exam.id)}">
        <div class="exam-card h-100 d-flex flex-column" data-exam-card data-status="${escapeHtml(exam.status)}" data-can-start="${isStartable ? '1' : '0'}" data-join-window-minutes="${escapeHtml(exam.join_window_minutes || 0)}" data-join-window-end-ts="${escapeHtml(exam.start_ts || '')}" data-join-window-start-ts="${escapeHtml(exam.join_window_start_ts || '')}">
          <div class="p-3 pb-2">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
              <div class="flex-grow-1">
                <div class="exam-code mb-2"><i class="fas fa-barcode"></i>${escapeHtml(exam.exam_code || 'EXAM')}</div>
                <h5 class="fw-bold mb-1 exam-title"><i class="fas fa-book-open text-secondary me-2"></i>${escapeHtml(exam.exam_name)}</h5>
                <div class="exam-subline">${escapeHtml(visibilityNote)}</div>
              </div>
              <span class="pill status-${escapeHtml(exam.status)}">${escapeHtml(statusLabel(exam.status))}</span>
            </div>

            <div class="exam-panel mt-3">
              <div class="exam-metrics">
                <div class="metric">
                  <span class="metric-label">${escapeHtml(labels.duration)}</span>
                  <span class="metric-value">${escapeHtml(exam.duration_minutes)}m</span>
                </div>
                <div class="metric">
                  <span class="metric-label">${escapeHtml(labels.questions)}</span>
                  <span class="metric-value">${escapeHtml(exam.qcount)}</span>
                </div>
                <div class="metric">
                  <span class="metric-label">${escapeHtml(labels.attemptsLeft)}</span>
                  <span class="metric-value">${escapeHtml(exam.attempts_left)}</span>
                </div>
              </div>
              <div class="exam-detail-row">
                <span class="exam-detail-chip"><i class="fas fa-clipboard-list"></i>${escapeHtml(modeLabel)}</span>
                <span class="exam-detail-chip"><i class="far fa-calendar-alt"></i>${escapeHtml(exam.start_time_label)}</span>
              </div>
              <div class="text-muted mt-2 join-window-info" style="font-size:11px">${windowInfo}</div>
              <div class="exam-note">
                <i class="fas fa-circle-info me-1 text-info"></i>
                ${parseInt(exam.join_window_minutes || 0, 10) > 0 ? escapeHtml(labels.joinWindowEnabled) : escapeHtml(labels.joinWindowDisabled)}
              </div>
            </div>
          </div>
          <div class="mt-auto pt-3">
            ${isStartable ? `<a href="${href}" class="${btnClass} join-window-action" data-join-action data-join-href="${href}">${escapeHtml(buttonText(exam))}</a>` : `<button class="${btnClass} join-window-action" data-join-action${disabled}>${escapeHtml(buttonText(exam))}</button>`}
          </div>
        </div>
      </div>`;
  }

  function formatJoinCountdown(ms) {
    const totalSeconds = Math.max(0, Math.floor(ms / 1000));
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
  }

  function refreshJoinTimers() {
    const now = Date.now();
    document.querySelectorAll('[data-exam-card]').forEach(card => {
      const joinEndTs = parseInt(card.dataset.joinWindowEndTs || '', 10);
      if (!joinEndTs) return;
      const countdownEl = card.querySelector('[data-join-countdown]');
      const openCountdownEl = card.querySelector('[data-join-open-countdown]');
      const actionEl = card.querySelector('[data-join-action]');
      const statusPill = card.querySelector('.pill');
      const canStart = card.dataset.canStart === '1';
      const status = card.dataset.status || '';
      const joinStartTs = parseInt(card.dataset.joinWindowStartTs || '', 10);
      const joinWindowMinutes = parseInt(card.dataset.joinWindowMinutes || '0', 10);

      function setJoinActionDisabled(disabled) {
        if (!actionEl) return;
        if (actionEl.tagName === 'A') {
          if (disabled) {
            if (!actionEl.dataset.joinHref) actionEl.dataset.joinHref = actionEl.getAttribute('href') || '';
            actionEl.removeAttribute('href');
            actionEl.setAttribute('aria-disabled', 'true');
            actionEl.setAttribute('tabindex', '-1');
            actionEl.style.pointerEvents = 'none';
            actionEl.style.cursor = 'not-allowed';
          } else {
            const originalHref = actionEl.dataset.joinHref || '';
            if (originalHref) actionEl.setAttribute('href', originalHref);
            actionEl.removeAttribute('aria-disabled');
            actionEl.removeAttribute('tabindex');
            actionEl.style.pointerEvents = '';
            actionEl.style.cursor = '';
          }
        } else {
          actionEl.disabled = disabled;
        }
      }

      if (status === 'join') {
        const remaining = joinEndTs - now;
        if (remaining > 0) {
          if (countdownEl) countdownEl.innerHTML = `${escapeHtml(labels.joinWindowClosesInText)} <span class="join-window-time">${formatJoinCountdown(remaining)}</span>`;
          setJoinActionDisabled(false);
          if (actionEl) {
            actionEl.classList.remove('btn-secondary');
            actionEl.classList.add('btn-navy');
            // Ensure label reflects join/start
            if (actionEl.dataset && actionEl.dataset.joinHref) {
              actionEl.textContent = `${labels.joinNowText} →`;
            } else {
              actionEl.textContent = `${labels.startExamText} →`;
            }
          }
        } else {
          if (countdownEl) countdownEl.textContent = labels.joinWindowClosedText;
          setJoinActionDisabled(true);
          if (actionEl) {
            actionEl.classList.remove('btn-navy');
            actionEl.classList.add('btn-secondary');
            // change text to currently ongoing when join window ended
            actionEl.textContent = `${escapeHtml(labels.currentlyOngoingText)}`;
          }
          if (statusPill) {
            statusPill.textContent = labels.upcomingText;
          }
        }
      } else if (status === 'active') {
        if (joinWindowMinutes > 0) {
          if (countdownEl) countdownEl.textContent = labels.joinWindowClosedText;
          setJoinActionDisabled(true);
          if (actionEl) {
            actionEl.classList.remove('btn-navy');
            actionEl.classList.add('btn-secondary');
            actionEl.textContent = labels.joinWindowClosedText;
          }
          if (statusPill) {
            statusPill.textContent = labels.statuses['active'] || 'Active';
          }
        } else {
          setJoinActionDisabled(false);
          if (actionEl) {
            actionEl.classList.remove('btn-secondary');
            actionEl.classList.add('btn-navy');
            actionEl.textContent = `${escapeHtml(labels.startExamText)} →`;
          }
        }
      } else if (status === 'upcoming' && !canStart) {
        const remaining = joinStartTs - now;
        if (remaining > 0) {
          if (openCountdownEl) openCountdownEl.innerHTML = `${escapeHtml(labels.joinWindowOpensInText)} <span class="join-window-time">${formatJoinCountdown(remaining)}</span>`;
        } else {
          if (openCountdownEl) openCountdownEl.textContent = labels.joinWindowOpensInText;
        }
        setJoinActionDisabled(true);
      }
    });
  }

  function renderExams(exams) {
    if (!Array.isArray(exams) || exams.length === 0) {
      grid.innerHTML = `
        <div class="col-12 exam-empty">
          <div class="exam-card text-center text-secondary py-5">
            <i class="fas fa-lock fa-2x mb-3 text-muted"></i>
            <h5 class="fw-bold">${escapeHtml(labels.noExams)}</h5>
            <p class="small mb-0">${escapeHtml(labels.emptyMessage)}</p>
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

  window.setInterval(refreshJoinTimers, 1000);
  refreshJoinTimers();

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
