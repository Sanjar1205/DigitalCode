<?php
$pageTitle = 'Talaba paneli';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('student');

$studentId = $_SESSION['user_id'];

// Mening fanlarim
$mySubjects = db()->fetchAll(
    "SELECT s.* FROM subjects s 
     JOIN subject_students ss ON s.id = ss.subject_id 
     WHERE ss.student_id = ? AND s.status = 'active'", [$studentId]
);

// Statistika
$totalTopics = db()->fetchOne(
    "SELECT COUNT(*) as cnt FROM topics t 
     JOIN subject_students ss ON t.subject_id = ss.subject_id 
     WHERE ss.student_id = ? AND t.status = 'published'", [$studentId]
)['cnt'];

$completedTopics = db()->fetchOne(
    "SELECT COUNT(*) as cnt FROM student_progress sp 
     JOIN topics t ON sp.topic_id = t.id 
     JOIN subject_students ss ON t.subject_id = ss.subject_id 
     WHERE sp.student_id = ? AND ss.student_id = ? 
     AND sp.content_read = 1 AND sp.video_watched = 1 
     AND sp.test_passed = 1 AND sp.task_completed = 1", [$studentId, $studentId]
)['cnt'];

$avgGrade = getAverageGrade($studentId);

$totalSubmissions = db()->fetchOne(
    "SELECT COUNT(*) as cnt FROM task_submissions WHERE student_id = ?", [$studentId]
)['cnt'];

// So'nggi baholar
$recentGrades = db()->fetchAll(
    "SELECT g.*, s.name as subject_name, t.title as topic_title
     FROM grades g 
     JOIN subjects s ON g.subject_id = s.id 
     LEFT JOIN topics t ON g.topic_id = t.id 
     WHERE g.student_id = ? 
     ORDER BY g.created_at DESC LIMIT 5", [$studentId]
);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Statistika -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary"><i class="fas fa-book"></i></div>
        <div class="stat-content">
            <h3><?= count($mySubjects) ?></h3>
            <p>Mening fanlarim</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
        <div class="stat-content">
            <h3><?= $completedTopics ?>/<?= $totalTopics ?></h3>
            <p>Yakunlangan mavzular</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning"><i class="fas fa-star"></i></div>
        <div class="stat-content">
            <h3><?= number_format($avgGrade, 2) ?></h3>
            <p>O'rtacha baho</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon info"><i class="fas fa-code"></i></div>
        <div class="stat-content">
            <h3><?= $totalSubmissions ?></h3>
            <p>Topshiriqlar</p>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Mening fanlarim -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h2>Mening fanlarim</h2>
                <a href="<?= SITE_URL ?>/student/my_subjects.php" class="btn btn-outline-primary btn-sm">Barchasi</a>
            </div>
            <div class="card-body">
                <?php if (empty($mySubjects)): ?>
                    <p style="text-align: center; color: var(--text-tertiary); padding: 2rem;">
                        Sizga hech qanday fan biriktirilmagan
                    </p>
                <?php else: ?>
                    <?php 
                    $langColors = [
                        'cpp' => '#00599C', 'java' => '#ED8B00', 'python' => '#3776AB',
                        'javascript' => '#F7DF1E', 'php' => '#777BB4', 'csharp' => '#239120'
                    ];
                    ?>
                    <div class="row g-3">
                        <?php foreach (array_slice($mySubjects, 0, 4) as $s): 
                            $progress = getSubjectProgress($studentId, $s['id']);
                            $color = $langColors[$s['programming_language']] ?? '#666';
                        ?>
                            <div class="col-md-6">
                                <a href="<?= SITE_URL ?>/student/learn.php?subject_id=<?= $s['id'] ?>" 
                                   style="text-decoration: none; color: inherit; display: block;">
                                    <div style="border: 1px solid var(--border); border-radius: var(--radius-md); padding: 1.25rem; transition: var(--transition); cursor: pointer;"
                                         onmouseover="this.style.borderColor='var(--primary)'; this.style.transform='translateY(-2px)'" 
                                         onmouseout="this.style.borderColor='var(--border)'; this.style.transform='translateY(0)'">
                                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                                            <div style="width: 48px; height: 48px; background: <?= $color ?>; border-radius: var(--radius); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-family: 'JetBrains Mono', monospace;">
                                                <?= strtoupper(substr($s['programming_language'], 0, 2)) ?>
                                            </div>
                                            <div>
                                                <strong style="font-size: 1rem;"><?= e($s['name']) ?></strong>
                                            </div>
                                        </div>
                                        <div class="progress-mini">
                                            <div class="progress-mini-bar" style="width: <?= $progress ?>%;"></div>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; margin-top: 0.5rem;">
                                            <span style="font-size: 0.85rem; color: var(--text-tertiary);">Progress</span>
                                            <strong style="font-size: 0.9rem;"><?= $progress ?>%</strong>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- So'nggi baholar -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h2>So'nggi baholar</h2>
                <a href="<?= SITE_URL ?>/student/grades.php" class="btn btn-outline-primary btn-sm">Hammasi</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($recentGrades)): ?>
                    <p style="text-align: center; color: var(--text-tertiary); padding: 2rem;">
                        Hozircha baholar yo'q
                    </p>
                <?php else: ?>
                    <?php foreach ($recentGrades as $g): ?>
                        <div style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong style="font-size: 0.95rem;"><?= e($g['subject_name']) ?></strong>
                                <?php if ($g['topic_title']): ?>
                                    <div style="font-size: 0.85rem; color: var(--text-tertiary);">
                                        <?= e($g['topic_title']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div style="text-align: right;">
                                <span class="badge badge-<?= gradeColor($g['grade']) ?>" style="font-size: 1rem; padding: 0.4rem 0.75rem;">
                                    <?= $g['grade'] ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
