<?php
$pageTitle = 'Fanlar';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'CSRF token xato'); redirect($_SERVER['REQUEST_URI']);
    }
    
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            db()->insert('subjects', [
                'name' => trim($_POST['name']),
                'description' => trim($_POST['description'] ?? ''),
                'programming_language' => $_POST['programming_language'],
                'created_by' => $_SESSION['user_id'],
                'status' => 'active'
            ]);
            setFlash('success', 'Yangi fan qo\'shildi');
        } elseif ($action === 'update') {
            db()->update('subjects', [
                'name' => trim($_POST['name']),
                'description' => trim($_POST['description'] ?? ''),
                'programming_language' => $_POST['programming_language'],
                'status' => $_POST['status']
            ], 'id = :id', ['id' => (int)$_POST['subject_id']]);
            setFlash('success', 'Fan yangilandi');
        } elseif ($action === 'delete') {
            db()->delete('subjects', 'id = :id', ['id' => (int)$_POST['subject_id']]);
            setFlash('success', 'Fan o\'chirildi');
        }
    } catch (Exception $e) {
        setFlash('danger', $e->getMessage());
    }
    redirect($_SERVER['REQUEST_URI']);
}

// Fanlar ro'yxati
$subjects = db()->fetchAll(
    "SELECT s.*, u.full_name as creator,
        (SELECT COUNT(*) FROM subject_teachers st WHERE st.subject_id = s.id) as teacher_count,
        (SELECT COUNT(*) FROM subject_students ss WHERE ss.subject_id = s.id) as student_count,
        (SELECT COUNT(*) FROM topics t WHERE t.subject_id = s.id) as topic_count
     FROM subjects s
     LEFT JOIN users u ON s.created_by = u.id
     ORDER BY s.created_at DESC"
);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="toolbar">
    <div style="flex: 1;">
        <p style="color: var(--text-secondary); margin: 0;">
            Jami <strong><?= count($subjects) ?></strong> ta fan
        </p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSubjectModal">
        <i class="fas fa-plus"></i> Yangi fan
    </button>
</div>

<div class="row g-3">
    <?php if (empty($subjects)): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body" style="text-align: center; padding: 3rem;">
                    <i class="fas fa-book" style="font-size: 3rem; color: var(--text-tertiary); margin-bottom: 1rem;"></i>
                    <h3 style="color: var(--text-tertiary);">Hech qanday fan yo'q</h3>
                    <p style="color: var(--text-tertiary);">Birinchi fanni qo'shish uchun yuqoridagi tugmani bosing</p>
                </div>
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
        <?php foreach ($subjects as $s): 
            $color = $langColors[$s['programming_language']] ?? '#666';
            $langName = $langNames[$s['programming_language']] ?? $s['programming_language'];
        ?>
            <div class="col-lg-4 col-md-6">
                <div class="card" style="height: 100%; transition: var(--transition);">
                    <div style="height: 100px; background: linear-gradient(135deg, <?= $color ?>, <?= $color ?>cc); display: flex; align-items: center; justify-content: center; color: white; font-size: 2.5rem; font-weight: 700; font-family: 'JetBrains Mono', monospace;">
                        <?= $langName ?>
                    </div>
                    <div class="card-body">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                            <h3 style="margin: 0; font-size: 1.1rem;"><?= e($s['name']) ?></h3>
                            <span class="badge badge-<?= $s['status'] === 'active' ? 'success' : 'secondary' ?>">
                                <?= $s['status'] === 'active' ? 'Faol' : 'Yopiq' ?>
                            </span>
                        </div>
                        <p style="color: var(--text-tertiary); font-size: 0.9rem; min-height: 3em; margin-bottom: 1rem;">
                            <?= e(mb_substr($s['description'], 0, 100)) ?><?= mb_strlen($s['description']) > 100 ? '...' : '' ?>
                        </p>
                        
                        <div style="display: flex; gap: 1rem; padding: 0.75rem 0; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); margin-bottom: 1rem;">
                            <div style="text-align: center; flex: 1;">
                                <div style="font-size: 1.25rem; font-weight: 700;"><?= $s['topic_count'] ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-tertiary);">Mavzu</div>
                            </div>
                            <div style="text-align: center; flex: 1;">
                                <div style="font-size: 1.25rem; font-weight: 700;"><?= $s['teacher_count'] ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-tertiary);">O'qituvchi</div>
                            </div>
                            <div style="text-align: center; flex: 1;">
                                <div style="font-size: 1.25rem; font-weight: 700;"><?= $s['student_count'] ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-tertiary);">Talaba</div>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 0.5rem;">
                            <button class="btn btn-sm btn-secondary edit-subject-btn flex-grow-1" 
                                    data-subject='<?= json_encode($s) ?>'>
                                <i class="fas fa-edit"></i> Tahrirlash
                            </button>
                            <a href="<?= SITE_URL ?>/admin/assignments.php?subject_id=<?= $s['id'] ?>" 
                               class="btn btn-sm btn-primary">
                                <i class="fas fa-link"></i>
                            </a>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?= e(Auth::getCsrfToken()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="subject_id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" 
                                        data-confirm="Fanni o'chirmoqchimisiz? Barcha bog'liq ma'lumotlar (mavzular, savollar) ham o'chiriladi.">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal: Yangi fan -->
<div class="modal fade" id="createSubjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="background: var(--bg-secondary); color: var(--text-primary);">
            <div class="modal-header" style="border-color: var(--border);">
                <h5 class="modal-title">Yangi fan qo'shish</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e(Auth::getCsrfToken()) ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Fan nomi <span class="required">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Dasturlash tili <span class="required">*</span></label>
                        <select name="programming_language" class="form-select" required>
                            <option value="cpp">C++</option>
                            <option value="java">Java</option>
                            <option value="python">Python</option>
                            <option value="javascript">JavaScript</option>
                            <option value="php">PHP</option>
                            <option value="csharp">C#</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tavsif</label>
                        <textarea name="description" class="form-control" rows="4"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-color: var(--border);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Bekor qilish</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Saqlash</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Tahrirlash -->
<div class="modal fade" id="editSubjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="background: var(--bg-secondary); color: var(--text-primary);">
            <div class="modal-header" style="border-color: var(--border);">
                <h5 class="modal-title">Fanni tahrirlash</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e(Auth::getCsrfToken()) ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="subject_id" id="edit_subject_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Fan nomi</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Dasturlash tili</label>
                        <select name="programming_language" id="edit_lang" class="form-select" required>
                            <option value="cpp">C++</option>
                            <option value="java">Java</option>
                            <option value="python">Python</option>
                            <option value="javascript">JavaScript</option>
                            <option value="php">PHP</option>
                            <option value="csharp">C#</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_status" class="form-select">
                            <option value="active">Faol</option>
                            <option value="inactive">Yopiq</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tavsif</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="4"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-color: var(--border);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Bekor qilish</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Yangilash</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$inlineJs = <<<'JS'
document.querySelectorAll('.edit-subject-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const s = JSON.parse(btn.dataset.subject);
        document.getElementById('edit_subject_id').value = s.id;
        document.getElementById('edit_name').value = s.name;
        document.getElementById('edit_lang').value = s.programming_language;
        document.getElementById('edit_status').value = s.status;
        document.getElementById('edit_description').value = s.description || '';
        new bootstrap.Modal(document.getElementById('editSubjectModal')).show();
    });
});
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
