<?php
$pageTitle = 'Mening yechimlarim';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('student');

$studentId = (int)$_SESSION['user_id'];

// Filtrlash
$filterSubject = (int)($_GET['subject_id'] ?? 0);
$filterStatus = $_GET['status'] ?? '';

$where = ['ts.student_id = ?'];
$params = [$studentId];

if ($filterSubject) {
    $where[] = 'sub.id = ?';
    $params[] = $filterSubject;
}

if ($filterStatus) {
    $where[] = 'ts.status = ?';
    $params[] = $filterStatus;
}

// Yechimlarni olish
$submissions = db()->fetchAll(
    "SELECT ts.*, t.title as task_title, t.description as task_description,
            tp.title as topic_title, tp.id as topic_id,
            sub.id as subject_id, sub.name as subject_name, sub.programming_language
     FROM task_submissions ts
     JOIN tasks t ON ts.task_id = t.id
     JOIN topics tp ON t.topic_id = tp.id
     JOIN subjects sub ON tp.subject_id = sub.id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY ts.submitted_at DESC", 
    $params
);

// Statistika
$stats = db()->fetchOne(
    "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
        SUM(CASE WHEN status != 'accepted' THEN 1 ELSE 0 END) as failed,
        AVG(score_percent) as avg_score,
        AVG(grade) as avg_grade
     FROM task_submissions WHERE student_id = ?", 
    [$studentId]
);

// Filter uchun fanlar
$mySubjects = db()->fetchAll(
    "SELECT DISTINCT s.id, s.name FROM subjects s
     JOIN subject_students ss ON s.id = ss.subject_id
     WHERE ss.student_id = ? ORDER BY s.name", [$studentId]
);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary"><i class="fas fa-code"></i></div>
        <div class="stat-content">
            <h3><?= (int)$stats['total'] ?></h3>
            <p>Jami yechimlar</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
        <div class="stat-content">
            <h3><?= (int)$stats['accepted'] ?></h3>
            <p>Qabul qilingan</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon danger"><i class="fas fa-times-circle"></i></div>
        <div class="stat-content">
            <h3><?= (int)$stats['failed'] ?></h3>
            <p>Xato</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning"><i class="fas fa-star"></i></div>
        <div class="stat-content">
            <h3><?= number_format((float)$stats['avg_grade'], 2) ?></h3>
            <p>O'rtacha baho</p>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: end;">
            <div style="flex: 1; min-width: 200px;">
                <label style="font-size: 0.85rem; color: var(--text-secondary);">Fan</label>
                <select name="subject_id" class="form-control">
                    <option value="">Barcha fanlar</option>
                    <?php foreach ($mySubjects as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $filterSubject == $s['id'] ? 'selected' : '' ?>>
                            <?= e($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex: 1; min-width: 200px;">
                <label style="font-size: 0.85rem; color: var(--text-secondary);">Holat</label>
                <select name="status" class="form-control">
                    <option value="">Hammasi</option>
                    <option value="accepted" <?= $filterStatus === 'accepted' ? 'selected' : '' ?>>✓ Qabul qilingan</option>
                    <option value="wrong_answer" <?= $filterStatus === 'wrong_answer' ? 'selected' : '' ?>>✗ Xato javob</option>
                    <option value="time_limit" <?= $filterStatus === 'time_limit' ? 'selected' : '' ?>>⏱️ Vaqt tugadi</option>
                    <option value="compile_error" <?= $filterStatus === 'compile_error' ? 'selected' : '' ?>>⚠️ Kompilyatsiya xato</option>
                    <option value="runtime_error" <?= $filterStatus === 'runtime_error' ? 'selected' : '' ?>>💥 Runtime xato</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Filtrlash
            </button>
            <a href="<?= SITE_URL ?>/student/my_submissions.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Tozalash
            </a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-history"></i> Yechimlar tarixi</h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($submissions)): ?>
            <p style="text-align: center; padding: 3rem; color: var(--text-tertiary);">
                <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                Hali bironta amaliy topshiriq topshirmagansiz
            </p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="table" style="margin: 0;">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Masala</th>
                            <th>Fan/Mavzu</th>
                            <th>Til</th>
                            <th>Holat</th>
                            <th>Testlar</th>
                            <th>Foiz</th>
                            <th>Baho</th>
                            <th>Vaqt</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $i => $s): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <strong><?= e($s['task_title']) ?></strong>
                                </td>
                                <td>
                                    <small>
                                        <?= e($s['subject_name']) ?><br>
                                        <span style="color: var(--text-tertiary);"><?= e($s['topic_title']) ?></span>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?= strtoupper(e($s['language'])) ?></span>
                                </td>
                                <td>
                                    <?php
                                    $statusInfo = [
                                        'accepted' => ['✓ Qabul qilindi', 'success'],
                                        'wrong_answer' => ['✗ Xato javob', 'danger'],
                                        'time_limit' => ['⏱️ Vaqt tugadi', 'warning'],
                                        'memory_limit' => ['💾 Xotira tugadi', 'warning'],
                                        'compile_error' => ['⚠️ Kompilyatsiya', 'danger'],
                                        'runtime_error' => ['💥 Runtime xato', 'danger'],
                                        'pending' => ['⏳ Kutilmoqda', 'secondary']
                                    ];
                                    [$statusText, $statusColor] = $statusInfo[$s['status']] ?? [$s['status'], 'secondary'];
                                    ?>
                                    <span class="badge badge-<?= $statusColor ?>">
                                        <?= $statusText ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= (int)$s['passed_tests'] ?></strong> / <?= (int)$s['total_tests'] ?>
                                </td>
                                <td>
                                    <strong style="color: <?= $s['score_percent'] >= 60 ? 'var(--success)' : 'var(--danger)' ?>;">
                                        <?= number_format((float)$s['score_percent'], 1) ?>%
                                    </strong>
                                </td>
                                <td>
                                    <?php if ($s['grade']): ?>
                                        <span class="badge" style="background: <?= gradeColor((int)$s['grade']) ?>; color: white; font-size: 1rem; padding: 0.25rem 0.6rem;">
                                            <?= (int)$s['grade'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-tertiary);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small style="color: var(--text-tertiary);">
                                        <?= formatDate($s['submitted_at'], 'd.m.Y H:i') ?>
                                    </small>
                                </td>
                                <td>
                                    <button type="button" 
                                            class="btn btn-sm btn-secondary"
                                            onclick="viewCode(<?= $s['id'] ?>)"
                                            title="Kodni ko'rish">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <!-- Yashirilgan kod (modal uchun) -->
                            <tr id="code-<?= $s['id'] ?>" style="display: none;">
                                <td colspan="10" style="background: var(--bg-tertiary); padding: 1.5rem;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                        <strong><i class="fas fa-code"></i> Yuborilgan kod (<?= strtoupper(e($s['language'])) ?>):</strong>
                                        <button type="button" 
                                                class="btn btn-sm btn-secondary" 
                                                onclick="document.getElementById('code-<?= $s['id'] ?>').style.display='none'">
                                            <i class="fas fa-times"></i> Yopish
                                        </button>
                                    </div>
                                    <pre style="background: #1a1a1a; color: #e6e6e6; padding: 1rem; border-radius: var(--radius); overflow-x: auto; max-height: 400px; font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; line-height: 1.5; margin: 0;"><?= htmlspecialchars($s['code']) ?></pre>
                                    <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                                        <a href="<?= SITE_URL ?>/student/code_editor.php?topic_id=<?= $s['topic_id'] ?>&task_id=<?= $s['task_id'] ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-redo"></i> Qaytadan urinish
                                        </a>
                                        <button type="button" class="btn btn-sm btn-secondary" 
                                                onclick="copyCode(<?= $s['id'] ?>)">
                                            <i class="fas fa-copy"></i> Nusxalash
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function viewCode(id) {
    const row = document.getElementById('code-' + id);
    if (row.style.display === 'none') {
        row.style.display = '';
    } else {
        row.style.display = 'none';
    }
}

function copyCode(id) {
    const pre = document.querySelector('#code-' + id + ' pre');
    if (!pre) return;
    navigator.clipboard.writeText(pre.textContent).then(() => {
        if (typeof showToast === 'function') {
            showToast('Kod nusxalandi!', 'success');
        } else {
            alert('Kod nusxalandi!');
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
