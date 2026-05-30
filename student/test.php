<?php
$pageTitle = 'Test';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('student');

$studentId = (int)$_SESSION['user_id'];
$topicId = (int)($_GET['topic_id'] ?? 0);

// Mavzu va ruxsat tekshirish
$topic = db()->fetchOne(
    "SELECT t.* FROM topics t 
     JOIN subject_students ss ON t.subject_id = ss.subject_id 
     WHERE t.id = ? AND ss.student_id = ?", [$topicId, $studentId]
);

if (!$topic) {
    setFlash('danger', 'Bu mavzuga ruxsatingiz yo\'q');
    redirect(SITE_URL . '/student/my_subjects.php');
}

// Mavzu ochiqmi
if (!isTopicUnlocked($studentId, $topicId)) {
    setFlash('danger', 'Bu mavzu hali qulflangan');
    redirect(SITE_URL . '/student/my_subjects.php');
}

// Avval matn va video yakunlanganmi
$status = getTopicCompletionStatus($studentId, $topicId);
$videoId = getYoutubeId($topic['video_url'] ?? '');

if (!$status['content_read'] || ($videoId && !$status['video_watched'])) {
    setFlash('warning', 'Test topshirish uchun avval matn va videoni yakunlang');
    redirect(SITE_URL . "/student/learn.php?subject_id={$topic['subject_id']}&topic_id=$topicId");
}

// ============================================================
// TEST JAVOBLARINI QABUL QILISH
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'CSRF token xato'); 
        redirect($_SERVER['REQUEST_URI']);
    }
    
    // Joriy urinish raqamini olish
    $maxAttemptRow = db()->fetchOne(
        "SELECT MAX(attempt_number) as m FROM test_results WHERE student_id = ? AND topic_id = ?",
        [$studentId, $topicId]
    );
    $attemptNumber = ((int)($maxAttemptRow['m'] ?? 0)) + 1;
    
    // Foydalanuvchi javoblarini olish
    $answers = $_POST['answer'] ?? [];
    
    // Savollarni olish
    $questions = db()->fetchAll("SELECT * FROM questions WHERE topic_id = ? ORDER BY id", [$topicId]);
    
    $totalPoints = 0;
    $earnedPoints = 0;
    
    foreach ($questions as $q) {
        $qId = (int)$q['id'];
        $qPoints = (int)$q['points'];
        $totalPoints += $qPoints;
        
        // Foydalanuvchi javobini normalize qilish
        $userAnswer = $answers[$qId] ?? null;
        
        if ($userAnswer === null || $userAnswer === '') {
            $userAnswerArr = [];
        } elseif (is_array($userAnswer)) {
            $userAnswerArr = $userAnswer;
        } else {
            $userAnswerArr = [$userAnswer];
        }
        
        // Hammasini int qilib, 0 va bo'shlarni olib tashlash
        $userAnswerArr = array_map('intval', $userAnswerArr);
        $userAnswerArr = array_values(array_filter($userAnswerArr, fn($v) => $v > 0));
        sort($userAnswerArr, SORT_NUMERIC);
        
        // To'g'ri javoblarni olish (is_correct = 1)
        $correctRows = db()->fetchAll(
            "SELECT id FROM answers WHERE question_id = ? AND is_correct = 1", 
            [$qId]
        );
        $correctAnswers = array_map(fn($r) => (int)$r['id'], $correctRows);
        sort($correctAnswers, SORT_NUMERIC);
        
        // Solishtirish
        $isCorrect = false;
        if (!empty($userAnswerArr) && !empty($correctAnswers)) {
            $isCorrect = ($userAnswerArr === $correctAnswers);
        }
        
        $pointsEarned = $isCorrect ? $qPoints : 0;
        $earnedPoints += $pointsEarned;
        
        // Natijani saqlash
        db()->insert('test_results', [
            'student_id' => $studentId,
            'topic_id' => $topicId,
            'question_id' => $qId,
            'selected_answers' => implode(',', $userAnswerArr),
            'is_correct' => $isCorrect ? 1 : 0,
            'points_earned' => $pointsEarned,
            'attempt_number' => $attemptNumber
        ]);
    }
    
    $scorePercent = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 100 : 0;
    $passingScore = (int)($topic['passing_score'] ?? 60);
    $passed = $scorePercent >= $passingScore;
    
    // Progressni yangilash
    $existing = db()->fetchOne(
        "SELECT * FROM student_progress WHERE student_id = ? AND topic_id = ?", 
        [$studentId, $topicId]
    );
    
    $updateData = [
        'test_score' => round($scorePercent, 2),
        'test_passed' => $passed ? 1 : 0
    ];
    
    if ($existing) {
        db()->update('student_progress', $updateData, 
            'student_id = :sid AND topic_id = :tid', 
            ['sid' => $studentId, 'tid' => $topicId]);
    } else {
        db()->insert('student_progress', array_merge($updateData, [
            'student_id' => $studentId, 
            'topic_id' => $topicId, 
            'unlocked' => 1
        ]));
    }
    
    // O'tgan bo'lsa baho qo'yish (faqat birinchi marta)
    if ($passed) {
        $existingGrade = db()->fetchOne(
            "SELECT id FROM grades WHERE student_id = ? AND topic_id = ? AND type = 'test'",
            [$studentId, $topicId]
        );
        
        if (!$existingGrade) {
            $grade = calculateGrade($scorePercent);
            db()->insert('grades', [
                'student_id' => $studentId,
                'subject_id' => $topic['subject_id'],
                'topic_id' => $topicId,
                'grade' => $grade,
                'type' => 'test',
                'comment' => "Test natijasi: " . round($scorePercent, 2) . "%",
                'given_by' => $studentId
            ]);
        }
    }
    
    $_SESSION['test_result'] = [
        'score' => round($scorePercent, 2),
        'passed' => $passed,
        'earned' => $earnedPoints,
        'total' => $totalPoints,
        'attempt' => $attemptNumber,
        'passing_score' => $passingScore
    ];
    
    redirect(SITE_URL . "/student/test.php?topic_id=$topicId&result=1");
}

// ============================================================
// NATIJANI KO'RSATISH
// ============================================================
$showResult = isset($_GET['result']) && isset($_SESSION['test_result']);
$result = $showResult ? $_SESSION['test_result'] : null;
if ($showResult) unset($_SESSION['test_result']);

// Savollarni yuklash
$questions = db()->fetchAll(
    "SELECT * FROM questions WHERE topic_id = ? ORDER BY id", 
    [$topicId]
);
foreach ($questions as $i => $q) {
    $questions[$i]['answers'] = db()->fetchAll(
        "SELECT * FROM answers WHERE question_id = ? ORDER BY order_number, id", 
        [$q['id']]
    );
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($showResult): ?>
    <!-- ============ NATIJA SAHIFASI ============ -->
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 3rem;">
            <?php if ($result['passed']): ?>
                <div style="font-size: 5rem; color: var(--success); margin-bottom: 1rem;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2 style="color: var(--success);">Tabriklaymiz!</h2>
                <p style="color: var(--text-secondary);">Test muvaffaqiyatli o'tildi 🎉</p>
            <?php else: ?>
                <div style="font-size: 5rem; color: var(--danger); margin-bottom: 1rem;">
                    <i class="fas fa-times-circle"></i>
                </div>
                <h2 style="color: var(--danger);">Test o'tilmadi</h2>
            <?php endif; ?>
            
            <p style="font-size: 1.1rem; color: var(--text-secondary); margin: 1rem 0;">
                <?= $result['earned'] ?>/<?= $result['total'] ?> ball to'pladingiz
            </p>
            
            <div style="font-size: 3rem; font-weight: 700; color: <?= $result['passed'] ? 'var(--success)' : 'var(--danger)' ?>;">
                <?= $result['score'] ?>%
            </div>
            
            <p style="color: var(--text-tertiary);">
                O'tish bali: <?= $result['passing_score'] ?? 60 ?>% | Urinish #<?= $result['attempt'] ?>
            </p>
            
            <div style="margin-top: 2rem; display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
                <a href="<?= SITE_URL ?>/student/learn.php?subject_id=<?= $topic['subject_id'] ?>&topic_id=<?= $topicId ?>" 
                   class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Mavzuga qaytish
                </a>
                <?php if (!$result['passed']): ?>
                    <a href="<?= SITE_URL ?>/student/test.php?topic_id=<?= $topicId ?>" class="btn btn-warning">
                        <i class="fas fa-redo"></i> Qaytadan urinish
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- ============ TEST FORMASI ============ -->
    <div style="margin-bottom: 1rem;">
        <a href="<?= SITE_URL ?>/student/learn.php?subject_id=<?= $topic['subject_id'] ?>&topic_id=<?= $topicId ?>" 
           class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Orqaga
        </a>
    </div>
    
    <div class="card mb-3">
        <div class="card-body">
            <h2><?= e($topic['title']) ?> — Test</h2>
            <p style="color: var(--text-secondary); margin: 0;">
                Jami <strong><?= count($questions) ?></strong> ta savol. 
                O'tish uchun <strong><?= (int)$topic['passing_score'] ?>%</strong> bal kerak.
            </p>
        </div>
    </div>
    
    <?php if (empty($questions)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            Bu mavzu uchun test savollari hali qo'shilmagan.
        </div>
    <?php else: ?>
    <form method="POST" id="testForm">
        <input type="hidden" name="csrf_token" value="<?= e(Auth::getCsrfToken()) ?>">
        
        <?php foreach ($questions as $i => $q): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <div style="display: flex; align-items: start; gap: 0.75rem; margin-bottom: 1rem;">
                        <span class="badge badge-primary" style="flex-shrink: 0;">
                            <?= $i + 1 ?>/<?= count($questions) ?>
                        </span>
                        <div style="flex: 1;">
                            <p style="font-size: 1.05rem; font-weight: 500; margin: 0;">
                                <?= e($q['question_text']) ?>
                            </p>
                            <small style="color: var(--text-tertiary);">
                                <?php
                                $typeText = [
                                    'single' => 'Bitta to\'g\'ri javobni tanlang',
                                    'multiple' => 'Bir nechta javobni tanlash mumkin',
                                    'true_false' => 'Rost yoki Yolg\'on'
                                ][$q['question_type']] ?? '';
                                ?>
                                <?= $typeText ?> · <?= (int)$q['points'] ?> ball
                            </small>
                        </div>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <?php foreach ($q['answers'] as $a): ?>
                            <label style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; border: 1px solid var(--border); border-radius: var(--radius); cursor: pointer; transition: var(--transition);"
                                   onmouseover="this.style.borderColor='var(--primary)'; this.style.background='var(--primary-light)'" 
                                   onmouseout="this.style.borderColor='var(--border)'; this.style.background='transparent'">
                                <?php if ($q['question_type'] === 'multiple'): ?>
                                    <input type="checkbox" 
                                           name="answer[<?= (int)$q['id'] ?>][]" 
                                           value="<?= (int)$a['id'] ?>" 
                                           style="width: 18px; height: 18px;">
                                <?php else: ?>
                                    <input type="radio" 
                                           name="answer[<?= (int)$q['id'] ?>]" 
                                           value="<?= (int)$a['id'] ?>" 
                                           style="width: 18px; height: 18px;" 
                                           required>
                                <?php endif; ?>
                                <span><?= e($a['answer_text']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div style="position: sticky; bottom: 0; background: var(--bg-secondary); padding: 1rem; border-top: 1px solid var(--border); margin: 0 -2rem;">
            <button type="submit" class="btn btn-primary btn-lg btn-block" id="submitBtn">
                <i class="fas fa-check"></i> Testni yakunlash
            </button>
        </div>
    </form>
    
    <script>
    document.getElementById('testForm').addEventListener('submit', function(e) {
        // Multiple savollarda kamida 1 ta tanlangan bo'lishi shart
        const checkboxes = document.querySelectorAll('input[type="checkbox"][name^="answer["]');
        const groups = {};
        checkboxes.forEach(cb => {
            if (!groups[cb.name]) groups[cb.name] = 0;
            if (cb.checked) groups[cb.name]++;
        });
        
        for (const name in groups) {
            if (groups[name] === 0) {
                e.preventDefault();
                alert("Iltimos, bir nechta javobli savollarda kamida bitta variantni tanlang!");
                return;
            }
        }
        
        if (!confirm('Testni yakunlamoqchimisiz?')) {
            e.preventDefault();
        }
    });
    </script>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
