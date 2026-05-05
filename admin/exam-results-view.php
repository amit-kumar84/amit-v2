<?php
$ADMIN_TITLE = 'Exam Results';
require_once __DIR__ . '/../includes/helpers.php';
require_login('admin');

$examId = (int)($_GET['exam_id'] ?? 0);
if (!$examId) die('Exam not found');

$examStmt = db()->prepare('SELECT e.*, c.name AS creator_name FROM exams e LEFT JOIN users c ON c.id=e.created_by WHERE e.id=?');
$examStmt->execute([$examId]);
$exam = $examStmt->fetch();
if (!$exam) die('Exam not found');

// Get marking details from questions
$markingsStmt = db()->prepare('SELECT SUM(marks) as total_positive, SUM(negative_marks) as total_negative, COUNT(*) as total_questions FROM questions WHERE exam_id=?');
$markingsStmt->execute([$examId]);
$markings = $markingsStmt->fetch() ?? ['total_positive' => 0, 'total_negative' => 0, 'total_questions' => 0];

$sql = 'SELECT a.*, u.name AS sname, u.email AS semail, u.roll_number, u.dob
        FROM attempts a
        JOIN users u ON u.id=a.user_id
        WHERE a.exam_id=? AND a.status="submitted"
        ORDER BY a.score DESC, a.submitted_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute([$examId]);
$results = $stmt->fetchAll();

$studentResults = [];
foreach ($results as $r) {
    $rollKey = trim((string)($r['roll_number'] ?? ''));
    if ($rollKey === '') {
        $rollKey = 'user:' . (int)$r['user_id'];
    }
    $rollKey = function_exists('mb_strtolower') ? mb_strtolower($rollKey) : strtolower($rollKey);

    if (!isset($studentResults[$rollKey])) {
        $studentResults[$rollKey] = [
            'id' => (int)$r['user_id'],
            'name' => $r['sname'],
            'email' => $r['semail'],
            'roll_number' => $r['roll_number'],
            'dob' => $r['dob'],
            'attempts' => [],
        ];
    } else {
        if (empty($studentResults[$rollKey]['name']) && !empty($r['sname'])) $studentResults[$rollKey]['name'] = $r['sname'];
        if (empty($studentResults[$rollKey]['email']) && !empty($r['semail'])) $studentResults[$rollKey]['email'] = $r['semail'];
        if (empty($studentResults[$rollKey]['roll_number']) && !empty($r['roll_number'])) $studentResults[$rollKey]['roll_number'] = $r['roll_number'];
        if (empty($studentResults[$rollKey]['dob']) && !empty($r['dob'])) $studentResults[$rollKey]['dob'] = $r['dob'];
    }

    $studentResults[$rollKey]['attempts'][] = $r;
}

foreach ($studentResults as &$student) {
    usort($student['attempts'], function ($left, $right) {
        if ((int)$left['attempt_no'] === (int)$right['attempt_no']) {
            return (int)$left['id'] <=> (int)$right['id'];
        }
        return (int)$left['attempt_no'] <=> (int)$right['attempt_no'];
    });
}
unset($student);

$totalAttempts = count($studentResults);
$avgScore = 0;
$passCount = 0;
if ($totalAttempts > 0) {
    $scores = array_map(function ($student) {
        $latestAttempt = $student['attempts'][count($student['attempts']) - 1] ?? null;
        return $latestAttempt ? (float)$latestAttempt['score'] : 0;
    }, array_values($studentResults));
    $avgScore = round(array_sum($scores) / $totalAttempts, 2);
    foreach ($studentResults as $student) {
        $latestAttempt = $student['attempts'][count($student['attempts']) - 1] ?? null;
        if ($latestAttempt && $latestAttempt['total'] > 0) {
            $pct = ($latestAttempt['score'] / $latestAttempt['total']) * 100;
            if ($pct >= 40) $passCount++;
        }
    }
}
?>
<?php require __DIR__ . '/_shell_top.php'; ?>

<style>
  /* Enhanced Header Styles */
  .exam-header-section { background: linear-gradient(135deg, #0E2A47 0%, #1e5a96 50%, #00A9E0 100%); color: white; padding: 32px; border-radius: 16px; margin-bottom: 28px; position: relative; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.12); }
  .exam-header-section::before { content: ''; position: absolute; top: -40%; right: -40%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%); }
  .exam-header-section::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent); }
  
  .exam-header-content { position: relative; z-index: 1; }
  .exam-title-main { font-size: 2rem; font-weight: 900; margin: 0 0 8px 0; letter-spacing: -0.02em; }
  .exam-code-badge { display: inline-block; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3); padding: 6px 14px; border-radius: 999px; font-size: 0.85rem; font-weight: 700; margin-bottom: 16px; }
  
  .header-details-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-top: 16px; }
  .header-detail-item { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; padding: 12px 14px; backdrop-filter: blur(10px); }
  .header-detail-label { font-size: 0.75rem; color: rgba(255,255,255,0.75); text-transform: uppercase; letter-spacing: 0.03em; font-weight: 700; margin-bottom: 4px; }
  .header-detail-value { font-size: 0.95rem; font-weight: 700; color: rgba(255,255,255,0.95); }
  
  .marking-box { background: linear-gradient(135deg, rgba(34,197,94,0.15), rgba(79,172,254,0.15)); border: 1px solid rgba(34,197,94,0.3); border-radius: 10px; padding: 12px 14px; }
  .marking-label { font-size: 0.75rem; color: rgba(255,255,255,0.75); text-transform: uppercase; letter-spacing: 0.02em; font-weight: 700; margin-bottom: 3px; }
  .marking-value { font-size: 1.4rem; font-weight: 900; color: #4fec7e; }
  
  .negative-box { background: linear-gradient(135deg, rgba(239,68,68,0.15), rgba(251,113,133,0.15)); border: 1px solid rgba(239,68,68,0.3); border-radius: 10px; padding: 12px 14px; }
  .negative-value { color: #ff8799; }
  
  @media (max-width: 768px) {
    .exam-header-section { padding: 24px; }
    .exam-title-main { font-size: 1.5rem; }
    .header-details-grid { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; }
  }
  
  @media (max-width: 576px) {
    .exam-header-section { padding: 16px; }
    .exam-title-main { font-size: 1.2rem; }
    .header-details-grid { grid-template-columns: 1fr; }
    .header-detail-label { font-size: 0.7rem; }
    .header-detail-value { font-size: 0.85rem; }
  }

  /* Results Table Styling */
  .results-table { margin-top: 24px; }
  .results-table .table thead { background: linear-gradient(135deg, #f0f9ff 0%, #e0f7ff 100%); position: sticky; top: 0; z-index: 10; }
  .results-table .table thead th { color: #0E2A47; font-weight: 800; border: none; padding: 14px 12px; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.04em; vertical-align: middle; background: linear-gradient(135deg, #f0f9ff 0%, #e0f7ff 100%); }
  .results-table .table thead th i { margin-right: 6px; opacity: 0.8; color: #00A9E0; }
  .results-table .table tbody tr { border-bottom: 1px solid rgba(2,6,23,0.06); transition: all 0.2s ease; }
  .results-table .table tbody tr:hover { background-color: rgba(0,169,224,0.08); transform: translateX(2px); }
  .results-table .table tbody td { padding: 12px; vertical-align: middle; }
  .results-table .badge { font-weight: 700; padding: 5px 10px; font-size: 0.8rem; }
  .results-table .btn-action { font-weight: 700; padding: 4px 10px; font-size: 0.8rem; border-radius: 6px; }

  /* Student Row Animations & Styling */
  .results-table .table tbody tr { animation: row-entrance 0.5s ease-out backwards; }
  .results-table .table tbody tr:nth-child(1) { animation-delay: 0.05s; }
  .results-table .table tbody tr:nth-child(2) { animation-delay: 0.1s; }
  .results-table .table tbody tr:nth-child(3) { animation-delay: 0.15s; }
  .results-table .table tbody tr:nth-child(4) { animation-delay: 0.2s; }
  .results-table .table tbody tr:nth-child(5) { animation-delay: 0.25s; }
  .results-table .table tbody tr:nth-child(n+6) { animation-delay: 0.3s; }

  @keyframes row-entrance {
    from {
      opacity: 0;
      transform: translateY(10px) translateX(-8px);
    }
    to {
      opacity: 1;
      transform: translateY(0) translateX(0);
    }
  }

  /* Student Name Cell */
  .results-table .table tbody td:nth-child(1) { font-weight: 700; color: #0E2A47; font-size: 0.95rem; letter-spacing: -0.01em; }

  /* Roll Number Cell */
  .results-table .table tbody td:nth-child(2) { color: #475569; font-weight: 600; font-size: 0.85rem; font-family: 'Courier New', monospace; background: linear-gradient(135deg, rgba(100,116,139,0.03), rgba(100,116,139,0.06)); border-radius: 4px; }

  /* Email Cell */
  .results-table .table tbody td:nth-child(3) { color: #64748b; font-size: 0.85rem; }

  /* Attempt Count Badge */
  .results-table .table tbody td:nth-child(4) .badge { background: linear-gradient(135deg, #3b82f6, #1d4ed8) !important; box-shadow: 0 4px 12px rgba(59,130,246,0.3); font-weight: 800; letter-spacing: 0.05em; }

  /* Score Cell */
  .results-table .table tbody td:nth-child(5) { font-weight: 800; font-size: 0.95rem; }

  /* Submitted Date Cell */
  .results-table .table tbody td:nth-child(6) { color: #64748b; font-size: 0.8rem; }

  /* Attempt Badges */
  .results-table .badge { font-weight: 800; padding: 6px 11px; font-size: 0.75rem; border-radius: 8px; transition: all 0.2s ease; cursor: pointer; background: linear-gradient(135deg, #6366f1, #4f46e5) !important; box-shadow: 0 2px 8px rgba(99,102,241,0.25); }
  .results-table .badge:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(99,102,241,0.35); }

  /* Review Button */
  .results-table .btn-action { background: linear-gradient(135deg, #00A9E0, #0082b8); color: white; border: none; font-weight: 800; font-size: 0.8rem; padding: 6px 12px; border-radius: 8px; transition: all 0.3s cubic-bezier(.2,.9,.2,1); box-shadow: 0 4px 12px rgba(0,169,224,0.2); }
  .results-table .btn-action:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,169,224,0.35); background: linear-gradient(135deg, #00c5ff, #0092e0); color: white; text-decoration: none; }
  .results-table .btn-action i { margin-right: 4px; }

  @media (max-width: 768px) {
    .results-table .table thead th { font-size: 0.75rem; padding: 10px 8px; }
    .results-table .table tbody td { padding: 10px 8px; font-size: 0.85rem; }
    .results-table .btn-action { padding: 4px 8px; font-size: 0.7rem; }
  }

</style>

<div class="exam-header-section">
  <div class="exam-header-content">
    <div class="exam-code-badge"><i class="fas fa-bookmark me-1"></i><?= h($exam['exam_code']) ?></div>
    <h1 class="exam-title-main"><i class="fas fa-file-alt me-2"></i><?= h($exam['exam_name']) ?></h1>
    
    <div class="header-details-grid">
      <div class="header-detail-item">
        <div class="header-detail-label"><i class="fas fa-user"></i> Hosted By</div>
        <div class="header-detail-value"><?= h($exam['creator_name'] ?? 'System') ?></div>
      </div>
      <div class="header-detail-item">
        <div class="header-detail-label"><i class="fas fa-calendar-alt"></i> Start Date & Time</div>
        <div class="header-detail-value"><?= fmt_dt($exam['start_time'], 'M d, Y \a\t g:i A') ?></div>
      </div>
      <div class="header-detail-item">
        <div class="header-detail-label"><i class="fas fa-hourglass-end"></i> End Date & Time</div>
        <div class="header-detail-value"><?= fmt_dt($exam['end_time'], 'M d, Y \a\t g:i A') ?></div>
      </div>
      <div class="marking-box">
        <div class="marking-label"><i class="fas fa-plus-circle"></i> Positive Marks per Question</div>
        <div class="marking-value"><?= (float)($markings['total_positive'] ?? 0) > 0 ? sprintf('%.2f', (float)$markings['total_positive'] / max((int)$markings['total_questions'], 1)) : '0' ?></div>
      </div>
      <div class="negative-box">
        <div class="marking-label"><i class="fas fa-minus-circle"></i> Negative Marks per Question</div>
        <div class="marking-value negative-value"><?= (float)($markings['total_negative'] ?? 0) > 0 ? sprintf('%.2f', (float)$markings['total_negative'] / max((int)$markings['total_questions'], 1)) : '0' ?></div>
      </div>
      <div class="header-detail-item">
        <div class="header-detail-label"><i class="fas fa-questions"></i> Total Questions</div>
        <div class="header-detail-value"><?= (int)$markings['total_questions'] ?></div>
      </div>
    </div>
  </div>
</div>

<!-- Floating export controls (top-right) -->
<div class="export-top-right no-print">
    <button class="export-btn-main" onclick="exportAllCSV()" title="Download all results as CSV file">
        <div class="export-btn-pulse"></div>
        <div class="export-btn-content">
            <i class="fas fa-file-csv"></i>
            <span>Export CSV</span>
        </div>
    </button>
</div>

<div class="container-content">
    <div class="row g-3 mb-4 no-print">
        <div class="col-md-3"><div class="stat-card"><div class="stat-value"><?= $totalAttempts ?></div><div class="stat-label">Total Submissions</div></div></div>
        <div class="col-md-3"><div class="stat-card"><div class="stat-value"><?= $avgScore ?></div><div class="stat-label">Average Score</div></div></div>
        <div class="col-md-3"><div class="stat-card"><div class="stat-value"><?= $passCount ?></div><div class="stat-label">Passed (40%+)</div></div></div>
        <div class="col-md-3"><div class="stat-card"><div class="stat-value"><?= round(($passCount / max($totalAttempts, 1)) * 100, 1) ?>%</div><div class="stat-label">Pass Rate</div></div></div>
    </div>

    <?php if (!$studentResults): ?>
        <div class="text-center py-5 text-muted results-table">
            <p><i class="fas fa-inbox fa-3x mb-3"></i></p>
            <p>No submissions yet</p>
        </div>
    <?php else: ?>
        <div class="results-table">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 20%"><i class="fas fa-user"></i>Student Name</th>
                            <th style="width: 12%"><i class="fas fa-id-card"></i>Roll Number</th>
                            <th style="width: 18%"><i class="fas fa-envelope"></i>Email</th>
                            <th style="width: 10%"><i class="fas fa-list-ol"></i>Count</th>
                            <th style="width: 10%"><i class="fas fa-star"></i>Latest Score</th>
                            <th style="width: 12%"><i class="fas fa-calendar-check"></i>Submitted</th>
                            <th style="width: 10%" class="text-center"><i class="fas fa-history"></i>Attempts</th>
                            <th style="width: 8%" class="text-center"><i class="fas fa-eye"></i>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($studentResults as $student): ?>
                            <?php
                                $attemptCount = count($student['attempts']);
                                $latest = $student['attempts'][$attemptCount - 1];
                                $attemptLinks = [];
                                foreach ($student['attempts'] as $attempt) {
                                    $attemptLinks[] = '<a href="' . h(url('admin/attempt.php?id=' . $attempt['id'])) . '" class="badge bg-secondary text-decoration-none me-1 mb-1">#' . (int)$attempt['attempt_no'] . '</a>';
                                }
                                $latestPct = ($latest['total'] > 0) ? round(($latest['score'] / $latest['total']) * 100, 1) : 0;
                                $statusClass = $latestPct >= 40 ? 'percentage-pass' : 'percentage-fail';
                            ?>
                            <tr>
                                <td class="fw-medium"><i class="fas fa-user me-2" style="color: #00A9E0;"></i><?= h($student['name']) ?></td>
                                <td class="small"><i class="fas fa-id-card me-1" style="color: #6366f1;"></i><?= h($student['roll_number']) ?></td>
                                <td class="small text-muted"><i class="fas fa-envelope me-1" style="color: #8b5cf6;"></i><?= h($student['email']) ?></td>
                                <td><span class="badge bg-navy" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8) !important; box-shadow: 0 4px 12px rgba(59,130,246,0.3);"><?= $attemptCount ?></span></td>
                                <td class="fw-bold" style="color: #059669;"><?= $latest['score'] ?> <span style="color: #94a3b8;">/</span> <?= $latest['total'] ?></td>
                                <td class="small text-muted"><i class="fas fa-clock me-1" style="opacity: 0.6;"></i><?= fmt_dt($latest['submitted_at']) ?></td>
                                <td class="small"><?= implode(' ', $attemptLinks) ?></td>
                                <td class="text-center no-print">
                                    <a href="<?= url('admin/attempt.php?id='.$latest['id']) ?>" class="btn btn-sm btn-outline-info btn-action" style="background: linear-gradient(135deg, #00A9E0, #0082b8); color: white; border: none; font-weight: 800; box-shadow: 0 4px 12px rgba(0,169,224,0.2);"><i class="fas fa-eye"></i> Review</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    const studentResults = <?= json_encode(array_values($studentResults), JSON_UNESCAPED_UNICODE) ?>;

    function exportAllCSV() {
        const examName = <?= json_encode($exam['exam_name']) ?>;
        const examCode = <?= json_encode($exam['exam_code']) ?>;
        const rows = [
            ['Exam Results Export'],
            ['Exam Name:', examName],
            ['Exam Code:', examCode],
            ['Export Date:', new Date().toLocaleString()],
            [''],
            ['Student Name', 'Roll Number', 'Email', 'Attempt', 'Score', 'Total', 'Percentage', 'Submitted']
        ];

        studentResults.forEach(student => {
            (student.attempts || []).forEach(attempt => {
                const pct = Number(attempt.total) > 0 ? ((Number(attempt.score) / Number(attempt.total)) * 100).toFixed(1) : '0.0';
                rows.push([
                    student.name || '',
                    student.roll_number || '',
                    student.email || '',
                    `#${attempt.attempt_no}`,
                    `${attempt.score}`,
                    `${attempt.total}`,
                    `${pct}%`,
                    attempt.submitted_at || ''
                ]);
            });
        });

        const csv = rows.map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `exam_results_${examCode}_${new Date().toISOString().split('T')[0]}.csv`;
        link.click();
    }
</script>

<?php require __DIR__ . '/_shell_bottom.php'; ?>