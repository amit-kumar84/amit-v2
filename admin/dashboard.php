<?php $ADMIN_TITLE = 'System Online'; require __DIR__ . '/_shell_top.php'; ?>
<?php
$pdo = db();
$totals = [
  'students' => (int)$pdo->query('SELECT COUNT(*) FROM users WHERE role="student"')->fetchColumn(),
  'exams'    => (int)$pdo->query('SELECT COUNT(*) FROM exams')->fetchColumn(),
  'live'     => (int)$pdo->query('SELECT COUNT(*) FROM attempts WHERE status="in_progress"')->fetchColumn(),
  'today'    => (int)$pdo->query('SELECT COUNT(*) FROM attempts WHERE status="submitted" AND DATE(submitted_at)=CURDATE()')->fetchColumn(),
];
$active = $pdo->query('SELECT e.id, e.exam_name, e.end_time, (SELECT COUNT(*) FROM attempts WHERE exam_id=e.id AND status="in_progress") live FROM exams e WHERE NOW() BETWEEN e.start_time AND e.end_time')->fetchAll();
$hot = $pdo->query('SELECT u.name, u.roll_number, COUNT(v.id) c FROM violations v JOIN users u ON u.id=v.user_id GROUP BY v.user_id ORDER BY c DESC LIMIT 5')->fetchAll();
$avg = $pdo->query('SELECT e.exam_name, AVG(a.score/a.total)*100 pct, COUNT(a.id) n FROM attempts a JOIN exams e ON e.id=a.exam_id WHERE a.status="submitted" AND a.total>0 GROUP BY a.exam_id ORDER BY MAX(a.submitted_at) DESC LIMIT 5')->fetchAll();
$live = $pdo->query('SELECT a.id, a.ends_at, u.name, u.roll_number, e.exam_name, (SELECT COUNT(*) FROM violations WHERE attempt_id=a.id) vcount FROM attempts a JOIN users u ON u.id=a.user_id JOIN exams e ON e.id=a.exam_id WHERE a.status="in_progress" LIMIT 20')->fetchAll();
?>
<div class="dashboard-hero mb-3">
  <div class="tricolor"></div>
  <div class="hero-inner">
    <div style="flex-shrink:0"><img src="<?= url('assets/icons/BEL-Logo-Trnsprent.png') ?>" alt="BEL" style="width:200px; height:120px; object-fit:contain"></div>
    <div style="flex:1; text-align:center">
      <h1 class="fw-bold mb-1" style="font-size:clamp(1.4rem, 2.6vw, 2.4rem);">Admin Dashboard</h1>
      <div class="text-muted">Bharat Electronics Limited — Government of India</div>
    </div>
    <div class="hero-status-wrap">
      <div class="live-badge"><span class="pulse-dot"></span><span class="green-dot"></span> SYSTEM ONLINE</div>
      <div class="system-clock" aria-label="Current time">
        <div class="clock-time" id="systemClock">
          <span class="clock-part" data-clock-hour>00</span>
          <span class="clock-sep">:</span>
          <span class="clock-part" data-clock-minute>00</span>
          <span class="clock-sep">:</span>
          <span class="clock-part" data-clock-second>00</span>
          <span class="clock-ampm" data-clock-ampm>AM</span>
        </div>
        <div class="clock-date" data-clock-date>01 Jan 1970</div>
      </div>
    </div>
  </div>
</div>
<div class="dashboard-stats-bar mb-3">
  <div class="stat-card"><div class="stat-value stat-count stat-students" data-to="<?= $totals['students'] ?>"><?= $totals['students'] ?></div><div class="stat-label">Students</div></div>
  <div class="stat-card"><div class="stat-value stat-count stat-exams" data-to="<?= $totals['exams'] ?>"><?= $totals['exams'] ?></div><div class="stat-label">Exams</div></div>
  <div class="stat-card"><div class="stat-value stat-count stat-live" data-to="<?= $totals['live'] ?>"><?= $totals['live'] ?></div><div class="stat-label">Live Attempts</div></div>
  <div class="stat-card"><div class="stat-value stat-count stat-today" data-to="<?= $totals['today'] ?>"><?= $totals['today'] ?></div><div class="stat-label">Submitted Today</div></div>
</div>
<div class="d-flex justify-content-between mb-3">
</div>
<div class="row g-3">
  <div class="col-12">
    <div class="dashboard-grid">
      <div>
        <div class="panel">
          <h6><i class="far fa-clock me-2"></i>Active Examination Windows</h6>
          <?php if (!$active): ?><p class="text-muted text-center small py-3">No exam in active window.</p>
          <?php else: ?><table class="data-table"><thead><tr><th>Exam</th><th>Live</th><th>Closes</th><th>Countdown</th></tr></thead><tbody class="active-exams-body">
            <?php foreach ($active as $a): $endsTs = strtotime($a['end_time'])*1000; ?><tr>
              <td><?= h($a['exam_name']) ?></td>
              <td><span class="badge bg-warning text-dark"><?= (int)$a['live'] ?></span></td>
              <td class="small text-muted"><?= fmt_dt($a['end_time']) ?></td>
              <td><span class="countdown exam-countdown" data-ends="<?= $endsTs ?>">--:--</span></td>
            </tr><?php endforeach; ?>
          </tbody></table><?php endif; ?>
        </div>

        <div class="panel mt-3">
          <h6><i class="fas fa-chart-line me-2"></i>Recent Average Scores</h6>
          <?php if (!$avg): ?><p class="text-muted text-center small py-3">No submissions yet.</p>
          <?php else: ?><div class="scores-list"><?php foreach ($avg as $a): $pct = round((float)$a['pct'], 1); ?>
            <div class="mb-2"><div class="d-flex justify-content-between small"><span class="fw-medium"><?= h($a['exam_name']) ?></span><span class="text-muted"><?= $pct ?>% · <?= (int)$a['n'] ?> attempts</span></div>
              <div class="progress" style="height:8px"><div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div></div></div>
          <?php endforeach; ?></div><?php endif; ?>
        </div>

      </div>
      <div>
        <div class="panel">
          <h6><i class="fas fa-users me-2"></i>Violation Hotspots</h6>
          <?php if (!$hot): ?><p class="text-muted text-center small py-3">No violations recorded.</p>
          <?php else: ?><ul class="mini-list mb-0 violations-list"><?php foreach ($hot as $h): ?><li><span><b><?= h($h['name']) ?></b> <small class="text-muted"><?= h($h['roll_number']) ?></small></span><span class="badge bg-danger"><?= (int)$h['c'] ?></span></li><?php endforeach; ?></ul><?php endif; ?>
        </div>

        <div class="panel mt-3">
          <h6><i class="fas fa-bolt me-2"></i>Currently Writing</h6>
          <?php if (!$live): ?><p class="text-muted text-center small py-3">Nobody is writing now.</p>
          <?php else: ?><table class="data-table"><thead><tr><th>Candidate</th><th>Exam</th><th>Viol.</th><th>Ends</th></tr></thead><tbody class="writers-body">
            <?php foreach ($live as $l): ?><tr><td><b><?= h($l['name']) ?></b> <small class="text-muted"><?= h($l['roll_number']) ?></small></td><td class="small"><?= h($l['exam_name']) ?></td><td><span class="badge <?= $l['vcount']>=3?'bg-danger':($l['vcount']>0?'bg-warning text-dark':'bg-secondary') ?>"><?= (int)$l['vcount'] ?></span></td><td class="small text-muted"><?= date('H:i', strtotime($l['ends_at'])) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="<?= url('assets/js/admin-dashboard.js') ?>"></script>
<script>
(function(){
  var formatter = new Intl.DateTimeFormat('en-IN', {
    timeZone: 'Asia/Kolkata',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: true
  });
  function tick(){
    var now = new Date();
    var parts = formatter.formatToParts(now);
    var values = {};
    for (var i = 0; i < parts.length; i++) {
      if (parts[i].type !== 'literal') values[parts[i].type] = parts[i].value;
    }
    var hourEl = document.querySelector('[data-clock-hour]');
    var minuteEl = document.querySelector('[data-clock-minute]');
    var secondEl = document.querySelector('[data-clock-second]');
    var ampmEl = document.querySelector('[data-clock-ampm]');
    var dateEl = document.querySelector('[data-clock-date]');
    var dateFormatter = new Intl.DateTimeFormat('en-IN', {
      timeZone: 'Asia/Kolkata',
      day: '2-digit',
      month: 'short',
      year: 'numeric'
    });
    if (hourEl) hourEl.textContent = values.hour || '00';
    if (minuteEl) minuteEl.textContent = values.minute || '00';
    if (secondEl) secondEl.textContent = values.second || '00';
    if (ampmEl) ampmEl.textContent = (values.dayPeriod || 'AM').toUpperCase();
    if (dateEl) dateEl.textContent = dateFormatter.format(now);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ tick(); setInterval(tick, 1000); });
  } else {
    tick();
    setInterval(tick, 1000);
  }
})();
</script>
<?php require __DIR__ . '/_shell_bottom.php'; ?>
