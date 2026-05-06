<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/lang.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $u = require_login('student');
    $assigned = assigned_exam_ids((int)$u['id']);

    if ($assigned) {
        $in = str_repeat('?,', count($assigned) - 1) . '?';
        $stmt = db()->prepare("SELECT e.*, (SELECT COUNT(*) FROM questions WHERE exam_id=e.id AND deleted_at IS NULL) AS qcount FROM exams e WHERE e.id IN ($in) ORDER BY e.start_time DESC");
        $stmt->execute($assigned);
        $exams = $stmt->fetchAll();
    } else {
        $exams = [];
    }

    foreach ($exams as &$e) {
        $e['status'] = exam_status($e);
        $used = db()->prepare('SELECT COUNT(*) FROM attempts WHERE user_id=? AND exam_id=? AND status="submitted"');
        $used->execute([$u['id'], $e['id']]);
        $e['attempts_used'] = (int)$used->fetchColumn();
        $e['attempts_left'] = max(0, (int)$e['max_attempts'] - $e['attempts_used']);
        $e['can_start'] = exam_can_start_now($e) && $e['attempts_left'] > 0 && (int)$e['qcount'] > 0;
        $e['start_time_label'] = fmt_dt($e['start_time']);
        $e['end_time_label'] = fmt_dt($e['end_time']);
        $e['qcount'] = (int)$e['qcount'];
        $e['duration_minutes'] = (int)$e['duration_minutes'];
        $e['join_window_minutes'] = max(0, (int)$e['join_window_minutes']);
        $e['join_window_start'] = $e['join_window_minutes'] > 0 ? date('Y-m-d H:i:s', strtotime($e['start_time']) - $e['join_window_minutes'] * 60) : null;
        $e['join_window_start_label'] = $e['join_window_start'] ? fmt_dt($e['join_window_start']) : null;
        $e['join_window_start_ts'] = $e['join_window_start'] ? strtotime($e['join_window_start']) * 1000 : null;
        $e['start_ts'] = strtotime($e['start_time']) * 1000;
        $e['end_ts'] = strtotime($e['end_time']) * 1000;
    }
    unset($e);

    echo json_encode([
        'success' => true,
        'exams' => $exams,
        'timestamp' => time(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
