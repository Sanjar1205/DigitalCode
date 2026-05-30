<?php
$pageTitle = 'Hisobotlar';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('teacher');

$teacherId = $_SESSION['user_id'];

// Mening fanlarim bo'yicha statistika
$mySubjects = db()->fetchAll(
    "SELECT s.*, 
        (SELECT COUNT(*) FROM subject_students ss WHERE ss.subject_id = s.id) as student_count,
        (SELECT COUNT(*) FROM topics t WHERE t.subject_id = s.id) as topic_count,
        (SELECT AVG(g.grade) FROM grades g WHERE g.subject_id = s.id) as avg_grade,
        (SELECT COUNT(*) FROM task_submissions ts 
            JOIN tasks t ON ts.task_id = t.id 
            JOIN topics tp ON t.topic_id = tp.id 
            WHERE tp.subject_id = s.id) as total_submissions
     FROM subjects s 
     JOIN subject_teachers st ON s.id = st.subject_id 
     WHERE st.teacher_id = ?", [$teacherId]
);

$totalStudents = db()->fetchOne(
    "SELECT COUNT(DISTINCT ss.student_id) as cnt 
     FROM subject_students ss 
     JOIN subject_teachers st ON ss.subject_id = st.subject_id 
     WHERE st.teacher_id = ?", [$teacherId]
)['cnt'];

$totalSubmissions = db()->fetchOne(
    "SELECT COUNT(*) as cnt FROM task_submissions ts 
     JOIN tasks t ON ts.task_id = t.id 
     JOIN topics tp ON t.topic_id = tp.id 
     JOIN subject_teachers st ON tp.subject_id = st.subject_id 
     WHERE st.teacher_id = ?", [$teacherId]
)['cnt'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary"><i class="fas fa-book"></i></div>
        <div class="stat-content">
            <h3><?= count($mySubjects) ?></h3>
            <p>Fanlar</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success"><i class="fas fa-user-graduate"></i></div>
        <div class="stat-content">
            <h3><?= $totalStudents ?></h3>
            <p>Talabalar</p>
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

<div class="card">
    <div class="card-header">
        <h2>Fanlar bo'yicha hisobot</h2>
    </div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fan</th>
                    <th>Til</th>
                    <th>Talabalar</th>
                    <th>Mavzular</th>
                    <th>O'rtacha baho</th>
                    <th>Topshiriqlar</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mySubjects as $s): ?>
                    <tr>
                        <td><strong><?= e($s['name']) ?></strong></td>
                        <td><span class="badge badge-info"><?= strtoupper($s['programming_language']) ?></span></td>
                        <td><?= $s['student_count'] ?></td>
                        <td><?= $s['topic_count'] ?></td>
                        <td>
                            <span class="badge badge-<?= gradeColor(round($s['avg_grade'] ?? 0)) ?>">
                                <?= number_format((float)($s['avg_grade'] ?? 0), 2) ?>
                            </span>
                        </td>
                        <td><?= $s['total_submissions'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
