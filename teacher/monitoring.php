<?php
$pageTitle = 'Talabalarni monitoring';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('teacher');

$teacherId = $_SESSION['user_id'];
$subjectId = (int)($_GET['subject_id'] ?? 0);

$mySubjects = db()->fetchAll(
    "SELECT s.* FROM subjects s 
     JOIN subject_teachers st ON s.id = st.subject_id 
     WHERE st.teacher_id = ?", [$teacherId]
);

$students = [];
$selectedSubject = null;

if ($subjectId) {
    $selectedSubject = db()->fetchOne(
        "SELECT s.* FROM subjects s 
         JOIN subject_teachers st ON s.id = st.subject_id 
         WHERE s.id = ? AND st.teacher_id = ?", [$subjectId, $teacherId]
    );
    
    if ($selectedSubject) {
        // Talabalar va ularning progressi
        $students = db()->fetchAll(
            "SELECT u.id, u.full_name, u.username, u.email,
                (SELECT COUNT(*) FROM topics WHERE subject_id = ? AND status = 'published') as total_topics,
                (SELECT COUNT(*) FROM student_progress sp 
                    JOIN topics t ON sp.topic_id = t.id 
                    WHERE sp.student_id = u.id AND t.subject_id = ?
                    AND sp.content_read = 1 AND sp.video_watched = 1 
                    AND sp.test_passed = 1 AND sp.task_completed = 1
                ) as completed_topics,
                (SELECT AVG(grade) FROM grades WHERE student_id = u.id AND subject_id = ?) as avg_grade,
                (SELECT COUNT(*) FROM task_submissions ts 
                    JOIN tasks t ON ts.task_id = t.id 
                    JOIN topics tp ON t.topic_id = tp.id 
                    WHERE ts.student_id = u.id AND tp.subject_id = ?
                ) as submission_count
             FROM users u 
             JOIN subject_students ss ON u.id = ss.student_id 
             WHERE ss.subject_id = ? 
             ORDER BY u.full_name",
            [$subjectId, $subjectId, $subjectId, $subjectId, $subjectId]
        );
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET">
            <label class="form-label">Fanni tanlang:</label>
            <select name="subject_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- Fan tanlang --</option>
                <?php foreach ($mySubjects as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $subjectId === (int)$s['id'] ? 'selected' : '' ?>>
                        <?= e($s['name']) ?> (<?= strtoupper($s['programming_language']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<?php if ($selectedSubject): ?>
    <div class="card">
        <div class="card-header">
            <h2><?= e($selectedSubject['name']) ?> — <?= count($students) ?> ta talaba</h2>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Talaba</th>
                        <th>Progress</th>
                        <th>Yakunlangan</th>
                        <th>O'rtacha baho</th>
                        <th>Topshiriqlar</th>
                        <th>Amallar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $st): 
                        $progressPercent = $st['total_topics'] > 0 ? round(($st['completed_topics'] / $st['total_topics']) * 100) : 0;
                        $avgGrade = (float)($st['avg_grade'] ?? 0);
                    ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div class="user-avatar" style="width: 36px; height: 36px;">
                                        <?= strtoupper(substr($st['full_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <strong><?= e($st['full_name']) ?></strong>
                                        <br><small style="color: var(--text-tertiary);">@<?= e($st['username']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td style="min-width: 200px;">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div style="flex: 1; height: 8px; background: var(--bg-tertiary); border-radius: 100px;">
                                        <div style="width: <?= $progressPercent ?>%; height: 100%; background: linear-gradient(90deg, var(--primary), var(--secondary)); border-radius: 100px;"></div>
                                    </div>
                                    <span style="font-weight: 600; min-width: 40px; text-align: right;"><?= $progressPercent ?>%</span>
                                </div>
                            </td>
                            <td>
                                <span style="font-weight: 600;"><?= $st['completed_topics'] ?></span>
                                <small style="color: var(--text-tertiary);">/<?= $st['total_topics'] ?></small>
                            </td>
                            <td>
                                <?php if ($avgGrade > 0): ?>
                                    <span class="badge badge-<?= gradeColor(round($avgGrade)) ?>">
                                        <?= number_format($avgGrade, 2) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--text-tertiary);">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $st['submission_count'] ?></td>
                            <td>
                                <a href="<?= SITE_URL ?>/teacher/student_detail.php?student_id=<?= $st['id'] ?>&subject_id=<?= $subjectId ?>" 
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i> Batafsil
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 3rem;">
            <i class="fas fa-eye" style="font-size: 3rem; color: var(--text-tertiary);"></i>
            <h3 style="color: var(--text-tertiary); margin-top: 1rem;">Fanni tanlang</h3>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
