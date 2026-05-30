<?php
// QUICK DIAGNOSTIC - Sizning oxirgi urinishingizdagi xatoni ko'rsatadi
// http://localhost/codeacademy/student/test_check.php?topic_id=1

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('student');

$topicId = (int)($_GET['topic_id'] ?? 1);
$studentId = $_SESSION['user_id'];

echo "<style>body{font-family:monospace;padding:20px;background:#1a1a1a;color:#0f0;} h2{color:#fc0;} table{border-collapse:collapse;margin:10px 0;width:100%;} td,th{border:1px solid #555;padding:8px;text-align:left;} .ok{color:#0f0;background:#0a3a0a;} .err{color:#f44;background:#3a0a0a;} .header{background:#222;}</style>";

echo "<h2>🔍 Oxirgi urinish tahlili</h2>";

// Oxirgi urinish raqami
$lastAttempt = db()->fetchOne(
    "SELECT MAX(attempt_number) as m FROM test_results WHERE student_id = ? AND topic_id = ?",
    [$studentId, $topicId]
)['m'];

if (!$lastAttempt) {
    echo "<p class='err'>Hech qanday urinish topilmadi.</p>";
    exit;
}

echo "<p>Oxirgi urinish: <strong>#$lastAttempt</strong></p>";

// Oxirgi urinishdagi natijalar
$results = db()->fetchAll(
    "SELECT tr.*, q.question_text, q.question_type, q.points 
     FROM test_results tr 
     JOIN questions q ON tr.question_id = q.id 
     WHERE tr.student_id = ? AND tr.topic_id = ? AND tr.attempt_number = ?
     ORDER BY tr.question_id",
    [$studentId, $topicId, $lastAttempt]
);

echo "<table>";
echo "<tr class='header'><th>Savol</th><th>Turi</th><th>Sizning tanlovingiz (ID)</th><th>Aslida to'g'ri (ID)</th><th>Mos?</th><th>Ball</th></tr>";

$totalEarned = 0;
$totalPoints = 0;

foreach ($results as $r) {
    $totalPoints += $r['points'];
    $totalEarned += $r['points_earned'];
    
    $userIds = $r['selected_answers'] ? explode(',', $r['selected_answers']) : [];
    $userIds = array_map('intval', array_filter($userIds));
    sort($userIds);
    
    $correctIds = array_column(db()->fetchAll(
        "SELECT id FROM answers WHERE question_id = ? AND is_correct = 1", [$r['question_id']]
    ), 'id');
    $correctIds = array_map('intval', $correctIds);
    sort($correctIds);
    
    // Javob matnlarini olish
    $userTexts = [];
    foreach ($userIds as $aid) {
        $a = db()->fetchOne("SELECT answer_text FROM answers WHERE id = ?", [$aid]);
        if ($a) $userTexts[] = $a['answer_text'] . " (#$aid)";
    }
    
    $correctTexts = [];
    foreach ($correctIds as $aid) {
        $a = db()->fetchOne("SELECT answer_text FROM answers WHERE id = ?", [$aid]);
        if ($a) $correctTexts[] = $a['answer_text'] . " (#$aid)";
    }
    
    $match = ($userIds === $correctIds);
    $class = $match ? 'ok' : 'err';
    
    echo "<tr class='$class'>";
    echo "<td>" . htmlspecialchars(substr($r['question_text'], 0, 50)) . "</td>";
    echo "<td>{$r['question_type']}</td>";
    echo "<td>" . htmlspecialchars(implode(', ', $userTexts) ?: '(bo\'sh)') . "<br><small>RAW: '{$r['selected_answers']}'</small></td>";
    echo "<td>" . htmlspecialchars(implode(', ', $correctTexts)) . "</td>";
    echo "<td>" . ($match ? '✓' : '✗') . "<br><small>" . ($r['is_correct'] ? 'DB:correct' : 'DB:wrong') . "</small></td>";
    echo "<td>{$r['points_earned']}/{$r['points']}</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h3>Yakuniy: $totalEarned / $totalPoints = " . round($totalPoints > 0 ? ($totalEarned/$totalPoints)*100 : 0, 1) . "%</h3>";

echo "<hr><p><a href='test.php?topic_id=$topicId' style='color:#0cf'>← Yangi urinish</a></p>";
