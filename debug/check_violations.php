<?php
require_once __DIR__ . '/../includes/helpers.php';

// Get latest violations
$recent = db()->query("
    SELECT v.id, v.attempt_id, v.event_type, v.description, v.created_at, a.user_id
    FROM violations v
    JOIN attempts a ON a.id = v.attempt_id
    ORDER BY v.id DESC
    LIMIT 20
")->fetchAll();

$count_by_attempt = db()->query("
    SELECT attempt_id, COUNT(*) as count
    FROM violations
    WHERE attempt_id IN (SELECT id FROM attempts ORDER BY id DESC LIMIT 5)
    GROUP BY attempt_id
    ORDER BY attempt_id DESC
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Violation Debug</title>
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body class="p-4">
    <div class="container">
        <h2>Recent Violations (Last 20)</h2>
        <table class="table table-sm table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Attempt</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($recent as $v): ?>
                <tr>
                    <td><?= $v['id'] ?></td>
                    <td><?= $v['attempt_id'] ?></td>
                    <td><span class="badge bg-danger"><?= $v['event_type'] ?></span></td>
                    <td><?= substr($v['description'], 0, 50) ?></td>
                    <td><?= $v['created_at'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2 class="mt-4">Violation Count by Recent Attempt</h2>
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Attempt ID</th>
                    <th>Violation Count</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($count_by_attempt as $c): ?>
                <tr>
                    <td><?= $c['attempt_id'] ?></td>
                    <td><strong><?= $c['count'] ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="alert alert-info mt-4">
            <p><strong>Instructions for Testing:</strong></p>
            <ol>
                <li>Note the current attempt ID from the exam page</li>
                <li>Trigger violations (tab switch, copy, devtools, etc.)</li>
                <li>Refresh this page to see updated violation count</li>
                <li>Compare badge count on exam page with database count</li>
            </ol>
        </div>
    </div>
</body>
</html>
