<?php
/**
 * SUBMISSION TEKSHIRUV SAHIFASI
 * Foydalanish: http://localhost/codeacademy/student/check_submissions.php
 * Tekshirgandan so'ng bu faylni o'chiring
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireLogin();

$studentId = (int)$_SESSION['user_id'];

echo "<style>body{font-family:monospace;padding:20px;background:#1a1a1a;color:#0f0;line-height:1.6;} h2{color:#fc0;border-bottom:1px solid #444;padding-bottom:5px;} .ok{color:#0f0;} .err{color:#f44;} .warn{color:#fa0;} table{border-collapse:collapse;width:100%;} td,th{border:1px solid #555;padding:6px 8px;text-align:left;} pre{background:#000;padding:10px;border-radius:5px;border:1px solid #333;white-space:pre-wrap;}</style>";

echo "<h1>🔍 Task Submissions tekshiruvi</h1>";
echo "<p>Sizning ID: <strong>$studentId</strong> (" . e($_SESSION['full_name'] ?? '?') . ")</p>";

// 1. Jadval tuzilishini tekshirish
echo "<h2>1️⃣ task_submissions jadval tuzilishi</h2>";
try {
    $columns = db()->fetchAll("SHOW COLUMNS FROM task_submissions");
    echo "<table><tr><th>Ustun</th><th>Tip</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr><td><strong>{$col['Field']}</strong></td><td>{$col['Type']}</td><td>" . e($col['Default'] ?? 'NULL') . "</td></tr>";
    }
    echo "</table>";
    
    // Kerakli ustunlar borligini tekshirish
    $colNames = array_column($columns, 'Field');
    $required = ['student_id', 'task_id', 'code', 'language', 'status', 'passed_tests', 'total_tests', 'score_percent', 'grade'];
    $missing = array_diff($required, $colNames);
    
    if (empty($missing)) {
        echo "<p class='ok'>✅ Hamma kerakli ustunlar mavjud</p>";
    } else {
        echo "<p class='err'>❌ Yetishmaydigan ustunlar: " . implode(', ', $missing) . "</p>";
    }
} catch (Exception $e) {
    echo "<p class='err'>❌ Xato: " . e($e->getMessage()) . "</p>";
}

// 2. Sizning yozuvlaringiz
echo "<h2>2️⃣ Sizning task_submissions yozuvlaringiz</h2>";
$submissions = db()->fetchAll(
    "SELECT * FROM task_submissions WHERE student_id = ? ORDER BY id DESC LIMIT 20",
    [$studentId]
);

if (empty($submissions)) {
    echo "<p class='err'>❌ Hech qanday yozuv yo'q!</p>";
    echo "<p>Bu degani:</p>";
    echo "<ul>";
    echo "<li>Submit tugmasi bosilgan, lekin SQL Insert xato bilan tugagan</li>";
    echo "<li>YOKI faqat Run bosilgan (Run saqlamaydi!)</li>";
    echo "</ul>";
} else {
    echo "<p class='ok'>✅ <strong>" . count($submissions) . "</strong> ta yozuv topildi</p>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Task</th><th>Til</th><th>Status</th><th>Passed/Total</th><th>%</th><th>Baho</th><th>Vaqt</th></tr>";
    foreach ($submissions as $s) {
        echo "<tr>";
        echo "<td>{$s['id']}</td>";
        echo "<td>{$s['task_id']}</td>";
        echo "<td>{$s['language']}</td>";
        echo "<td>{$s['status']}</td>";
        echo "<td>{$s['passed_tests']}/{$s['total_tests']}</td>";
        echo "<td>{$s['score_percent']}%</td>";
        echo "<td><strong>{$s['grade']}</strong></td>";
        echo "<td><small>" . formatDate($s['submitted_at']) . "</small></td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 3. Grades jadvalini ham tekshirish
echo "<h2>3️⃣ Sizning grades yozuvlaringiz (amaliy)</h2>";
$grades = db()->fetchAll(
    "SELECT g.*, s.name as subject_name, t.title as topic_title 
     FROM grades g 
     LEFT JOIN subjects s ON g.subject_id = s.id 
     LEFT JOIN topics t ON g.topic_id = t.id 
     WHERE g.student_id = ? AND g.type = 'task'
     ORDER BY g.id DESC", 
    [$studentId]
);

if (empty($grades)) {
    echo "<p class='warn'>⚠️ Amaliy uchun hech qanday baho yozuvi yo'q</p>";
} else {
    echo "<p class='ok'>✅ <strong>" . count($grades) . "</strong> ta amaliy baho topildi</p>";
    echo "<table>";
    echo "<tr><th>Fan</th><th>Mavzu</th><th>Baho</th><th>Izoh</th><th>Vaqt</th></tr>";
    foreach ($grades as $g) {
        echo "<tr><td>" . e($g['subject_name']) . "</td><td>" . e($g['topic_title']) . "</td><td><strong>{$g['grade']}</strong></td><td><small>" . e($g['comment']) . "</small></td><td><small>" . formatDate($g['created_at']) . "</small></td></tr>";
    }
    echo "</table>";
}

// 4. PHP error log oxirgi 50 qator
echo "<h2>4️⃣ PHP error logidan oxirgi yozuvlar</h2>";
$errorLog = ini_get('error_log');
if (!$errorLog) {
    // XAMPP default
    $errorLog = 'C:\\xampp\\php\\logs\\php_error_log';
}
if (file_exists($errorLog)) {
    $content = file_get_contents($errorLog);
    $lines = array_slice(explode("\n", $content), -30);
    echo "<small>Fayl: <code>" . e($errorLog) . "</code></small>";
    echo "<pre>" . htmlspecialchars(implode("\n", $lines)) . "</pre>";
} else {
    echo "<p class='warn'>Log fayli topilmadi: $errorLog</p>";
}

// 5. Tezkor test yozuvi
if (isset($_GET['insert_test'])) {
    echo "<h2>5️⃣ Test yozuv qo'shish</h2>";
    try {
        // Birinchi mavjud taskni topish
        $task = db()->fetchOne("SELECT id, topic_id FROM tasks LIMIT 1");
        if ($task) {
            $insertId = db()->insert('task_submissions', [
                'student_id' => $studentId,
                'task_id' => $task['id'],
                'code' => '// test code',
                'language' => 'cpp',
                'status' => 'accepted',
                'passed_tests' => 5,
                'total_tests' => 5,
                'score_percent' => 100,
                'grade' => 5
            ]);
            echo "<p class='ok'>✅ Test yozuv qo'shildi (ID: $insertId)</p>";
            echo "<p>Endi sahifani yangilang — yozuv ko'rinishi kerak</p>";
        } else {
            echo "<p class='err'>Hech qanday task topilmadi</p>";
        }
    } catch (Exception $e) {
        echo "<p class='err'>❌ Xato: " . e($e->getMessage()) . "</p>";
    }
} else {
    echo "<p><a href='?insert_test=1' style='color:#0cf;'>🧪 Tezkor test yozuv qo'shish (DB ishlayotganini tasdiqlash uchun)</a></p>";
}

echo "<hr><p style='color:#666;'>Tekshirgandan so'ng bu faylni o'chirib tashlang!</p>";
