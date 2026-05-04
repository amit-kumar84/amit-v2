<?php
// api/dashboard-data.php — returns live dashboard data as JSON
require_once __DIR__ . '/../includes/helpers.php';
$me = current_user();
if (!$me || $me['role'] !== 'admin') json_out(['ok'=>false,'error'=>'unauthorized'], 401);

$pdo = db();

// Totals
$totals = [
  'students' => (int)$pdo->query('SELECT COUNT(*) FROM users WHERE role="student"')->fetchColumn(),
  'exams'    => (int)$pdo->query('SELECT COUNT(*) FROM exams')->fetchColumn(),
  'live'     => (int)$pdo->query('SELECT COUNT(*) FROM attempts WHERE status="in_progress"')->fetchColumn(),
  'today'    => (int)$pdo->query('SELECT COUNT(*) FROM attempts WHERE status="submitted" AND DATE(submitted_at)=CURDATE()')->fetchColumn(),
];

// Active exams
$active = $pdo->query('SELECT e.id, e.exam_name, e.end_time, (SELECT COUNT(*) FROM attempts WHERE exam_id=e.id AND status="in_progress") live FROM exams e WHERE NOW() BETWEEN e.start_time AND e.end_time')->fetchAll(PDO::FETCH_ASSOC);

// Violation hotspots
$hot = $pdo->query('SELECT u.name, u.roll_number, COUNT(v.id) c FROM violations v JOIN users u ON u.id=v.user_id GROUP BY v.user_id ORDER BY c DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);

// Average scores
$avg = $pdo->query('SELECT e.exam_name, AVG(a.score/a.total)*100 pct, COUNT(a.id) n FROM attempts a JOIN exams e ON e.id=a.exam_id WHERE a.status="submitted" AND a.total>0 GROUP BY a.exam_id ORDER BY MAX(a.submitted_at) DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);

// Currently writing
$live = $pdo->query('SELECT a.id, a.ends_at, u.name, u.roll_number, e.exam_name, (SELECT COUNT(*) FROM violations WHERE attempt_id=a.id) vcount FROM attempts a JOIN users u ON u.id=a.user_id JOIN exams e ON e.id=a.exam_id WHERE a.status="in_progress" LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);

json_out([
  'ok' => true,
  'timestamp' => date('H:i:s'),
  'totals' => $totals,
  'active' => $active,
  'violations' => $hot,
  'scores' => $avg,
  'writers' => $live
]);
