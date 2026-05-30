<?php
/**
 * MAVZU OCHILISH DIAGNOSTIKASI
 * Foydalanish: http://localhost/codeacademy/student/check_unlock.php?subject_id=1
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('student');

$studentId = (int)$_SESSION['user_id'];
$subjectId = (int)($_GET['subject_id'] ?? 1);

echo "<style>body{font-family:monospace;padding:20px;background:#1a1a1a;color:#0f0;line-height:1.6;} h2{color:#fc0;border-bottom:1px solid #444;padding-bottom:5px;} .ok{color:#0f0;} .err{color:#f44;} .warn{color:#fa0;} table{border-collapse:collapse;width:100%;margin:10px 0;} td,th{border:1px solid #555;padding:6px 10px;text-align:left;} pre{background:#000;padding:10px;border-radius:5px;border:1px solid #333;}</style>";

echo "<h1>🔓 Mavzu qulflanish diagnostikasi</h1>";
echo "<p>Talaba ID: <strong>$studentId</strong> | Fan ID: <strong>$subjectId</strong></p>";

// Fan
$subject = db()->fetchOne("SELECT * FROM subjects WHERE id = ?", [$subjectId]);
echo "<h2>Fan: " . e($subject['name'] ?? '?') . "</h2>";

// Mavzular
$topics = db()->fetchAll(
    "SELECT * FROM topics WHERE subject_id = ? ORDER BY order_number",
    [$subjectId]
);

echo "<h2>📚 Mavzular ro'yxati va holat</h2>";
echo "<table>";
echo "<tr><th>#</th><th>ID</th><th>Mavzu</th><th>Video?</th><th>Savol?</th><th>Masala?</th><th>Ochilganmi?</th></tr>";

foreach ($topics as $t) {
    $hasVideo = !empty($t['video_url']) && getYoutubeId($t['video_url']);
    $qCount = (int)db()->fetchOne("SELECT COUNT(*) c FROM questions WHERE topic_id = ?", [$t['id']])['c'];
    $taskCount = (int)db()->fetchOne("SELECT COUNT(*) c FROM tasks WHERE topic_id = ?", [$t['id']])['c'];
    $unlocked = isTopicUnlocked($studentId, $t['id']);
    
    echo "<tr>";
    echo "<td>{$t['order_number']}</td>";
    echo "<td>{$t['id']}</td>";
    echo "<td>" . e($t['title']) . "</td>";
    echo "<td>" . ($hasVideo ? '<span class="ok">✓ Ha</span>' : '<span class="warn">✗ Yo\'q</span>') . "</td>";
    echo "<td>" . ($qCount > 0 ? "<span class='ok'>$qCount ta</span>" : "<span class='warn'>Yo\'q</span>") . "</td>";
    echo "<td>" . ($taskCount > 0 ? "<span class='ok'>$taskCount ta</span>" : "<span class='warn'>Yo\'q</span>") . "</td>";
    echo "<td>" . ($unlocked ? '<span class="ok">🔓 Ochiq</span>' : '<span class="err">🔒 Qulflangan</span>') . "</td>";
    echo "</tr>";
}
echo "</table>";

// student_progress yozuvlari
echo "<h2>📊 student_progress jadvali (sizning yozuvlaringiz)</h2>";
$progress = db()->fetchAll(
    "SELECT sp.*, t.title, t.order_number 
     FROM student_progress sp 
     JOIN topics t ON sp.topic_id = t.id
     WHERE sp.student_id = ? AND t.subject_id = ?
     ORDER BY t.order_number",
    [$studentId, $subjectId]
);

if (empty($progress)) {
    echo "<p class='err'>❌ Hech qanday progress yozuvi yo'q!</p>";
} else {
    echo "<table>";
    echo "<tr><th>#</th><th>Mavzu</th><th>content_read</th><th>video_watched</th><th>test_passed</th><th>task_completed</th><th>task_grade</th><th>unlocked</th></tr>";
    foreach ($progress as $p) {
        echo "<tr>";
        echo "<td>{$p['order_number']}</td>";
        echo "<td>" . e($p['title']) . "</td>";
        echo "<td class='" . ($p['content_read'] ? 'ok' : 'err') . "'>" . ($p['content_read'] ? '✓' : '✗') . " (" . $p['content_scroll_percent'] . "%)</td>";
        echo "<td class='" . ($p['video_watched'] ? 'ok' : 'err') . "'>" . ($p['video_watched'] ? '✓' : '✗') . " (" . $p['video_watch_percent'] . "%)</td>";
        echo "<td class='" . ($p['test_passed'] ? 'ok' : 'err') . "'>" . ($p['test_passed'] ? '✓' : '✗') . " (" . $p['test_score'] . "%)</td>";
        echo "<td class='" . ($p['task_completed'] ? 'ok' : 'err') . "'>" . ($p['task_completed'] ? '✓' : '✗') . "</td>";
        echo "<td>{$p['task_grade']}</td>";
        echo "<td>" . ($p['unlocked'] ? '✓' : '✗') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Diagnostika - 1-mavzu nima uchun keyingi mavzuni ochmayapti?
if (count($topics) >= 2) {
    $firstTopic = $topics[0];
    $secondTopic = $topics[1];
    
    echo "<h2>🔍 Tahlil: 2-mavzu nima uchun qulflangan?</h2>";
    
    $firstProgress = db()->fetchOne(
        "SELECT * FROM student_progress WHERE student_id = ? AND topic_id = ?",
        [$studentId, $firstTopic['id']]
    );
    
    if (!$firstProgress) {
        echo "<p class='err'>❌ 1-mavzu uchun progress yozuvi yo'q. Avval mavzuni o'qib chiqing.</p>";
    } else {
        $hasVideo = !empty($firstTopic['video_url']) && getYoutubeId($firstTopic['video_url']);
        $hasQuestions = (int)db()->fetchOne("SELECT COUNT(*) c FROM questions WHERE topic_id = ?", [$firstTopic['id']])['c'] > 0;
        $hasTasks = (int)db()->fetchOne("SELECT COUNT(*) c FROM tasks WHERE topic_id = ?", [$firstTopic['id']])['c'] > 0;
        
        echo "<p>1-mavzuda quyidagilar bor: ";
        echo $hasVideo ? "📹 Video " : "";
        echo $hasQuestions ? "❓ Savol " : "";
        echo $hasTasks ? "💻 Masala " : "";
        echo "</p>";
        
        echo "<table>";
        echo "<tr><th>Shart</th><th>Mavjudmi?</th><th>Bajarilganmi?</th><th>Natija</th></tr>";
        
        // 1. content_read
        echo "<tr>";
        echo "<td>Matn o'qish</td>";
        echo "<td>✓ Ha</td>";
        echo "<td>" . ($firstProgress['content_read'] ? '✓' : '✗') . "</td>";
        echo "<td class='" . ($firstProgress['content_read'] ? 'ok' : 'err') . "'>" . ($firstProgress['content_read'] ? 'OK' : 'BLOK!') . "</td>";
        echo "</tr>";
        
        // 2. video
        echo "<tr>";
        echo "<td>Video ko'rish</td>";
        echo "<td>" . ($hasVideo ? '✓ Ha' : '✗ Yo\'q (shart emas)') . "</td>";
        echo "<td>" . ($firstProgress['video_watched'] ? '✓' : '✗') . "</td>";
        if (!$hasVideo) {
            echo "<td class='ok'>O'TKAZILDI</td>";
        } else {
            echo "<td class='" . ($firstProgress['video_watched'] ? 'ok' : 'err') . "'>" . ($firstProgress['video_watched'] ? 'OK' : 'BLOK!') . "</td>";
        }
        echo "</tr>";
        
        // 3. test
        echo "<tr>";
        echo "<td>Test o'tish</td>";
        echo "<td>" . ($hasQuestions ? '✓ Ha' : '✗ Yo\'q (shart emas)') . "</td>";
        echo "<td>" . ($firstProgress['test_passed'] ? '✓' : '✗') . "</td>";
        if (!$hasQuestions) {
            echo "<td class='ok'>O'TKAZILDI</td>";
        } else {
            echo "<td class='" . ($firstProgress['test_passed'] ? 'ok' : 'err') . "'>" . ($firstProgress['test_passed'] ? 'OK' : 'BLOK!') . "</td>";
        }
        echo "</tr>";
        
        // 4. task
        echo "<tr>";
        echo "<td>Amaliy bajarish</td>";
        echo "<td>" . ($hasTasks ? '✓ Ha' : '✗ Yo\'q (shart emas)') . "</td>";
        echo "<td>" . ($firstProgress['task_completed'] ? '✓' : '✗') . "</td>";
        if (!$hasTasks) {
            echo "<td class='ok'>O'TKAZILDI</td>";
        } else {
            echo "<td class='" . ($firstProgress['task_completed'] ? 'ok' : 'err') . "'>" . ($firstProgress['task_completed'] ? 'OK' : 'BLOK!') . "</td>";
        }
        echo "</tr>";
        
        echo "</table>";
        
        $unlockedNow = isTopicUnlocked($studentId, $secondTopic['id']);
        echo "<h3>Yakuniy natija: " . ($unlockedNow ? '<span class="ok">🔓 2-mavzu OCHIQ bo\'lishi kerak</span>' : '<span class="err">🔒 2-mavzu QULFLANGAN</span>') . "</h3>";
    }
}

// task_submissions tekshirish
echo "<h2>💻 Sizning oxirgi task_submissions yozuvlaringiz</h2>";
$subs = db()->fetchAll(
    "SELECT ts.*, t.title as task_title, tp.title as topic_title, tp.id as topic_id
     FROM task_submissions ts
     JOIN tasks t ON ts.task_id = t.id
     JOIN topics tp ON t.topic_id = tp.id
     WHERE ts.student_id = ? AND tp.subject_id = ?
     ORDER BY ts.id DESC LIMIT 10",
    [$studentId, $subjectId]
);

if (empty($subs)) {
    echo "<p class='err'>❌ Hech qanday submission yo'q. Submit qilmadingizmi?</p>";
} else {
    echo "<table>";
    echo "<tr><th>ID</th><th>Mavzu</th><th>Status</th><th>Tests</th><th>%</th><th>Baho</th></tr>";
    foreach ($subs as $s) {
        echo "<tr>";
        echo "<td>{$s['id']}</td>";
        echo "<td>" . e($s['topic_title']) . " (id: {$s['topic_id']})</td>";
        echo "<td>{$s['status']}</td>";
        echo "<td>{$s['passed_tests']}/{$s['total_tests']}</td>";
        echo "<td>{$s['score_percent']}%</td>";
        echo "<td><strong>{$s['grade']}</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Tezkor tuzatish
if (isset($_GET['fix']) && count($topics) > 0) {
    echo "<h2>🛠️ Tezkor tuzatish</h2>";
    
    $firstTopic = $topics[0];
    
    // 1-mavzu uchun barcha shartlarni majburiy bajarilgan deb belgilash
    $existing = db()->fetchOne(
        "SELECT * FROM student_progress WHERE student_id = ? AND topic_id = ?",
        [$studentId, $firstTopic['id']]
    );
    
    $data = [
        'content_read' => 1,
        'content_scroll_percent' => 100,
        'video_watched' => 1,
        'video_watch_percent' => 100,
        'test_passed' => 1,
        'task_completed' => 1,
        'unlocked' => 1
    ];
    
    if ($existing) {
        db()->update('student_progress', $data, 
            'student_id = :sid AND topic_id = :tid',
            ['sid' => $studentId, 'tid' => $firstTopic['id']]);
    } else {
        db()->insert('student_progress', array_merge($data, [
            'student_id' => $studentId,
            'topic_id' => $firstTopic['id']
        ]));
    }
    
    echo "<p class='ok'>✅ 1-mavzu uchun barcha shartlar majburiy 'bajarilgan' deb belgilandi</p>";
    echo "<p><a href='?subject_id=$subjectId' style='color:#0cf;'>← Sahifani yangilash</a></p>";
} else {
    echo "<hr>";
    echo "<p><a href='?subject_id=$subjectId&fix=1' style='color:#fa0;font-size:1.1rem;'>🛠️ Tezkor tuzatish: 1-mavzu shartlarini majburiy bajarilgan deb belgilash</a></p>";
    echo "<p style='color:#888'>(Bu degani — 2-mavzu albatta ochiladi)</p>";
}

echo "<hr><p style='color:#666;'>Tekshirgandan so'ng bu faylni o'chirib tashlang!</p>";
