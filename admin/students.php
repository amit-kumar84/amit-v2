<?php $ADMIN_TITLE = 'Students';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mailer.php';
$u = require_login('admin');
ensure_softdelete_and_permissions();
$creds = null;
$mailStatus = null;

function ensure_student_columns(): void {
  $cols = [];
  foreach (db()->query('SHOW COLUMNS FROM users')->fetchAll() as $col) {
    $cols[$col['Field']] = true;
  }
  if (empty($cols['plain_password'])) {
    db()->exec('ALTER TABLE users ADD COLUMN plain_password VARCHAR(80) NULL AFTER password_hash');
  }
  if (empty($cols['photo_path'])) {
    db()->exec('ALTER TABLE users ADD COLUMN photo_path VARCHAR(255) NULL AFTER category');
  }
}

ensure_student_columns();

// ensure exam_code exists on exams
ensure_exam_code_column();

// Load current and upcoming exams for assignment UI — restricted to exams this admin has access to.
$accessibleIds = accessible_exam_ids($u);
if (!empty($u['is_super']) || can_view_all('exams', $u)) {
    $exams = db()->query("SELECT id, exam_name, exam_code, start_time, end_time FROM exams WHERE end_time >= NOW() AND deleted_at IS NULL ORDER BY start_time DESC")->fetchAll();
} elseif ($accessibleIds) {
    $in = implode(',', array_map('intval', $accessibleIds));
    $exams = db()->query("SELECT id, exam_name, exam_code, start_time, end_time FROM exams WHERE id IN ($in) AND end_time >= NOW() AND deleted_at IS NULL ORDER BY start_time DESC")->fetchAll();
} else {
    $exams = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    try {
        if ($act === 'add') {
            $name  = trim($_POST['name']);
            $email = strtolower(trim($_POST['email']));
            $roll  = trim($_POST['roll']);
            $dob   = $_POST['dob'];
            $cat   = $_POST['category'] ?? 'external';
            $sendMail = !empty($_POST['send_mail']);

            if (!$name || !$email || !$roll || !$dob) throw new Exception('All fields are required.');
            $chk = db()->prepare('SELECT 1 FROM users WHERE email=? OR (role="student" AND roll_number=?)');
            $chk->execute([$email, $roll]);
            if ($chk->fetch()) throw new Exception('Email or Roll number already exists.');

                  $photoPath = null;
                  if (!empty($_FILES['photo']['name'])) $photoPath = save_candidate_photo($_FILES['photo'], $roll);

                  $pwd = gen_password(6);

                  db()->prepare('INSERT INTO users (role,name,email,password_hash,plain_password,roll_number,dob,category,photo_path,created_by)
                     VALUES ("student",?,?,?,?,?,?,?,?,?)')
                ->execute([$name, $email, password_hash($pwd, PASSWORD_BCRYPT), $pwd, $roll, $dob, $cat, $photoPath, current_user()['id']]);

            $userId = (int)db()->lastInsertId();
            $examIds = array_map('intval', $_POST['exam_ids'] ?? []);
            if ($examIds) set_exam_assignments($userId, $examIds);

            $payload = ['table' => 'users', 'after' => ['id' => $userId, 'name' => $name, 'email' => $email, 'roll_number' => $roll, 'dob' => $dob, 'category' => $cat]];
            log_admin_activity('student_add', 'Created student ' . $name . ' (' . $roll . ')', current_user(), 'admin/students.php', $payload);
            $creds = compact('name', 'email', 'roll', 'dob', 'pwd');
            if ($sendMail && defined('SMTP_HOST') && SMTP_HOST) {
              $res = mail_credentials(['name' => $name, 'email' => $email, 'roll_number' => $roll, 'dob' => $dob], $pwd);
                $mailStatus = $res['ok'] ? ['ok' => true, 'msg' => "Credentials emailed to $email"] : ['ok' => false, 'msg' => 'Mail failed: ' . $res['error']];
            } elseif ($sendMail) {
                $mailStatus = ['ok' => false, 'msg' => 'SMTP not configured — set SMTP_HOST in includes/config.php to enable auto-email.'];
            }
        } elseif ($act === 'update') {
            $id    = (int)$_POST['id'];
            $name  = trim($_POST['name']);
            $email = strtolower(trim($_POST['email']));
            $roll  = trim($_POST['roll']);
            $dob   = $_POST['dob'];
            $cat   = $_POST['category'] ?? 'external';
            $existing = db()->prepare('SELECT photo_path FROM users WHERE id=?'); $existing->execute([$id]); $ex = $existing->fetch();
            $photoPath = $ex['photo_path'] ?? null;
            if (!empty($_FILES['photo']['name'])) $photoPath = save_candidate_photo($_FILES['photo'], $roll) ?: $photoPath;
            $before = db()->prepare('SELECT * FROM users WHERE id=?'); $before->execute([$id]); $beforeRow = $before->fetch() ?: [];
            db()->prepare('UPDATE users SET name=?, email=?, roll_number=?, dob=?, category=?, photo_path=? WHERE id=? AND role="student"')
              ->execute([$name, $email, $roll, $dob, $cat, $photoPath, $id]);
            // If admin provided a password, validate exact length 6 and update
            if (isset($_POST['password']) && trim((string)$_POST['password']) !== '') {
              $npwd = trim((string)$_POST['password']);
              if (strlen($npwd) !== 6) throw new Exception('Password must be exactly 6 characters.');
              db()->prepare('UPDATE users SET password_hash=?, plain_password=? WHERE id=? AND role="student"')
                ->execute([password_hash($npwd, PASSWORD_BCRYPT), $npwd, $id]);
              $payload['after']['password_changed'] = true;
            }
          // save exam assignments from edit form
          $examIds = array_map('intval', $_POST['exam_ids'] ?? []);
          if ($examIds) set_exam_assignments($id, $examIds);
            $payload = ['table' => 'users', 'before' => array_slice($beforeRow, 0, 8), 'after' => ['id' => $id, 'name' => $name, 'email' => $email, 'roll_number' => $roll, 'dob' => $dob, 'category' => $cat]];
            log_admin_activity('student_update', 'Updated student ' . $name . ' (' . $roll . ')', current_user(), 'admin/students.php', $payload);
            flash('Student updated', 'success');
            redirect(url('admin/students.php'));
        } elseif ($act === 'delete') {
          $sid = (int)$_POST['id'];
          $del = db()->prepare('SELECT * FROM users WHERE id=? AND role="student" AND deleted_at IS NULL'); $del->execute([$sid]); $delRow = $del->fetch();
          if (!$delRow) throw new Exception('Student not found or already deleted');
          // Ownership + permission check
          if (!current_user()['is_super'] && (int)($delRow['created_by'] ?? 0) !== (int)current_user()['id'])
            throw new Exception('You can delete only students you created.');
          if (!can('students','delete', current_user())) throw new Exception('Permission denied: delete students.');
          soft_delete('users', $sid, current_user(), " AND role='student'");
          $payload = ['table' => 'users', 'before' => array_slice($delRow, 0, 12), 'soft_delete' => true];
          log_admin_activity('student_delete', 'Soft-deleted student id ' . $sid, current_user(), 'admin/students.php', $payload);
          flash('Moved to Trash. Super admin can restore or permanently delete.', 'success');
          redirect(url('admin/students.php'));
        } elseif ($act === 'bulk_delete') {
          if (!can('students','delete', current_user())) throw new Exception('Permission denied: delete students.');
          $ids = array_map('intval', $_POST['ids'] ?? []);
          $ids = array_filter($ids);
          if (!$ids) throw new Exception('No students selected');
          // Ownership filter for non-super
          if (!current_user()['is_super']) {
            $place = implode(',', array_fill(0, count($ids), '?'));
            $chk = db()->prepare("SELECT id FROM users WHERE id IN ($place) AND role='student' AND created_by=?");
            $chk->execute(array_merge($ids, [(int)current_user()['id']]));
            $ids = array_map(fn($r)=>(int)$r['id'], $chk->fetchAll());
            if (!$ids) throw new Exception('None of the selected students were created by you.');
          }
          $n = 0;
          foreach ($ids as $sid) {
            if (soft_delete('users', (int)$sid, current_user(), " AND role='student'")) $n++;
          }
          $payload = ['table' => 'users', 'ids_deleted' => $ids, 'count' => $n, 'soft_delete' => true];
          log_admin_activity('student_bulk_delete', 'Soft-deleted student ids: ' . implode(',', $ids), current_user(), 'admin/students.php', $payload);
          flash('Moved ' . $n . ' students to Trash', 'success');
          redirect(url('admin/students.php'));
        } elseif ($act === 'reset') {
            $id  = (int)$_POST['id'];
            $before = db()->prepare('SELECT password_hash FROM users WHERE id=?'); $before->execute([$id]); $beforeHash = $before->fetchColumn();
            $pwd = gen_password(6);
            db()->prepare('UPDATE users SET password_hash=?, plain_password=? WHERE id=? AND role="student"')
                ->execute([password_hash($pwd, PASSWORD_BCRYPT), $pwd, $id]);
            $row = db()->prepare('SELECT name,email,roll_number AS roll,dob,plain_password FROM users WHERE id=?'); $row->execute([$id]); $creds = $row->fetch(); $creds['pwd'] = $pwd;
            $payload = ['table' => 'users', 'before' => ['id' => $id, 'password_hash' => $beforeHash], 'after' => ['id' => $id, 'password_hash' => password_hash($pwd, PASSWORD_BCRYPT)]];
            log_admin_activity('student_reset', 'Reset password for student id ' . $id, current_user(), 'admin/students.php', $payload);
        } elseif ($act === 'email') {
            $id = (int)$_POST['id'];
            $row = db()->prepare('SELECT name,email,roll_number,dob,plain_password FROM users WHERE id=? AND role="student"');
            $row->execute([$id]); $s = $row->fetch();
            if (!$s) throw new Exception('Student not found');
            if (!$s['plain_password']) throw new Exception('No plain password on file. Click Reset first to generate one.');
            if (!defined('SMTP_HOST') || !SMTP_HOST) throw new Exception('SMTP not configured. Set SMTP_HOST in includes/config.php.');
            $res = mail_credentials($s, $s['plain_password']);
            $mailStatus = $res['ok'] ? ['ok' => true, 'msg' => "Credentials emailed to {$s['email']}"] : ['ok' => false, 'msg' => 'Mail failed: ' . $res['error']];
            $payload = ['table' => 'users', 'action_type' => 'email_sent', 'student_id' => $id, 'email_to' => $s['email']];
            log_admin_activity('student_email', 'Emailed credentials for student ' . $s['name'] . ' (' . $s['email'] . ')', current_user(), 'admin/students.php', $payload);
        } elseif ($act === 'bulk') {
            $csv    = trim($_POST['csv'] ?? '');
            $lines  = preg_split('/\r\n|\n|\r/', $csv);
            $header = str_getcsv(array_shift($lines));
            $created = 0; $errors = [];
            foreach ($lines as $i => $ln) {
                if (!trim($ln)) continue;
                $row = array_combine($header, str_getcsv($ln));
                try {
                    $name  = trim($row['name']);
                    $email = strtolower(trim($row['email']));
                    $roll  = trim($row['roll_number'] ?? $row['roll'] ?? '');
                    $dobRaw   = trim($row['dob']);
                    // Normalize DOB to YYYY-MM-DD accepting several common formats
                    $dob = '';
                    if ($dobRaw !== '') {
                      $d = DateTime::createFromFormat('Y-m-d', $dobRaw) ?: DateTime::createFromFormat('d-m-Y', $dobRaw) ?: DateTime::createFromFormat('d/m/Y', $dobRaw) ?: DateTime::createFromFormat('m/d/Y', $dobRaw);
                      if ($d && $d->format('Y') > 1900) {
                        $dob = $d->format('Y-m-d');
                      } else {
                        throw new Exception('invalid dob format');
                      }
                    }
                    $cat   = trim($row['category'] ?? 'external');
                    if (!$name || !$email || !$roll || !$dob) throw new Exception('missing field');
                    $chk = db()->prepare('SELECT 1 FROM users WHERE email=? OR (role="student" AND roll_number=?)'); $chk->execute([$email, $roll]);
                    if ($chk->fetch()) throw new Exception('exists');
                    $pwd   = trim($row['password'] ?? '') ?: gen_password(6);
                    db()->prepare('INSERT INTO users (role,name,email,password_hash,plain_password,roll_number,dob,category) VALUES ("student",?,?,?,?,?,?,?)')
                      ->execute([$name, $email, password_hash($pwd, PASSWORD_BCRYPT), $pwd, $roll, $dob, $cat]);
                    $newId = (int)db()->lastInsertId();
                    // allow per-row exam codes (column 'exam_codes') else use bulk select
                    $rowExamIds = [];
                    if (!empty($row['exam_codes'])) {
                      $codes = preg_split('/[|,;]+/', $row['exam_codes']);
                      $rowExamIds = exam_ids_from_codes($codes);
                    }
                    $bulkExamIds = array_map('intval', $_POST['bulk_exam_ids'] ?? []);
                    $assignIds = $rowExamIds ?: $bulkExamIds;
                    if ($assignIds) set_exam_assignments($newId, $assignIds);
                    $created++;
                } catch (Exception $e) { $errors[] = 'Row ' . ($i + 2) . ': ' . $e->getMessage(); }
            }
            $payload = ['table' => 'users', 'count' => $created];
            log_admin_activity('student_bulk_import', 'Bulk imported ' . $created . ' student(s)', current_user(), 'admin/students.php', $payload);
            flash("Bulk: created $created, errors " . count($errors) . (count($errors) ? '. ' . implode('; ', array_slice($errors, 0, 5)) : ''), $errors ? 'error' : 'success');
            redirect(url('admin/students.php'));
        }
    } catch (Exception $e) {
        flash($e->getMessage(), 'error');
        redirect(url('admin/students.php'));
    }
}

require __DIR__ . '/_shell_top.php';
$q = trim($_GET['q'] ?? '');
$examFilter = (int)($_GET['exam_id'] ?? 0);

$where = ['role="student"', 'deleted_at IS NULL'];
$params = [];
// Ownership filter: non-super admins see (a) students they created PLUS (b) students
// registered for any exam they have access to (own or granted).
if (!$u['is_super'] && !can_view_all('students', $u)) {
  $accessibleExamIds = accessible_exam_ids($u);
  if ($accessibleExamIds) {
    $in = implode(',', array_map('intval', $accessibleExamIds));
    $where[] = "(created_by = ? OR EXISTS (SELECT 1 FROM exam_assignments ea WHERE ea.user_id = users.id AND ea.exam_id IN ($in)))";
    $params[] = (int)$u['id'];
  } else {
    $where[] = 'created_by = ?';
    $params[] = (int)$u['id'];
  }
}
if ($q !== '') {
  $where[] = '(name LIKE ? OR email LIKE ? OR roll_number LIKE ? OR DATE_FORMAT(dob, "%Y-%m-%d") LIKE ?)';
  $like = "%$q%";
  $params = [$like, $like, $like, $like];
}
if ($examFilter > 0) {
  $where[] = 'EXISTS (SELECT 1 FROM exam_assignments ea WHERE ea.user_id = users.id AND ea.exam_id = ?)';
  $params[] = $examFilter;
}
$stmt = db()->prepare('SELECT * FROM users WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC');
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Prefetch assigned exams for listed students
$assignedMap = [];
$studentIds = array_column($rows, 'id');
if ($studentIds) {
  $in = str_repeat('?,', count($studentIds) - 1) . '?';
  $qa = db()->prepare("SELECT ea.user_id, e.id AS exam_id, e.exam_name, e.exam_code FROM exam_assignments ea JOIN exams e ON e.id=ea.exam_id WHERE ea.user_id IN ($in) ORDER BY e.start_time DESC");
  $qa->execute($studentIds);
  foreach ($qa->fetchAll() as $ar) {
    $assignedMap[$ar['user_id']][] = $ar;
  }
}

// Backfill missing plain passwords for legacy student rows so the list can show real credentials.
foreach ($rows as &$row) {
  if (empty($row['plain_password'])) {
    $plainPassword = gen_password(6);
    db()->prepare('UPDATE users SET password_hash = ?, plain_password = ? WHERE id = ? AND role = "student"')
      ->execute([password_hash($plainPassword, PASSWORD_BCRYPT), $plainPassword, $row['id']]);
    $row['plain_password'] = $plainPassword;
  }
}
unset($row);
$smtpReady = defined('SMTP_HOST') && SMTP_HOST !== '';
?>
<div class="d-flex justify-content-between mb-3 gap-2 flex-wrap">
  <form class="d-flex gap-2 flex-wrap">
    <input name="q" value="<?= h($q) ?>" class="form-control" style="width:320px" placeholder="Search name, email, roll, DOB" data-testid="students-search-input">
    <select name="exam_id" class="form-select" style="width:260px" data-testid="students-exam-filter">
      <option value="">All assigned exams</option>
      <?php foreach ($exams as $ex): ?>
        <option value="<?= (int)$ex['id'] ?>" <?= $examFilter === (int)$ex['id'] ? 'selected' : '' ?>><?= h($ex['exam_name']) ?> <?= $ex['exam_code'] ? '— ' . h($ex['exam_code']) : '' ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-outline-secondary">Filter</button>
  </form>
  <div class="d-flex gap-2">
    <button class="btn btn-navy" data-bs-toggle="modal" data-bs-target="#addModal" data-testid="add-student-btn" data-perm-section="students" data-perm-action="create"><i class="fas fa-user-plus me-1"></i>Add Student</button>
    <button class="btn btn-outline-navy" data-bs-toggle="modal" data-bs-target="#bulkModal" data-testid="bulk-upload-btn" data-perm-section="students" data-perm-action="create">Bulk Upload (CSV)</button>
  </div>
</div>

<?php if (!$smtpReady): ?>
  <div class="alert alert-warning small py-2 mb-3" data-testid="smtp-warning">
    <i class="fas fa-circle-info me-1"></i> <b>SMTP not configured</b> — emails will be skipped. Set <code>SMTP_HOST</code> in <code>includes/config.php</code> to auto-email credentials.
  </div>
<?php endif; ?>

<?php if ($mailStatus): ?>
  <div class="alert alert-<?= $mailStatus['ok'] ? 'success' : 'danger' ?> small py-2" data-testid="mail-status"><?= h($mailStatus['msg']) ?></div>
<?php endif; ?>

<?php if ($creds): ?>
<div class="alert alert-warning" data-testid="new-creds-alert">
  <h6 class="fw-bold mb-2"><i class="fas fa-key me-1"></i>Credentials — share with candidate</h6>
  <pre class="mb-0" style="font-size:12px">Name: <?= h($creds['name']) ?>
Roll / Staff ID: <?= h($creds['roll']) ?>
DOB: <?= h($creds['dob']) ?>
Email: <?= h($creds['email']) ?>
Password: <?= h($creds['pwd']) ?></pre>
</div>
<?php endif; ?>

<div class="bg-white border">
<div class="d-flex justify-content-between p-2"><div></div><div><button id="bulk-delete-btn" class="btn btn-outline-danger btn-sm" disabled data-perm-section="students" data-perm-action="delete">Delete selected</button></div></div>
<table class="data-table" data-testid="students-table"><thead><tr>
  <th style="width:36px"><input type="checkbox" id="select-all-students"></th>
  <th>Photo</th><th>Name</th><th>Email</th><th>Roll/Staff</th><th>Assigned Exams</th><th>DOB</th><th>Password</th><th>Category</th><th>Created</th><th class="text-end">Actions</th>
</tr></thead><tbody>
<?php foreach ($rows as $r): ?>
  <tr data-testid="student-row-<?= $r['id'] ?>">
    <td><input type="checkbox" class="student-chk" data-id="<?= $r['id'] ?>"></td>
    <td>
      <?php if (!empty($r['photo_path'])): ?>
        <img src="<?= url($r['photo_path']) ?>" alt="" style="width:42px;height:54px;object-fit:cover;border:1px solid #cbd5e1;border-radius:3px">
      <?php else: ?>
        <div style="width:42px;height:54px;border:1px dashed #cbd5e1;border-radius:3px;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:10px;text-align:center;line-height:1.1">No<br>Photo</div>
      <?php endif; ?>
    </td>
    <td class="fw-medium"><?= h($r['name']) ?><div class="small text-muted"><?= h($r['email']) ?></div></td>
    <td><?= h($r['email']) ?></td>
    <td class="font-monospace small"><?= h($r['roll_number']) ?></td>
    <td>
      <?php if (!empty($assignedMap[$r['id']])): ?>
        <?php foreach ($assignedMap[$r['id']] as $ae): ?>
          <div class="small badge bg-light text-dark mb-1" style="display:inline-block;margin-right:6px;border:1px solid #e6e9ef"><?= h($ae['exam_name']) ?> <?= $ae['exam_code'] ? '— ' . h($ae['exam_code']) : '' ?></div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="small text-muted">—</div>
      <?php endif; ?>
    </td>
    <td class="small"><?= h($r['dob']) ?></td>
    <td class="small">
      <?php if (!empty($r['plain_password'])): ?>
        <code data-testid="pwd-cell-<?= $r['id'] ?>"><?= h($r['plain_password']) ?></code>
      <?php else: ?>
        <span class="text-muted">—</span>
      <?php endif; ?>
    </td>
    <td><?= strtoupper(h($r['category'])) ?></td>
    <td class="small text-muted"><?= fmt_dt($r['created_at']) ?></td>
    <td class="text-end" style="white-space:nowrap">
      <button class="btn btn-sm btn-outline-secondary edit-btn"
      data-id="<?= $r['id'] ?>" data-name="<?= h($r['name']) ?>" data-email="<?= h($r['email']) ?>"
      data-roll="<?= h($r['roll_number']) ?>" data-dob="<?= h($r['dob']) ?>" data-category="<?= h($r['category']) ?>"
      data-photo="<?= h($r['photo_path'] ?? '') ?>" data-assigned-exams='<?= h(json_encode(assigned_exam_ids($r['id']))) ?>'
      data-testid="edit-btn-<?= $r['id'] ?>" data-perm-section="students" data-perm-action="edit"><i class="fas fa-pen"></i></button>
      <a href="<?= url('admin/admit-card.php?id=' . $r['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" data-testid="hallticket-btn-<?= $r['id'] ?>">Hall Ticket</a>
      <!-- Reset button removed per admin requirements -->
        <?php if ($smtpReady): ?>
        <form method="post" class="d-inline"><?= csrf_input() ?><input type="hidden" name="action" value="email"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-outline-navy" title="Email credentials" data-testid="email-btn-<?= $r['id'] ?>" data-perm-section="students" data-perm-action="view"><i class="fas fa-envelope"></i></button></form>
      <?php endif; ?>
      <form method="post" class="d-inline" onsubmit="event.preventDefault(); appConfirm('Delete this student?').then(ok=>{ if(ok) this.submit(); });"><?= csrf_input() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-outline-danger" data-testid="delete-btn-<?= $r['id'] ?>" data-perm-section="students" data-perm-action="delete"><i class="fas fa-trash"></i></button></form>
    </td>
  </tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">No students found</td></tr><?php endif; ?>
</tbody></table></div>

<!-- Add Modal -->
<div class="modal fade" id="addModal"><div class="modal-dialog modal-lg"><form method="post" enctype="multipart/form-data" class="modal-content" data-testid="add-student-form"><?= csrf_input() ?>
  <input type="hidden" name="action" value="add">
  <div class="modal-header">
    <div>
      <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add New Student</h5>
      <div class="small text-muted" style="margin-top:4px; font-size:0.85rem;">Create candidate profile and assign exams</div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
  </div>
  <div class="modal-body">
    <div class="row g-3">
      <div class="col-md-8">
        <div class="mb-3">
          <label class="form-label-xs"><i class="fas fa-user me-2" style="color:#0E2A47;"></i>Full Name</label>
          <input name="name" class="form-control" required data-testid="add-name-input" placeholder="Student full name">
        </div>
        <div class="mb-3">
          <label class="form-label-xs"><i class="fas fa-envelope me-2" style="color:#00A9E0;"></i>Email</label>
          <input name="email" type="email" class="form-control" required data-testid="add-email-input" placeholder="student@company.com">
        </div>
        <div class="row g-2">
          <div class="col-md-7">
            <label class="form-label-xs"><i class="fas fa-id-card me-2" style="color:#FF9933;"></i>Roll / Staff ID</label>
            <input name="roll" class="form-control" required placeholder="BEL1001 / 21EC045" data-testid="add-roll-input">
          </div>
          <div class="col-md-5">
            <label class="form-label-xs"><i class="fas fa-calendar me-2" style="color:#138808;"></i>Date of Birth</label>
            <input name="dob" type="date" class="form-control" required data-testid="add-dob-input">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label-xs"><i class="fas fa-tag me-2" style="color:#7c3aed;"></i>Category</label>
          <select name="category" class="form-select" data-testid="add-category-select">
            <option value="external">External Candidate</option>
            <option value="internal">Internal (BEL Staff)</option>
          </select>
        </div>
        <div class="mb-3 p-3 rounded" style="background: linear-gradient(135deg, rgba(0,169,224,0.04), rgba(19,136,8,0.04)); border:1px solid rgba(0,169,224,0.15);">
          <label class="form-label-xs" style="margin-bottom:12px;"><i class="fas fa-list-check me-2"></i>Assign Exams</label>
          <input type="text" class="form-control form-control-sm mb-2 exam-search" data-target="add-exams-list" placeholder="🔍 Search exam name or code">
          <div class="mb-2 d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="add-select-all" style="flex:1;"><i class="fas fa-check me-1"></i>Select all</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="add-clear-all" style="flex:1;"><i class="fas fa-times me-1"></i>Clear</button>
          </div>
          <div class="border rounded p-2" style="max-height:220px;overflow:auto; background:white;" id="add-exams-list">
            <?php foreach ($exams as $ex): $st = exam_status($ex); ?>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="exam_ids[]" value="<?= (int)$ex['id'] ?>" id="add_exam_<?= (int)$ex['id'] ?>">
                <label class="form-check-label small" for="add_exam_<?= (int)$ex['id'] ?>"><strong><?= h($ex['exam_name']) ?></strong> <?= $ex['exam_code'] ? '<code>' . h($ex['exam_code']) . '</code>' : '' ?> <span class="text-muted">(<?= $st ?>)</span></label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="p-3 rounded" style="background: linear-gradient(135deg, rgba(124,58,237,0.04), rgba(168,85,247,0.04)); border:1px solid rgba(124,58,237,0.15);">
          <label class="form-label-xs mb-3"><i class="fas fa-image me-2"></i>Candidate Photo</label>
          <div id="add-photo-preview" class="mb-3 text-center">
            <div style="width:100%;height:160px;border:2px dashed rgba(124,58,237,0.3);border-radius:8px;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:12px;background:rgba(255,255,255,0.5);">
              <div>
                <i class="fas fa-cloud-upload-alt mb-2" style="display:block;font-size:24px;color:#cbd5e1;"></i>
                Upload photo
              </div>
            </div>
          </div>
          <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm mb-2" data-testid="add-photo-input" id="add-photo-input">
          <small class="text-muted d-block" style="font-size:0.8rem; line-height:1.5;">JPG/PNG/WEBP<br>Max 2MB<br>Shown on Hall Ticket</small>
        </div>
      </div>
    </div>

    <div class="mt-3 p-3 rounded" style="background: linear-gradient(135deg, rgba(16,185,129,0.06), rgba(34,197,94,0.04)); border:1px solid rgba(16,185,129,0.15);">
      <div class="form-check">
        <input type="checkbox" name="send_mail" id="sendMail" class="form-check-input" <?= $smtpReady ? 'checked' : 'disabled' ?> data-testid="add-sendmail-chk">
        <label for="sendMail" class="form-check-label small fw-500"><i class="fas fa-envelope-circle-check me-2" style="color:#059669;"></i>Email credentials to candidate <?= !$smtpReady ? '<span class="text-warning small">(SMTP not configured)</span>' : '' ?></label>
      </div>
      <small class="text-muted mt-2 d-block">Password auto-generated. Student logs in with <strong>Roll + DOB + Password</strong>.</small>
    </div>
  </div>
  <div class="modal-footer">
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button>
    <button class="btn btn-navy" data-testid="add-submit-btn"><i class="fas fa-save me-2"></i>Create Candidate</button>
  </div>
</form></div></div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal"><div class="modal-dialog modal-lg"><form method="post" enctype="multipart/form-data" class="modal-content" data-testid="edit-student-form"><?= csrf_input() ?>
  <input type="hidden" name="action" value="update">
  <input type="hidden" name="id" id="e_id">
  <div class="modal-header"><h5 class="modal-title">Edit Student</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="row g-3">
      <div class="col-md-8">
        <label class="form-label-xs">Full Name</label><input name="name" id="e_name" class="form-control mb-2" required>
        <label class="form-label-xs">Email</label><input name="email" id="e_email" type="email" class="form-control mb-2" required>
        <div class="row g-2">
          <div class="col-md-7"><label class="form-label-xs">Roll / Staff ID</label><input name="roll" id="e_roll" class="form-control" required></div>
          <div class="col-md-5"><label class="form-label-xs">Date of Birth</label><input name="dob" id="e_dob" type="date" class="form-control" required></div>
        </div>
        <label class="form-label-xs mt-2">Category</label>
        <select name="category" id="e_cat" class="form-select"><option value="external">External Candidate</option><option value="internal">Internal (BEL Staff)</option></select>
        <label class="form-label-xs mt-2">Password (6 characters)</label>
        <div class="input-group mb-2">
          <input name="password" id="e_password" type="text" maxlength="6" class="form-control form-control-sm" placeholder="Leave blank to keep unchanged" pattern=".{6,6}" title="Exactly 6 characters">
          <button type="button" id="e_generate_pwd_btn" class="btn btn-outline-secondary">Generate</button>
        </div>
        <label class="form-label-xs mt-2">Assign Exams</label>
        <input type="text" class="form-control form-control-sm mb-2 exam-search" data-target="edit-exams-list" placeholder="Search exam name or code">
        <div class="mb-2">
          <button type="button" class="btn btn-sm btn-outline-secondary me-1" id="edit-select-all">Select all</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="edit-clear-all">Clear</button>
        </div>
        <div class="border rounded p-2" style="max-height:220px;overflow:auto" id="edit-exams-list">
          <?php foreach ($exams as $ex): $st = exam_status($ex); ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="exam_ids[]" value="<?= (int)$ex['id'] ?>" id="edit_exam_<?= (int)$ex['id'] ?>">
              <label class="form-check-label small" for="edit_exam_<?= (int)$ex['id'] ?>"><?= h($ex['exam_name']) ?> <?= $ex['exam_code'] ? '— ' . h($ex['exam_code']) : '' ?> <span class="text-muted">(<?= $st ?>)</span></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="col-md-4">
        <div id="e_photo_wrap" class="mb-2 text-center"></div>
        <label class="form-label-xs">Replace Photo <span class="text-muted">(optional)</span></label>
        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm">
      </div>
    </div>
  </div>
  <div class="modal-footer">
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
    <button class="btn btn-navy" data-testid="edit-submit-btn">Save Changes</button>
  </div>
</form></div></div>

<!-- Bulk Modal -->
<div class="modal fade" id="bulkModal"><div class="modal-dialog modal-lg"><form method="post" class="modal-content"><?= csrf_input() ?>
  <input type="hidden" name="action" value="bulk">
  <div class="modal-header"><h5 class="modal-title"><i class="fas fa-users me-2"></i>Bulk Upload Students (CSV)</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-4">
      <div class="small fw-bold text-muted mb-3" style="letter-spacing:0.05em; text-transform:uppercase;">📋 CSV Header Format</div>
      <div style="background: linear-gradient(135deg, rgba(14,42,71,0.04), rgba(0,169,224,0.04)); padding:12px 16px; border-radius:8px; border-left:4px solid #00A9E0;">
        <code style="color:#0E2A47; font-weight:600;">name,email,roll_number,dob,category,password</code>
      </div>
      <div class="small text-muted mt-2">Optional per-row exam codes: <code>exam_codes</code> (separate by comma/semicolon/pipe). DOB: <code>YYYY-MM-DD</code>. Password is optional.</div>
    </div>

    <label class="form-label-xs mb-2"><i class="fas fa-file-lines me-2"></i>CSV Content</label>
    <textarea name="csv" rows="10" class="form-control font-monospace small" placeholder="name,email,roll_number,dob,category,password
Ravi Kumar,ravi@bel.in,BEL1001,1998-04-12,internal" data-testid="bulk-csv-textarea"></textarea>

    <div class="mt-4 pt-3 border-top">
      <label class="form-label-xs mb-3"><i class="fas fa-list-check me-2"></i>Assign Exams to All Uploaded Students</label>
      <div class="mb-3">
        <input type="text" class="form-control form-control-sm" data-target="bulk-exams-list" placeholder="🔍 Search exam name or code">
      </div>
      <div class="mb-3 d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="bulk-select-all" style="flex:1;"><i class="fas fa-check me-1"></i>Select all</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="bulk-clear-all" style="flex:1;"><i class="fas fa-times me-1"></i>Clear</button>
      </div>
      <div class="border rounded p-3" style="max-height:240px;overflow:auto; background: linear-gradient(135deg, rgba(0,169,224,0.02), rgba(19,136,8,0.02));" id="bulk-exams-list" data-testid="bulk-exams-select">
        <?php foreach ($exams as $ex): $st = exam_status($ex); ?>
          <div class="form-check">
            <input class="form-check-input bulk-exam-chk" type="checkbox" name="bulk_exam_ids[]" value="<?= (int)$ex['id'] ?>" id="bulk_exam_<?= (int)$ex['id'] ?>">
            <label class="form-check-label small" for="bulk_exam_<?= (int)$ex['id'] ?>"><strong><?= h($ex['exam_name']) ?></strong> <?= $ex['exam_code'] ? '<code style="background:rgba(0,169,224,0.1);">' . h($ex['exam_code']) . '</code>' : '' ?> <span class="text-muted" style="font-size:0.8rem;">(<?= $st ?>)</span></label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cancel</button><button class="btn btn-navy" data-testid="bulk-submit-btn"><i class="fas fa-upload me-2"></i>Upload</button></div>
</form></div></div>

<script>
// Edit modal populate
document.querySelectorAll('.edit-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const d = btn.dataset;
    document.getElementById('e_id').value = d.id;
    document.getElementById('e_name').value = d.name;
    document.getElementById('e_email').value = d.email;
    document.getElementById('e_roll').value = d.roll;
    document.getElementById('e_dob').value = d.dob;
    document.getElementById('e_cat').value = d.category;
    // clear password field
    const pwdField = document.getElementById('e_password'); if (pwdField) pwdField.value = '';
    const wrap = document.getElementById('e_photo_wrap');
    wrap.innerHTML = d.photo
      ? `<img src="<?= url('') ?>${d.photo}" style="width:110px;height:140px;object-fit:cover;border:1px solid #cbd5e1">`
      : `<div style="width:110px;height:140px;margin:0 auto;border:1px dashed #cbd5e1;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:11px">No photo</div>`;
    // clear edit checkboxes then set according to data-assigned-exams
    try {
      const assigned = JSON.parse(d.assignedExams || '[]');
      document.querySelectorAll('#edit-exams-list input[type=checkbox]').forEach(cb => cb.checked = false);
      assigned.forEach(id => {
        const el = document.getElementById('edit_exam_' + id);
        if (el) el.checked = true;
      });
    } catch (e) { /* ignore */ }
    new bootstrap.Modal(document.getElementById('editModal')).show();
  });
});

// Password generator for edit modal
function genRandomPwd(len) {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
  let out = '';
  for (let i = 0; i < len; i++) out += chars[Math.floor(Math.random() * chars.length)];
  return out;
}
const genBtn = document.getElementById('e_generate_pwd_btn');
if (genBtn) {
  genBtn.addEventListener('click', () => {
    const f = document.getElementById('e_password');
    if (!f) return;
    f.value = genRandomPwd(6);
  });
}

// Add modal select all / clear
document.getElementById('add-select-all').addEventListener('click', () => document.querySelectorAll('#addModal input[name="exam_ids[]"]').forEach(cb => cb.checked = true));
document.getElementById('add-clear-all').addEventListener('click', () => document.querySelectorAll('#addModal input[name="exam_ids[]"]').forEach(cb => cb.checked = false));

// Edit modal select all / clear
document.getElementById('edit-select-all').addEventListener('click', () => document.querySelectorAll('#edit-exams-list input[type=checkbox]').forEach(cb => cb.checked = true));
document.getElementById('edit-clear-all').addEventListener('click', () => document.querySelectorAll('#edit-exams-list input[type=checkbox]').forEach(cb => cb.checked = false));

// Bulk modal select all / clear
document.getElementById('bulk-select-all').addEventListener('click', () => document.querySelectorAll('.bulk-exam-chk').forEach(cb => cb.checked = true));
document.getElementById('bulk-clear-all').addEventListener('click', () => document.querySelectorAll('.bulk-exam-chk').forEach(cb => cb.checked = false));

function filterExamList(input) {
  const targetId = input.dataset.target;
  const target = document.getElementById(targetId);
  if (!target) return;
  const term = input.value.trim().toLowerCase();
  target.querySelectorAll('.form-check').forEach(item => {
    const text = item.textContent.toLowerCase();
    item.style.display = text.includes(term) ? '' : 'none';
  });
}

document.querySelectorAll('.exam-search').forEach(input => {
  input.addEventListener('input', () => filterExamList(input));
});

// Photo preview for Add Student modal
document.getElementById('add-photo-input').addEventListener('change', function(e) {
  const file = e.target.files[0];
  const preview = document.getElementById('add-photo-preview');
  if (file && file.type.startsWith('image/')) {
    const reader = new FileReader();
    reader.onload = function(event) {
      preview.innerHTML = `<img src="${event.target.result}" style="width:100%;height:160px;object-fit:cover;border-radius:8px;border:2px solid rgba(124,58,237,0.3);">`;
    };
    reader.readAsDataURL(file);
  } else {
    preview.innerHTML = `<div style="width:100%;height:160px;border:2px dashed rgba(124,58,237,0.3);border-radius:8px;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:12px;background:rgba(255,255,255,0.5);"><div><i class="fas fa-cloud-upload-alt mb-2" style="display:block;font-size:24px;color:#cbd5e1;"></i>Upload photo</div></div>`;
  }
});

// Bulk delete handling
const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
const selectAll = document.getElementById('select-all-students');
function updateBulkButton() {
  const any = Array.from(document.querySelectorAll('.student-chk')).some(cb => cb.checked);
  bulkDeleteBtn.disabled = !any;
}
if (selectAll) selectAll.addEventListener('change', () => { document.querySelectorAll('.student-chk').forEach(cb => cb.checked = selectAll.checked); updateBulkButton(); });
document.querySelectorAll('.student-chk').forEach(cb => cb.addEventListener('change', updateBulkButton));
bulkDeleteBtn.addEventListener('click', () => {
  const ids = Array.from(document.querySelectorAll('.student-chk')).filter(c=>c.checked).map(c=>c.dataset.id);
  if (!ids.length) return;
  appConfirm('Delete ' + ids.length + ' selected students? This cannot be undone.').then(ok => {
    if (!ok) return;
    // build and submit form with CSRF
    const f = document.createElement('form'); f.method='post'; f.action='<?= url('admin/students.php') ?>';
    const c = document.createElement('input'); c.type='hidden'; c.name='_csrf'; c.value='<?= h(csrf()) ?>'; f.appendChild(c);
    const a = document.createElement('input'); a.type='hidden'; a.name='action'; a.value='bulk_delete'; f.appendChild(a);
    ids.forEach(id => { const i = document.createElement('input'); i.type='hidden'; i.name='ids[]'; i.value = id; f.appendChild(i); });
    document.body.appendChild(f); f.submit();
  });
});
</script>
<?php require __DIR__ . '/_shell_bottom.php'; ?>
