<?php
$pageTitle = 'Amaliy masalalar';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('teacher');

$teacherId = $_SESSION['user_id'];
$topicId = (int)($_GET['topic_id'] ?? 0);

$topic = null;
if ($topicId) {
    $topic = db()->fetchOne(
        "SELECT t.*, s.name as subject_name FROM topics t 
         JOIN subjects s ON t.subject_id = s.id 
         JOIN subject_teachers st ON s.id = st.subject_id 
         WHERE t.id = ? AND st.teacher_id = ?", [$topicId, $teacherId]
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $topic) {
    if (!Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'CSRF token xato'); redirect($_SERVER['REQUEST_URI']);
    }
    
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            $taskId = db()->insert('tasks', [
                'topic_id' => $topicId,
                'title' => trim($_POST['title']),
                'description' => $_POST['description'],
                'input_example' => $_POST['input_example'] ?? '',
                'output_example' => $_POST['output_example'] ?? '',
                'time_limit' => (int)($_POST['time_limit'] ?? 1000),
                'memory_limit' => (int)($_POST['memory_limit'] ?? 256),
                'difficulty' => $_POST['difficulty']
            ]);
            
            $inputs = $_POST['test_inputs'] ?? [];
            $outputs = $_POST['test_outputs'] ?? [];
            $hidden = $_POST['test_hidden'] ?? [];
            
            foreach ($inputs as $idx => $input) {
                if (empty(trim($input))) continue;
                db()->insert('test_cases', [
                    'task_id' => $taskId,
                    'input_data' => $input,
                    'expected_output' => $outputs[$idx] ?? '',
                    'is_hidden' => isset($hidden[$idx]) ? 1 : 0,
                    'order_number' => $idx + 1
                ]);
            }
            setFlash('success', 'Masala qo\'shildi');
            
        } elseif ($action === 'delete') {
            db()->delete('tasks', 'id = :id', ['id' => (int)$_POST['task_id']]);
            setFlash('success', 'Masala o\'chirildi');
        }
    } catch (Exception $e) {
        setFlash('danger', $e->getMessage());
    }
    redirect($_SERVER['REQUEST_URI']);
}

$myTopics = db()->fetchAll(
    "SELECT t.id, t.title, s.name as subject_name 
     FROM topics t 
     JOIN subjects s ON t.subject_id = s.id 
     JOIN subject_teachers st ON s.id = st.subject_id 
     WHERE st.teacher_id = ? 
     ORDER BY s.name, t.order_number", [$teacherId]
);

$tasks = [];
if ($topic) {
    $tasks = db()->fetchAll(
        "SELECT t.*, 
            (SELECT COUNT(*) FROM test_cases WHERE task_id = t.id) as test_count,
            (SELECT COUNT(*) FROM task_submissions WHERE task_id = t.id) as submission_count
         FROM tasks t WHERE t.topic_id = ?", [$topicId]
    );
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET">
            <label class="form-label">Mavzuni tanlang:</label>
            <select name="topic_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- Mavzu tanlang --</option>
                <?php foreach ($myTopics as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $topicId === (int)$t['id'] ? 'selected' : '' ?>>
                        <?= e($t['subject_name']) ?> — <?= e($t['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<?php if ($topic): ?>
    <div class="toolbar">
        <h2 style="margin: 0; flex: 1;"><?= e($topic['title']) ?> — Amaliy masalalar</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
            <i class="fas fa-plus"></i> Yangi masala
        </button>
    </div>
    
    <?php if (empty($tasks)): ?>
        <div class="card">
            <div class="card-body" style="text-align: center; padding: 3rem;">
                <i class="fas fa-code" style="font-size: 3rem; color: var(--text-tertiary);"></i>
                <h3 style="color: var(--text-tertiary); margin-top: 1rem;">Hech qanday masala yo'q</h3>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($tasks as $t): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                        <div style="flex: 1;">
                            <h3 style="margin-bottom: 0.5rem;"><?= e($t['title']) ?></h3>
                            <div style="display: flex; gap: 0.5rem; margin-bottom: 0.75rem;">
                                <span class="badge badge-<?= ['easy'=>'success','medium'=>'warning','hard'=>'danger'][$t['difficulty']] ?>">
                                    <?= ['easy'=>'Oson','medium'=>'O\'rta','hard'=>'Qiyin'][$t['difficulty']] ?>
                                </span>
                                <span class="badge badge-info"><i class="fas fa-clock"></i> <?= $t['time_limit'] ?>ms</span>
                                <span class="badge badge-info"><i class="fas fa-memory"></i> <?= $t['memory_limit'] ?>MB</span>
                                <span class="badge badge-secondary"><i class="fas fa-vial"></i> <?= $t['test_count'] ?> test</span>
                                <span class="badge badge-primary"><i class="fas fa-code"></i> <?= $t['submission_count'] ?> topshiriq</span>
                            </div>
                            <div style="color: var(--text-secondary); white-space: pre-wrap; font-size: 0.9rem;"><?= e(mb_substr($t['description'], 0, 200)) ?>...</div>
                        </div>
                        <form method="POST" style="display: inline; margin-left: 1rem;">
                            <input type="hidden" name="csrf_token" value="<?= e(Auth::getCsrfToken()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger" 
                                    data-confirm="Masalani o'chirmoqchimisiz?">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php else: ?>
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 3rem;">
            <i class="fas fa-arrow-up" style="font-size: 2rem; color: var(--text-tertiary);"></i>
            <h3 style="color: var(--text-tertiary); margin-top: 1rem;">Mavzuni tanlang</h3>
        </div>
    </div>
<?php endif; ?>

<!-- Modal: Yangi masala -->
<?php if ($topic): ?>
<div class="modal fade" id="createTaskModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content" style="background: var(--bg-secondary); color: var(--text-primary);">
            <div class="modal-header" style="border-color: var(--border);">
                <h5 class="modal-title">Yangi amaliy masala</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e(Auth::getCsrfToken()) ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Masala nomi <span class="required">*</span></label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Qiyinligi</label>
                            <select name="difficulty" class="form-select">
                                <option value="easy">Oson</option>
                                <option value="medium" selected>O'rta</option>
                                <option value="hard">Qiyin</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Masala matni (HTML qabul qilinadi)</label>
                            <textarea name="description" class="form-control" rows="6" required placeholder="Masalani batafsil tushuntirib bering..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kirish (input) misoli</label>
                            <textarea name="input_example" class="form-control code-font" rows="3" placeholder="5 3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Chiqish (output) misoli</label>
                            <textarea name="output_example" class="form-control code-font" rows="3" placeholder="8"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Vaqt limit (ms)</label>
                            <input type="number" name="time_limit" class="form-control" value="1000" min="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Xotira limit (MB)</label>
                            <input type="number" name="memory_limit" class="form-control" value="256" min="16">
                        </div>
                    </div>
                    
                    <hr style="margin: 1.5rem 0;">
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                        <h5 style="margin: 0;">Test caselar</h5>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="addTestCase()">
                            <i class="fas fa-plus"></i> Test qo'shish
                        </button>
                    </div>
                    
                    <div id="testCases">
                        <div class="test-case" style="border: 1px solid var(--border); padding: 1rem; border-radius: var(--radius); margin-bottom: 0.75rem;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                <strong>Test #1</strong>
                                <label>
                                    <input type="checkbox" name="test_hidden[0]" value="1" checked> Yashirin
                                </label>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">Input</label>
                                    <textarea name="test_inputs[]" class="form-control code-font" rows="2" placeholder="5 3"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Expected output</label>
                                    <textarea name="test_outputs[]" class="form-control code-font" rows="2" placeholder="8"></textarea>
                                </div>
                            </div>
                        </div>
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
<?php endif; ?>

<?php
$inlineJs = <<<'JS'
let testCount = 1;
function addTestCase() {
    testCount++;
    const div = document.createElement('div');
    div.className = 'test-case';
    div.style.cssText = 'border: 1px solid var(--border); padding: 1rem; border-radius: var(--radius); margin-bottom: 0.75rem;';
    div.innerHTML = `
        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
            <strong>Test #${testCount}</strong>
            <div>
                <label><input type="checkbox" name="test_hidden[${testCount-1}]" value="1" checked> Yashirin</label>
                <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.test-case').remove()">×</button>
            </div>
        </div>
        <div class="row g-2">
            <div class="col-md-6">
                <textarea name="test_inputs[]" class="form-control code-font" rows="2" placeholder="Input"></textarea>
            </div>
            <div class="col-md-6">
                <textarea name="test_outputs[]" class="form-control code-font" rows="2" placeholder="Expected output"></textarea>
            </div>
        </div>
    `;
    document.getElementById('testCases').appendChild(div);
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
