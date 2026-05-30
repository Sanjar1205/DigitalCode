<?php
$pageTitle = 'Mening fanlarim';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('student');

$studentId = $_SESSION['user_id'];

$subjects = db()->fetchAll(
    "SELECT s.*, 
        (SELECT COUNT(*) FROM topics WHERE subject_id = s.id AND status = 'published') as topic_count,
        (SELECT u.full_name FROM users u 
            JOIN subject_teachers st ON u.id = st.teacher_id 
            WHERE st.subject_id = s.id LIMIT 1) as teacher_name
     FROM subjects s 
     JOIN subject_students ss ON s.id = ss.subject_id 
     WHERE ss.student_id = ? AND s.status = 'active' 
     ORDER BY s.name", [$studentId]
);

require_once __DIR__ . '/../includes/header.php';
?>

<?php if (empty($subjects)): ?>
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 3rem;">
            <i class="fas fa-book" style="font-size: 3rem; color: var(--text-tertiary);"></i>
            <h3 style="color: var(--text-tertiary); margin-top: 1rem;">Sizga hech qanday fan biriktirilmagan</h3>
            <p style="color: var(--text-tertiary);">Administrator yoki o'qituvchi bilan bog'laning</p>
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
            $progress = getSubjectProgress($studentId, $s['id']);
        ?>
            <div class="col-lg-4 col-md-6">
                <div class="card" style="height: 100%; transition: var(--transition);"
                     onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='var(--shadow-lg)'" 
                     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='var(--shadow)'">
                    <div style="height: 140px; background: linear-gradient(135deg, <?= $color ?>, <?= $color ?>cc); display: flex; align-items: center; justify-content: center; color: white; font-size: 2.75rem; font-weight: 700; font-family: 'JetBrains Mono', monospace; position: relative;">
                        <?= $langName ?>
                        <div style="position: absolute; top: 1rem; right: 1rem; background: rgba(0,0,0,0.3); padding: 0.25rem 0.75rem; border-radius: 100px; font-size: 0.85rem;">
                            <?= $s['topic_count'] ?> mavzu
                        </div>
                    </div>
                    <div class="card-body">
                        <h3 style="margin-bottom: 0.5rem; font-size: 1.15rem;"><?= e($s['name']) ?></h3>
                        <p style="color: var(--text-tertiary); font-size: 0.9rem; min-height: 2.5em; margin-bottom: 1rem;">
                            <?= e(mb_substr($s['description'] ?? '', 0, 90)) ?>...
                        </p>
                        
                        <?php if ($s['teacher_name']): ?>
                            <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.75rem;">
                                <i class="fas fa-chalkboard-teacher"></i> <?= e($s['teacher_name']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div style="margin-bottom: 1rem;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                                <small style="color: var(--text-tertiary);">Progress</small>
                                <strong style="font-size: 0.85rem;"><?= $progress ?>%</strong>
                            </div>
                            <div class="progress-mini" style="height: 6px;">
                                <div class="progress-mini-bar" style="width: <?= $progress ?>%;"></div>
                            </div>
                        </div>
                        
                        <a href="<?= SITE_URL ?>/student/learn.php?subject_id=<?= $s['id'] ?>" 
                           class="btn btn-primary btn-block">
                            <i class="fas fa-play"></i> Davom etish
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
