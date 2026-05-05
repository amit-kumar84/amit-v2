<?php $ADMIN_TITLE = 'Attempt Review';
require_once __DIR__ . '/../includes/helpers.php'; $me = require_login('admin');
ensure_softdelete_and_permissions();
$aid = (int)($_GET['id'] ?? 0);
$a = db()->prepare('SELECT a.*, u.name AS sname, u.email AS semail, u.roll_number, u.dob, u.photo_path, e.exam_name, e.created_by AS exam_owner, creator.name AS creator_name, creator.email AS creator_email FROM attempts a JOIN users u ON u.id=a.user_id JOIN exams e ON e.id=a.exam_id LEFT JOIN users creator ON creator.id=e.created_by WHERE a.id=?');
$a->execute([$aid]); $att = $a->fetch();
if (!$att) { flash('Not found','error'); redirect(url('admin/results.php')); }
if (empty($me['is_super']) && (int)$att['exam_owner'] !== (int)$me['id']) {
  flash('You can review only attempts for exams you created.','error'); redirect(url('admin/results.php'));
}
$qs = db()->prepare('SELECT * FROM questions WHERE exam_id=? AND deleted_at IS NULL ORDER BY id'); $qs->execute([$att['exam_id']]); $questions = $qs->fetchAll();
$optsMap = [];
if ($questions) { $ids=array_column($questions,'id'); $in=str_repeat('?,',count($ids)-1).'?';
  $o = db()->prepare("SELECT * FROM question_options WHERE question_id IN ($in) AND deleted_at IS NULL ORDER BY opt_order"); $o->execute($ids);
  foreach ($o->fetchAll() as $r) $optsMap[$r['question_id']][] = $r; }
$ans = []; $ax = db()->prepare('SELECT * FROM attempt_answers WHERE attempt_id=?'); $ax->execute([$aid]);
foreach ($ax->fetchAll() as $r) $ans[$r['question_id']] = $r;
$viols = db()->prepare('SELECT * FROM violations WHERE attempt_id=? ORDER BY event_time'); $viols->execute([$aid]); $violations = $viols->fetchAll();
require __DIR__ . '/_shell_top.php';

// Fetch attempt history for same exam and roll number (covers multiple accounts with same roll)
$history = [];
$hst = db()->prepare('SELECT a.id, a.attempt_no, a.score, a.total, a.submitted_at FROM attempts a JOIN users u ON u.id=a.user_id WHERE a.exam_id=? AND LOWER(u.roll_number)=LOWER(?) ORDER BY a.attempt_no ASC');
$hst->execute([$att['exam_id'], $att['roll_number']]);
$history = $hst->fetchAll();

function fmtSel($q, $a, $opts){
  if (!$a || !$a['selected_json']) return '<i class="text-muted">Not answered</i>';
  $s = json_decode($a['selected_json'], true);
  if (in_array($q['question_type'], ['mcq','multi_select'])) {
    $texts = []; foreach ($opts as $o) if (in_array((int)$o['opt_order'], array_map('intval',$s['selected']??[]))) $texts[] = h($o['opt_text']);
    return implode(', ', $texts);
  }
  if ($q['question_type']==='true_false') return !empty($s['bool'])?'True':'False';
  return h($s['text'] ?? $s['numeric'] ?? '');
}
function fmtCorrect($q, $opts){
  if (in_array($q['question_type'], ['mcq','multi_select'])) return implode(', ', array_map(fn($o)=>h($o['opt_text']), array_filter($opts, fn($o)=>$o['is_correct'])));
  if ($q['question_type']==='true_false') return $q['correct_bool']?'True':'False';
  if ($q['question_type']==='short_answer') return h($q['correct_text']);
  return h($q['correct_numeric']);
}
?>
<div class="d-flex justify-content-between mb-3 flex-wrap gap-2 align-items-center">
  <a href="<?= url('admin/exam-results-view.php?exam_id='.$att['exam_id']) ?>" class="btn btn-sm btn-back align-self-center"><i class="fas fa-arrow-left me-2"></i>Back to Exam Results</a>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= url('admin/attempt-pdf.php?id='.$aid) ?>" target="_blank" class="btn btn-sm btn-navy"><i class="fas fa-file-pdf me-1"></i>Result + Answer Key (PDF / Print)</a>
    <a href="<?= url('admin/export-attempt.php?id='.$aid) ?>" class="btn btn-sm btn-success"><i class="fas fa-file-csv me-1"></i>Export CSV</a>
  </div>
</div>
<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="exam-card"><small class="text-muted text-uppercase">Student</small><div class="h5 mb-0"><?= h($att['sname']) ?></div><small><?= h($att['semail']) ?> · <?= h($att['roll_number']) ?></small></div></div>
  <div class="col-md-3"><div class="exam-card"><small class="text-muted text-uppercase">Exam</small><div class="h5 mb-0"><?= h($att['exam_name']) ?></div><small>Attempt #<?= (int)$att['attempt_no'] ?></small></div></div>
  <div class="col-md-3"><div class="exam-card"><small class="text-muted text-uppercase">Hosted By</small><div class="h6 mb-0"><?= h($att['creator_name'] ?? '—') ?></div><small class="text-muted"><?= h($att['creator_email'] ?? '') ?></small></div></div>
  <div class="col-md-3"><div class="exam-card"><small class="text-muted text-uppercase">Score</small><div class="h5 mb-0 text-success"><?= $att['score'] ?> / <?= $att['total'] ?></div><small><?= fmt_dt($att['submitted_at']) ?></small></div></div>
</div>
<?php if ($history): ?>
<div class="attempt-history-card card p-3 mb-3">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <small class="text-muted">Attempt History</small>
    <small class="text-muted">Total: <?= count($history) ?></small>
  </div>
  <div class="attempt-history-list d-flex gap-3 flex-wrap">
    <?php foreach ($history as $idx => $h): $hid = $h['id']; $active = $hid == $aid; $delay = $idx * 0.06; ?>
      <div class="attempt-item p-3 rounded-3<?= $active ? ' active' : '' ?>" style="--btn-delay:<?= $delay ?>s;">
        <div class="d-flex align-items-center">
          <span class="attempt-icon me-3"><i class="fas <?= $active ? 'fa-star' : 'fa-history' ?>"></i></span>
          <div>
            <a href="<?= url('admin/attempt.php?id='.$hid) ?>" class="attempt-open-btn btn btn-sm <?= $active ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-pill">Attempt #<?= (int)$h['attempt_no'] ?></a>
            <div class="small text-muted mt-1"><?= fmt_dt($h['submitted_at']) ?></div>
          </div>
          <div class="ms-auto">
            <span class="badge score-badge"><?= h($h['score']).' / '.h($h['total']) ?></span>
          </div>
        </div>
        
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<?php if ($violations): ?>
<div class="alert alert-danger"><b><i class="fas fa-triangle-exclamation"></i> Violations Detected (<?= count($violations) ?>)</b>
  <ul class="mb-0 small"><?php foreach ($violations as $v): ?><li><b><?= h($v['event_type']) ?></b> — <?= h($v['description']) ?> <small class="text-muted">(<?= date('H:i:s', strtotime($v['event_time'])) ?>)</small></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<table class="data-table"><thead><tr><th style="width:40px">#</th><th>Question</th><th>Selected</th><th>Correct</th><th style="width:100px">Result</th></tr></thead><tbody>
<?php foreach ($questions as $i => $q): $a = $ans[$q['id']] ?? null; ?>
  <tr><td><?= $i+1 ?></td><td><?= h($q['question_text']) ?></td>
    <td><?= fmtSel($q, $a, $optsMap[$q['id']] ?? []) ?></td>
    <td class="text-success fw-medium"><?= fmtCorrect($q, $optsMap[$q['id']] ?? []) ?></td>
    <td><?php if (!$a||!$a['selected_json']) echo '<span class="badge bg-secondary">Skipped</span>'; elseif ($a['is_correct']) echo '<span class="badge bg-success">Correct</span>'; else echo '<span class="badge bg-danger">Wrong</span>'; ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php require __DIR__ . '/_shell_bottom.php'; ?>
