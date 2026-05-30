<?php
$pageTitle = 'Talaba batafsil';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('teacher');

$teacherId = $_SESSION['user_id'];
$studentId = (int)($_GET['student_id'] ?? 0);
$subjectId = (int)($_GET['subject_id'] ?? 0);

// Tekshirish
$check = db()->fetchOne(
    "SELECT u.*, s.name as subject_name 
     FROM users u, subjects s 
     WHERE u.id = ? AND s.id = ?
     AND EXISTS (SELECT 1 FROM subject_teachers WHERE teacher_id = ? AND subject_id = ?)
     AND EXISTS (SELECT 1 FROM subject_students WHERE student_id = ? AND subject_id = ?)",
    [$studentId, $subjectId, $teacherId, $subjectId, $studentId, $subjectId]
);

if (!$check) {
    setFlash('danger', 'Ruxsat yo\'q');
    redirect(SITE_URL . '/teacher/monitoring.php');
}

// Talabaning bu fan bo'yicha mavzular progressi
$topics = db()->fetchAll(
    "SELECT t.*, sp.* 
     FROM topics t 
     LEFT JOIN student_progress sp ON sp.topic_id = t.id AND sp.student_id = ?
     WHERE t.subject_id = ? AND t.status = 'published'
     ORDER BY t.order_number", [$studentId, $subjectId]
);

// Bahoyalar
$grades = db()->fetchAll(
    "SELECT g.*, t.title as topic_title 
     FROM grades g 
     LEFT JOIN topics t ON g.topic_id = t.id 
     WHERE g.student_id = ? AND g.subject_id = ? 
     ORDER BY g.created_at DESC", [$studentId, $subjectId]
);

// Topshiriqlar
$submissions = db()->fetchAll(
    "SELECT ts.*, t.title as task_title, tp.title as topic_title 
     FROM task_submissions ts 
     JOIN tasks t ON ts.task_id = t.id 
     JOIN topics tp ON t.topic_id = tp.id 
     WHERE ts.student_id = ? AND tp.subject_id = ? 
     ORDER BY ts.submitted_at DESC LIMIT 20",
    [$studentId, $subjectId]
);

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 1rem;">
    <a href="<?= SITE_URL ?>/teacher/monitoring.php?subject_id=<?= $subjectId ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Orqaga
    </a>
</div>

<!-- Talaba ma'lumotlari -->
<div class="card mb-3">
    <div class="card-body">
        <div style="display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap;">
            <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: 700;">
                <?= strtoupper(substr($check['full_name'], 0, 1)) ?>
            </div>
            <div style="flex: 1;">
                <h2 style="margin: 0 0 0.25rem;"><?= e($check['full_name']) ?></h2>
                <div style="color: var(--text-tertiary);">
                    @<?= e($check['username']) ?> · <?= e($check['email']) ?>
                </div>
                <div style="color: var(--text-secondary); margin-top: 0.5rem;">
                    <i class="fas fa-book"></i> <?= e($check['subject_name']) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mavzular bo'yicha progress -->
<div class="card mb-3">
    <div class="card-header">
        <h3>Mavzular progressi</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php foreach ($topics as $t): 
            $contentRead = (int)($t['content_read'] ?? 0);
            $videoWatched = (int)($t['video_watched'] ?? 0);
            $testPassed = (int)($t['test_passed'] ?? 0);
            $taskCompleted = (int)($t['task_completed'] ?? 0);
            $completed = $contentRead + $videoWatched + $testPassed + $taskCompleted;
            $percent = ($completed / 4) * 100;
        ?>
            <div style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--border);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <strong><?= e($t['title']) ?></strong>
                    <span class="badge badge-<?= $completed === 4 ? 'success' : ($completed > 0 ? 'warning' : 'secondary') ?>">
                        <?= $completed ?>/4 bosqich
                    </span>
                </div>
                <div style="display: flex; gap: 1rem; font-size: 0.85rem; color: var(--text-secondary);">
                    <span><i class="fas fa-<?= $contentRead ? 'check-circle' : 'times-circle' ?>" style="color: <?= $contentRead ? 'var(--success)' : 'var(--text-tertiary)' ?>"></i> Matn</span>
                    <span><i class="fas fa-<?= $videoWatched ? 'check-circle' : 'times-circle' ?>" style="color: <?= $videoWatched ? 'var(--success)' : 'var(--text-tertiary)' ?>"></i> Video</span>
                    <span><i class="fas fa-<?= $testPassed ? 'check-circle' : 'times-circle' ?>" style="color: <?= $testPassed ? 'var(--success)' : 'var(--text-tertiary)' ?>"></i> Test (<?= $t['test_score'] ?? 0 ?>%)</span>
                    <span><i class="fas fa-<?= $taskCompleted ? 'check-circle' : 'times-circle' ?>" style="color: <?= $taskCompleted ? 'var(--success)' : 'var(--text-tertiary)' ?>"></i> Amaliy (<?= $t['task_grade'] ?? '—' ?>)</span>
                </div>
                <div style="margin-top: 0.5rem;">
                    <div class="progress-mini">
                        <div class="progress-mini-bar" style="width: <?= $percent ?>%;"></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="row g-3">
    <!-- Bahoyalar -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3>Baholar</h3></div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr><th>Sana</th><th>Mavzu</th><th>Tur</th><th>Baho</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grades as $g): ?>
                            <tr>
                                <td style="font-size: 0.85rem;"><?= formatDate($g['created_at']) ?></td>
                                <td style="font-size: 0.85rem;"><?= e($g['topic_title'] ?? '-') ?></td>
                                <td><span class="badge badge-info"><?= $g['type'] ?></span></td>
                                <td><span class="badge badge-<?= gradeColor($g['grade']) ?>"><?= $g['grade'] ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Topshiriqlar -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3>So'nggi topshiriqlar</h3></div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr><th>Sana</th><th>Masala</th><th>Status</th><th>Foiz</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $s): ?>
                            <tr>
                                <td style="font-size: 0.85rem;"><?= formatDate($s['submitted_at']) ?></td>
                                <td style="font-size: 0.85rem;"><?= e($s['task_title']) ?></td>
                                <td><span class="badge badge-<?= ['accepted'=>'success','wrong_answer'=>'danger','pending'=>'warning'][$s['status']] ?? 'secondary' ?>"><?= $s['status'] ?></span></td>
                                <td><strong><?= $s['score_percent'] ?>%</strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
