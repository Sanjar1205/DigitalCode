<?php
$pageTitle = 'Mening baholarim';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('student');

$studentId = $_SESSION['user_id'];

// Barcha baholar
$grades = db()->fetchAll(
    "SELECT g.*, s.name as subject_name, t.title as topic_title, u.full_name as teacher_name
     FROM grades g 
     JOIN subjects s ON g.subject_id = s.id 
     LEFT JOIN topics t ON g.topic_id = t.id 
     LEFT JOIN users u ON g.given_by = u.id 
     WHERE g.student_id = ? 
     ORDER BY g.created_at DESC", [$studentId]
);

// Fanlar bo'yicha o'rtacha
$bySubject = db()->fetchAll(
    "SELECT s.name, s.programming_language, AVG(g.grade) as avg_grade, COUNT(g.id) as count
     FROM grades g 
     JOIN subjects s ON g.subject_id = s.id 
     WHERE g.student_id = ? 
     GROUP BY s.id 
     ORDER BY avg_grade DESC", [$studentId]
);

// Bahodlar taqsimoti
$distribution = db()->fetchAll(
    "SELECT grade, COUNT(*) as count FROM grades WHERE student_id = ? GROUP BY grade ORDER BY grade DESC", 
    [$studentId]
);

$totalGrades = array_sum(array_column($distribution, 'count'));
$avgGrade = getAverageGrade($studentId);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary"><i class="fas fa-star"></i></div>
        <div class="stat-content">
            <h3><?= number_format($avgGrade, 2) ?></h3>
            <p>O'rtacha baho</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success"><i class="fas fa-list-ol"></i></div>
        <div class="stat-content">
            <h3><?= $totalGrades ?></h3>
            <p>Jami baholar</p>
        </div>
    </div>
    <?php foreach ($distribution as $d): ?>
        <div class="stat-card">
            <div class="stat-icon <?= ['5'=>'success','4'=>'primary','3'=>'warning','2'=>'danger'][$d['grade']] ?? 'info' ?>">
                <i class="fas fa-award"></i>
            </div>
            <div class="stat-content">
                <h3><?= $d['count'] ?></h3>
                <p>"<?= $d['grade'] ?>" baho</p>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <!-- Fanlar bo'yicha -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-chart-bar"></i> Fanlar bo'yicha</h2>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($bySubject)): ?>
                    <p style="text-align: center; padding: 2rem; color: var(--text-tertiary);">
                        Hozircha baholar yo'q
                    </p>
                <?php else: ?>
                    <?php foreach ($bySubject as $s): ?>
                        <div style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--border);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <strong><?= e($s['name']) ?></strong>
                                <span class="badge badge-<?= gradeColor(round($s['avg_grade'])) ?>">
                                    <?= number_format($s['avg_grade'], 2) ?>
                                </span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--text-tertiary);">
                                <span><?= strtoupper($s['programming_language']) ?></span>
                                <span><?= $s['count'] ?> baho</span>
                            </div>
                            <div style="margin-top: 0.5rem;">
                                <div class="progress-mini" style="height: 6px;">
                                    <div class="progress-mini-bar" style="width: <?= ($s['avg_grade']/5)*100 ?>%;"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Chart -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-chart-pie"></i> Baholar taqsimoti</h2>
            </div>
            <div class="card-body">
                <?php if ($totalGrades > 0): ?>
                    <canvas id="gradesChart" height="120"></canvas>
                <?php else: ?>
                    <p style="text-align: center; padding: 2rem; color: var(--text-tertiary);">
                        Baholar mavjud emas
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Barcha baholar -->
<div class="card mt-3">
    <div class="card-header">
        <h2>Barcha baholarim</h2>
    </div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Sana</th>
                    <th>Fan</th>
                    <th>Mavzu</th>
                    <th>Tur</th>
                    <th>Baho</th>
                    <th>Izoh</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($grades)): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-tertiary);">
                        Baholar yo'q
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($grades as $g): ?>
                        <tr>
                            <td style="white-space: nowrap; font-size: 0.85rem;">
                                <?= formatDate($g['created_at']) ?>
                            </td>
                            <td><strong><?= e($g['subject_name']) ?></strong></td>
                            <td style="font-size: 0.9rem;"><?= e($g['topic_title'] ?? '-') ?></td>
                            <td>
                                <span class="badge badge-info">
                                    <?= ['test'=>'Test','task'=>'Kod','practical'=>'Amaliy','independent'=>'Mustaqil','manual'=>'O\'qituvchidan'][$g['type']] ?? e($g['type']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= gradeColor($g['grade']) ?>" style="font-size: 0.95rem;">
                                    <?= $g['grade'] ?>
                                </span>
                            </td>
                            <td style="font-size: 0.85rem; color: var(--text-secondary);">
                                <?= e($g['comment'] ?: '-') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$labels = json_encode(array_map(fn($d) => '"' . $d['grade'] . '" baho', $distribution));
$dataValues = json_encode(array_column($distribution, 'count'));

$extraJs = ['https://cdn.jsdelivr.net/npm/chart.js'];
$inlineJs = <<<JS
if (document.getElementById('gradesChart')) {
    new Chart(document.getElementById('gradesChart'), {
        type: 'bar',
        data: {
            labels: $labels,
            datasets: [{
                label: 'Baholar soni',
                data: $dataValues,
                backgroundColor: ['#10B981', '#4F46E5', '#F59E0B', '#EF4444'],
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
