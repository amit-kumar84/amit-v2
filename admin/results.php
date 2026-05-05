<?php $ADMIN_TITLE = 'Results & Attempt History';
require_once __DIR__ . '/../includes/helpers.php'; require_login('admin');
require __DIR__ . '/_shell_top.php';

$q = trim($_GET['q'] ?? '');
$examId = (int)($_GET['exam_id'] ?? 0);

// Load CLOSED exams only for filter dropdown
$exams = db()->query('SELECT id, exam_name, exam_code, end_time FROM exams WHERE end_time IS NOT NULL AND end_time < NOW() ORDER BY start_time DESC')->fetchAll();

// Build query for closed/expired exams only
$sql = 'SELECT a.*, u.name AS sname, u.email AS semail, u.roll_number, u.dob, e.exam_name, e.exam_code, e.start_time, e.end_time, e.created_by, c.name AS creator_name FROM attempts a JOIN users u ON u.id=a.user_id JOIN exams e ON e.id=a.exam_id LEFT JOIN users c ON c.id=e.created_by WHERE a.status="submitted" AND e.end_time IS NOT NULL AND e.end_time < NOW()';
if ($q) {
  $qLike = db()->quote("%$q%");
  $sql .= " AND (u.name LIKE $qLike OR e.exam_name LIKE $qLike OR u.roll_number LIKE $qLike OR u.dob LIKE $qLike";
  if (is_numeric($q)) {
    $sql .= ' OR a.exam_id=' . (int)$q;
  }
  $sql .= ')';
}
if ($examId) $sql .= ' AND a.exam_id=' . (int)$examId;
$rows = db()->query($sql . ' ORDER BY a.exam_id DESC, a.submitted_at DESC')->fetchAll();

// Group results by exam and calculate statistics
$examResults = [];
foreach ($rows as $r) {
  $eid = (int)$r['exam_id'];
  if (!isset($examResults[$eid])) {
    $examResults[$eid] = [
      'exam_name' => $r['exam_name'],
      'exam_code' => $r['exam_code'],
      'exam_id' => $eid,
      'creator_name' => $r['creator_name'] ?? 'System',
      'start_time' => $r['start_time'],
      'end_time' => $r['end_time'],
      'attempts' => [],
      'total_score' => 0,
      'pass_count' => 0
    ];
  }
  $examResults[$eid]['attempts'][] = $r;
  $examResults[$eid]['total_score'] += (float)$r['score'];
  if ($r['total'] > 0) {
    $pct = ($r['score'] / $r['total']) * 100;
    if ($pct >= 40) $examResults[$eid]['pass_count']++;
  }
}

// Calculate stats for each exam
foreach ($examResults as &$exam) {
  $attemptCount = count($exam['attempts']);
  $exam['avg_score'] = $attemptCount > 0 ? round($exam['total_score'] / $attemptCount, 2) : 0;
  $exam['pass_rate'] = $attemptCount > 0 ? round(($exam['pass_count'] / $attemptCount) * 100, 1) : 0;
}
unset($exam);
?>
<style>


  /* Exam Card */
  .exam-card { transition: all 0.32s cubic-bezier(.2,.9,.2,1); border: 1px solid rgba(2,6,23,0.06); border-radius: 16px; overflow: hidden; background: white; height: 100%; }
  .exam-card:hover { transform: translateY(-8px); box-shadow: 0 24px 60px rgba(2,6,23,0.15) !important; }

  /* Card Header */
  .exam-card-header { background: linear-gradient(135deg, #0E2A47 0%, #00A9E0 100%); color: white; padding: 20px; position: relative; overflow: hidden; }
  .exam-card-header::before { content: ''; position: absolute; top: -50%; right: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%); }
  .exam-card-header .title { position: relative; z-index: 1; font-size: 1.25rem; font-weight: 900; letter-spacing: -0.01em; margin: 0 0 4px 0; line-height: 1.3; }
  .exam-card-header .code { position: relative; z-index: 1; font-size: 0.8rem; color: rgba(255,255,255,0.80); letter-spacing: 0.03em; font-weight: 700; }
  .exam-card-header .badge-count { position: relative; z-index: 1; display: inline-block; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3); padding: 5px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; margin-top: 8px; animation: badge-pulse 2.2s ease-in-out infinite; }
  @keyframes badge-pulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.06); } }

  /* Card Body */
  .card-body { padding: 20px; }
  .exam-info-row { display: flex; align-items: center; margin-bottom: 12px; font-size: 0.9rem; }
  .exam-info-icon { width: 28px; height: 28px; background: linear-gradient(135deg, #e0f2fe, #f0fdf4); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #0E2A47; margin-right: 10px; font-weight: 700; flex-shrink: 0; }
  .exam-info-label { color: #64748b; font-weight: 600; font-size: 0.8rem; min-width: 70px; }
  .exam-info-value { color: #1e293b; font-weight: 700; flex-grow: 1; }

  .exam-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 16px 0; }
  .stat-box { background: linear-gradient(135deg, #f8fafc, #f0fdf4); border-radius: 10px; padding: 12px; text-align: center; border: 1px solid rgba(2,6,23,0.04); }
  .stat-value { font-size: 1.6rem; font-weight: 900; background: linear-gradient(135deg, #0E2A47, #00A9E0); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
  .stat-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 700; letter-spacing: 0.03em; margin-top: 3px; }

  .view-btn { background: linear-gradient(135deg, #0E2A47, #00A9E0); color: white; border: none; font-weight: 800; border-radius: 10px; padding: 10px 16px; transition: all 0.3s ease; letter-spacing: 0.02em; font-size: 0.9rem; }
  .view-btn:hover { transform: translateY(-3px); box-shadow: 0 16px 40px rgba(0,169,224,0.3); color: white; text-decoration: none; }
  .view-btn i { margin-right: 6px; }

  /* Filter Section */
  .filter-section { display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; align-items: center; }
  .filter-section input, .filter-section select { border-radius: 10px; border: 1px solid rgba(2,6,23,0.08); }
  .filter-section .btn { font-weight: 700; border-radius: 10px; }

  /* Grid Layout */
  .results-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }

  @media (max-width: 768px) {
    .results-header { padding: 24px; }
    .results-header h1 { font-size: 1.6rem; }
    .results-grid { grid-template-columns: 1fr; }
    .filter-section { flex-direction: column; }
    .filter-section input, .filter-section select { width: 100% !important; }
  }

  @media (max-width: 576px) {
    .results-header { padding: 16px; }
    .results-header h1 { font-size: 1.3rem; }
    .card-body { padding: 16px; }
    .exam-info-row { font-size: 0.85rem; }
  }
</style>

<div class="filter-section">
  <form class="d-flex gap-2 flex-grow-1">
    <input name="q" value="<?= h($q) ?>" class="form-control" placeholder="🔍 Search by student or exam..." style="flex:1; min-width:200px;">
    <select name="exam_id" class="form-select" style="min-width:200px;">
      <option value="">📚 All Exams</option>
      <?php foreach ($exams as $ex): ?>
        <option value="<?= (int)$ex['id'] ?>" <?= $examId === (int)$ex['id'] ? 'selected' : '' ?>><?= h($ex['exam_name']) ?> (<?= h($ex['exam_code']) ?>)</option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-outline-secondary" style="border-radius: 10px; font-weight: 700;">Filter</button>
  </form>
  <a href="<?= url('admin/export-results.php' . ($examId ? '?exam_id=' . $examId : '')) ?>" class="btn btn-success" style="border-radius: 10px; font-weight: 700; white-space: nowrap;"><i class="fas fa-download me-1"></i>Export</a>
</div>

<?php if (!$examResults): ?>
  <div class="alert alert-warning text-center py-5" style="border-radius: 14px; border: 2px solid #fbbf24; background: linear-gradient(135deg,#fef3c7,#fef9e7);">
    <i class="fas fa-inbox fa-3x mb-3" style="color: #d97706;"></i>
    <p style="font-weight: 700; font-size: 1.1rem; color: #92400e;">No closed exams with submissions yet</p>
  </div>
<?php else: ?>
  <div class="results-grid">
    <?php foreach ($examResults as $idx => $exam): ?>
      <div style="opacity: 0; animation: card-entrance 0.6s cubic-bezier(.2,.9,.2,1) forwards; animation-delay: <?= $idx * 0.08 ?>s;">
        <div class="card exam-card shadow-sm">
          <div class="exam-card-header">
            <h5 class="title"><?= h($exam['exam_name']) ?></h5>
            <small class="code">📝 <?= h($exam['exam_code']) ?></small>
            <div class="badge-count"><i class="fas fa-users"></i> <?= count($exam['attempts']) ?> Submissions</div>
          </div>
          <div class="card-body">
            <!-- Exam Info -->
            <div class="exam-info-row">
              <div class="exam-info-icon">👤</div>
              <div class="exam-info-label">Hosted by:</div>
              <div class="exam-info-value"><?= h($exam['creator_name']) ?></div>
            </div>
            <div class="exam-info-row">
              <div class="exam-info-icon">▶️</div>
              <div class="exam-info-label">Start:</div>
              <div class="exam-info-value"><?= fmt_dt($exam['start_time'], 'M d, Y \a\t g:i A') ?></div>
            </div>
            <div class="exam-info-row">
              <div class="exam-info-icon">⏹️</div>
              <div class="exam-info-label">End:</div>
              <div class="exam-info-value"><?= fmt_dt($exam['end_time'], 'M d, Y \a\t g:i A') ?></div>
            </div>

            <!-- Stats -->
            <div class="exam-stats">
              <div class="stat-box">
                <div class="stat-value"><?= $exam['avg_score'] ?></div>
                <div class="stat-label">Avg Score</div>
              </div>
              <div class="stat-box">
                <div class="stat-value"><?= $exam['pass_rate'] ?>%</div>
                <div class="stat-label">Pass Rate</div>
              </div>
            </div>

            <!-- Action Button -->
            <a href="<?= url('admin/exam-results-view.php?exam_id='.$exam['exam_id']) ?>" target="_blank" class="btn view-btn w-100">
              <i class="fas fa-chart-line"></i>View Details
            </a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <style>
    @keyframes card-entrance { from { opacity: 0; transform: translateY(16px) scale(0.96); } to { opacity: 1; transform: translateY(0) scale(1); } }
  </style>
<?php endif; ?>
<?php require __DIR__ . '/_shell_bottom.php'; ?>
