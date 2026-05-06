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
$canStart = exam_can_start_now($exam) && $attempts_left > 0;
$joinWindow = max(0, (int)($exam['join_window_minutes'] ?? 0));
$examStatus = exam_status($exam);
$showCountdown = $joinWindow > 0;
$isHi = lang() === 'hi';
$PAGE_TITLE = t('in_title');
require __DIR__ . '/../includes/header.php';
?>
<div class="tricolor"><span></span><span></span><span></span></div>
<header class="instruction-topbar">
  <div class="container-fluid px-3 px-md-4">
    <div class="instruction-topbar-inner">
      <div class="instruction-brand">
        <a href="<?= url('student/dashboard.php') ?>" class="student-topbar-brand instruction-brand-mark" aria-label="<?= h(t('brand')) ?>">
          <img src="<?= url('assets/icons/BEL-Logo-Trnsprent.png') ?>" alt="BEL">
        </a>
        <div class="instruction-brand-copy">
          <div class="instruction-brand-kicker">BEL Kotdwar</div>
          <div class="instruction-brand-title"><?= t('in_title') ?></div>
          <div class="instruction-brand-subtitle"><?= $isHi ? 'सुरक्षित, निगरानी-युक्त और नियंत्रित परीक्षा वातावरण' : 'Secure, monitored and controlled examination environment' ?></div>
        </div>
      </div>
      <div class="instruction-topbar-actions">
        <a href="?exam_id=<?= $eid ?>&lang=<?= $isHi ? 'en' : 'hi' ?>" class="btn btn-sm btn-outline-light instruction-lang-btn"><?= t('lang_toggle') ?></a>
        <a href="<?= url('student/dashboard.php') ?>" class="btn btn-sm btn-outline-light instruction-back-btn"><i class="fas fa-arrow-left"></i> <?= t('sl_back') ?></a>
      </div>
    </div>
  </div>
</header>

<style>
  .exam-intro-shell {
    position: relative;
    overflow: hidden;
    border-radius: 28px;
    background:
      radial-gradient(circle at top left, rgba(0,169,224,0.12), transparent 34%),
      radial-gradient(circle at bottom right, rgba(255,153,51,0.12), transparent 28%),
      linear-gradient(180deg, rgba(255,255,255,0.98), rgba(244,250,255,0.95));
    border: 1px solid rgba(0, 169, 224, 0.14);
    box-shadow: 0 24px 60px rgba(14, 42, 71, 0.14);
  }
  .exam-intro-shell::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(0,169,224,0.10), rgba(255,153,51,0.08), rgba(16,185,129,0.10));
    opacity: 0.55;
    pointer-events: none;
  }
  .exam-intro-shell > * {
    position: relative;
    z-index: 1;
  }
  .exam-intro-header {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 18px;
    margin-bottom: 18px;
    border-bottom: 1px solid rgba(148,163,184,0.18);
  }
  .instruction-topbar {
    position: sticky;
    top: 0;
    z-index: 1030;
    color: #fff;
    border-bottom: 1px solid rgba(255,255,255,0.15);
    background:
      radial-gradient(circle at top left, rgba(255,255,255,0.16), transparent 38%),
      linear-gradient(135deg, rgba(14,42,71,0.98), rgba(11,35,59,0.96) 42%, rgba(0,169,224,0.92));
    backdrop-filter: blur(12px);
    box-shadow: 0 10px 30px rgba(8, 28, 48, 0.18);
  }
  .instruction-topbar-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 6px 0 10px;
  }
  .instruction-brand {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
    flex: 1 1 auto;
  }
  .instruction-brand-mark {
    flex-shrink: 0;
    text-decoration: none;
    animation: topbar-brand-entrance 0.6s cubic-bezier(0.2, 0.9, 0.2, 1);
  }
  .instruction-brand-mark img {
    width: 190px;
    height: 76px;
    background: #fff;
    padding: 2px;
    margin: 0;
    border-radius: 8px;
    object-fit: contain;
    object-position: center center;
    display: block;
    box-shadow: 0 8px 20px rgba(2,6,23,0.15);
    transition: transform 0.3s ease;
  }
  .instruction-brand-mark img:hover {
    transform: scale(1.05);
  }
  .instruction-brand-copy {
    min-width: 0;
  }
  .instruction-brand-kicker {
    font-size: 11px;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.72);
    font-weight: 700;
  }
  .instruction-brand-title {
    font-size: 19px;
    line-height: 1.15;
    font-weight: 900;
    letter-spacing: -0.01em;
  }
  .instruction-brand-subtitle {
    margin-top: 3px;
    font-size: 12px;
    color: rgba(255,255,255,0.78);
  }
  .instruction-topbar-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: flex-end;
  }
  .instruction-topbar .btn-outline-light {
    border-color: rgba(255,255,255,0.32);
    background: rgba(255,255,255,0.06);
    box-shadow: none;
  }
  .instruction-topbar .btn-outline-light:hover {
    background: rgba(255,255,255,0.16);
    border-color: rgba(255,255,255,0.55);
  }
  .instruction-lang-btn, .instruction-back-btn {
    border-radius: 999px;
    padding-left: 14px;
    padding-right: 14px;
    font-weight: 700;
    letter-spacing: 0.01em;
  }
  .instruction-topbar .instruction-lang-btn {
    min-width: 86px;
  }
  @media (max-width: 767.98px) {
    .instruction-topbar-inner {
      flex-direction: column;
      align-items: flex-start;
    }
    .instruction-topbar-actions {
      width: 100%;
      justify-content: flex-start;
    }
    .instruction-brand-title {
      font-size: 16px;
    }
    .instruction-brand-mark img {
      width: 170px;
      height: 70px;
    }
  }
  .exam-title-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px 16px;
  }
  .exam-intro-title {
    margin: 0;
    font-size: clamp(1.75rem, 3.6vw, 2.35rem);
    line-height: 1.15;
    color: #0E2A47;
    font-weight: 900;
    letter-spacing: -0.02em;
  }
  .exam-intro-subtitle {
    margin-top: 4px;
    color: #546b84;
    font-size: 13px;
    line-height: 1.6;
    max-width: none;
    width: 100%;
  }
  .exam-code-line {
    margin-top: 8px;
    color: #0E2A47;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }
  .exam-code-line i {
    color: #00A9E0;
  }
  .exam-intro-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 2px;
  }
  .exam-guidance-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin-top: 18px;
  }
  .exam-guidance-card {
    position: relative;
    overflow: hidden;
    border-radius: 18px;
    background: rgba(255,255,255,0.86);
    border: 1px solid rgba(0,169,224,0.12);
    box-shadow: 0 10px 24px rgba(14,42,71,0.06);
    padding: 16px 16px 16px 18px;
    transition: transform 0.24s ease, box-shadow 0.24s ease, border-color 0.24s ease;
  }
  .exam-guidance-card:hover {
    transform: translateY(-2px);
    border-color: rgba(0,169,224,0.22);
    box-shadow: 0 16px 34px rgba(14,42,71,0.10);
  }
  .exam-guidance-card::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 5px;
    background: linear-gradient(180deg, #0E2A47, #00A9E0, #FF9933);
  }
  .exam-guidance-head {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
  }
  .exam-guidance-step {
    width: 36px;
    height: 36px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, rgba(14,42,71,0.96), rgba(0,169,224,0.92));
    color: #fff;
    font-weight: 900;
    box-shadow: 0 10px 20px rgba(0,169,224,0.16);
    flex: 0 0 auto;
  }
  .exam-guidance-title {
    margin: 0;
    color: #0E2A47;
    font-size: 15px;
    font-weight: 900;
    letter-spacing: -0.01em;
  }
  .exam-guidance-body {
    color: #4f647d;
    font-size: 13px;
    line-height: 1.65;
  }
  .exam-guidance-body strong { color: #0E2A47; }
  .exam-notice {
    margin: 16px 0 0;
    padding: 14px 16px;
    border-radius: 18px;
    border: 1px solid rgba(0,169,224,0.14);
    background: linear-gradient(135deg, rgba(14,42,71,0.06), rgba(0,169,224,0.05), rgba(255,153,51,0.05));
    color: #2e4359;
    display: flex;
    gap: 10px;
    align-items: flex-start;
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.7);
  }
  .exam-notice i {
    margin-top: 2px;
    color: #00A9E0;
  }
  .custom-instructions-card {
    margin-top: 18px;
    border-radius: 18px;
    border: 1px solid rgba(124,58,237,0.14);
    background: linear-gradient(135deg, rgba(124,58,237,0.05), rgba(59,130,246,0.04));
    box-shadow: 0 10px 24px rgba(14,42,71,0.06);
    overflow: hidden;
  }
  .custom-instructions-head {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 16px;
    background: rgba(255,255,255,0.68);
    border-bottom: 1px solid rgba(124,58,237,0.10);
    color: #0E2A47;
    font-weight: 900;
  }
  .custom-instructions-head i { color: #7c3aed; }
  .custom-instructions-body {
    padding: 14px 16px 16px;
    color: #44576e;
    font-size: 13px;
    line-height: 1.75;
  }
  .bilingual-notice {
    margin-top: 18px;
    border-radius: 18px;
    overflow: hidden;
    border: 1px solid rgba(0,169,224,0.14);
    background: linear-gradient(135deg, rgba(14,42,71,0.05), rgba(0,169,224,0.04), rgba(255,153,51,0.04));
    box-shadow: 0 10px 24px rgba(14,42,71,0.06);
  }
  .bilingual-notice-head {
    padding: 14px 16px;
    background: rgba(255,255,255,0.72);
    border-bottom: 1px solid rgba(0,169,224,0.10);
    color: #0E2A47;
    font-weight: 900;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .bilingual-notice-head i { color: #00A9E0; }
  .bilingual-notice-body {
    display: grid;
    gap: 12px;
    padding: 14px 16px 16px;
  }
  .bilingual-line {
    margin: 0;
    color: #455a72;
    font-size: 13px;
    line-height: 1.7;
  }
  .bilingual-line strong {
    color: #0E2A47;
  }
  .declaration-card {
    margin-top: 18px;
    padding: 16px;
    border-radius: 18px;
    border: 1px solid rgba(16,185,129,0.16);
    background: linear-gradient(135deg, rgba(16,185,129,0.05), rgba(14,42,71,0.03));
    box-shadow: 0 10px 24px rgba(14,42,71,0.06);
  }
  .declaration-title {
    margin: 0 0 8px;
    color: #0E2A47;
    font-weight: 900;
    font-size: 14px;
  }
  .declaration-text {
    color: #44576e;
    font-size: 13px;
    line-height: 1.7;
  }
  .declaration-text .hi {
    display: block;
    margin-top: 6px;
    color: #5a6f86;
  }
  .intro-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
    color: #0E2A47;
    background: rgba(255,255,255,0.82);
    border: 1px solid rgba(0,169,224,0.12);
    box-shadow: 0 6px 14px rgba(14,42,71,0.06);
  }
  .intro-chip i { color: #00A9E0; }
  .countdown-stage {
    position: relative;
    display: grid;
    place-items: center;
    padding: 18px 12px 26px;
    margin: 8px 0 16px;
  }
  .countdown-stage::before,
  .countdown-stage::after {
    content: '';
    position: absolute;
    width: 170px;
    height: 170px;
    border-radius: 50%;
    filter: blur(2px);
    opacity: 0.34;
    animation: floatBlob 8s ease-in-out infinite;
    pointer-events: none;
  }
  .countdown-stage::before {
    left: 8%;
    top: 6%;
    background: radial-gradient(circle, rgba(0,169,224,0.38), rgba(0,169,224,0));
  }
  .countdown-stage::after {
    right: 8%;
    bottom: 4%;
    background: radial-gradient(circle, rgba(255,153,51,0.34), rgba(255,153,51,0));
    animation-delay: -2s;
  }
  .countdown-rings {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    pointer-events: none;
  }
  .countdown-ring {
    position: absolute;
    border-radius: 50%;
    border: 1px solid rgba(0,169,224,0.14);
    animation: ringPulse 2.4s ease-in-out infinite;
  }
  .countdown-ring.one { width: 220px; height: 220px; }
  .countdown-ring.two { width: 290px; height: 290px; animation-delay: .3s; }
  .countdown-ring.three { width: 360px; height: 360px; animation-delay: .6s; }
  .countdown-badge {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 8px 14px;
    border-radius: 999px;
    margin-bottom: 14px;
    background: linear-gradient(90deg, rgba(14,42,71,0.92), rgba(0,169,224,0.92), rgba(255,153,51,0.92));
    background-size: 200% 100%;
    color: #fff;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    box-shadow: 0 12px 22px rgba(0,169,224,0.18);
    animation: badgeShift 4s ease infinite, badgeFloat 2.6s ease-in-out infinite;
  }
  .countdown-badge i { color: #ffd166; }
  #bigTimer {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 380px;
    padding: 10px 16px;
    border-radius: 0;
    background: transparent;
    color: #fff !important;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.18em;
    font-family: 'Courier New', Courier, monospace;
    font-weight: 900;
    font-size: clamp(4rem, 10vw, 7rem);
    line-height: 1;
    text-transform: uppercase;
    text-shadow:
      0 0 10px rgba(0, 255, 198, 0.72),
      0 0 18px rgba(0, 169, 224, 0.78),
      0 0 28px rgba(255, 153, 51, 0.28),
      0 10px 26px rgba(0,0,0,0.25);
    animation: timerBreath 1.6s ease-in-out infinite, timerHue 5s linear infinite, digitalFlicker 4.2s infinite;
  }
  #bigTimer::before {
    content: '';
    position: absolute;
    inset: -10px -18px;
    border-radius: 18px;
    background: radial-gradient(circle, rgba(0,169,224,0.18), transparent 68%);
    pointer-events: none;
    z-index: -1;
  }
  #bigTimer::after {
    content: '';
    position: absolute;
    left: 8px;
    right: 8px;
    top: 50%;
    height: 3px;
    transform: translateY(-50%);
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.22), transparent);
    opacity: 0.88;
    pointer-events: none;
    animation: scanLine 2.1s ease-in-out infinite;
  }
  #bigTimer.timer-urgent {
    color: #fff8f2 !important;
    text-shadow:
      0 0 12px rgba(239, 68, 68, 0.88),
      0 0 20px rgba(245, 158, 11, 0.54),
      0 12px 26px rgba(0,0,0,0.28);
    animation: timerPulse 0.72s ease-in-out infinite, timerHue 2.4s linear infinite, digitalFlicker 0.9s infinite;
  }
  #bigTimer .timer-digit {
    display: inline-block;
    min-width: 0.68em;
    padding: 0 0.03em;
    border-radius: 10px;
    background: linear-gradient(180deg, rgba(255,255,255,0.06), rgba(255,255,255,0.015));
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.10);
  }
  #bigTimer .timer-colon {
    display: inline-block;
    width: 0.36em;
    text-align: center;
    color: #ffd166;
    animation: colonBlink 1s steps(2, end) infinite;
  }
  .countdown-progress {
    width: min(520px, 92%);
    height: 14px;
    margin: 18px auto 0;
    border-radius: 999px;
    overflow: hidden;
    background: rgba(255,255,255,0.22);
    border: 1px solid rgba(255,255,255,0.18);
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.12);
  }
  .countdown-progress > span {
    display: block;
    height: 100%;
    width: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #10b981, #00A9E0, #FF9933);
    background-size: 200% 100%;
    transform-origin: left center;
    transition: width 0.35s ease, filter 0.35s ease;
    animation: progressShift 3.5s linear infinite;
  }
  .countdown-progress.urgent > span {
    background: linear-gradient(90deg, #ef4444, #f97316, #f59e0b);
  }
  .countdown-hint {
    margin-top: 14px;
    font-size: 13px;
    color: #41556d;
    line-height: 1.5;
  }
  .countdown-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: center;
  }
  .countdown-actions .btn {
    min-width: 170px;
    border-radius: 999px;
    padding: 12px 18px;
    font-weight: 800;
    letter-spacing: 0.02em;
    box-shadow: 0 10px 20px rgba(14,42,71,0.12);
  }
  @keyframes badgeShift { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
  @keyframes badgeFloat { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-3px)} }
  @keyframes ringPulse { 0%,100%{transform:scale(0.96); opacity:0.18} 50%{transform:scale(1); opacity:0.4} }
  @keyframes floatBlob { 0%,100%{transform:translateY(0) scale(1)} 50%{transform:translateY(-12px) scale(1.06)} }
  @keyframes timerBreath { 0%,100%{transform:scale(1)} 50%{transform:scale(1.025)} }
  @keyframes timerPulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.045)} }
  @keyframes timerHue { 0%{filter:hue-rotate(0deg) saturate(1.05)} 100%{filter:hue-rotate(360deg) saturate(1.05)} }
  @keyframes progressShift { 0%{background-position:0% 50%} 100%{background-position:200% 50%} }
  @keyframes digitalFlicker {
    0%, 100% { opacity: 1; }
    47% { opacity: 0.99; }
    48% { opacity: 0.92; }
    49% { opacity: 1; }
    78% { opacity: 0.98; }
    79% { opacity: 0.96; }
    80% { opacity: 1; }
  }
  @keyframes scanLine {
    0%, 100% { opacity: 0.35; transform: translateY(-50%) scaleX(0.92); }
    50% { opacity: 0.9; transform: translateY(-50%) scaleX(1.02); }
  }
  @keyframes colonBlink {
    0%, 49% { opacity: 1; }
    50%, 100% { opacity: 0.35; }
  }
</style>

<main class="container py-4" style="max-width:900px">
  <div class="exam-card exam-intro-shell p-4 p-md-5">
    <div class="exam-intro-header">
      <div>
        <div class="exam-title-row">
          <h2 class="exam-intro-title mb-0"><?= h($exam['exam_name']) ?></h2>
          <span class="intro-chip"><i class="fas fa-shield-alt"></i><?= $showCountdown ? t('in_exam_type_join') : t('in_exam_type_direct') ?></span>
        </div>
        <div class="exam-code-line"><i class="fas fa-barcode me-1"></i><?= t('in_exam_code_label') ?>: <?= h($exam['exam_code'] ?: ('EXAM-' . $eid)) ?></div>
        <div class="exam-intro-subtitle">
          <?= $showCountdown ? t('in_intro_join') : t('in_intro_direct') ?>
        </div>
        <div class="exam-intro-meta">
          <span class="intro-chip"><i class="far fa-clock"></i><?= (int)$exam['duration_minutes'] ?> <?= $isHi ? 'मिनट' : 'min' ?></span>
          <span class="intro-chip"><i class="fas fa-list-ol"></i><?= (int)$exam['qcount'] ?> <?= $isHi ? 'प्रश्न' : 'questions' ?></span>
          <span class="intro-chip"><i class="fas fa-redo"></i><?= $attempts_left ?> <?= $isHi ? 'प्रयास शेष' : 'attempts left' ?></span>
        </div>
      </div>
    </div>
    <?php $guidanceCards = [
      ['1', t('in_guidance_1_title'), t('in_guidance_1_body')],
      ['2', t('in_guidance_2_title'), t('in_guidance_2_body')],
      ['3', t('in_guidance_3_title'), t('in_guidance_3_body')],
      ['4', t('in_guidance_4_title'), t('in_guidance_4_body')],
      ['5', t('in_guidance_5_title'), t('in_guidance_5_body')],
      ['6', t('in_guidance_6_title'), t('in_guidance_6_body')],
      ['7', t('in_guidance_7_title'), t('in_guidance_7_body')],
      ['8', t('in_guidance_8_title'), t('in_guidance_8_body')],
    ]; ?>
    <div class="exam-guidance-grid">
      <?php foreach ($guidanceCards as $s): ?>
        <div class="exam-guidance-card">
          <div class="exam-guidance-head">
            <div class="exam-guidance-step"><?= h($s[0]) ?></div>
            <h5 class="exam-guidance-title"><?= h($s[1]) ?></h5>
          </div>
          <div class="exam-guidance-body">
            <?= $isHi ? h($s[2]) : $s[2] ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="bilingual-notice">
      <div class="bilingual-notice-head"><i class="fas fa-bullhorn"></i><?= t('in_notice_title') ?></div>
      <div class="bilingual-notice-body">
        <p class="bilingual-line"><?= t('in_notice_body') ?></p>
      </div>
    </div>

    <?php if (!empty($exam['instructions'])): ?>
      <div class="custom-instructions-card">
        <div class="custom-instructions-head"><i class="fas fa-file-lines"></i><?= t('in_office_title') ?></div>
        <div class="custom-instructions-body">
          <p class="mb-0"><?= t('in_office_preamble') ?></p>
          <div class="mt-2"><?= nl2br(h($exam['instructions'])) ?></div>
        </div>
      </div>
    <?php endif; ?>

    <div class="exam-notice">
      <i class="fas fa-circle-info"></i>
      <div>
        <div class="fw-bold text-dark"><?= t('in_compliance_title') ?></div>
        <div class="small"><?= t('in_compliance_body') ?></div>
      </div>
    </div>

    <div class="declaration-card">
      <div class="declaration-title"><?= t('in_declaration_title') ?></div>
      <div class="declaration-text">
        <?= t('in_declaration_body') ?>
      </div>
    </div>

    <form id="beginForm" method="get" action="<?= url('student/take-exam.php') ?>" class="mt-4">
      <input type="hidden" name="exam_id" value="<?= $eid ?>">
      <div class="form-check mb-3">
        <input type="checkbox" class="form-check-input" id="agree" required>
        <label class="form-check-label small" for="agree">
          <?= t('in_agree') ?>
          <span class="d-block text-muted mt-1"><?= t('in_agree_detail') ?></span>
        </label>
      </div>
      <?php if ($canStart): ?>
        <button id="confirmBeginBtn" type="button" class="btn btn-success btn-lg"><i class="fas fa-shield-alt me-2"></i><?= t('in_begin') ?></button>
      <?php else: ?>
        <button type="button" class="btn btn-secondary btn-lg" disabled><i class="fas fa-ban me-2"></i><?= t('sd_join_window_closed') ?></button>
      <?php endif; ?>
      <a href="<?= url('student/dashboard.php') ?>" class="btn btn-outline-secondary btn-lg ms-2">Cancel</a>
    </form>

    <!-- Fullscreen start overlay -->
    <div id="startOverlay" style="display:none; position:fixed; inset:0; background:radial-gradient(circle at 20% 10%, rgba(255,215,0,0.08), rgba(0,0,0,0.9)); z-index:1050; align-items:center; justify-content:center;">
      <div style="text-align:center; color:#fff; width:100%;">
        <div style="max-width:980px; margin:0 auto;">
          <h2 id="overlayTitle" class="mb-3" style="font-size:28px; font-weight:700; text-shadow:0 2px 6px rgba(0,0,0,0.6)"><?= t('in_overlay_preparing') ?></h2>
          <?php if ($showCountdown): ?>
            <div class="countdown-stage">
              <div class="countdown-rings" aria-hidden="true">
                <span class="countdown-ring one"></span>
                <span class="countdown-ring two"></span>
                <span class="countdown-ring three"></span>
              </div>
              <div class="countdown-badge"><i class="fas fa-hourglass-half"></i><?= t('in_overlay_countdown_badge') ?></div>
              <div id="bigTimer">--:--</div>
              <div class="countdown-progress"><span id="countdownBar"></span></div>
            </div>
            <p id="overlayMsg" class="mb-4" style="opacity:0.9"><?= t('in_overlay_countdown_msg') ?></p>
          <?php else: ?>
            <div id="bigTimer" style="display:none"></div>
            <p id="overlayMsg" class="mb-4" style="opacity:0.9"><?= t('in_overlay_direct_msg') ?></p>
          <?php endif; ?>
          <div class="countdown-actions">
            <button id="startNowBtn" class="btn btn-lg btn-primary me-2" disabled><?= t('in_overlay_start') ?></button>
            <button id="overlayCancelBtn" class="btn btn-lg btn-outline-light"><?= t('in_overlay_cancel') ?></button>
          </div>
          <div class="countdown-hint">
            <i class="fas fa-circle-info me-1"></i>
            <?= $showCountdown ? t('in_overlay_hint_countdown') : t('in_overlay_hint_direct') ?>
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
      const showCountdown = <?= $showCountdown ? 'true' : 'false' ?>;
      const eid = <?= $eid ?>;
      const confirmBtn = document.getElementById('confirmBeginBtn');
      const overlay = document.getElementById('startOverlay');
      const bigTimer = document.getElementById('bigTimer');
      const countdownBar = document.getElementById('countdownBar');
      const countdownProgress = countdownBar ? countdownBar.parentElement : null;
      const startNowBtn = document.getElementById('startNowBtn');
      const overlayCancelBtn = document.getElementById('overlayCancelBtn');
      const beginForm = document.getElementById('beginForm');
      const agree = document.getElementById('agree');

      let timerId = null;
      let autoStartTriggered = false;
      let countdownTotalMs = 0;

      function formatMS(ms){
        if (ms <= 0) return '00:00';
        const s = Math.floor(ms/1000);
        const mm = String(Math.floor(s/60)).padStart(2,'0');
        const ss = String(s%60).padStart(2,'0');
        return `${mm}:${ss}`;
      }

      function setTimerDisplay(text) {
        if (!bigTimer) return;
        const safeText = String(text || '00:00');
        bigTimer.innerHTML = safeText
          .split('')
          .map(ch => ch === ':' ? '<span class="timer-colon">:</span>' : `<span class="timer-digit">${ch}</span>`)
          .join('');
      }

      function updateTimer(){
        if (!showCountdown) {
          startNowBtn.disabled = false;
          return;
        }
        const now = Date.now();
        const rem = startTs - now;
        const total = Math.max(1, countdownTotalMs || rem || 1);
        const ratio = Math.max(0, Math.min(1, rem / total));
        if (rem <= 0){
          setTimerDisplay('00:00');
          if (countdownBar) countdownBar.style.width = '100%';
          if (countdownProgress) countdownProgress.classList.add('urgent');
          if (bigTimer) bigTimer.classList.add('timer-urgent');
          startNowBtn.disabled = false;
          startNowBtn.classList.remove('btn-secondary');
          startNowBtn.classList.add('btn-success');
          // stop interval but keep overlay visible until user clicks start
          clearInterval(timerId);
          timerId = null;
          return;
        }
        setTimerDisplay(formatMS(rem));
        if (countdownBar) countdownBar.style.width = `${Math.max(4, Math.round(ratio * 100))}%`;
        if (countdownProgress) countdownProgress.classList.toggle('urgent', rem <= 10000);
        if (bigTimer) bigTimer.classList.toggle('timer-urgent', rem <= 10000);
      }

      function openOverlay(){
        overlay.style.display = 'flex';
        countdownTotalMs = showCountdown ? Math.max(1, startTs - Date.now()) : 0;
        if (countdownBar && showCountdown) countdownBar.style.width = '100%';
        updateTimer();
        if (showCountdown && !timerId) timerId = setInterval(updateTimer, 500);
      }

      function requestFullscreenAndSubmit(){
        const el = document.documentElement;
        const fs = el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen || el.msRequestFullscreen;
        if (fs) {
          try { fs.call(el); } catch (err) { /* ignore */ }
        }
        beginForm.submit();
      }

      function autoStartExam(){
        if (autoStartTriggered) return;
        autoStartTriggered = true;
        if (timerId) { clearInterval(timerId); timerId = null; }
        setTimerDisplay('00:00');
        
        // Request fullscreen before redirecting
        const el = document.documentElement;
        const fs = el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen || el.msRequestFullscreen;
        if (fs) {
          try { fs.call(el); } catch (err) { /* ignore */ }
        }
        
        // Redirect to take-exam page
        setTimeout(function() {
          window.location.href = '<?= url("student/take-exam.php") ?>?exam_id=' + eid;
        }, 200);
      }

      function closeOverlay(){
        overlay.style.display = 'none';
        if (timerId){ clearInterval(timerId); timerId = null; }
        autoStartTriggered = false;
        countdownTotalMs = 0;
        if (countdownProgress) countdownProgress.classList.remove('urgent');
        if (bigTimer) bigTimer.classList.remove('timer-urgent');
      }

      if (confirmBtn) confirmBtn.addEventListener('click', function(){
        // validate checkbox
        if (!agree.checkValidity()){
          agree.reportValidity();
          return;
        }
        if (showCountdown) {
          openOverlay();
        } else {
          requestFullscreenAndSubmit();
        }
      });

      overlayCancelBtn.addEventListener('click', function(e){
        e.preventDefault();
        closeOverlay();
      });

      startNowBtn.addEventListener('click', function(e){
        // when enabled, request fullscreen then submit
        if (startNowBtn.disabled) return;
        requestFullscreenAndSubmit();
      });

      // keyboard: Esc closes overlay
      document.addEventListener('keydown', function(ev){ if (ev.key === 'Escape' && overlay.style.display === 'flex'){ closeOverlay(); } });

      if (showCountdown) {
        const originalUpdateTimer = updateTimer;
        updateTimer = function(){
          const now = Date.now();
          const rem = startTs - now;
          if (rem <= 1000) {
            // Redirect when timer reaches 00:01 or less
            setTimerDisplay('00:00');
            autoStartExam();
            return;
          }
          originalUpdateTimer();
        };
      }
    })();
    </script>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
