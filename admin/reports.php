<?php
$pageTitle = 'Hisobotlar';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('admin');

// Umumiy statistika
$stats = [
    'total_users' => db()->fetchOne("SELECT COUNT(*) as cnt FROM users")['cnt'],
    'total_subjects' => db()->fetchOne("SELECT COUNT(*) as cnt FROM subjects")['cnt'],
    'total_topics' => db()->fetchOne("SELECT COUNT(*) as cnt FROM topics")['cnt'],
    'total_submissions' => db()->fetchOne("SELECT COUNT(*) as cnt FROM task_submissions")['cnt'],
    'avg_grade' => db()->fetchOne("SELECT AVG(grade) as avg FROM grades")['avg'] ?? 0,
];

// Eng faol talabalar (TOP 10)
$topStudents = db()->fetchAll(
    "SELECT u.id, u.full_name, u.username,
        COUNT(DISTINCT sp.topic_id) as completed_topics,
        AVG(g.grade) as avg_grade,
        COUNT(DISTINCT ts.id) as submissions
     FROM users u
     LEFT JOIN student_progress sp ON sp.student_id = u.id 
        AND sp.content_read = 1 AND sp.video_watched = 1 
        AND sp.test_passed = 1 AND sp.task_completed = 1
     LEFT JOIN grades g ON g.student_id = u.id
     LEFT JOIN task_submissions ts ON ts.student_id = u.id
     WHERE u.role = 'student' AND u.status = 'active'
     GROUP BY u.id
     ORDER BY completed_topics DESC, avg_grade DESC
     LIMIT 10"
);

// Fanlar bo'yicha statistika
$subjectStats = db()->fetchAll(
    "SELECT s.id, s.name, s.programming_language,
        (SELECT COUNT(*) FROM subject_students ss WHERE ss.subject_id = s.id) as student_count,
        (SELECT COUNT(*) FROM topics t WHERE t.subject_id = s.id) as topic_count,
        (SELECT AVG(g.grade) FROM grades g WHERE g.subject_id = s.id) as avg_grade
     FROM subjects s
     ORDER BY student_count DESC"
);

// Oxirgi 7 kun faollik
$activityChart = db()->fetchAll(
    "SELECT DATE(created_at) as date, COUNT(DISTINCT user_id) as count 
     FROM activity_logs 
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     GROUP BY DATE(created_at)
     ORDER BY date ASC"
);

// Bahodlar taqsimoti
$gradeDistribution = db()->fetchAll(
    "SELECT grade, COUNT(*) as count FROM grades GROUP BY grade ORDER BY grade DESC"
);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Umumiy statistika -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary"><i class="fas fa-users"></i></div>
        <div class="stat-content">
            <h3><?= $stats['total_users'] ?></h3>
            <p>Foydalanuvchilar</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success"><i class="fas fa-book"></i></div>
        <div class="stat-content">
            <h3><?= $stats['total_subjects'] ?></h3>
            <p>Fanlar</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning"><i class="fas fa-list"></i></div>
        <div class="stat-content">
            <h3><?= $stats['total_topics'] ?></h3>
            <p>Mavzular</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon info"><i class="fas fa-code"></i></div>
        <div class="stat-content">
            <h3><?= $stats['total_submissions'] ?></h3>
            <p>Topshiriqlar</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon danger"><i class="fas fa-star"></i></div>
        <div class="stat-content">
            <h3><?= number_format((float)$stats['avg_grade'], 2) ?></h3>
            <p>O'rtacha baho</p>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Faollik chart -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-chart-line"></i> Oxirgi 7 kun faollik</h2>
            </div>
            <div class="card-body">
                <canvas id="activityChart" height="80"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Bahodlar pie -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-chart-pie"></i> Bahoyaring taqsimoti</h2>
            </div>
            <div class="card-body">
                <canvas id="gradesChart" height="180"></canvas>
            </div>
        </div>
    </div>
    
    <!-- TOP talabalar -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-trophy"></i> Eng faol talabalar</h2>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Talaba</th>
                            <th>Yakunlangan mavzular</th>
                            <th>O'rtacha baho</th>
                            <th>Topshiriqlar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topStudents as $i => $st): ?>
                            <tr>
                                <td>
                                    <?php if ($i < 3): ?>
                                        <i class="fas fa-medal" style="color: <?= ['#FFD700','#C0C0C0','#CD7F32'][$i] ?>; font-size: 1.25rem;"></i>
                                    <?php else: ?>
                                        <?= $i + 1 ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= e($st['full_name']) ?></strong>
                                    <br><small style="color: var(--text-tertiary);">@<?= e($st['username']) ?></small>
                                </td>
                                <td><?= $st['completed_topics'] ?></td>
                                <td>
                                    <span class="badge badge-<?= gradeColor(round($st['avg_grade'] ?? 0)) ?>">
                                        <?= number_format((float)($st['avg_grade'] ?? 0), 2) ?>
                                    </span>
                                </td>
                                <td><?= $st['submissions'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Fanlar bo'yicha -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-book"></i> Fanlar bo'yicha</h2>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php foreach ($subjectStats as $s): ?>
                    <div style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--border);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <strong><?= e($s['name']) ?></strong>
                            <span class="badge badge-info"><?= strtoupper($s['programming_language']) ?></span>
                        </div>
                        <div style="display: flex; gap: 1rem; font-size: 0.85rem; color: var(--text-secondary);">
                            <span><i class="fas fa-user-graduate"></i> <?= $s['student_count'] ?></span>
                            <span><i class="fas fa-list"></i> <?= $s['topic_count'] ?></span>
                            <span><i class="fas fa-star"></i> <?= number_format((float)($s['avg_grade'] ?? 0), 2) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php
$activityLabels = json_encode(array_map(fn($x) => formatDate($x['date']), $activityChart));
$activityData = json_encode(array_column($activityChart, 'count'));
$gradeLabels = json_encode(array_map(fn($g) => 'Baho ' . $g['grade'], $gradeDistribution));
$gradeData = json_encode(array_column($gradeDistribution, 'count'));

$extraJs = ['https://cdn.jsdelivr.net/npm/chart.js'];
$inlineJs = <<<JS
document.addEventListener('DOMContentLoaded', function() {
    // Activity chart
    new Chart(document.getElementById('activityChart'), {
        type: 'line',
        data: {
            labels: $activityLabels,
            datasets: [{
                label: 'Faol foydalanuvchilar',
                data: $activityData,
                borderColor: '#4F46E5',
                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });
    
    // Grades chart
    new Chart(document.getElementById('gradesChart'), {
        type: 'doughnut',
        data: {
            labels: $gradeLabels,
            datasets: [{
                data: $gradeData,
                backgroundColor: ['#10B981', '#4F46E5', '#F59E0B', '#EF4444'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
});
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
