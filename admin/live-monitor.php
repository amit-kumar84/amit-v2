<?php $ADMIN_TITLE = 'Live Monitor';
require_once __DIR__ . '/../includes/helpers.php'; $me = require_login('admin');
ensure_softdelete_and_permissions();
require __DIR__ . '/_shell_top.php';

[$ownWhere, $ownParams] = ownership_sql_clause('exams', 'e', $me);
if (empty($me['is_super']) && !can_view_all('exams', $me)) {
  $acc = accessible_exam_ids($me);
  if ($acc) { $in = implode(',', array_map('intval', $acc)); $ownWhere = " AND e.id IN ($in)"; $ownParams = []; }
}
$filter = $_GET['filter'] ?? 'open'; // open = upcoming + live by default
$extraStatusWhere = '';
switch ($filter) {
  case 'all':
    $extraStatusWhere = '';
    break;
  case 'live':
    $extraStatusWhere = ' AND NOW() BETWEEN e.start_time AND e.end_time';
    break;
  case 'upcoming':
    $extraStatusWhere = ' AND NOW() < e.start_time';
    break;
  case 'closed':
    $extraStatusWhere = ' AND NOW() > e.end_time';
    break;
  case 'open':
  case 'upcoming_live':
  default:
    // upcoming + live
    $extraStatusWhere = ' AND (NOW() < e.start_time OR NOW() BETWEEN e.start_time AND e.end_time)';
}
$sql = 'SELECT e.*, creator.name AS creator_name, creator.email AS creator_email,
          (SELECT COUNT(*) FROM exam_assignments WHERE exam_id=e.id) AS registered,
          (SELECT COUNT(*) FROM attempts WHERE exam_id=e.id AND status="in_progress") AS live,
          (SELECT COUNT(*) FROM attempts WHERE exam_id=e.id AND status="submitted") AS submitted,
          (SELECT COUNT(*)
             FROM violations v
             JOIN attempts a ON a.id = v.attempt_id
            WHERE a.exam_id = e.id) AS violations
        FROM exams e LEFT JOIN users creator ON creator.id=e.created_by
        WHERE e.deleted_at IS NULL' . $ownWhere . '
        ORDER BY
          CASE WHEN NOW() BETWEEN e.start_time AND e.end_time THEN 0
               WHEN NOW() < e.start_time THEN 1 ELSE 2 END,
          e.start_time DESC';
$stmt = db()->prepare($sql);
$stmt->execute($ownParams);
$exams = $stmt->fetchAll();

function matches_live_monitor_filter(string $filter, string $status): bool {
  return $filter === 'all'
    || ($filter === 'live' && $status === 'active')
    || ($filter === 'upcoming' && $status === 'upcoming')
    || ($filter === 'closed' && $status === 'closed')
    || ($filter === 'open' && in_array($status, ['active', 'upcoming'], true));
}
$exams = array_values(array_filter($exams, fn($e) => matches_live_monitor_filter($filter, exam_status($e))));
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-1 fw-bold"><i class="fas fa-satellite-dish text-danger me-2"></i>Live Examination Monitoring</h5>
    <p class="text-muted small mb-0">Real-time proctoring — click any exam to open the classroom view.</p>
  </div>
  <div class="d-flex gap-3 align-items-center flex-wrap">
    <form method="get" class="mb-0">
      <select name="filter" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="open" <?= $filter==='open' ? 'selected' : '' ?>>Open (Live + Upcoming)</option>
        <option value="live" <?= $filter==='live' ? 'selected' : '' ?>>Live</option>
        <option value="upcoming" <?= $filter==='upcoming' ? 'selected' : '' ?>>Upcoming</option>
        <option value="closed" <?= $filter==='closed' ? 'selected' : '' ?>>Closed</option>
        <option value="all" <?= $filter==='all' ? 'selected' : '' ?>>All</option>
      </select>
    </form>
    <?php if (!$me['is_super'] && !can_view_all('exams',$me)): ?>
      <span class="badge bg-light text-dark border"><i class="fas fa-eye me-1"></i>Own + granted only</span>
    <?php else: ?>
      <span class="badge bg-success"><i class="fas fa-globe me-1"></i>All exams visible</span>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3" id="live-grid">
<?php foreach ($exams as $e): $st = exam_status($e); $registered = (int)$e['registered']; $liveN = (int)$e['live']; $submitted = (int)$e['submitted']; $absent = max(0, $registered - $liveN - $submitted); if ($st === 'upcoming') { $liveN = 0; $submitted = 0; $absent = 0; } ?>
  <div class="col-md-6 col-lg-4">
    <div class="exam-card" style="border-left:4px solid <?= $st==='active'?'#dc2626':($st==='upcoming'?'#f59e0b':'#94a3b8') ?>">
      <div class="d-flex justify-content-between align-items-start mb-2">
        <h6 class="fw-bold mb-0"><?= h($e['exam_name']) ?></h6>
        <?php if ($st==='active'): ?>
          <span class="badge bg-danger" style="animation:pulse 1.2s infinite"><span class="pulse-dot"></span> LIVE</span>
        <?php else: ?>
          <span class="pill status-<?= $st ?>"><?= $st ?></span>
        <?php endif; ?>
      </div>
      <div class="small text-muted mb-2">Code: <b><?= h($e['exam_code'] ?? '—') ?></b></div>
      <div class="small mb-2" style="background:#f8fafc; border-left:3px solid var(--navy); padding:6px 10px">
        <i class="fas fa-user-shield me-1"></i><b>Hosted by:</b> <?= h($e['creator_name'] ?? '—') ?>
        <?php if (!empty($e['creator_email'])): ?><div class="text-muted" style="font-size:11px"><?= h($e['creator_email']) ?></div><?php endif; ?>
      </div>
      <div class="row g-2 text-center mb-3 monitor-stats">
        <div class="col-6 col-sm-3"><div class="monitor-stat-box border rounded bg-light"><div class="fw-bold h5 mb-0"><?= $registered ?></div><small class="text-muted">Registered</small></div></div>
        <div class="col-6 col-sm-3"><div class="monitor-stat-box border rounded text-warning" style="background:#fef3c7"><div class="fw-bold h5 mb-0"><?= $liveN ?></div><small class="text-muted">Writing</small></div></div>
        <div class="col-6 col-sm-3"><div class="monitor-stat-box border rounded text-success" style="background:#d1fae5"><div class="fw-bold h5 mb-0"><?= $submitted ?></div><small class="text-muted">Done</small></div></div>
        <div class="col-6 col-sm-3"><div class="monitor-stat-box border rounded text-danger" style="background:#fee2e2"><div class="fw-bold h5 mb-0"><?= $absent ?></div><small class="text-muted">Absent</small></div></div>
      </div>
      <div class="small text-muted mb-2"><i class="far fa-clock me-1"></i><?= fmt_dt($e['start_time']) ?> → <?= fmt_dt($e['end_time']) ?></div>
      <a href="<?= url('admin/monitor-exam.php?exam_id='.$e['id']) ?>" class="btn btn-navy w-100"><i class="fas fa-chalkboard-teacher me-1"></i>Open Classroom View</a>
    </div>
  </div>
<?php endforeach; ?>
<?php if (!$exams): ?>
  <div class="col-12"><div class="exam-card text-center text-muted py-5"><i class="fas fa-satellite fa-2x mb-2"></i><div>No exams to monitor.</div></div></div>
<?php endif; ?>
</div>

<style>
.pulse-dot{display:inline-block;width:8px;height:8px;background:#fff;border-radius:50%;margin-right:4px;animation:pulse 1.2s infinite}
@keyframes pulse { 0%,100%{opacity:1}50%{opacity:.4} }
</style>
<script>setTimeout(()=>location.reload(), 5000);</script>
<?php require __DIR__ . '/_shell_bottom.php'; ?>
