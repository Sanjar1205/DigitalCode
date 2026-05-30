<?php
$pageTitle = 'Test savollari';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('teacher');

$teacherId = $_SESSION['user_id'];
$topicId = (int)($_GET['topic_id'] ?? 0);

// Topic tekshirish
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
            $qid = db()->insert('questions', [
                'topic_id' => $topicId,
                'question_text' => trim($_POST['question_text']),
                'question_type' => $_POST['question_type'],
                'points' => (int)($_POST['points'] ?? 1)
            ]);
            
            $answers = $_POST['answers'] ?? [];
            $correct = $_POST['correct'] ?? [];
            
            // single, multiple va true_false uchun farqli logika
            if ($_POST['question_type'] === 'true_false') {
                db()->insert('answers', [
                    'question_id' => $qid, 'answer_text' => 'Ha (True)',
                    'is_correct' => $_POST['tf_answer'] === 'true' ? 1 : 0, 'order_number' => 1
                ]);
                db()->insert('answers', [
                    'question_id' => $qid, 'answer_text' => 'Yo\'q (False)',
                    'is_correct' => $_POST['tf_answer'] === 'false' ? 1 : 0, 'order_number' => 2
                ]);
            } else {
                foreach ($answers as $idx => $text) {
                    if (empty(trim($text))) continue;
                    $isCorrect = 0;
                    if ($_POST['question_type'] === 'single') {
                        $isCorrect = (isset($correct[0]) && (int)$correct[0] === $idx) ? 1 : 0;
                    } elseif ($_POST['question_type'] === 'multiple') {
                        $isCorrect = in_array($idx, $correct) ? 1 : 0;
                    }
                    db()->insert('answers', [
                        'question_id' => $qid, 'answer_text' => trim($text),
                        'is_correct' => $isCorrect, 'order_number' => $idx + 1
                    ]);
                }
            }
            setFlash('success', 'Savol qo\'shildi');
            
        } elseif ($action === 'delete') {
            db()->delete('questions', 'id = :id', ['id' => (int)$_POST['question_id']]);
            setFlash('success', 'Savol o\'chirildi');
        }
    } catch (Exception $e) {
        setFlash('danger', $e->getMessage());
    }
    redirect($_SERVER['REQUEST_URI']);
}

// Teacher fanlarining barcha mavzulari
$myTopics = db()->fetchAll(
    "SELECT t.id, t.title, s.name as subject_name 
     FROM topics t 
     JOIN subjects s ON t.subject_id = s.id 
     JOIN subject_teachers st ON s.id = st.subject_id 
     WHERE st.teacher_id = ? 
     ORDER BY s.name, t.order_number", [$teacherId]
);

$questions = [];
if ($topic) {
    $questions = db()->fetchAll(
        "SELECT q.*, 
            (SELECT COUNT(*) FROM answers WHERE question_id = q.id) as answer_count
         FROM questions q WHERE q.topic_id = ? ORDER BY q.id", [$topicId]
    );
    
    foreach ($questions as &$q) {
        $q['answers'] = db()->fetchAll("SELECT * FROM answers WHERE question_id = ? ORDER BY order_number", [$q['id']]);
    }
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
        <h2 style="margin: 0; flex: 1;"><?= e($topic['title']) ?> — Savollar</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createQuestionModal">
            <i class="fas fa-plus"></i> Yangi savol
        </button>
    </div>
    
    <?php if (empty($questions)): ?>
        <div class="card">
            <div class="card-body" style="text-align: center; padding: 3rem;">
                <i class="fas fa-question-circle" style="font-size: 3rem; color: var(--text-tertiary);"></i>
                <h3 style="color: var(--text-tertiary); margin-top: 1rem;">Hech qanday savol yo'q</h3>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($questions as $i => $q): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                        <div style="flex: 1;">
                            <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <span class="badge badge-primary">#<?= $i + 1 ?></span>
                                <span class="badge badge-info">
                                    <?= ['single' => 'Bir javob', 'multiple' => 'Bir necha javob', 'true_false' => 'Rost/Yolg\'on'][$q['question_type']] ?>
                                </span>
                                <span class="badge badge-warning"><?= $q['points'] ?> ball</span>
                            </div>
                            <p style="margin: 0; font-size: 1.05rem; font-weight: 500;"><?= e($q['question_text']) ?></p>
                        </div>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?= e(Auth::getCsrfToken()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger" 
                                    data-confirm="Savolni o'chirmoqchimisiz?">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                    
                    <div style="background: var(--bg-tertiary); padding: 0.75rem; border-radius: var(--radius);">
                        <?php foreach ($q['answers'] as $j => $a): ?>
                            <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.4rem 0;">
                                <?php if ($a['is_correct']): ?>
                                    <i class="fas fa-check-circle" style="color: var(--success); font-size: 1.1rem;"></i>
                                <?php else: ?>
                                    <i class="far fa-circle" style="color: var(--text-tertiary);"></i>
                                <?php endif; ?>
                                <span style="<?= $a['is_correct'] ? 'font-weight: 600; color: var(--success);' : '' ?>">
                                    <?= e($a['answer_text']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
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

<!-- Modal: Yangi savol -->
<?php if ($topic): ?>
<div class="modal fade" id="createQuestionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background: var(--bg-secondary); color: var(--text-primary);">
            <div class="modal-header" style="border-color: var(--border);">
                <h5 class="modal-title">Yangi test savoli</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e(Auth::getCsrfToken()) ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Savol matni <span class="required">*</span></label>
                        <textarea name="question_text" class="form-control" rows="2" required></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Savol turi</label>
                            <select name="question_type" id="question_type" class="form-select" onchange="changeQuestionType()">
                                <option value="single">Bir javob (radio)</option>
                                <option value="multiple">Bir necha javob (checkbox)</option>
                                <option value="true_false">Rost/Yolg'on</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ball</label>
                            <input type="number" name="points" class="form-control" value="1" min="1">
                        </div>
                    </div>
                    
                    <div id="answersContainer" style="margin-top: 1rem;">
                        <label class="form-label">Javob variantlari</label>
                        <div id="answersList">
                            <?php for ($i = 0; $i < 4; $i++): ?>
                                <div class="answer-item" style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                                    <input type="radio" name="correct[]" value="<?= $i ?>" class="correct-input">
                                    <input type="text" name="answers[]" class="form-control" placeholder="Javob <?= $i + 1 ?>">
                                </div>
                            <?php endfor; ?>
                        </div>
                        <small class="text-muted">To'g'ri javobni belgilang</small>
                    </div>
                    
                    <div id="trueFalseContainer" style="display: none; margin-top: 1rem;">
                        <label class="form-label">To'g'ri javob</label>
                        <select name="tf_answer" class="form-select">
                            <option value="true">Ha (True)</option>
                            <option value="false">Yo'q (False)</option>
                        </select>
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
function changeQuestionType() {
    const type = document.getElementById('question_type').value;
    const answersContainer = document.getElementById('answersContainer');
    const tfContainer = document.getElementById('trueFalseContainer');
    
    if (type === 'true_false') {
        answersContainer.style.display = 'none';
        tfContainer.style.display = 'block';
    } else {
        answersContainer.style.display = 'block';
        tfContainer.style.display = 'none';
        
        // Multi yoki single tanlovga qarab inputlarni o'zgartirish
        document.querySelectorAll('.correct-input').forEach((inp, idx) => {
            inp.type = type === 'single' ? 'radio' : 'checkbox';
            inp.name = type === 'single' ? 'correct[]' : 'correct[]';
        });
    }
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
