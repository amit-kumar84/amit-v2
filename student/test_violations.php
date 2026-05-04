<?php
require_once __DIR__ . '/../includes/helpers.php';

// Get violation breakdown for attempt 27
$violations = db()->query("
    SELECT event_type, COUNT(*) as count
    FROM violations
    WHERE attempt_id = 27
    GROUP BY event_type
    ORDER BY count DESC
")->fetchAll();

$sample = db()->query("
    SELECT id, event_type, description, event_time
    FROM violations
    WHERE attempt_id = 27
    ORDER BY id DESC
    LIMIT 20
")->fetchAll();

echo "=== VIOLATION BREAKDOWN FOR ATTEMPT 27 ===\n";
foreach ($violations as $v) {
    echo "{$v['event_type']}: {$v['count']} violations\n";
}

echo "\n=== RECENT VIOLATIONS (LAST 20) ===\n";
foreach ($sample as $s) {
    echo "[{$s['id']}] {$s['event_type']}: {$s['description']}\n";
}

$total = db()->query("SELECT COUNT(*) as total FROM violations WHERE attempt_id = 27")->fetch();
echo "\n=== TOTAL: {$total['total']} violations ===\n";
