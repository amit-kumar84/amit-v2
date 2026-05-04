<?php $ADMIN_TITLE = 'Classroom Monitor';
require_once __DIR__ . '/../includes/helpers.php'; $me = require_login('admin');
ensure_softdelete_and_permissions();

$eid = (int)($_GET['exam_id'] ?? 0);
$exam = db()->prepare('SELECT e.*, creator.name AS creator_name, creator.email AS creator_email FROM exams e LEFT JOIN users creator ON creator.id=e.created_by WHERE e.id=? AND e.deleted_at IS NULL');
$exam->execute([$eid]);
$ex = $exam->fetch();
if (!$ex) { flash('Exam not found','error'); redirect(url('admin/live-monitor.php')); }
if (!$me['is_super'] && (int)$ex['created_by'] !== (int)$me['id']) {
  flash('You can monitor only exams you created.','error'); redirect(url('admin/live-monitor.php'));
}
require __DIR__ . '/_shell_top.php';
$st = exam_status($ex);
?>
<style>
.monitor-hero { 
  background:linear-gradient(135deg, #0E2A47 0%, #081a2e 50%, #0c1f35 100%); 
  color:#fff; 
  border-left:4px solid var(--saffron); 
  border-radius:8px; 
  padding:20px 24px;
  box-shadow:0 8px 24px rgba(15,23,42,.15);
  position:relative;
  overflow:hidden;
}
.monitor-hero::before {
  content:'';
  position:absolute;
  top:0;
  left:0;
  right:0;
  bottom:0;
  background:radial-gradient(circle at 100% 0%, rgba(245,158,11,.1) 0%, transparent 70%);
  pointer-events:none;
}
.monitor-hero h4 { 
  letter-spacing:.02em;
  position:relative;
  z-index:1;
}
.stat-chip { 
  background:linear-gradient(135deg, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0.05) 100%);
  border:1px solid rgba(255,255,255,0.2); 
  border-radius:8px; 
  padding:12px 18px; 
  min-width:120px; 
  text-align:center;
  transition:all .3s;
  backdrop-filter:blur(10px);
  box-shadow:0 4px 12px rgba(0,0,0,.1);
}
.stat-chip:hover {
  background:linear-gradient(135deg, rgba(255,255,255,0.25) 0%, rgba(255,255,255,0.15) 100%);
  transform:translateY(-2px);
  box-shadow:0 6px 16px rgba(0,0,0,.15);
}
.stat-chip .num { 
  font-size:28px; 
  font-weight:800; 
  line-height:1;
  transition:transform .2s;
}
.stat-chip:hover .num {
  transform:scale(1.1);
}
.stat-chip .lbl { 
  font-size:10px; 
  letter-spacing:.1em; 
  text-transform:uppercase; 
  opacity:.8; 
  margin-top:6px;
  font-weight:600;
}
.classroom { 
  display:grid; 
  grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); 
  gap:20px;
  animation:fadeIn .5s ease-out;
}
@keyframes fadeIn {
  0% { opacity:0; }
  100% { opacity:1; }
}
.status-group { 
  margin-bottom:24px;
  animation:fadeIn .5s ease-out;
}
.status-group:last-child { margin-bottom:0; }
.status-group h6 { 
  font-weight:800; 
  font-size:12px; 
  text-transform:uppercase; 
  letter-spacing:.08em; 
  color:#0f172a;
  margin-bottom:12px;
  display:flex;
  align-items:center;
  gap:8px;
}
.status-group h6::before {
  content:'';
  width:3px;
  height:18px;
  border-radius:2px;
  background:linear-gradient(180deg, #f59e0b, #f97316);
}
.status-group .grid { 
  display:grid; 
  grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); 
  gap:16px;
}
.stu-card { 
  background:linear-gradient(135deg, #e0f7ff 0%, #f0fafe 100%); 
  border:0; 
  border-radius:8px; 
  padding:14px; 
  position:relative; 
  transition:all .3s cubic-bezier(0.34, 1.56, 0.64, 1); 
  border-top:4px solid #94a3b8; 
  cursor:pointer; 
  box-shadow:0 2px 8px rgba(15,23,42,.08), 0 1px 3px rgba(15,23,42,.04); 
  overflow:hidden;
}
.stu-card::before {
  content:'';
  position:absolute;
  inset:0;
  background:linear-gradient(135deg, rgba(255,255,255,.5) 0%, rgba(255,255,255,0) 100%);
  opacity:0;
  transition:opacity .3s;
  pointer-events:none;
}
.stu-card:hover::before { opacity:1; }
.stu-card.writing { 
  border-top-color:#f59e0b; 
  background:linear-gradient(135deg, #dbeafe 0%, #e0f7ff 100%);
}
.stu-card.writing:hover { 
  box-shadow:0 8px 24px rgba(245,158,11,.25), 0 2px 8px rgba(15,23,42,.08);
  transform:translateY(-4px) scale(1.02);
}
.stu-card.submitted { 
  border-top-color:#16a34a; 
  background:linear-gradient(135deg, #dcfce7 0%, #e0f7ff 100%);
}
.stu-card.submitted:hover { 
  box-shadow:0 8px 24px rgba(22,163,74,.25), 0 2px 8px rgba(15,23,42,.08);
  transform:translateY(-4px) scale(1.02);
}
.stu-card.absent { 
  border-top-color:#dc2626; 
  background:linear-gradient(135deg, #fee2e2 0%, #e0f7ff 100%);
  opacity:0.75;
}
.stu-card.absent:hover { 
  box-shadow:0 8px 24px rgba(220,38,38,.2), 0 2px 8px rgba(15,23,42,.08);
  transform:translateY(-4px) scale(1.02);
  opacity:1;
}
.stu-card.closed-highlight { 
  box-shadow:0 4px 16px rgba(15,23,42,.15), 0 0 0 2px rgba(59,130,246,.3);
  border-color:rgba(59,130,246,.2);
}
.stu-card.absent.closed-highlight { 
  border-color:#dc2626; 
  box-shadow:0 4px 16px rgba(220,38,38,.2), 0 0 0 2px rgba(220,38,38,.2);
}
.stu-card.violated { 
  animation:shake .4s, pulse-red .6s;
  box-shadow:0 0 0 3px rgba(220,38,38,.4), 0 8px 24px rgba(220,38,38,.25);
}
.stu-card:hover { 
  transform:translateY(-3px); 
}
.stu-card .absent-stamp { 
  position:absolute; 
  top:-10px; 
  left:-8px; 
  transform:rotate(-25deg); 
  width:120px;
  height:120px;
  opacity:0.15;
  z-index:1;
  pointer-events:none;
  display:flex;
  align-items:center;
  justify-content:center;
  overflow:hidden;
}
.stu-card .absent-stamp img {
  width:100%;
  height:100%;
  object-fit:contain;
  filter:grayscale(100%) brightness(0);
}
.stu-card .card-actions { 
  display:flex; 
  justify-content:center; 
  margin-top:12px;
  padding-top:10px;
  border-top:1px solid rgba(15,23,42,.05);
}
.stu-card .card-actions .btn { 
  width:100%;
  transition:all .3s;
  font-size:12px;
  font-weight:600;
  text-transform:uppercase;
  letter-spacing:.05em;
}
.stu-card .card-actions .btn:hover {
  transform:scale(1.05);
}
.stu-photo { 
  width:72px; 
  height:92px; 
  object-fit:cover; 
  border:2px solid #cbd5e1; 
  border-radius:6px; 
  display:block; 
  margin:0 auto 10px;
  transition:all .3s;
  box-shadow:0 4px 12px rgba(0,0,0,.1);
}
.stu-card:hover .stu-photo {
  border-color:#f59e0b;
  box-shadow:0 6px 16px rgba(245,158,11,.2);
  transform:scale(1.05);
}
.stu-no-photo { 
  width:72px; 
  height:92px; 
  border:2px dashed #cbd5e1; 
  display:flex; 
  align-items:center; 
  justify-content:center; 
  color:#94a3b8; 
  font-size:11px; 
  margin:0 auto 10px; 
  border-radius:6px;
  background:linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  transition:all .3s;
}
.stu-card:hover .stu-no-photo {
  border-color:#f59e0b;
  background:linear-gradient(135deg, #fffbf0 0%, #fff9f5 100%);
}
.stu-meta { 
  font-size:11px; 
  line-height:1.5;
  padding:2px 0;
}
.stu-meta .name { 
  font-weight:700; 
  color:#0f172a; 
  font-size:13px; 
  margin-bottom:4px; 
  text-align:center;
  display:block;
  transition:color .2s;
}
.stu-card:hover .stu-meta .name {
  color:#f59e0b;
}
.stu-meta .roll { 
  font-family:monospace; 
  color:#475569; 
  text-align:center;
  display:block;
  margin-bottom:2px;
  font-size:10px;
}
.stu-meta .dob { 
  color:#64748b; 
  text-align:center; 
  font-size:10px;
  display:block;
}
.stu-badge { 
  position:absolute; 
  top:8px; 
  right:8px; 
  font-size:10px; 
  font-weight:700; 
  padding:4px 10px; 
  border-radius:12px; 
  text-transform:uppercase; 
  letter-spacing:.08em;
  box-shadow:0 2px 6px rgba(0,0,0,.1);
  backdrop-filter:blur(10px);
  animation:bounce-in .4s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.stu-badge.writing { 
  background:linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
  color:#fff;
  box-shadow:0 4px 12px rgba(245,158,11,.3);
}
.stu-badge.submitted { 
  background:linear-gradient(135deg, #34d399 0%, #10b981 100%);
  color:#fff;
  box-shadow:0 4px 12px rgba(16,185,129,.3);
}
.stu-badge.absent { 
  background:linear-gradient(135deg, #f87171 0%, #ef4444 100%);
  color:#fff;
  box-shadow:0 4px 12px rgba(239,68,68,.3);
}
.viol-indicator { 
  position:absolute; 
  top:8px; 
  left:8px; 
  font-size:10px; 
  font-weight:700; 
  padding:4px 10px; 
  border-radius:12px; 
  background:linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
  color:#fff;
  box-shadow:0 4px 12px rgba(239,68,68,.35);
  animation:pulse-red .6s infinite;
  display:flex;
  align-items:center;
  gap:4px;
}
.timer-pill { 
  background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
  color:#fff; 
  font-family:monospace; 
  font-weight:700; 
  padding:8px 16px; 
  border-radius:6px; 
  letter-spacing:.05em;
  box-shadow:0 4px 12px rgba(15,23,42,.3);
  transition:all .3s;
  display:inline-block;
}
.timer-pill:hover {
  transform:scale(1.05);
  box-shadow:0 6px 16px rgba(15,23,42,.4);
}
.timer-pill.warn { 
  background:linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  box-shadow:0 4px 12px rgba(245,158,11,.3);
  animation:pulse-warn 1.5s ease-in-out infinite;
}
.timer-pill.danger { 
  background:linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
  box-shadow:0 4px 12px rgba(220,38,38,.4);
  animation:pulse 1s infinite;
}
@keyframes pulse-warn {
  0%, 100% { transform:scale(1); }
  50% { transform:scale(1.05); }
}
@keyframes shake { 
  0%, 100% { transform:translateX(0); } 
  10%, 30%, 50%, 70%, 90% { transform:translateX(-2px); } 
  20%, 40%, 60%, 80% { transform:translateX(2px); } 
}
@keyframes pulse-red { 
  0%, 100% { box-shadow:0 0 0 3px rgba(220,38,38,.4), 0 8px 24px rgba(220,38,38,.25); }
  50% { box-shadow:0 0 0 8px rgba(220,38,38,.1), 0 8px 24px rgba(220,38,38,.25); }
}
@keyframes pulse { 0%,100%{opacity:1}50%{opacity:.7} }
@keyframes bounce-in { 
  0% { transform:scale(0.95) translateY(2px); opacity:0; }
  100% { transform:scale(1) translateY(0); opacity:1; }
}
@keyframes pulse-glow {
  0%, 100% { box-shadow:0 0 0 0 rgba(245,158,11,.7), 0 8px 24px rgba(245,158,11,.2); }
  50% { box-shadow:0 0 0 12px rgba(245,158,11,.0), 0 8px 24px rgba(245,158,11,.2); }
}
@keyframes slideDown {
  0% { transform:translateY(-20px); opacity:0; }
  100% { transform:translateY(0); opacity:1; }
}
@keyframes float {
  0%, 100% { transform:translateY(0px); }
  50% { transform:translateY(-8px); }
}
@keyframes countdown-pulse {
  0%, 100% { transform:scale(1); }
  50% { transform:scale(1.05); }
}
.pre-live-banner { 
  display:none; 
  background:linear-gradient(135deg, #fff7ed 0%, #fef3c7 40%, #fff 100%);
  border:2px solid #fed7aa;
  border-left:6px solid #f59e0b;
  border-radius:8px; 
  padding:24px 28px; 
  margin-bottom:20px;
  box-shadow:0 8px 32px rgba(245,158,11,.15), 0 4px 12px rgba(15,23,42,.08);
  animation:slideDown .5s ease-out, pulse-glow 2s ease-in-out infinite;
  position:relative;
  overflow:hidden;
}
.pre-live-banner::before {
  content:'';
  position:absolute;
  top:0;
  left:0;
  right:0;
  height:3px;
  background:linear-gradient(90deg, transparent, #f59e0b, transparent);
  animation:shimmer 2s infinite;
}
@keyframes shimmer {
  0% { transform:translateX(-100%); }
  100% { transform:translateX(100%); }
}
.pre-live-banner.open { display:block; }
.pre-live-banner .countdown { 
  font-family:monospace; 
  font-size:42px; 
  font-weight:900;
  color:#dc2626;
  letter-spacing:.08em;
  text-shadow:0 2px 8px rgba(220,38,38,.3);
  animation:countdown-pulse 1s ease-in-out infinite;
  display:inline-block;
  background:linear-gradient(135deg, #fff4e6 0%, #fffbf0 100%);
  padding:12px 20px;
  border-radius:8px;
  border:2px solid #fed7aa;
  box-shadow:0 4px 12px rgba(220,38,38,.15);
}
.pre-live-banner .hint { 
  color:#92400e;
  font-size:13px;
  font-weight:600;
  letter-spacing:.05em;
}
.pre-live-banner > div:first-child {
  animation:float 3s ease-in-out infinite;
}
.monitor-locked { 
  background:linear-gradient(135deg, #f0f9ff 0%, #e0f7ff 50%, #f0f9ff 100%);
  border:2px dashed #06b6d4;
  border-radius:8px; 
  padding:48px 32px; 
  text-align:center; 
  color:#0c4a6e;
  animation:slideDown .5s ease-out;
  position:relative;
  overflow:hidden;
}
.monitor-locked::before {
  content:'';
  position:absolute;
  inset:0;
  background:radial-gradient(circle at 20% 50%, rgba(6,182,212,.05) 0%, transparent 50%),
             radial-gradient(circle at 80% 80%, rgba(34,211,238,.05) 0%, transparent 50%);
  pointer-events:none;
}
.monitor-locked > * {
  position:relative;
  z-index:1;
}
.monitor-locked .fw-bold { 
  font-size:18px;
  margin-bottom:8px;
  color:#0369a1;
  animation:bounce-in .6s ease-out;
}
.monitor-locked > div:last-child {
  font-size:14px;
  color:#0c4a6e;
  margin-top:8px;
  animation:bounce-in .8s ease-out;
}

/* Notification bell */
.notif-bell { position:relative; background:#fff; border:1px solid #cbd5e1; border-radius:50%; width:44px; height:44px; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; transition:all .15s; }
.notif-bell:hover { background:#f1f5f9; }
.notif-bell.ring { animation:ring .6s ease-in-out 3; }
@keyframes ring { 0%,100%{transform:rotate(0)} 25%{transform:rotate(-15deg)} 75%{transform:rotate(15deg)} }
.notif-bell .count { position:absolute; top:-4px; right:-4px; background:#dc2626; color:#fff; border-radius:50%; min-width:20px; height:20px; font-size:11px; font-weight:700; display:flex; align-items:center; justify-content:center; padding:0 5px; }
.notif-panel { position:fixed; top:70px; right:20px; width:420px; max-height:70vh; overflow-y:auto; background:#fff; border:1px solid #e2e8f0; border-radius:4px; box-shadow:0 10px 30px rgba(15,23,42,.2); z-index:3000; display:none; }
.notif-panel.open { display:block; }
.notif-panel header { padding:12px 16px; border-bottom:1px solid #e2e8f0; background:#f8fafc; display:flex; justify-content:space-between; align-items:center; }
.notif-panel .item { padding:10px 14px; border-bottom:1px solid #f1f5f9; display:flex; gap:10px; }
.notif-panel .item:last-child { border-bottom:0; }
.notif-panel .item img { width:40px; height:48px; object-fit:cover; border:1px solid #cbd5e1; border-radius:3px; flex-shrink:0; }
.notif-panel .item .no-pic { width:40px; height:48px; background:#e2e8f0; border-radius:3px; flex-shrink:0; display:flex; align-items:center; justify-content:center; color:#94a3b8; }
.notif-empty { text-align:center; padding:30px; color:#64748b; }

/* Center alert overlay */
.viol-alert-wrap { position:fixed; inset:0; background:rgba(15,23,42,.5); display:none; align-items:center; justify-content:center; z-index:4000; backdrop-filter:blur(3px); }
.viol-alert-wrap.open { display:flex; animation:fadein .2s; }
@keyframes fadein { from{opacity:0} to{opacity:1} }
.viol-alert { background:#fff; border:3px solid #dc2626; border-radius:6px; width:min(520px,92%); overflow:hidden; box-shadow:0 20px 60px rgba(220,38,38,.45); }
.viol-alert header { background:#dc2626; color:#fff; padding:14px 18px; display:flex; align-items:center; gap:10px; }
.viol-alert header h5 { margin:0; font-weight:800; letter-spacing:.02em; }
.viol-alert .body { display:flex; gap:16px; padding:18px; }
.viol-alert .body img { width:90px; height:110px; object-fit:cover; border:2px solid #0f172a; border-radius:3px; }
.viol-alert .body .no-pic { width:90px; height:110px; background:#e2e8f0; display:flex; align-items:center; justify-content:center; border-radius:3px; color:#94a3b8; font-size:11px; }
.viol-alert .name { font-size:20px; font-weight:800; color:#0f172a; }
.viol-alert .roll { font-family:monospace; color:#475569; font-weight:700; }
.viol-alert .dob { color:#64748b; font-size:12px; }
.viol-alert .what { background:#fee2e2; border-left:4px solid #dc2626; padding:10px 14px; margin-top:10px; color:#991b1b; font-weight:600; font-size:14px; }
.viol-alert footer { padding:10px 18px; display:flex; gap:8px; justify-content:flex-end; background:#f8fafc; border-top:1px solid #e2e8f0; }
</style>

<div class="monitor-hero d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
  <div>
    <div class="small" style="opacity:.7; letter-spacing:.15em; text-transform:uppercase"><i class="fas fa-satellite-dish me-1"></i>Live Classroom Monitor</div>
    <h4 class="fw-bold mb-1 mt-1"><?= h($ex['exam_name']) ?></h4>
    <div class="small" style="opacity:.8">
      <span><i class="fas fa-user-shield me-1"></i>Hosted by <b><?= h($ex['creator_name'] ?? '—') ?></b>
        <?= !empty($ex['creator_email'])? '<span style="opacity:.7">('.h($ex['creator_email']).')</span>':'' ?></span>
      <span class="ms-3"><i class="far fa-clock me-1"></i><?= fmt_dt($ex['start_time']) ?> → <?= fmt_dt($ex['end_time']) ?></span>
    </div>
  </div>
  <div class="d-flex gap-2 align-items-center flex-wrap">
    <div class="stat-chip"><div class="num" id="k-reg">0</div><div class="lbl">Registered</div></div>
    <div class="stat-chip"><div class="num text-warning" id="k-live">0</div><div class="lbl">Present</div></div>
    <div class="stat-chip"><div class="num text-success" id="k-sub">0</div><div class="lbl">Done</div></div>
    <div class="stat-chip"><div class="num text-danger" id="k-abs">0</div><div class="lbl">Absent</div></div>
    <div class="stat-chip" style="background:rgba(220,38,38,0.2)"><div class="num" id="k-viol">0</div><div class="lbl">Violations</div></div>
    <div class="text-center">
      <div class="timer-pill" id="timer">--:--:--</div>
      <div style="font-size:10px; opacity:.7; margin-top:3px" id="timer-lbl">Status</div>
    </div>
    <button class="notif-bell" onclick="toggleNotifPanel()" title="Alerts">
      <i class="fas fa-bell text-warning"></i>
      <span class="count" id="notif-count" style="display:none">0</span>
    </button>
    <div class="dropdown">
      <button class="btn btn-outline-light btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-file-export me-1"></i>Export</button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="<?= url('admin/export-classroom.php?exam_id='.$eid) ?>"><i class="fas fa-file-csv me-1 text-success"></i>Classroom Roster (CSV)</a></li>
        <li><a class="dropdown-item" target="_blank" href="<?= url('admin/export-classroom-pdf.php?exam_id='.$eid) ?>"><i class="fas fa-file-pdf me-1 text-danger"></i>Attendance Sheet (Print / PDF)</a></li>
      </ul>
    </div>
  </div>
</div>

<div class="pre-live-banner" id="pre-live-banner">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-4">
    <div style="flex:1; min-width:280px">
      <div class="small text-uppercase fw-bold" style="letter-spacing:.12em; color:#9a3412; display:flex; align-items:center; gap:8px"><i class="fas fa-hourglass-start" style="color:#f59e0b; font-size:16px"></i>Exam Will Go Live Soon</div>
      <div class="hint mt-2" style="font-size:14px; line-height:1.6">The classroom roster is being prepared. Students will be able to start once the exam begins at the scheduled time.</div>
    </div>
    <div class="text-end" style="min-width:180px">
      <div style="font-size:12px; color:#9a3412; letter-spacing:.05em; margin-bottom:8px; font-weight:600">TIME REMAINING</div>
      <div class="countdown" id="pre-live-countdown">00:00:00</div>
      <div class="hint" id="pre-live-label" style="margin-top:8px">Starts in</div>
    </div>
  </div>
</div>

<div class="exam-card">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h6 class="fw-bold mb-0"><i class="fas fa-chalkboard-teacher me-2"></i>Classroom Roster</h6>
      <small class="text-muted">Auto-refreshes every 5 seconds · <span id="last-sync">—</span></small>
    </div>
    <div class="d-flex gap-2">
      <input type="text" id="roster-search" class="form-control form-control-sm" placeholder="Filter by name / roll…" style="width:220px">
      <select id="roster-filter" class="form-select form-select-sm" style="width:150px">
        <option value="all">All</option>
        <option value="writing">Present</option>
        <option value="submitted">Done</option>
        <option value="absent">Absent</option>
      </select>
    </div>
  </div>
  <div id="classroom-wrap">
    <div class="classroom" id="classroom"><div class="text-muted text-center py-4 w-100">Loading students…</div></div>
  </div>
</div>

<!-- Notification Panel -->
<div class="notif-panel" id="notif-panel">
  <header>
    <div><i class="fas fa-bell me-1"></i> <b>Violation Alerts</b></div>
    <div class="d-flex gap-2 align-items-center">
      <button class="btn btn-sm btn-outline-secondary" onclick="clearNotifs()">Clear</button>
      <button class="btn btn-sm btn-outline-secondary" onclick="toggleNotifPanel()">Close</button>
    </div>
  </header>
  <div id="notif-list"><div class="notif-empty"><i class="fas fa-bell-slash fa-2x mb-2"></i><br>No alerts yet</div></div>
</div>

<!-- Center Violation Alert Overlay -->
<div class="viol-alert-wrap" id="viol-overlay">
  <div class="viol-alert">
    <header><i class="fas fa-triangle-exclamation fa-lg"></i><h5>EXAM VIOLATION DETECTED</h5></header>
    <div class="body">
      <div id="va-pic"></div>
      <div class="flex-grow-1">
        <div class="name" id="va-name">—</div>
        <div class="roll" id="va-roll">—</div>
        <div class="dob" id="va-dob">—</div>
        <div class="what" id="va-what">—</div>
        <div class="small text-secondary mt-1" id="va-detail">—</div>
        <div class="small text-muted mt-2" id="va-time">—</div>
      </div>
    </div>
    <footer>
      <button class="btn btn-outline-secondary" onclick="closeViolAlert()">OK</button>
    </footer>
  </div>
</div>

<!-- Answer Key Modal -->
<div class="viol-alert-wrap" id="answer-modal-wrap">
  <div class="viol-alert">
    <header><i class="fas fa-book-open fa-lg"></i><h5>Student Results</h5></header>
    <div class="body">
      <div id="am-pic"></div>
      <div class="flex-grow-1">
        <div class="name" id="am-name">—</div>
        <div class="roll" id="am-roll">—</div>
        <div class="dob" id="am-dob">—</div>
        <div class="small text-muted mt-1" id="am-status">—</div>
        <div id="attempt-options" style="display:none; margin-top:10px;">
          <div id="single-attempt-btn"></div>
          <div id="multi-attempts" style="display:none;">
            <p class="mb-2">Select an attempt to view:</p>
            <div id="attempt-radios"></div>
            <button id="view-selected-btn" class="btn btn-primary btn-sm mt-2" onclick="viewSelectedAttempt()">View Selected</button>
          </div>
        </div>
        <div class="small text-secondary mt-1" id="am-note">Answer key available only after the exam is closed.</div>
      </div>
    </div>
    <footer>
      <button class="btn btn-outline-secondary" onclick="closeAnswerModal()">Close</button>
    </footer>
  </div>
</div>

<script>
const EXAM_ID = <?= (int)$eid ?>;
const EXAM_START = <?= strtotime($ex['start_time']) * 1000 ?>;
const EXAM_END = <?= strtotime($ex['end_time']) * 1000 ?>;
const FEED_URL = <?= json_encode(url('api/monitor-feed.php')) ?>;
const ALERTS_URL = <?= json_encode(url('api/monitor-alerts.php')) ?>;
const ATTEMPT_URL = <?= json_encode(url('admin/attempt.php')) ?>;
const NOTIF_STORE_KEY = 'monitor_notifs_' + EXAM_ID;
const NOTIF_TS_KEY = 'monitor_last_alert_ts_' + EXAM_ID;
let lastAlertTs = Number(localStorage.getItem(NOTIF_TS_KEY) || 0);
let currentPhase = null;
let rosterPhase = null;
const NOTIFS = loadStoredNotifs();
const notifPanel = document.getElementById('notif-panel');
const notifList = document.getElementById('notif-list');
const notifCount = document.getElementById('notif-count');
const bell = document.querySelector('.notif-bell');

updateNotifBadge();
renderNotifList();

function fmtClock(ms) {
  const s = Math.max(0, Math.floor(ms/1000));
  const h = String(Math.floor(s/3600)).padStart(2,'0');
  const m = String(Math.floor(s%3600/60)).padStart(2,'0');
  const sec = String(s%60).padStart(2,'0');
  return `${h}:${m}:${sec}`;
}
function getPhase(now = Date.now()) {
  if (now < EXAM_START) return 'upcoming';
  if (now < EXAM_END) return 'active';
  return 'closed';
}
function updateTimer() {
  const now = Date.now();
  const t = document.getElementById('timer');
  const l = document.getElementById('timer-lbl');
  const banner = document.getElementById('pre-live-banner');
  const countdown = document.getElementById('pre-live-countdown');
  const label = document.getElementById('pre-live-label');
  const phase = getPhase(now);
  if (phase === 'upcoming') {
    const remaining = EXAM_START - now;
    t.textContent = fmtClock(remaining);
    t.className = 'timer-pill';
    l.textContent = 'Starts in';
    if (banner) banner.classList.add('open');
    if (countdown) countdown.textContent = fmtClock(remaining);
    if (label) label.textContent = 'Starts in';
  } else if (phase === 'active') {
    const remain = EXAM_END - now;
    t.textContent = fmtClock(remain);
    t.className = 'timer-pill' + (remain < 5*60*1000 ? ' danger' : (remain < 15*60*1000 ? ' warn' : ''));
    l.textContent = 'Time remaining';
    if (banner) banner.classList.remove('open');
  } else {
    t.textContent = '00:00:00';
    t.className = 'timer-pill';
    l.textContent = 'Exam ended';
    if (banner) banner.classList.remove('open');
  }
  if (currentPhase !== phase) {
    const wasUpcoming = currentPhase === 'upcoming';
    currentPhase = phase;
    if (wasUpcoming && phase === 'active') fetchFeed();
  }
}
setInterval(updateTimer, 1000); updateTimer();

async function fetchFeed() {
  try {
    const r = await fetch(FEED_URL + '?exam_id=' + EXAM_ID, {credentials:'same-origin'});
    const data = await r.json();
    if (!data.ok) return;
    const phase = data.exam_state || getPhase();
    document.getElementById('k-reg').textContent = data.registered;
    document.getElementById('k-live').textContent = phase === 'upcoming' ? 0 : data.writing;
    document.getElementById('k-sub').textContent = phase === 'upcoming' ? 0 : data.submitted;
    document.getElementById('k-abs').textContent = phase === 'upcoming' ? 0 : data.absent;
    document.getElementById('k-viol').textContent = phase === 'upcoming' ? 0 : data.total_violations;
    if (phase === 'upcoming') {
      document.getElementById('classroom').innerHTML = '<div class="monitor-locked w-100"><div class="fw-bold mb-1">Classroom view is not live yet</div><div>The roster will appear automatically when the exam starts.</div></div>';
    } else {
      rosterPhase = phase;
      renderRoster(data.students, phase);
    }
    document.getElementById('last-sync').textContent = new Date().toLocaleTimeString();
  } catch(e) { console.warn('feed error', e); }
}

function renderRoster(students, phase) {
  const isClosed = phase === 'closed';
  const search = (document.getElementById('roster-search').value || '').trim().toLowerCase();
  const filter = document.getElementById('roster-filter').value;
  const wrap = document.getElementById('classroom');
  const filtered = students.filter(s => {
    if (filter !== 'all' && s.status !== filter) return false;
    if (search && !(s.name.toLowerCase().includes(search) || (s.roll||'').toLowerCase().includes(search))) return false;
    return true;
  });
  if (!filtered.length) { wrap.innerHTML = '<div class="text-muted text-center py-4 w-100">No students match.</div>'; return; }
    const cardHtml = (items) => items.map(s => {
      const pic = s.photo_url
        ? `<img class="stu-photo" src="${s.photo_url}" alt="">`
        : `<div class="stu-no-photo">No Photo</div>`;
      const viol = (s.violations||0) > 0
        ? `<span class="viol-indicator"><i class="fas fa-triangle-exclamation me-1"></i>${s.violations}</span>` : '';
      const badge = `<span class="stu-badge ${s.status}">${s.status_label}</span>`;
      const stamp = s.status === 'absent' ? `<div class="absent-stamp"><img src="<?= url('assets/icons/BEL-Logo-Trnsprent.png') ?>" alt="BEL"></div>` : '';
      const closedClass = isClosed ? 'closed-highlight' : '';
      const attemptInfo = isClosed && s.attempt_count > 0 ? `<div class="small text-muted mt-1">Attempts: ${s.attempt_count}</div>` : '';
      const actionButton = isClosed && s.attempt_count > 0
        ? `<div class="card-actions"><button class="btn btn-sm btn-outline-primary" data-sid="${s.id}">View Results</button></div>`
        : '';
      return `<div class="stu-card ${s.status} ${closedClass}" data-sid="${s.id}" data-attempt-count="${s.attempt_count || 0}" data-attempt-ids="${(s.attempt_ids || []).join(',')}" data-attempt-details="${(s.attempt_details || []).join(',')}">
        ${stamp}${badge}${viol}
        ${pic}
        <div class="stu-meta">
          <div class="name">${escapeHtml(s.name)}</div>
          <div class="roll">${escapeHtml(s.roll||'—')}</div>
          <div class="dob">DOB: ${escapeHtml(s.dob||'—')}</div>
          ${attemptInfo}
        </div>
        ${actionButton}
      </div>`;
    }).join('');
  wrap.innerHTML = cardHtml(filtered);
}
function escapeHtml(s){return String(s).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function loadStoredNotifs() {
  try {
    return JSON.parse(localStorage.getItem(NOTIF_STORE_KEY) || '[]').filter(a => a && a.id);
  } catch (e) {
    return [];
  }
}
function persistNotifs() {
  try {
    localStorage.setItem(NOTIF_STORE_KEY, JSON.stringify(NOTIFS));
    localStorage.setItem(NOTIF_TS_KEY, String(lastAlertTs || 0));
  } catch (e) {}
}
function updateNotifBadge() {
  if (!NOTIFS.length) {
    notifCount.style.display = 'none';
    notifCount.textContent = '0';
    return;
  }
  notifCount.style.display = '';
  notifCount.textContent = NOTIFS.length > 99 ? '99+' : NOTIFS.length;
}

async function fetchAlerts() {
  try {
    const r = await fetch(ALERTS_URL + '?exam_id=' + EXAM_ID + '&since=' + lastAlertTs, {credentials:'same-origin'});
    const data = await r.json();
    if (!data.ok || !data.alerts || !data.alerts.length) return;
    data.alerts.forEach(a => {
      if (Number(a.exam_id) !== Number(EXAM_ID)) return;
      if (NOTIFS.some(existing => Number(existing.id) === Number(a.id))) return;
      NOTIFS.unshift(a);
      if (a.ts > lastAlertTs) lastAlertTs = a.ts;
      showViolAlert(a);
      flashStudent(a.user_id);
    });
    persistNotifs();
    updateNotifBadge();
    bell.classList.add('ring');
    setTimeout(() => bell.classList.remove('ring'), 2000);
    renderNotifList();
  } catch(e) { console.warn('alerts error', e); }
}

function getEventLabel(type) {
  const labels = {
    fullscreen_exit: 'Fullscreen exit',
    fullscreen_key_exit: 'Fullscreen exit key',
    screenshot_attempt: 'Screenshot / screenshot blocked',
    tab_switch: 'Tab switch',
    window_blur: 'Window lost focus',
    right_click: 'Right-click',
    copy_paste: 'Copy / paste attempt',
    blocked_key: 'Blocked key',
    windows_key_attempt: 'Windows / Super key attempt',
    extension_overlay: 'Extension overlay detected',
    screen_sharing: 'Screen share / display capture',
    remote_access: 'Remote access pattern',
    second_display: 'Second display detected'
  };
  return labels[type] || type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function renderNotifList() {
  if (!NOTIFS.length) { notifList.innerHTML = '<div class="notif-empty"><i class="fas fa-bell-slash fa-2x mb-2"></i><br>No alerts yet</div>'; return; }
  notifList.innerHTML = NOTIFS.slice(0,50).map(a => {
    const pic = a.photo_url ? `<img src="${a.photo_url}" alt="">` : `<div class="no-pic"><i class="fas fa-user"></i></div>`;
    const label = escapeHtml(getEventLabel(a.event_type));
    const detail = escapeHtml(a.description || 'No details available');
    return `<div class="item">
      ${pic}
      <div style="flex:1; font-size:13px">
        <div><b>${escapeHtml(a.name)}</b> <small class="text-muted">(${escapeHtml(a.roll||'—')})</small></div>
        <div class="text-danger" style="font-size:12px; font-weight:600">${label}</div>
        <div class="small text-muted">${detail}</div>
        <div class="small text-muted" style="font-size:10px">${new Date(a.ts).toLocaleString()}</div>
      </div>
    </div>`;
  }).join('');
}

function flashStudent(uid) {
  const card = document.querySelector(`.stu-card[data-sid="${uid}"]`);
  if (card) { card.classList.add('violated'); setTimeout(()=>card.classList.remove('violated'), 3000); }
}

function openStudentCard(evt) {
  const card = evt.target.closest('.stu-card');
  if (!card) return;
  const attemptCount = parseInt(card.dataset.attemptCount) || 0;
  if (rosterPhase === 'closed' && attemptCount > 0) {
    // Open modal with attempt options
    const studentName = card.querySelector('.name')?.textContent || '—';
    const studentRoll = card.querySelector('.roll')?.textContent || '—';
    const studentDob = card.querySelector('.dob')?.textContent || '—';
    const studentStatus = card.querySelector('.stu-badge')?.textContent || '—';
    document.getElementById('am-name').textContent = studentName;
    document.getElementById('am-roll').textContent = studentRoll;
    document.getElementById('am-dob').textContent = studentDob;
    document.getElementById('am-status').textContent = `Status: ${studentStatus}`;
    document.getElementById('am-pic').innerHTML = card.querySelector('.stu-photo')
      ? `<img class="stu-photo" src="${card.querySelector('.stu-photo').src}" alt="">`
      : '<div class="no-pic">No Photo</div>';
    
    const attemptIds = card.dataset.attemptIds.split(',').map(id => parseInt(id));
    const attemptDetails = card.dataset.attemptDetails.split(',');
    
    const optionsDiv = document.getElementById('attempt-options');
    const singleBtn = document.getElementById('single-attempt-btn');
    const multiDiv = document.getElementById('multi-attempts');
    const radiosDiv = document.getElementById('attempt-radios');
    
    if (attemptCount === 1) {
      singleBtn.innerHTML = `<a class="btn btn-primary" href="${ATTEMPT_URL}?id=${attemptIds[0]}" target="_blank">View Result & Answer Key</a>`;
      singleBtn.style.display = 'block';
      multiDiv.style.display = 'none';
    } else {
      radiosDiv.innerHTML = attemptIds.map((id, i) => {
        const detail = attemptDetails[i] || '';
        const [score, date] = detail.split('|');
        return `<label class="d-block mb-2">
          <input type="radio" name="attempt-select" value="${id}"> 
          Attempt ${i+1}: ${score} on ${date}
        </label>`;
      }).join('');
      multiDiv.style.display = 'block';
      singleBtn.style.display = 'none';
    }
    optionsDiv.style.display = 'block';
    document.getElementById('answer-modal-wrap').classList.add('open');
    return;
  }
  // For active exams or absent, do nothing or show info modal
  if (rosterPhase !== 'closed') return;
  // For absent in closed, show modal without options
  const studentName = card.querySelector('.name')?.textContent || '—';
  const studentRoll = card.querySelector('.roll')?.textContent || '—';
  const studentDob = card.querySelector('.dob')?.textContent || '—';
  const studentStatus = card.querySelector('.stu-badge')?.textContent || '—';
  document.getElementById('am-name').textContent = studentName;
  document.getElementById('am-roll').textContent = studentRoll;
  document.getElementById('am-dob').textContent = studentDob;
  document.getElementById('am-status').textContent = `Status: ${studentStatus}`;
  document.getElementById('am-pic').innerHTML = card.querySelector('.stu-photo')
    ? `<img class="stu-photo" src="${card.querySelector('.stu-photo').src}" alt="">`
    : '<div class="no-pic">No Photo</div>';
  document.getElementById('attempt-options').style.display = 'none';
  document.getElementById('answer-modal-wrap').classList.add('open');
}
function closeAnswerModal() { document.getElementById('answer-modal-wrap').classList.remove('open'); }

function viewSelectedAttempt() {
  const selected = document.querySelector('input[name="attempt-select"]:checked');
  if (selected) {
    window.open(`${ATTEMPT_URL}?id=${selected.value}`, '_blank');
  }
}

document.getElementById('classroom').addEventListener('click', openStudentCard);

function showViolAlert(a) {
  const label = getEventLabel(a.event_type);
  const detail = a.description || 'No details available';
  document.getElementById('va-name').textContent = a.name || '—';
  document.getElementById('va-roll').textContent = 'Roll: ' + (a.roll || '—');
  document.getElementById('va-dob').textContent = 'DOB: ' + (a.dob || '—');
  document.getElementById('va-what').textContent = label;
  document.getElementById('va-detail').textContent = detail;
  document.getElementById('va-time').textContent = new Date(a.ts).toLocaleString();
  document.getElementById('va-pic').innerHTML = a.photo_url ? `<img src="${a.photo_url}" alt="">` : `<div class="no-pic">No Photo</div>`;
  document.getElementById('viol-overlay').classList.add('open');
  try { beep(); } catch(e){}
  // Auto-close after 10 seconds if user doesn't dismiss
  clearTimeout(window.__va_timer);
  window.__va_timer = setTimeout(closeViolAlert, 10000);
}
function closeViolAlert() { document.getElementById('viol-overlay').classList.remove('open'); }
function toggleNotifPanel() { notifPanel.classList.toggle('open'); if (notifPanel.classList.contains('open')) renderNotifList(); }
function clearNotifs() { NOTIFS.length = 0; lastAlertTs = Date.now(); persistNotifs(); updateNotifBadge(); renderNotifList(); }

function beep() {
  try {
    const C = window.AudioContext || window.webkitAudioContext;
    const ctx = new C();
    const o = ctx.createOscillator(); const g = ctx.createGain();
    o.type='square'; o.frequency.value=1040;
    o.connect(g); g.connect(ctx.destination);
    g.gain.setValueAtTime(0.0001, ctx.currentTime);
    o.start();
    g.gain.exponentialRampToValueAtTime(0.2, ctx.currentTime + 0.01);
    g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.4);
    o.stop(ctx.currentTime + 0.45);
  } catch(e){}
}

document.getElementById('roster-search').addEventListener('input', fetchFeed);
document.getElementById('roster-filter').addEventListener('change', fetchFeed);
document.getElementById('viol-overlay').addEventListener('click', (e)=>{ if(e.target.id==='viol-overlay') closeViolAlert(); });

fetchFeed();
fetchAlerts();
setInterval(fetchFeed, 5000);
setInterval(fetchAlerts, 5000);
</script>
<?php require __DIR__ . '/_shell_bottom.php'; ?>
