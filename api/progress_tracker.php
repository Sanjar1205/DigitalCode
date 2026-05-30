<?php
/**
 * Progress Tracker API
 * Talabaning matn o'qishi va video tomosha qilishini kuzatadi
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!Auth::isLoggedIn() || !Auth::hasRole('student')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Avtorizatsiya talab qilinadi']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Faqat POST']);
    exit;
}

$studentId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$topicId = (int)($input['topic_id'] ?? 0);

if (!$topicId) {
    echo json_encode(['success' => false, 'message' => 'topic_id talab qilinadi']);
    exit;
}

// Mavzu ruxsati tekshirish
$topic = db()->fetchOne(
    "SELECT t.* FROM topics t 
     JOIN subject_students ss ON t.subject_id = ss.subject_id 
     WHERE t.id = ? AND ss.student_id = ?", 
    [$topicId, $studentId]
);

if (!$topic) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Bu mavzuga ruxsat yo\'q']);
    exit;
}

// Mavjud progress yozuvi
$existing = db()->fetchOne(
    "SELECT * FROM student_progress WHERE student_id = ? AND topic_id = ?",
    [$studentId, $topicId]
);

$updateData = [];

// Scroll percent
if (isset($input['content_scroll_percent'])) {
    $newPercent = max(0, min(100, (int)$input['content_scroll_percent']));
    // Faqat yuqoriga ko'tarish (kamaytirmaslik)
    $oldPercent = (int)($existing['content_scroll_percent'] ?? 0);
    if ($newPercent > $oldPercent) {
        $updateData['content_scroll_percent'] = $newPercent;
        if ($newPercent >= 100) {
            $updateData['content_read'] = 1;
        }
    }
}

// Video percent
if (isset($input['video_watch_percent'])) {
    $newPercent = max(0, min(100, (int)$input['video_watch_percent']));
    $oldPercent = (int)($existing['video_watch_percent'] ?? 0);
    if ($newPercent > $oldPercent) {
        $updateData['video_watch_percent'] = $newPercent;
        if ($newPercent >= 90) {
            $updateData['video_watched'] = 1;
        }
    }
}

if (empty($updateData)) {
    echo json_encode(['success' => true, 'message' => 'Yangilash kerak emas']);
    exit;
}

try {
    if ($existing) {
        db()->update('student_progress', $updateData, 
            'student_id = :sid AND topic_id = :tid',
            ['sid' => $studentId, 'tid' => $topicId]);
    } else {
        db()->insert('student_progress', array_merge($updateData, [
            'student_id' => $studentId,
            'topic_id' => $topicId,
            'unlocked' => 1
        ]));
    }
    
    echo json_encode([
        'success' => true,
        'updated' => $updateData,
        'completion' => getTopicCompletionStatus($studentId, $topicId)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
