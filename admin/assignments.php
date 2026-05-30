<?php
$pageTitle = 'Biriktirish';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'CSRF token xato'); redirect($_SERVER['REQUEST_URI']);
    }
    
    $action = $_POST['action'] ?? '';
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    
    try {
        if ($action === 'assign_teachers') {
            db()->delete('subject_teachers', 'subject_id = :sid', ['sid' => $subjectId]);
            foreach ($_POST['teacher_ids'] ?? [] as $tid) {
                db()->insert('subject_teachers', ['subject_id' => $subjectId, 'teacher_id' => (int)$tid]);
            }
            setFlash('success', 'O\'qituvchilar biriktirildi');
        } elseif ($action === 'assign_students') {
            db()->delete('subject_students', 'subject_id = :sid', ['sid' => $subjectId]);
            foreach ($_POST['student_ids'] ?? [] as $sid) {
                db()->insert('subject_students', ['subject_id' => $subjectId, 'student_id' => (int)$sid]);
            }
            setFlash('success', 'Talabalar biriktirildi');
        }
    } catch (Exception $e) {
        setFlash('danger', $e->getMessage());
    }
    redirect($_SERVER['REQUEST_URI']);
}

$selectedSubjectId = (int)($_GET['subject_id'] ?? 0);
$subjects = db()->fetchAll("SELECT * FROM subjects ORDER BY name");

$selectedSubject = null;
$assignedTeachers = [];
$assignedStudents = [];
$allTeachers = [];
$allStudents = [];

if ($selectedSubjectId) {
    $selectedSubject = db()->fetchOne("SELECT * FROM subjects WHERE id = ?", [$selectedSubjectId]);
    if ($selectedSubject) {
        $assignedTeachers = array_column(db()->fetchAll(
            "SELECT teacher_id FROM subject_teachers WHERE subject_id = ?", [$selectedSubjectId]
        ), 'teacher_id');
        $assignedStudents = array_column(db()->fetchAll(
            "SELECT student_id FROM subject_students WHERE subject_id = ?", [$selectedSubjectId]
        ), 'student_id');
        $allTeachers = db()->fetchAll("SELECT id, full_name, username FROM users WHERE role='teacher' AND status='active' ORDER BY full_name");
        $allStudents = db()->fetchAll("SELECT id, full_name, username FROM users WHERE role='student' AND status='active' ORDER BY full_name");
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET">
            <label class="form-label">Fanni tanlang:</label>
            <div style="display: flex; gap: 0.75rem;">
                <select name="subject_id" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Fan tanlang --</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $selectedSubjectId === (int)$s['id'] ? 'selected' : '' ?>>
                            <?= e($s['name']) ?> (<?= strtoupper($s['programming_language']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedSubject): ?>
    <div class="row g-3">
        <!-- O'qituvchilarni biriktirish -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-chalkboard-teacher" style="color: var(--success);"></i> O'qituvchilar</h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= e(Auth::getCsrfToken()) ?>">
                        <input type="hidden" name="action" value="assign_teachers">
                        <input type="hidden" name="subject_id" value="<?= $selectedSubject['id'] ?>">
                        
                        <div style="max-height: 400px; overflow-y: auto; border: 1px solid var(--border); border-radius: var(--radius); padding: 0.5rem;">
                            <?php foreach ($allTeachers as $t): ?>
                                <label style="display: flex; align-items: center; padding: 0.5rem; border-radius: var(--radius); cursor: pointer; transition: var(--transition);" 
                                       onmouseover="this.style.background='var(--bg-tertiary)'" 
                                       onmouseout="this.style.background='transparent'">
                                    <input type="checkbox" name="teacher_ids[]" value="<?= $t['id'] ?>" 
                                           <?= in_array($t['id'], $assignedTeachers) ? 'checked' : '' ?>
                                           style="margin-right: 0.75rem; width: 18px; height: 18px;">
                                    <div class="user-avatar" style="width: 32px; height: 32px; font-size: 0.8rem; margin-right: 0.75rem;">
                                        <?= strtoupper(substr($t['full_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 500;"><?= e($t['full_name']) ?></div>
                                        <small style="color: var(--text-tertiary);">@<?= e($t['username']) ?></small>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block mt-3">
                            <i class="fas fa-save"></i> O'qituvchilarni biriktirish
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Talabalarni biriktirish -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-user-graduate" style="color: var(--primary);"></i> Talabalar</h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= e(Auth::getCsrfToken()) ?>">
                        <input type="hidden" name="action" value="assign_students">
                        <input type="hidden" name="subject_id" value="<?= $selectedSubject['id'] ?>">
                        
                        <input type="text" id="studentSearch" class="form-control mb-2" 
                               placeholder="Talabani qidirish...">
                        
                        <div style="margin-bottom: 0.5rem; display: flex; gap: 0.5rem;">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="toggleStudents(true)">
                                <i class="fas fa-check-square"></i> Barchasini tanlash
                            </button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="toggleStudents(false)">
                                <i class="fas fa-square"></i> Bekor qilish
                            </button>
                        </div>
                        
                        <div style="max-height: 350px; overflow-y: auto; border: 1px solid var(--border); border-radius: var(--radius); padding: 0.5rem;" id="studentsList">
                            <?php foreach ($allStudents as $s): ?>
                                <label class="student-item" style="display: flex; align-items: center; padding: 0.5rem; border-radius: var(--radius); cursor: pointer;" 
                                       data-name="<?= e(strtolower($s['full_name'] . ' ' . $s['username'])) ?>"
                                       onmouseover="this.style.background='var(--bg-tertiary)'" 
                                       onmouseout="this.style.background='transparent'">
                                    <input type="checkbox" name="student_ids[]" value="<?= $s['id'] ?>" 
                                           <?= in_array($s['id'], $assignedStudents) ? 'checked' : '' ?>
                                           class="student-checkbox" 
                                           style="margin-right: 0.75rem; width: 18px; height: 18px;">
                                    <div class="user-avatar" style="width: 32px; height: 32px; font-size: 0.8rem; margin-right: 0.75rem;">
                                        <?= strtoupper(substr($s['full_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 500;"><?= e($s['full_name']) ?></div>
                                        <small style="color: var(--text-tertiary);">@<?= e($s['username']) ?></small>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block mt-3">
                            <i class="fas fa-save"></i> Talabalarni biriktirish
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 3rem;">
            <i class="fas fa-link" style="font-size: 3rem; color: var(--text-tertiary); margin-bottom: 1rem;"></i>
            <h3 style="color: var(--text-tertiary);">Fanni tanlang</h3>
            <p style="color: var(--text-tertiary);">O'qituvchi va talabalarni biriktirish uchun fan tanlang</p>
        </div>
    </div>
<?php endif; ?>

<?php
$inlineJs = <<<'JS'
const search = document.getElementById('studentSearch');
if (search) {
    search.addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase();
        document.querySelectorAll('.student-item').forEach(item => {
            const name = item.dataset.name;
            item.style.display = name.includes(term) ? 'flex' : 'none';
        });
    });
}

function toggleStudents(state) {
    document.querySelectorAll('.student-checkbox').forEach(cb => {
        const item = cb.closest('.student-item');
        if (item.style.display !== 'none') {
            cb.checked = state;
        }
    });
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
