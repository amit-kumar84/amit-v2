<?php $ADMIN_TITLE = 'Trash / Deleted Records';
require_once __DIR__ . '/../includes/helpers.php';
$me = require_login('admin');
ensure_softdelete_and_permissions();

if (empty($me['is_super'])) {
  require __DIR__ . '/_shell_top.php';
  ?><div class="alert alert-danger">Only super admin can access the Trash. This section includes permanent delete operations.</div><?php
  require __DIR__ . '/_shell_bottom.php';
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_check();
  $a = $_POST['action'] ?? '';
  $kind = $_POST['kind'] ?? '';
  $id = (int)($_POST['id'] ?? 0);
  // Bulk actions: ids[] with action bulk_restore or bulk_hard_delete
  if (in_array($a, ['bulk_restore', 'bulk_hard_delete'])) {
    $ids = array_map('intval', $_POST['ids'] ?? []);
    if (!$ids) { flash('No items selected','error'); redirect(url('admin/trash.php')); }
    $tableMap = [
      'exam'     => 'exams',
      'student'  => 'users',
      'admin'    => 'users',
      'question' => 'questions',
    ];
    $table = $tableMap[$kind] ?? null;
    if (!$table) { flash('Invalid request','error'); redirect(url('admin/trash.php')); }
    $extra = '';
    if ($kind === 'student') $extra = " AND role='student'";
    if ($kind === 'admin')   $extra = " AND role='admin'";
    if ($a === 'bulk_restore') {
      $place = implode(',', array_fill(0, count($ids), '?'));
      $stmt = db()->prepare("UPDATE $table SET deleted_at=NULL, deleted_by=NULL, deleted_by_name=NULL, deleted_by_email=NULL WHERE id IN ($place) $extra");
      $stmt->execute($ids);
      log_admin_activity($kind.'_bulk_restore', 'Restored multiple '.$kind.' ids from Trash', $me, 'admin/trash.php', ['table'=>$table,'ids'=>$ids]);
      flash('Restored selected items','success');
    } else {
      // permanent delete
      $place = implode(',', array_fill(0, count($ids), '?'));
      // optionally capture snapshots for logging
      $snap = db()->prepare("SELECT * FROM $table WHERE id IN ($place) $extra"); $snap->execute($ids); $rows = $snap->fetchAll();
      $stmt = db()->prepare("DELETE FROM $table WHERE id IN ($place) $extra"); $stmt->execute($ids);
      log_admin_activity($kind.'_bulk_hard_delete', 'Permanently deleted multiple '.$kind.' ids', $me, 'admin/trash.php', ['table'=>$table,'before'=>array_slice($rows,0,10),'ids'=>$ids]);
      flash('Permanently deleted selected items','success');
    }
    redirect(url('admin/trash.php?tab='.urlencode($kind)));
  }
  $tableMap = [
    'exam'     => 'exams',
    'student'  => 'users',       // role=student
    'admin'    => 'users',       // role=admin
    'question' => 'questions',
  ];
  $table = $tableMap[$kind] ?? null;
  if (!$table || !$id) { flash('Invalid request','error'); redirect(url('admin/trash.php')); }

  $extra = '';
  if ($kind === 'student') $extra = " AND role='student'";
  if ($kind === 'admin')   $extra = " AND role='admin'";

  if ($a === 'restore') {
    $stmt = db()->prepare("UPDATE $table SET deleted_at=NULL, deleted_by=NULL, deleted_by_name=NULL, deleted_by_email=NULL WHERE id=? $extra");
    $stmt->execute([$id]);
    log_admin_activity($kind.'_restore', "Restored $kind id $id from Trash", $me, 'admin/trash.php', ['table'=>$table,'id'=>$id]);
    flash('Restored successfully','success');
  } elseif ($a === 'hard_delete') {
    // Permanent delete (super admin only, already enforced above)
    $snap = db()->prepare("SELECT * FROM $table WHERE id=? $extra");
    $snap->execute([$id]);
    $row = $snap->fetch() ?: [];
    $stmt = db()->prepare("DELETE FROM $table WHERE id=? $extra");
    $stmt->execute([$id]);
    log_admin_activity($kind.'_hard_delete', "Permanently deleted $kind id $id", $me, 'admin/trash.php', ['table'=>$table,'before'=>array_slice($row,0,14)]);
    flash('Permanently deleted','success');
  }
  redirect(url('admin/trash.php?tab='.urlencode($kind)));
}

require __DIR__ . '/_shell_top.php';
$tab = $_GET['tab'] ?? 'exam';

// Deleted exams
$delExams = db()->query("SELECT e.*, creator.name AS creator_name FROM exams e LEFT JOIN users creator ON creator.id=e.created_by WHERE e.deleted_at IS NOT NULL ORDER BY e.deleted_at DESC")->fetchAll();
$delStudents = db()->query("SELECT u.*, creator.name AS creator_name FROM users u LEFT JOIN users creator ON creator.id=u.created_by WHERE u.role='student' AND u.deleted_at IS NOT NULL ORDER BY u.deleted_at DESC")->fetchAll();
$delAdmins = db()->query("SELECT u.*, creator.name AS creator_name FROM users u LEFT JOIN users creator ON creator.id=u.created_by WHERE u.role='admin' AND u.deleted_at IS NOT NULL ORDER BY u.deleted_at DESC")->fetchAll();
$delQuestions = db()->query("SELECT q.*, e.exam_name FROM questions q LEFT JOIN exams e ON e.id=q.exam_id WHERE q.deleted_at IS NOT NULL ORDER BY q.deleted_at DESC")->fetchAll();
$trashStats = [
  'exam' => count($delExams),
  'student' => count($delStudents),
  'admin' => count($delAdmins),
  'question' => count($delQuestions),
];
$trashTotal = array_sum($trashStats);

function rowActions($kind, $id) { ?>
  <div class="d-flex gap-1 justify-content-end">
    <form method="post" class="d-inline"><?= csrf_input() ?>
      <input type="hidden" name="action" value="restore">
      <input type="hidden" name="kind" value="<?= h($kind) ?>">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <button class="btn btn-sm btn-outline-success" title="Restore"><i class="fas fa-rotate-left me-1"></i>Restore</button>
    </form>
    <form method="post" class="d-inline" onsubmit="event.preventDefault(); appConfirm('Permanently delete? This cannot be undone.').then(ok=>{ if(ok) this.submit(); });"><?= csrf_input() ?>
      <input type="hidden" name="action" value="hard_delete">
      <input type="hidden" name="kind" value="<?= h($kind) ?>">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <button class="btn btn-sm btn-danger" title="Permanent delete"><i class="fas fa-trash me-1"></i>Delete Forever</button>
    </form>
  </div>
<?php }
?>
<style>
body {
  background:
    linear-gradient(180deg, #f4f7fb 0%, #edf2f7 100%);
}
.trash-page {
  position:relative;
}
.trash-page::before {
  content:'';
  position:absolute;
  inset:0;
  background:
    radial-gradient(circle at top right, rgba(15,23,42,.05), transparent 22%),
    radial-gradient(circle at bottom left, rgba(202,138,4,.06), transparent 18%);
  pointer-events:none;
}
.trash-hero {
  position:relative;
  overflow:hidden;
  border-radius:18px;
  padding:22px 24px;
  margin-bottom:18px;
  color:#f8fafc;
  background:linear-gradient(135deg, #0f172a 0%, #1e293b 55%, #334155 100%);
  border:1px solid rgba(148,163,184,.18);
  box-shadow:0 14px 32px rgba(15,23,42,.14);
}
.trash-hero::after {
  content:'';
  position:absolute;
  inset:0;
  background:linear-gradient(90deg, rgba(202,138,4,.16), transparent 45%, rgba(255,255,255,.06));
  pointer-events:none;
}
.trash-hero .eyebrow {
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:6px 12px;
  border-radius:999px;
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.12);
  font-size:11px;
  font-weight:800;
  letter-spacing:.14em;
  text-transform:uppercase;
}
.trash-hero h2 {
  margin:14px 0 6px;
  font-weight:900;
  letter-spacing:-.02em;
}
.trash-hero p {
  margin:0;
  max-width:820px;
  color:rgba(248,250,252,.78);
  line-height:1.6;
}
.trash-summary {
  display:grid;
  grid-template-columns:repeat(5, minmax(0,1fr));
  gap:12px;
  margin-top:18px;
}
.trash-stat {
  position:relative;
  overflow:hidden;
  border-radius:14px;
  padding:14px 16px;
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.12);
  transition:transform .18s ease, background .18s ease;
}
.trash-stat:hover { transform:translateY(-2px); background:rgba(255,255,255,.1); }
.trash-stat .label {
  display:flex;
  align-items:center;
  gap:8px;
  font-size:11px;
  font-weight:800;
  letter-spacing:.1em;
  text-transform:uppercase;
  opacity:.9;
}
.trash-stat .value { font-size:28px; line-height:1; font-weight:900; margin-top:8px; }
.trash-stat .hint { font-size:11px; opacity:.75; margin-top:6px; }
.trash-stat.exam,
.trash-stat.student,
.trash-stat.admin,
.trash-stat.question,
.trash-stat.total {
  box-shadow:none;
}
.trash-stat.total { background:rgba(202,138,4,.14); }
.trash-stat.exam { background:rgba(37,99,235,.14); }
.trash-stat.student { background:rgba(5,150,105,.14); }
.trash-stat.admin { background:rgba(124,58,237,.14); }
.trash-stat.question { background:rgba(194,65,12,.14); }
.tab-row { display:flex; flex-wrap:wrap; gap:10px; margin:0 0 18px; }
.tab-pill {
  position:relative;
  padding:10px 16px;
  border:1px solid #cbd5e1;
  border-radius:999px;
  background:#fff;
  font-weight:800;
  color:#334155;
  text-decoration:none;
  font-size:13px;
  display:inline-flex;
  align-items:center;
  gap:8px;
  box-shadow:0 6px 16px rgba(15,23,42,.05);
  transition:transform .18s ease, box-shadow .18s ease, background .18s ease, color .18s ease;
}
.tab-pill:hover { transform:translateY(-1px); box-shadow:0 10px 20px rgba(15,23,42,.08); }
.tab-pill.active {
  color:#fff;
  border-color:#0f172a;
  background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
  box-shadow:0 12px 24px rgba(15,23,42,.14);
}
.tab-pill .cnt {
  min-width:24px;
  height:24px;
  padding:0 7px;
  border-radius:999px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  font-size:11px;
  font-weight:900;
  background:#c2410c;
  color:#fff;
}
.tab-pill.active .cnt { background:rgba(255,255,255,.14); }
.trash-toolbar {
  display:flex;
  justify-content:flex-end;
  gap:10px;
  margin-bottom:12px;
}
.trash-toolbar .btn {
  border-radius:999px;
  padding:.45rem .9rem;
  font-weight:800;
  box-shadow:0 6px 14px rgba(15,23,42,.06);
}
.trash-panel {
  background:#fff;
  border:1px solid #d6dbe2;
  border-radius:18px;
  overflow:hidden;
  box-shadow:0 14px 30px rgba(15,23,42,.06);
}
.trash-panel .table-wrap { overflow-x:auto; }
.trash-tbl { margin:0; }
.trash-tbl thead th {
  background:linear-gradient(135deg, #111827 0%, #374151 100%) !important;
  color:#fff !important;
  border-color:rgba(255,255,255,.08) !important;
  font-size:11px;
  text-transform:uppercase;
  letter-spacing:.08em;
  padding-top:14px !important;
  padding-bottom:14px !important;
}
.trash-tbl tbody tr {
  background:#fff !important;
  transition:background .15s ease;
}
.trash-tbl tbody tr:hover {
  background:#f8fafc !important;
}
.trash-tbl tr td { color:#111827; vertical-align:middle; }
.trash-tbl .deleter {
  font-size:11px;
  color:#7c2d12;
  background:#fef3c7;
  padding:4px 9px;
  border-radius:999px;
  border:1px solid #f59e0b33;
  display:inline-flex;
  align-items:center;
  gap:6px;
}
.trash-tbl .font-monospace { color:#1d4ed8; font-weight:700; }
.trash-tbl .fw-bold { color:#111827; }
.trash-tbl .action-stack { display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap; }
.trash-tbl .action-stack .btn { border-radius:999px; font-weight:800; padding:.36rem .75rem; }
.trash-empty {
  text-align:center;
  padding:28px 18px;
  color:#64748b;
  background:#fff;
}
@media (max-width: 1200px) { .trash-summary { grid-template-columns:repeat(2, minmax(0,1fr)); } }
@media (max-width: 768px) {
  .trash-hero { padding:18px; border-radius:16px; }
  .trash-summary { grid-template-columns:1fr; }
  .trash-toolbar { justify-content:stretch; }
  .trash-toolbar .btn { flex:1; }
}
</style>

<div class="trash-page">
  <div class="trash-hero">
    <div class="eyebrow"><i class="fas fa-building-shield"></i> Official Records Desk</div>
    <h2 class="mb-0">Trash / Deleted Records</h2>
    <p>Administrative archive for deleted exams, users, and questions. Review records with discretion, restore where permitted, or carry out permanent deletion with audit trace preserved.</p>
    <div class="trash-summary">
      <div class="trash-stat total">
        <div class="label"><i class="fas fa-layer-group"></i>Total deleted</div>
        <div class="value"><?= (int)$trashTotal ?></div>
        <div class="hint">All deleted records combined</div>
      </div>
      <div class="trash-stat exam">
        <div class="label"><i class="fas fa-book-open"></i>Exams</div>
        <div class="value"><?= (int)$trashStats['exam'] ?></div>
        <div class="hint">Deleted exam entries</div>
      </div>
      <div class="trash-stat student">
        <div class="label"><i class="fas fa-user-graduate"></i>Students</div>
        <div class="value"><?= (int)$trashStats['student'] ?></div>
        <div class="hint">Deleted student accounts</div>
      </div>
      <div class="trash-stat admin">
        <div class="label"><i class="fas fa-user-shield"></i>Admins</div>
        <div class="value"><?= (int)$trashStats['admin'] ?></div>
        <div class="hint">Removed admin accounts</div>
      </div>
      <div class="trash-stat question">
        <div class="label"><i class="fas fa-circle-question"></i>Questions</div>
        <div class="value"><?= (int)$trashStats['question'] ?></div>
        <div class="hint">Deleted question items</div>
      </div>
    </div>
  </div>

  <div class="alert alert-warning mb-3 shadow-sm border-0" style="border-radius:18px; background:linear-gradient(135deg, #fff8e1, #fff1f2); color:#7c2d12;">
    <i class="fas fa-triangle-exclamation me-1"></i>
    <b>Super Admin — Trash</b>. Deleted records are retained here until you <b>Restore</b> them or <b>Delete Forever</b>. Permanent deletion cannot be undone and is logged for accountability.
  </div>

  <div class="tab-row mb-3">
    <a class="tab-pill <?= $tab==='exam'?'active':'' ?>" href="?tab=exam">
      <i class="fas fa-book-open"></i>Exams <span class="cnt"><?= count($delExams) ?></span></a>
    <a class="tab-pill <?= $tab==='student'?'active':'' ?>" href="?tab=student">
      <i class="fas fa-users"></i>Students <span class="cnt"><?= count($delStudents) ?></span></a>
    <a class="tab-pill <?= $tab==='admin'?'active':'' ?>" href="?tab=admin">
      <i class="fas fa-user-shield"></i>Admins <span class="cnt"><?= count($delAdmins) ?></span></a>
    <a class="tab-pill <?= $tab==='question'?'active':'' ?>" href="?tab=question">
      <i class="fas fa-circle-question"></i>Questions <span class="cnt"><?= count($delQuestions) ?></span></a>
  </div>

  <div class="trash-toolbar">
    <div id="trash-toolbar" class="d-flex gap-2">
      <button id="trash-restore" class="btn btn-sm btn-outline-success" disabled>Restore Selected</button>
      <button id="trash-delete" class="btn btn-sm btn-danger" disabled>Delete Selected</button>
    </div>
  </div>

  <?php if ($tab === 'exam'): ?>
    <div class="trash-panel">
      <div class="table-wrap">
        <table class="data-table trash-tbl">
          <thead><tr><th style="width:36px"><input type="checkbox" id="select-all-trash"></th><th>Exam</th><th>Code</th><th>Created By</th><th>Deleted By</th><th>Deleted At</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
          <?php foreach ($delExams as $e): ?>
            <tr>
              <td><input type="checkbox" class="trash-chk" value="<?= (int)$e['id'] ?>"></td>
              <td class="fw-bold"><?= h($e['exam_name']) ?></td>
              <td class="font-monospace"><?= h($e['exam_code']) ?></td>
              <td><?= h($e['creator_name'] ?? '—') ?><div class="small" style="opacity:.7"><?= fmt_dt($e['created_at']) ?></div></td>
              <td><span class="deleter"><i class="fas fa-user me-1"></i><?= h($e['deleted_by_name']) ?></span><div class="small" style="opacity:.8"><?= h($e['deleted_by_email']) ?></div></td>
              <td><?= fmt_dt($e['deleted_at']) ?></td>
              <td class="text-end"><?php rowActions('exam', $e['id']); ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$delExams): ?><tr><td colspan="7" class="text-center text-muted py-4 trash-empty">No deleted exams</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php elseif ($tab === 'student'): ?>
    <div class="trash-panel">
      <div class="table-wrap">
        <table class="data-table trash-tbl">
          <thead><tr><th style="width:36px"><input type="checkbox" id="select-all-trash"></th><th>Name</th><th>Roll</th><th>Email</th><th>Created By</th><th>Deleted By</th><th>Deleted At</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
          <?php foreach ($delStudents as $s): ?>
            <tr>
              <td><input type="checkbox" class="trash-chk" value="<?= (int)$s['id'] ?>"></td>
              <td class="fw-bold"><?= h($s['name']) ?></td>
              <td class="font-monospace"><?= h($s['roll_number']) ?></td>
              <td><?= h($s['email']) ?></td>
              <td><?= h($s['creator_name'] ?? '—') ?></td>
              <td><span class="deleter"><i class="fas fa-user me-1"></i><?= h($s['deleted_by_name']) ?></span><div class="small" style="opacity:.8"><?= h($s['deleted_by_email']) ?></div></td>
              <td><?= fmt_dt($s['deleted_at']) ?></td>
              <td class="text-end"><?php rowActions('student', $s['id']); ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$delStudents): ?><tr><td colspan="8" class="text-center text-muted py-4 trash-empty">No deleted students</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php elseif ($tab === 'admin'): ?>
    <div class="trash-panel">
      <div class="table-wrap">
        <table class="data-table trash-tbl">
          <thead><tr><th style="width:36px"><input type="checkbox" id="select-all-trash"></th><th>Name</th><th>Email</th><th>Deleted By</th><th>Deleted At</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
          <?php foreach ($delAdmins as $s): ?>
            <tr>
              <td><input type="checkbox" class="trash-chk" value="<?= (int)$s['id'] ?>"></td>
              <td class="fw-bold"><?= h($s['name']) ?></td>
              <td><?= h($s['email']) ?></td>
              <td><span class="deleter"><i class="fas fa-user me-1"></i><?= h($s['deleted_by_name']) ?></span><div class="small" style="opacity:.8"><?= h($s['deleted_by_email']) ?></div></td>
              <td><?= fmt_dt($s['deleted_at']) ?></td>
              <td class="text-end"><?php rowActions('admin', $s['id']); ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$delAdmins): ?><tr><td colspan="6" class="text-center text-muted py-4 trash-empty">No deleted admins</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php elseif ($tab === 'question'): ?>
    <div class="trash-panel">
      <div class="table-wrap">
        <table class="data-table trash-tbl">
          <thead><tr><th style="width:36px"><input type="checkbox" id="select-all-trash"></th><th>#</th><th>Question</th><th>Exam</th><th>Type</th><th>Deleted By</th><th>Deleted At</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
          <?php foreach ($delQuestions as $q): ?>
            <tr>
              <td><input type="checkbox" class="trash-chk" value="<?= (int)$q['id'] ?>"></td>
              <td><?= (int)$q['id'] ?></td>
              <td><?= h(mb_substr($q['question_text'], 0, 90)) ?><?= mb_strlen($q['question_text'])>90?'…':'' ?></td>
              <td><?= h($q['exam_name'] ?? '—') ?></td>
              <td><?= str_replace('_',' ',h($q['question_type'])) ?></td>
              <td><span class="deleter"><i class="fas fa-user me-1"></i><?= h($q['deleted_by_name']) ?></span><div class="small" style="opacity:.8"><?= h($q['deleted_by_email']) ?></div></td>
              <td><?= fmt_dt($q['deleted_at']) ?></td>
              <td class="text-end"><?php rowActions('question', $q['id']); ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$delQuestions): ?><tr><td colspan="8" class="text-center text-muted py-4 trash-empty">No deleted questions</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_shell_bottom.php'; ?>
<script>
// Trash bulk selection handling
const selectAll = document.getElementById('select-all-trash');
function updateTrashButtons() {
  const any = Array.from(document.querySelectorAll('.trash-chk')).some(cb => cb.checked);
  const restoreBtn = document.getElementById('trash-restore');
  const deleteBtn = document.getElementById('trash-delete');
  if (restoreBtn) restoreBtn.disabled = !any;
  if (deleteBtn) deleteBtn.disabled = !any;
}
if (selectAll) selectAll.addEventListener('change', () => { document.querySelectorAll('.trash-chk').forEach(cb => cb.checked = selectAll.checked); updateTrashButtons(); });
document.querySelectorAll('.trash-chk').forEach(cb => cb.addEventListener('change', updateTrashButtons));

function submitTrashBulk(action) {
  const ids = Array.from(document.querySelectorAll('.trash-chk')).filter(c=>c.checked).map(c=>c.value);
  if (!ids.length) return;
  const submit = () => {
    const f = document.createElement('form'); f.method='post'; f.action='<?= url('admin/trash.php') ?>';
    const c = document.createElement('input'); c.type='hidden'; c.name='_csrf'; c.value='<?= h(csrf()) ?>'; f.appendChild(c);
    const a = document.createElement('input'); a.type='hidden'; a.name='action'; a.value=action; f.appendChild(a);
    const k = document.createElement('input'); k.type='hidden'; k.name='kind'; k.value='<?= h($tab) ?>'; f.appendChild(k);
    ids.forEach(id => { const i = document.createElement('input'); i.type='hidden'; i.name='ids[]'; i.value = id; f.appendChild(i); });
    document.body.appendChild(f); f.submit();
  };
  if (action === 'bulk_hard_delete') {
    appConfirm('Permanently delete selected items? This cannot be undone.').then(ok => { if (ok) submit(); });
    return;
  }
  const f = document.createElement('form'); f.method='post'; f.action='<?= url('admin/trash.php') ?>';
  const c = document.createElement('input'); c.type='hidden'; c.name='_csrf'; c.value='<?= h(csrf()) ?>'; f.appendChild(c);
  const a = document.createElement('input'); a.type='hidden'; a.name='action'; a.value=action; f.appendChild(a);
  const k = document.createElement('input'); k.type='hidden'; k.name='kind'; k.value='<?= h($tab) ?>'; f.appendChild(k);
  ids.forEach(id => { const i = document.createElement('input'); i.type='hidden'; i.name='ids[]'; i.value = id; f.appendChild(i); });
  document.body.appendChild(f); f.submit();
}
const restoreBtn = document.getElementById('trash-restore'); if (restoreBtn) restoreBtn.addEventListener('click', () => submitTrashBulk('bulk_restore'));
const deleteBtn = document.getElementById('trash-delete'); if (deleteBtn) deleteBtn.addEventListener('click', () => submitTrashBulk('bulk_hard_delete'));
</script>
