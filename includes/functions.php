<?php
/**
 * Umumiy yordamchi funksiyalar
 */

/**
 * XSS himoyasi - HTML escape
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Avval flash xabar saqlash
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Flash xabarni olish va o'chirish
 */
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Sana formati (O'zbek)
 */
function formatDate($date, $withTime = false) {
    if (empty($date)) return '-';
    $months = ['Yanvar', 'Fevral', 'Mart', 'Aprel', 'May', 'Iyun',
               'Iyul', 'Avgust', 'Sentabr', 'Oktabr', 'Noyabr', 'Dekabr'];
    $time = strtotime($date);
    $day = date('d', $time);
    $month = $months[(int)date('n', $time) - 1];
    $year = date('Y', $time);
    $result = "$day $month $year";
    if ($withTime) {
        $result .= ', ' . date('H:i', $time);
    }
    return $result;
}

/**
 * Talaba mavzu cheklov tizimini tekshirish
 * 
 * Mavzu OCHILADI agar:
 * 1. Avvalgi mavzu to'liq yakunlangan bo'lsa
 * 2. Yoki bu birinchi mavzu bo'lsa
 */
/**
 * Mavzu ochilganligini tekshirish
 * 
 * Mavzu OCHILADI agar:
 * 1. Bu birinchi mavzu bo'lsa
 * 2. Yoki avvalgi mavzuning **mavjud** bo'lgan barcha shartlari bajarilgan bo'lsa
 *    (video yo'q bo'lsa - video sharti shart emas; savol yo'q bo'lsa - test shart emas; va h.k.)
 */
function isTopicUnlocked($studentId, $topicId) {
    $topic = db()->fetchOne(
        "SELECT subject_id, order_number FROM topics WHERE id = ?",
        [$topicId]
    );
    
    if (!$topic) return false;
    
    // Birinchi mavzu doim ochiq
    if ($topic['order_number'] == 1) {
        return true;
    }
    
    // Avvalgi mavzuni topish
    $previousTopic = db()->fetchOne(
        "SELECT * FROM topics WHERE subject_id = ? AND order_number = ? LIMIT 1",
        [$topic['subject_id'], $topic['order_number'] - 1]
    );
    
    if (!$previousTopic) return true;
    
    $previousId = $previousTopic['id'];
    
    // Avvalgi mavzu progressini tekshirish
    $progress = db()->fetchOne(
        "SELECT * FROM student_progress WHERE student_id = ? AND topic_id = ?",
        [$studentId, $previousId]
    );
    
    if (!$progress) return false;
    
    // Matn doim shart
    if (!$progress['content_read']) return false;
    
    // Video bor bo'lsa, ko'rilgan bo'lishi shart
    $videoUrl = $previousTopic['video_url'] ?? '';
    $hasVideo = !empty($videoUrl) && getYoutubeId($videoUrl);
    if ($hasVideo && !$progress['video_watched']) return false;
    
    // Savollar bor bo'lsa, test o'tilgan bo'lishi shart
    $hasQuestions = (int)db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM questions WHERE topic_id = ?", [$previousId]
    )['cnt'] > 0;
    if ($hasQuestions && !$progress['test_passed']) return false;
    
    // Amaliy topshiriqlar bor bo'lsa, bajarilishi shart
    $hasTasks = (int)db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM tasks WHERE topic_id = ?", [$previousId]
    )['cnt'] > 0;
    if ($hasTasks && !$progress['task_completed']) return false;
    
    return true;
}

/**
 * Talaba mavzudagi shartlardan qanchasini bajarganligini hisoblash
 * Faqat MAVJUD elementlar (video, savol, masala) hisobga olinadi
 */
function getTopicCompletionStatus($studentId, $topicId) {
    // Mavzu elementlarini tekshirish
    $topic = db()->fetchOne("SELECT video_url FROM topics WHERE id = ?", [$topicId]);
    $videoUrl = $topic['video_url'] ?? '';
    $hasVideo = !empty($videoUrl) && getYoutubeId($videoUrl);
    
    $hasQuestions = (int)(db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM questions WHERE topic_id = ?", [$topicId]
    )['cnt'] ?? 0) > 0;
    
    $hasTasks = (int)(db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM tasks WHERE topic_id = ?", [$topicId]
    )['cnt'] ?? 0) > 0;
    
    $progress = db()->fetchOne(
        "SELECT * FROM student_progress WHERE student_id = ? AND topic_id = ?",
        [$studentId, $topicId]
    );
    
    if (!$progress) {
        return [
            'content_read' => false,
            'video_watched' => false,
            'test_passed' => false,
            'task_completed' => false,
            'has_video' => $hasVideo,
            'has_questions' => $hasQuestions,
            'has_tasks' => $hasTasks,
            'percent' => 0,
            'is_complete' => false,
            'content_scroll_percent' => 0,
            'video_watch_percent' => 0,
            'test_score' => 0,
            'task_grade' => 0
        ];
    }
    
    // Real shartlar soni va bajarilganlar
    $required = 1; // Matn doim shart
    $completed = $progress['content_read'] ? 1 : 0;
    
    if ($hasVideo) {
        $required++;
        if ($progress['video_watched']) $completed++;
    }
    
    if ($hasQuestions) {
        $required++;
        if ($progress['test_passed']) $completed++;
    }
    
    if ($hasTasks) {
        $required++;
        if ($progress['task_completed']) $completed++;
    }
    
    return [
        'content_read' => (bool)$progress['content_read'],
        'video_watched' => (bool)$progress['video_watched'],
        'test_passed' => (bool)$progress['test_passed'],
        'task_completed' => (bool)$progress['task_completed'],
        'has_video' => $hasVideo,
        'has_questions' => $hasQuestions,
        'has_tasks' => $hasTasks,
        'content_scroll_percent' => (int)$progress['content_scroll_percent'],
        'video_watch_percent' => (int)$progress['video_watch_percent'],
        'test_score' => (float)$progress['test_score'],
        'task_grade' => (int)$progress['task_grade'],
        'percent' => $required > 0 ? round(($completed / $required) * 100) : 100,
        'is_complete' => $completed === $required
    ];
}

/**
 * Kod natijasini bahoga aylantirish (4 darajali)
 * 90-100% → 5 (a'lo)
 * 70-89%  → 4 (yaxshi)
 * 50-69%  → 3 (qoniqarli)
 * 0-49%   → 2 (qoniqarsiz)
 */
function calculateGrade($scorePercent) {
    if ($scorePercent >= 90) return 5;
    if ($scorePercent >= 70) return 4;
    if ($scorePercent >= 50) return 3;
    return 2;
}

/**
 * Bahoga muvofiq matn
 */
function gradeText($grade) {
    $grades = [
        5 => "A'lo (5)",
        4 => "Yaxshi (4)",
        3 => "Qoniqarli (3)",
        2 => "Qoniqarsiz (2)",
        0 => "Topshirilmagan"
    ];
    return $grades[$grade] ?? "-";
}

/**
 * Bahoga muvofiq rang (Bootstrap)
 */
function gradeColor($grade) {
    return [5 => 'success', 4 => 'primary', 3 => 'warning', 2 => 'danger', 0 => 'secondary'][$grade] ?? 'secondary';
}

/**
 * Fayl yuklash
 */
function uploadFile($file, $subDir = '', $allowedTypes = []) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Fayl yuklashda xatolik'];
    }
    
    if (empty($allowedTypes)) {
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'mp4', 'doc', 'docx'];
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes)) {
        return ['success' => false, 'message' => 'Bunday fayl turi ruxsat etilmagan'];
    }
    
    if ($file['size'] > 50 * 1024 * 1024) { // 50 MB
        return ['success' => false, 'message' => 'Fayl hajmi 50MB dan oshmasligi kerak'];
    }
    
    $uploadPath = UPLOAD_DIR . $subDir;
    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0777, true);
    }
    
    $newName = uniqid() . '_' . time() . '.' . $ext;
    $fullPath = $uploadPath . $newName;
    
    if (move_uploaded_file($file['tmp_name'], $fullPath)) {
        return [
            'success' => true,
            'file_name' => $newName,
            'file_path' => 'uploads/' . $subDir . $newName,
            'file_url' => UPLOAD_URL . $subDir . $newName,
            'file_size' => $file['size'],
            'extension' => $ext
        ];
    }
    
    return ['success' => false, 'message' => 'Fayl saqlashda xatolik'];
}

/**
 * YouTube ID ni olish
 */
function getYoutubeId($url) {
    preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches);
    return $matches[1] ?? null;
}

/**
 * Talaba uchun fan progressini hisoblash
 */
function getSubjectProgress($studentId, $subjectId) {
    $totalTopics = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM topics WHERE subject_id = ? AND status = 'published'",
        [$subjectId]
    )['cnt'];
    
    if ($totalTopics == 0) return 0;
    
    $completed = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM student_progress sp 
         JOIN topics t ON sp.topic_id = t.id 
         WHERE sp.student_id = ? AND t.subject_id = ? 
         AND sp.content_read = 1 AND sp.video_watched = 1 
         AND sp.test_passed = 1 AND sp.task_completed = 1",
        [$studentId, $subjectId]
    )['cnt'];
    
    return round(($completed / $totalTopics) * 100);
}

/**
 * Tizim sozlamasini olish
 */
function getSetting($key, $default = null) {
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key];
    
    $row = db()->fetchOne("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
    $value = $row ? $row['setting_value'] : $default;
    $cache[$key] = $value;
    return $value;
}

/**
 * Tizim sozlamasini saqlash
 */
function setSetting($key, $value, $group = 'general') {
    $existing = db()->fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$key]);
    if ($existing) {
        db()->update('settings', ['setting_value' => $value], 'setting_key = :key', ['key' => $key]);
    } else {
        db()->insert('settings', ['setting_key' => $key, 'setting_value' => $value, 'setting_group' => $group]);
    }
}

/**
 * Sahifa nomini formatlash
 */
function pageTitle($title = '') {
    return $title ? "$title — " . SITE_NAME : SITE_NAME;
}

/**
 * O'rtacha bahoni hisoblash
 */
function getAverageGrade($studentId, $subjectId = null) {
    $sql = "SELECT AVG(grade) as avg FROM grades WHERE student_id = ?";
    $params = [$studentId];
    if ($subjectId) {
        $sql .= " AND subject_id = ?";
        $params[] = $subjectId;
    }
    $result = db()->fetchOne($sql, $params);
    return $result['avg'] ? round($result['avg'], 2) : 0;
}
