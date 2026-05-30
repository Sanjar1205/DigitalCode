<?php
// VAQTINCHALIK DEBUG SAHIFASI - Test xatoligini topish uchun
// Foydalanish: http://localhost/codeacademy/student/test_debug.php?topic_id=1
// Tekshirgandan so'ng bu faylni o'chiring

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('student');

$topicId = (int)($_GET['topic_id'] ?? 1);
$studentId = $_SESSION['user_id'];

header('Content-Type: text/html; charset=utf-8');
echo "<style>body{font-family:monospace;padding:20px;background:#1a1a1a;color:#0f0;} h2{color:#fc0;} table{border-collapse:collapse;margin:10px 0;} td,th{border:1px solid #555;padding:8px;} .ok{color:#0f0;} .err{color:#f44;}</style>";

echo "<h2>🔍 Test Debug — Topic ID: $topicId</h2>";

// 1. Savollarni ko'rsatish
echo "<h2>1️⃣ Savollar va to'g'ri javoblar</h2>";
$questions = db()->fetchAll("SELECT * FROM questions WHERE topic_id = ?", [$topicId]);

foreach ($questions as $q) {
    echo "<div style='border:1px solid #555;padding:10px;margin:10px 0;'>";
    echo "<strong>Savol #{$q['id']} (turi: {$q['question_type']}, ball: {$q['points']}):</strong><br>";
    echo e($q['question_text']) . "<br><br>";
    
    $answers = db()->fetchAll("SELECT * FROM answers WHERE question_id = ? ORDER BY order_number", [$q['id']]);
    echo "<table><tr><th>Javob ID</th><th>Matn</th><th>is_correct</th><th>Type</th></tr>";
    foreach ($answers as $a) {
        $color = $a['is_correct'] ? 'ok' : 'err';
        $rawValue = var_export($a['is_correct'], true);
        $type = gettype($a['is_correct']);
        echo "<tr class='$color'><td>{$a['id']}</td><td>" . e($a['answer_text']) . "</td><td>$rawValue</td><td>$type</td></tr>";
    }
    echo "</table>";
    
    // To'g'ri javoblarni olish (test.php dagi kod kabi)
    $correctAnswers = array_column(db()->fetchAll(
        "SELECT id FROM answers WHERE question_id = ? AND is_correct = 1", [$q['id']]
    ), 'id');
    
    echo "<strong>To'g'ri javoblar IDsi (is_correct=1 bo'yicha):</strong> ";
    if (empty($correctAnswers)) {
        echo "<span class='err'>❌ BO'SH! Bu xato — to'g'ri javob topilmadi</span>";
    } else {
        echo "<span class='ok'>" . implode(', ', $correctAnswers) . "</span>";
    }
    echo "</div>";
}

// 2. So'ngi natija
echo "<h2>2️⃣ Sizning oxirgi urinishlaringiz</h2>";
$results = db()->fetchAll(
    "SELECT * FROM test_results WHERE student_id = ? AND topic_id = ? ORDER BY id DESC LIMIT 20",
    [$studentId, $topicId]
);

if (empty($results)) {
    echo "<p>Hali test ishlaganlik haqida yozuvlar yo'q.</p>";
} else {
    echo "<table><tr><th>ID</th><th>Q ID</th><th>Tanlanganlar</th><th>To'g'ri?</th><th>Olingan ball</th><th>Urinish</th></tr>";
    foreach ($results as $r) {
        $color = $r['is_correct'] ? 'ok' : 'err';
        echo "<tr class='$color'><td>{$r['id']}</td><td>{$r['question_id']}</td><td>{$r['selected_answers']}</td><td>" . ($r['is_correct'] ? '✓' : '✗') . "</td><td>{$r['points_earned']}</td><td>#{$r['attempt_number']}</td></tr>";
    }
    echo "</table>";
}

// 3. Test simulyatsiyasi
echo "<h2>3️⃣ Test simulyatsiyasi (to'g'ri javoblarni avtomatik tanlash)</h2>";
echo "<p>Quyida har savol uchun TO'G'RI javoblar IDsi va u qanday baholanadi:</p>";

$totalPoints = 0;
$earnedPoints = 0;

foreach ($questions as $q) {
    $totalPoints += $q['points'];
    $correctAnswers = array_column(db()->fetchAll(
        "SELECT id FROM answers WHERE question_id = ? AND is_correct = 1", [$q['id']]
    ), 'id');
    
    // Foydalanuvchi to'g'ri javobni tanladi deb taxmin qilamiz
    $userAnswerArr = $correctAnswers; // simulyatsiya
    $userAnswerArr = array_map('intval', $userAnswerArr);
    
    sort($userAnswerArr);
    sort($correctAnswers);
    $isCorrect = ($userAnswerArr == $correctAnswers);
    
    if ($isCorrect) $earnedPoints += $q['points'];
    
    $color = $isCorrect ? 'ok' : 'err';
    echo "<div class='$color'>Savol #{$q['id']}: To'g'ri javoblar [" . implode(',', $correctAnswers) . "], simulyatsiya: " . ($isCorrect ? '✓ TO\'G\'RI' : '✗ XATO!') . "</div>";
}

$scorePercent = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 100 : 0;
echo "<h3>Simulyatsiya natijasi: $earnedPoints / $totalPoints = " . round($scorePercent, 2) . "%</h3>";

if ($scorePercent < 100) {
    echo "<p class='err'>⚠️ DIQQAT: Hatto to'g'ri javoblar bilan ham 100% chiqmadi — bu kod xatoligi!</p>";
} else {
    echo "<p class='ok'>✅ Logika to'g'ri ishlayapti. Demak muammo balki POST'da javoblar yetib bormagandir.</p>";
}

echo "<hr><p><a href='test.php?topic_id=$topicId' style='color:#0cf'>← Testga qaytish</a></p>";
