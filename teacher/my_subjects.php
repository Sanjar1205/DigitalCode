<?php
$pageTitle = 'Mening fanlarim';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('teacher');

$teacherId = $_SESSION['user_id'];

$subjects = db()->fetchAll(
    "SELECT s.*, 
        (SELECT COUNT(*) FROM subject_students ss WHERE ss.subject_id = s.id) as student_count,
        (SELECT COUNT(*) FROM topics t WHERE t.subject_id = s.id) as topic_count,
        (SELECT COUNT(*) FROM topics t WHERE t.subject_id = s.id AND t.status = 'published') as published_count
     FROM subjects s 
     JOIN subject_teachers st ON s.id = st.subject_id 
     WHERE st.teacher_id = ? 
     ORDER BY s.name", [$teacherId]
);

require_once __DIR__ . '/../includes/header.php';
?>

<?php if (empty($subjects)): ?>
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 3rem;">
            <i class="fas fa-book" style="font-size: 3rem; color: var(--text-tertiary);"></i>
            <h3 style="color: var(--text-tertiary); margin-top: 1rem;">Hech qanday fan biriktirilmagan</h3>
            <p style="color: var(--text-tertiary);">Administrator sizga fanlarni biriktirishini kuting</p>
        </div>
    </div>
<?php else: ?>
    <?php 
    $langColors = [
        'cpp' => '#00599C', 'java' => '#ED8B00', 'python' => '#3776AB',
        'javascript' => '#F7DF1E', 'php' => '#777BB4', 'csharp' => '#239120'
    ];
    $langNames = [
        'cpp' => 'C++', 'java' => 'Java', 'python' => 'Python',
        'javascript' => 'JavaScript', 'php' => 'PHP', 'csharp' => 'C#'
    ];
    ?>
    <div class="row g-3">
        <?php foreach ($subjects as $s): 
            $color = $langColors[$s['programming_language']] ?? '#666';
            $langName = $langNames[$s['programming_language']] ?? $s['programming_language'];
        ?>
            <div class="col-lg-4 col-md-6">
                <div class="card" style="height: 100%;">
                    <div style="height: 120px; background: linear-gradient(135deg, <?= $color ?>, <?= $color ?>cc); display: flex; align-items: center; justify-content: center; color: white; font-size: 2.5rem; font-weight: 700; font-family: 'JetBrains Mono', monospace;">
                        <?= $langName ?>
                    </div>
                    <div class="card-body">
                        <h3 style="margin-bottom: 0.5rem; font-size: 1.1rem;"><?= e($s['name']) ?></h3>
                        <p style="color: var(--text-tertiary); font-size: 0.9rem; min-height: 3em;">
                            <?= e(mb_substr($s['description'], 0, 100)) ?><?= mb_strlen($s['description']) > 100 ? '...' : '' ?>
                        </p>
                        
                        <div style="display: flex; gap: 1rem; padding: 0.75rem 0; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); margin-bottom: 1rem;">
                            <div style="text-align: center; flex: 1;">
                                <div style="font-size: 1.25rem; font-weight: 700;"><?= $s['topic_count'] ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-tertiary);">Mavzu</div>
                            </div>
                            <div style="text-align: center; flex: 1;">
                                <div style="font-size: 1.25rem; font-weight: 700; color: var(--success);"><?= $s['published_count'] ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-tertiary);">Faol</div>
                            </div>
                            <div style="text-align: center; flex: 1;">
                                <div style="font-size: 1.25rem; font-weight: 700;"><?= $s['student_count'] ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-tertiary);">Talaba</div>
                            </div>
                        </div>
                        
                        <a href="<?= SITE_URL ?>/teacher/topics.php?subject_id=<?= $s['id'] ?>" 
                           class="btn btn-primary btn-block">
                            <i class="fas fa-list"></i> Mavzularni boshqarish
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
