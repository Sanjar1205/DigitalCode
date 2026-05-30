<?php
$pageTitle = 'O\'qituvchi paneli';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('teacher');

$teacherId = $_SESSION['user_id'];

// Statistika
$mySubjects = db()->fetchOne(
    "SELECT COUNT(*) as cnt FROM subject_teachers WHERE teacher_id = ?", [$teacherId]
)['cnt'];

$myStudents = db()->fetchOne(
    "SELECT COUNT(DISTINCT ss.student_id) as cnt 
     FROM subject_students ss 
     JOIN subject_teachers st ON ss.subject_id = st.subject_id 
     WHERE st.teacher_id = ?", [$teacherId]
)['cnt'];

$myTopics = db()->fetchOne(
    "SELECT COUNT(*) as cnt FROM topics WHERE created_by = ?", [$teacherId]
)['cnt'];

$pendingReviews = db()->fetchOne(
    "SELECT COUNT(*) as cnt FROM task_submissions ts 
     JOIN tasks t ON ts.task_id = t.id 
     JOIN topics tp ON t.topic_id = tp.id 
     JOIN subject_teachers st ON tp.subject_id = st.subject_id 
     WHERE st.teacher_id = ? AND ts.status = 'pending'",
    [$teacherId]
)['cnt'];

// Mening fanlarim
$subjects = db()->fetchAll(
    "SELECT s.*, 
        (SELECT COUNT(*) FROM subject_students ss WHERE ss.subject_id = s.id) as student_count,
        (SELECT COUNT(*) FROM topics t WHERE t.subject_id = s.id) as topic_count
     FROM subjects s 
     JOIN subject_teachers st ON s.id = st.subject_id 
     WHERE st.teacher_id = ?",
    [$teacherId]
);

// So'nggi topshiriqlar
$recentSubmissions = db()->fetchAll(
    "SELECT ts.*, u.full_name as student_name, t.title as task_title 
     FROM task_submissions ts 
     JOIN users u ON ts.student_id = u.id 
     JOIN tasks t ON ts.task_id = t.id 
     JOIN topics tp ON t.topic_id = tp.id 
     JOIN subject_teachers st ON tp.subject_id = st.subject_id 
     WHERE st.teacher_id = ? 
     ORDER BY ts.submitted_at DESC LIMIT 10",
    [$teacherId]
);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary"><i class="fas fa-book"></i></div>
        <div class="stat-content">
            <h3><?= $mySubjects ?></h3>
            <p>Mening fanlarim</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success"><i class="fas fa-user-graduate"></i></div>
        <div class="stat-content">
            <h3><?= $myStudents ?></h3>
            <p>Talabalar</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon info"><i class="fas fa-list"></i></div>
        <div class="stat-content">
            <h3><?= $myTopics ?></h3>
            <p>Yaratilgan mavzular</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning"><i class="fas fa-clock"></i></div>
        <div class="stat-content">
            <h3><?= $pendingReviews ?></h3>
            <p>Kutilayotgan topshiriqlar</p>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h2>Mening fanlarim</h2>
                <a href="<?= SITE_URL ?>/teacher/my_subjects.php" class="btn btn-outline-primary btn-sm">Barchasi</a>
            </div>
            <div class="card-body">
                <?php if (empty($subjects)): ?>
                    <p style="text-align: center; color: var(--text-tertiary);">Hech qanday fan biriktirilmagan</p>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach (array_slice($subjects, 0, 4) as $s): ?>
                            <div class="col-md-6">
                                <div style="border: 1px solid var(--border); border-radius: var(--radius); padding: 1rem;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                        <strong><?= e($s['name']) ?></strong>
                                        <span class="badge badge-info"><?= strtoupper($s['programming_language']) ?></span>
                                    </div>
                                    <div style="display: flex; gap: 1rem; font-size: 0.85rem; color: var(--text-secondary);">
                                        <span><i class="fas fa-list"></i> <?= $s['topic_count'] ?> mavzu</span>
                                        <span><i class="fas fa-user-graduate"></i> <?= $s['student_count'] ?> talaba</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h2>So'nggi topshiriqlar</h2>
                <a href="<?= SITE_URL ?>/teacher/monitoring.php" class="btn btn-outline-primary btn-sm">Hammasi</a>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($recentSubmissions)): ?>
                    <p style="color: var(--text-tertiary); text-align: center;">Topshiriqlar yo'q</p>
                <?php else: ?>
                    <?php foreach ($recentSubmissions as $sub): ?>
                        <div style="padding: 0.75rem 0; border-bottom: 1px solid var(--border);">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div style="flex: 1; min-width: 0;">
                                    <strong style="font-size: 0.9rem;"><?= e($sub['student_name']) ?></strong>
                                    <div style="font-size: 0.85rem; color: var(--text-secondary);"><?= e($sub['task_title']) ?></div>
                                </div>
                                <span class="badge badge-<?= ['accepted'=>'success','wrong_answer'=>'danger','pending'=>'warning'][$sub['status']] ?? 'secondary' ?>">
                                    <?= $sub['score_percent'] ?>%
                                </span>
                            </div>
                            <small style="color: var(--text-tertiary); font-size: 0.75rem;">
                                <?= formatDate($sub['submitted_at'], true) ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
