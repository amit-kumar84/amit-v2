<?php
// api/monitor-feed.php — returns live roster + stats for an exam
// Query: ?exam_id=<id>
require_once __DIR__ . '/../includes/helpers.php';
$me = current_user();
if (!$me || $me['role'] !== 'admin') json_out(['ok'=>false,'error'=>'unauthorized'], 401);
ensure_softdelete_and_permissions();

$eid = (int)($_GET['exam_id'] ?? 0);
$pdo = db();

$exam = $pdo->prepare('SELECT id, exam_name, created_by, start_time, end_time FROM exams WHERE id=? AND deleted_at IS NULL');
$exam->execute([$eid]);
$ex = $exam->fetch();
if (!$ex) json_out(['ok'=>false,'error'=>'exam not found'], 404);
if (empty($me['is_super']) && (int)$ex['created_by'] !== (int)$me['id']) {
    json_out(['ok'=>false,'error'=>'forbidden'], 403);
}
$state = exam_status($ex);

// Roster = students assigned to this exam
$stmt = $pdo->prepare(
  'SELECT u.id, u.name, u.roll_number, u.dob, u.photo_path,
          a.id AS attempt_id, a.status, a.started_at, a.submitted_at,
          (SELECT COUNT(*) FROM violations WHERE user_id=u.id AND attempt_id=a.id) AS violations,
          (SELECT COUNT(*) FROM attempts WHERE user_id=u.id AND exam_id=? AND status="submitted") AS attempt_count,
          (SELECT GROUP_CONCAT(id ORDER BY started_at DESC) FROM attempts WHERE user_id=u.id AND exam_id=? AND status="submitted") AS attempt_ids,
          (SELECT GROUP_CONCAT(CONCAT(score, "/", total, "|", DATE_FORMAT(started_at, "%Y-%m-%d %H:%i")) ORDER BY started_at DESC) FROM attempts WHERE user_id=u.id AND exam_id=? AND status="submitted") AS attempt_details
     FROM users u
     JOIN exam_assignments ea ON ea.user_id = u.id
     LEFT JOIN attempts a ON a.user_id = u.id AND a.exam_id = ?
                          AND a.id = (SELECT MAX(id) FROM attempts WHERE user_id=u.id AND exam_id=?)
    WHERE ea.exam_id = ? AND u.deleted_at IS NULL
    ORDER BY u.name');
$stmt->execute([$eid, $eid, $eid, $eid, $eid, $eid]);
$rows = $stmt->fetchAll();

$registered = count($rows);
$writing = 0; $submitted = 0; $absent = 0; $totalViol = 0;
$students = [];
if ($state !== 'upcoming') {
    foreach ($rows as $r) {
        $attempt_count = (int)$r['attempt_count'];
        $attempt_ids = $r['attempt_ids'] ? explode(',', $r['attempt_ids']) : [];
        $attempt_details = $r['attempt_details'] ? explode(',', $r['attempt_details']) : [];
        $status = 'absent'; $label = 'Absent';
        if ($state === 'closed') {
            if ($attempt_count > 0) {
                $status = 'submitted';
                $label = 'Done';
                $submitted++;
            } else {
                $absent++;
            }
        } else {
            if ($r['status'] === 'in_progress') { $status = 'writing';   $label = 'Present';   $writing++; }
            elseif ($r['status'] === 'submitted') { $status = 'submitted'; $label = 'Done'; $submitted++; }
            else { $absent++; }
        }
        $totalViol += (int)$r['violations'];
        $students[] = [
            'id'         => (int)$r['id'],
            'name'       => $r['name'],
            'roll'       => $r['roll_number'],
            'dob'        => $r['dob'],
            'photo_url'  => !empty($r['photo_path']) ? url($r['photo_path']) : null,
            'status'     => $status,
            'status_label' => $label,
            'violations' => (int)$r['violations'],
            'started_at' => $r['started_at'],
            'submitted_at' => $r['submitted_at'],
            'attempt_count' => $attempt_count,
            'attempt_ids' => array_map('intval', $attempt_ids),
            'attempt_details' => $attempt_details,
        ];
    }
}

json_out([
  'ok'                => true,
  'exam_id'           => $eid,
  'exam_name'         => $ex['exam_name'],
  'exam_state'        => $state,
  'registered'        => $registered,
  'writing'           => $state === 'upcoming' ? 0 : $writing,
  'submitted'         => $state === 'upcoming' ? 0 : $submitted,
  'absent'            => $state === 'upcoming' ? 0 : $absent,
  'total_violations'  => $state === 'upcoming' ? 0 : $totalViol,
  'students'          => $students,
  'countdown_ms'      => $state === 'upcoming' ? max(0, strtotime($ex['start_time']) * 1000 - time() * 1000) : 0,
  'server_ts'         => time() * 1000,
]);
