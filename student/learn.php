<?php
$pageTitle = 'O\'qish';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('student');

$studentId = $_SESSION['user_id'];
$subjectId = (int)($_GET['subject_id'] ?? 0);
$topicId   = (int)($_GET['topic_id'] ?? 0);

// Fan tekshirish
$subject = db()->fetchOne(
    "SELECT s.* FROM subjects s 
     JOIN subject_students ss ON s.id = ss.subject_id 
     WHERE s.id = ? AND ss.student_id = ?",
    [$subjectId, $studentId]
);
if (!$subject) {
    setFlash('danger', 'Bu fanga ruxsatingiz yo\'q');
    redirect(SITE_URL . '/student/my_subjects.php');
}

// Mavzular ro'yxati
$topics = db()->fetchAll(
    "SELECT * FROM topics WHERE subject_id = ? AND status = 'published' ORDER BY order_number",
    [$subjectId]
);

// Tanlangan mavzu
$currentTopic = null;
if ($topicId) {
    foreach ($topics as $t) {
        if ($t['id'] == $topicId) { $currentTopic = $t; break; }
    }
}
if (!$currentTopic && !empty($topics)) {
    $currentTopic = $topics[0];
    $topicId = $currentTopic['id'];
}

// Mavzu ochiqmi?
if ($currentTopic && !isTopicUnlocked($studentId, $currentTopic['id'])) {
    foreach ($topics as $t) {
        if (isTopicUnlocked($studentId, $t['id'])) {
            redirect(SITE_URL . "/student/learn.php?subject_id=$subjectId&topic_id={$t['id']}");
        }
    }
}

// Progressni boshlash
if ($currentTopic) {
    $existing = db()->fetchOne(
        "SELECT id FROM student_progress WHERE student_id = ? AND topic_id = ?",
        [$studentId, $currentTopic['id']]
    );
    if (!$existing) {
        db()->insert('student_progress', [
            'student_id' => $studentId,
            'topic_id'   => $currentTopic['id'],
            'unlocked'   => 1
        ]);
    }
}

// Progress holati
$status = $currentTopic ? getTopicCompletionStatus($studentId, $currentTopic['id']) : null;

// Joriy indeks
$currentTopicIndex = 0;
if ($currentTopic) {
    foreach ($topics as $i => $t) {
        if ($t['id'] == $currentTopic['id']) { $currentTopicIndex = $i; break; }
    }
}

// Savollar soni
$questionCount = $currentTopic ? (int)db()->fetchOne(
    "SELECT COUNT(*) as cnt FROM questions WHERE topic_id = ?",
    [$currentTopic['id']]
)['cnt'] : 0;

// Amaliy ish: o'qituvchi joylagan fayl (topic_files da type='practical')
$practicalFile = $currentTopic ? db()->fetchOne(
    "SELECT * FROM topic_files WHERE topic_id = ? AND file_type = 'practical' ORDER BY id DESC LIMIT 1",
    [$currentTopic['id']]
) : null;

// Talabaning amaliy ish topshirig'i (submissions jadvalida type='practical')
$practicalSubmission = $currentTopic ? db()->fetchOne(
    "SELECT * FROM submissions WHERE student_id = ? AND topic_id = ? AND type = 'practical' ORDER BY id DESC LIMIT 1",
    [$studentId, $currentTopic['id']]
) : null;

// Talabaning mustaqil ish topshirig'i (submissions jadvalida type='independent')
$independentSubmission = $currentTopic ? db()->fetchOne(
    "SELECT * FROM submissions WHERE student_id = ? AND topic_id = ? AND type = 'independent' ORDER BY id DESC LIMIT 1",
    [$studentId, $currentTopic['id']]
) : null;

// Kod editor uchun task
$task = $currentTopic ? db()->fetchOne(
    "SELECT * FROM tasks WHERE topic_id = ? LIMIT 1",
    [$currentTopic['id']]
) : null;

$videoId = $currentTopic && $currentTopic['video_url'] ? getYoutubeId($currentTopic['video_url']) : null;

// Uploadcare public key

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Uploadcare widget -->


<style>
.learn-layout {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 1.5rem;
}
@media (max-width: 992px) {
    .learn-layout { grid-template-columns: 1fr; }
}
.topics-sidebar {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    overflow: hidden;
    align-self: flex-start;
    position: sticky;
    top: calc(var(--header-height) + 1rem);
    max-height: calc(100vh - var(--header-height) - 2rem);
    overflow-y: auto;
}
.topic-sidebar-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border);
    text-decoration: none;
    color: var(--text-primary);
    transition: var(--transition);
}
.topic-sidebar-item:hover:not(.locked) {
    background: var(--bg-tertiary);
    color: var(--text-primary);
}
.topic-sidebar-item.locked {
    cursor: not-allowed;
    opacity: 0.55;
    background: var(--bg-tertiary);
}
.topic-sidebar-item.active {
    background: var(--primary-light);
    border-left: 3px solid var(--primary);
}
.topic-mini-num {
    width: 32px; height: 32px; border-radius: 50%;
    background: var(--primary-light); color: var(--primary);
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 0.85rem; flex-shrink: 0;
}
.topic-sidebar-item.completed .topic-mini-num { background: var(--success); color: white; }
.topic-sidebar-item.locked .topic-mini-num   { background: var(--bg-tertiary); color: var(--text-tertiary); }

.topic-content-area {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    overflow: hidden;
}
.section-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border);
    background: var(--bg-tertiary);
}
.section-header h3 { margin: 0; }
.section-body { padding: 1.5rem; }

.content-text {
    padding: 2rem;
    line-height: 1.8;
    font-size: 1rem;
}
.content-text h1, .content-text h2, .content-text h3 { margin: 1.5rem 0 1rem; }
.content-text p { margin: 0.75rem 0; }
.content-text pre {
    background: #1e293b; color: #e2e8f0;
    padding: 1rem; border-radius: var(--radius);
    overflow-x: auto; font-family: 'JetBrains Mono', monospace;
    font-size: 0.9rem;
}
.content-text code {
    background: var(--bg-tertiary); padding: 0.15rem 0.4rem;
    border-radius: 4px; font-size: 0.9em;
}
.content-text pre code { background: transparent; padding: 0; }
.content-text ul, .content-text ol { margin: 1rem 0; padding-left: 2rem; }
.content-text img { max-width: 100%; border-radius: var(--radius); margin: 1rem 0; }

.video-container {
    position: relative; padding-bottom: 56.25%;
    height: 0; overflow: hidden;
}
.video-container iframe {
    position: absolute; top: 0; left: 0;
    width: 100%; height: 100%; border: 0;
}

.steps-progress {
    background: var(--bg-tertiary);
    padding: 1rem 1.5rem;
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    border-bottom: 1px solid var(--border);
}
.step-item {
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.85rem;
}
.step-icon {
    width: 28px; height: 28px; border-radius: 50%;
    background: var(--bg-secondary); color: var(--text-tertiary);
    border: 2px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; flex-shrink: 0;
}
.step-item.done .step-icon {
    background: var(--success); color: white; border-color: var(--success);
}
.step-item.in-progress .step-icon {
    background: var(--warning); color: white; border-color: var(--warning);
    animation: pulse 1.5s ease-in-out infinite;
}
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

/* Upload zona */
.upload-zone {
    border: 2px dashed var(--border);
    border-radius: var(--radius-md);
    padding: 2.5rem;
    text-align: center;
    transition: var(--transition);
    cursor: pointer;
}
.upload-zone:hover { border-color: var(--primary); background: var(--primary-light); }

/* AI natija kartasi */
.ai-result-card {
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    overflow: hidden;
}
.ai-result-header {
    padding: 0.75rem 1.25rem;
    background: var(--bg-tertiary);
    display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid var(--border);
}
.ai-result-body { padding: 1.25rem; }

/* Amaliy ish topshiriq fayl */
.practical-file-card {
    display: flex; align-items: center; gap: 1rem;
    padding: 1rem 1.25rem;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background: var(--bg-tertiary);
    margin-bottom: 1.25rem;
}
.practical-file-icon {
    width: 44px; height: 44px; border-radius: var(--radius);
    background: var(--primary-light); color: var(--primary);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; flex-shrink: 0;
}
</style>

<div class="learn-layout">
    <!-- ═══════════════════════════════════════════════════
         CHAP PANEL: Mavzular ro'yxati
    ════════════════════════════════════════════════════ -->
    <aside class="topics-sidebar">
        <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); background: var(--bg-tertiary);">
            <strong><?= e($subject['name']) ?></strong>
            <div style="font-size: 0.8rem; color: var(--text-tertiary); margin-top: 0.25rem;">
                <i class="fas fa-list"></i> <?= count($topics) ?> ta mavzu
            </div>
        </div>

        <?php foreach ($topics as $idx => $t):
            $isUnlocked = isTopicUnlocked($studentId, $t['id']);
            $tStatus    = getTopicCompletionStatus($studentId, $t['id']);
            $isCurrent  = $t['id'] == $topicId;
            $classes    = [];
            if ($isCurrent)            $classes[] = 'active';
            if (!$isUnlocked)          $classes[] = 'locked';
            if ($tStatus['is_complete']) $classes[] = 'completed';
        ?>
            <?php if ($isUnlocked): ?>
                <a href="<?= SITE_URL ?>/student/learn.php?subject_id=<?= $subjectId ?>&topic_id=<?= $t['id'] ?>"
                   class="topic-sidebar-item <?= implode(' ', $classes) ?>">
            <?php else: ?>
                <div class="topic-sidebar-item <?= implode(' ', $classes) ?>"
                     title="Bu mavzu qulflangan. Avvalgi mavzularni yakunlang.">
            <?php endif; ?>
                <div class="topic-mini-num">
                    <?php if (!$isUnlocked): ?>
                        <i class="fas fa-lock"></i>
                    <?php elseif ($tStatus['is_complete']): ?>
                        <i class="fas fa-check"></i>
                    <?php else: ?>
                        <?= $idx + 1 ?>
                    <?php endif; ?>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-weight: 500; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <?= e($t['title']) ?>
                    </div>
                    <?php if ($isUnlocked && !$tStatus['is_complete']): ?>
                        <div style="font-size: 0.75rem; color: var(--text-tertiary); margin-top: 0.25rem;">
                            <?= $tStatus['percent'] ?>% bajarildi
                        </div>
                    <?php endif; ?>
                </div>
            <?php if ($isUnlocked): ?>
                </a>
            <?php else: ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </aside>

    <!-- ═══════════════════════════════════════════════════
         O'NG PANEL: Mavzu kontenti
    ════════════════════════════════════════════════════ -->
    <div>
        <?php if (!$currentTopic): ?>
            <div class="card">
                <div class="card-body" style="text-align: center; padding: 3rem;">
                    <i class="fas fa-book" style="font-size: 3rem; color: var(--text-tertiary);"></i>
                    <h3 style="color: var(--text-tertiary); margin-top: 1rem;">Bu fanda hali mavzular yo'q</h3>
                </div>
            </div>

        <?php else: ?>

            <!-- ─── BOSQICHLAR INDIKATORI ──────────────────────── -->
            <div class="topic-content-area" style="margin-bottom: 1.5rem;">
                <div style="padding: 1.5rem; border-bottom: 1px solid var(--border);">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <h2 style="margin: 0;"><?= e($currentTopic['title']) ?></h2>
                            <small style="color: var(--text-tertiary);">
                                <?= $currentTopicIndex + 1 ?> / <?= count($topics) ?> mavzu
                            </small>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <?php if ($status['is_complete']): ?>
                                <span class="badge badge-success" style="font-size: 0.85rem; padding: 0.4rem 0.75rem;">
                                    <i class="fas fa-check-circle"></i> Yakunlangan
                                </span>
                            <?php endif; ?>
                            <strong style="font-size: 1.5rem; color: var(--primary);"><?= $status['percent'] ?>%</strong>
                        </div>
                    </div>
                </div>

                <!-- 6 ta bosqich indikatori -->
                <div class="steps-progress">
                    <!-- 1: Matn -->
                    <div class="step-item <?= $status['content_read'] ? 'done' : ($status['content_scroll_percent'] > 0 ? 'in-progress' : '') ?>">
                        <div class="step-icon"><?= $status['content_read'] ? '<i class="fas fa-check"></i>' : '1' ?></div>
                        <div>
                            <strong>Matn</strong>
                            <div style="font-size: 0.72rem; color: var(--text-tertiary);"><?= $status['content_scroll_percent'] ?>% o'qildi</div>
                        </div>
                    </div>

                    <!-- 2: Video -->
                    <?php if ($videoId): ?>
                    <div class="step-item <?= $status['video_watched'] ? 'done' : ($status['video_watch_percent'] > 0 ? 'in-progress' : '') ?>">
                        <div class="step-icon"><?= $status['video_watched'] ? '<i class="fas fa-check"></i>' : '2' ?></div>
                        <div>
                            <strong>Video</strong>
                            <div style="font-size: 0.72rem; color: var(--text-tertiary);"><?= $status['video_watch_percent'] ?>% ko'rildi</div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- 3: Test -->
                    <?php if ($questionCount > 0): ?>
                    <div class="step-item <?= $status['test_passed'] ? 'done' : '' ?>">
                        <div class="step-icon"><?= $status['test_passed'] ? '<i class="fas fa-check"></i>' : '3' ?></div>
                        <div>
                            <strong>Test</strong>
                            <div style="font-size: 0.72rem; color: var(--text-tertiary);">
                                <?= $status['test_score'] > 0 ? number_format($status['test_score'], 1) . '%' : 'Topshirilmagan' ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- 4: Amaliy ish -->
                    <?php $practicalDone = $practicalSubmission && in_array($practicalSubmission['status'], ['graded']); ?>
                    <div class="step-item <?= $practicalDone ? 'done' : ($practicalSubmission ? 'in-progress' : '') ?>">
                        <div class="step-icon"><?= $practicalDone ? '<i class="fas fa-check"></i>' : '4' ?></div>
                        <div>
                            <strong>Amaliy</strong>
                            <div style="font-size: 0.72rem; color: var(--text-tertiary);">
                                <?php if ($practicalDone): ?>
                                    Baho: <?= $practicalSubmission['grade'] ?>
                                <?php elseif ($practicalSubmission): ?>
                                    Tekshirilmoqda...
                                <?php else: ?>
                                    Topshirilmagan
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- 5: Kod yozish -->
                    <?php if ($task): ?>
                    <div class="step-item <?= $status['task_completed'] ? 'done' : '' ?>">
                        <div class="step-icon"><?= $status['task_completed'] ? '<i class="fas fa-check"></i>' : '5' ?></div>
                        <div>
                            <strong>Kod</strong>
                            <div style="font-size: 0.72rem; color: var(--text-tertiary);">
                                <?= $status['task_grade'] > 0 ? 'Baho: ' . $status['task_grade'] : 'Topshirilmagan' ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- 6: Mustaqil ish -->
                    <?php $independentDone = $independentSubmission && $independentSubmission['status'] === 'graded'; ?>
                    <div class="step-item <?= $independentDone ? 'done' : ($independentSubmission ? 'in-progress' : '') ?>">
                        <div class="step-icon"><?= $independentDone ? '<i class="fas fa-check"></i>' : '6' ?></div>
                        <div>
                            <strong>Mustaqil</strong>
                            <div style="font-size: 0.72rem; color: var(--text-tertiary);">
                                <?php if ($independentDone): ?>
                                    Baho: <?= $independentSubmission['grade'] ?>
                                <?php elseif ($independentSubmission): ?>
                                    Tekshirilmoqda...
                                <?php else: ?>
                                    Topshirilmagan
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Keyingi mavzuga o'tish -->
                <?php if ($status['is_complete'] && $currentTopicIndex < count($topics) - 1): ?>
                    <div style="padding: 1rem 1.5rem; background: rgba(16,185,129,0.08); border-bottom: 1px solid var(--border);">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span><i class="fas fa-check-circle" style="color: var(--success);"></i> Mavzu yakunlandi! Keyingisiga o'tishingiz mumkin.</span>
                            <a href="<?= SITE_URL ?>/student/learn.php?subject_id=<?= $subjectId ?>&topic_id=<?= $topics[$currentTopicIndex + 1]['id'] ?>"
                               class="btn btn-success">Keyingi mavzu <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ─── 1-BOSQICH: MAVZU MATNI ─────────────────────── -->
            <div class="topic-content-area" style="margin-bottom: 1.5rem;">
                <div class="section-header">
                    <h3><i class="fas fa-book-open"></i> 1-bosqich: Mavzu matnini o'qing</h3>
                </div>
                <div class="content-text" id="topicContent" data-topic-id="<?= $currentTopic['id'] ?>">
                    <?= $currentTopic['content'] ?>
                </div>
            </div>

            <!-- ─── 2-BOSQICH: VIDEO ───────────────────────────── -->
            <?php if ($videoId): ?>
            <div class="topic-content-area" style="margin-bottom: 1.5rem;">
                <div class="section-header">
                    <h3><i class="fas fa-video"></i> 2-bosqich: Videoni tomosha qiling <small style="font-weight:400; color:var(--text-tertiary);">(90%+ kerak)</small></h3>
                </div>
                <div class="video-container">
                    <iframe id="videoPlayer"
                            src="https://www.youtube.com/embed/<?= e($videoId) ?>?enablejsapi=1"
                            allowfullscreen></iframe>
                </div>
            </div>
            <?php endif; ?>

            <!-- ─── 3-BOSQICH: TEST ───────────────────────────── -->
            <?php if ($questionCount > 0): ?>
            <div class="topic-content-area" style="margin-bottom: 1.5rem;">
                <div class="section-header">
                    <h3><i class="fas fa-question-circle"></i> 3-bosqich: Test topshiring
                        <small style="font-weight:400; color:var(--text-tertiary);">(<?= $currentTopic['passing_score'] ?>%+ kerak)</small>
                    </h3>
                </div>
                <div class="section-body">
                    <p>Mavzu bo'yicha <strong><?= $questionCount ?></strong> ta savol mavjud.</p>

                    <?php if (!$status['content_read'] || ($videoId && !$status['video_watched'])): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-lock"></i>
                            Avval matn <?= $videoId ? 'va videoni' : 'ni' ?> yakunlang
                        </div>
                    <?php elseif ($status['test_passed']): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            Test muvaffaqiyatli topshirildi (<?= number_format($status['test_score'], 1) ?>%)
                        </div>
                        <a href="<?= SITE_URL ?>/student/test.php?topic_id=<?= $currentTopic['id'] ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-redo"></i> Qaytadan
                        </a>
                    <?php else: ?>
                        <a href="<?= SITE_URL ?>/student/test.php?topic_id=<?= $currentTopic['id'] ?>" class="btn btn-primary">
                            <i class="fas fa-play"></i> Testni boshlash
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ─── 4-BOSQICH: AMALIY ISH (O'qituvchi topshirig'i) ── -->
            <div class="topic-content-area" style="margin-bottom: 1.5rem;">
                <div class="section-header">
                    <h3><i class="fas fa-tasks"></i> 4-bosqich: Amaliy ish
                        <small style="font-weight:400; color:var(--text-tertiary);">(O'qituvchi topshirig'i bo'yicha bajaring)</small>
                    </h3>
                </div>
                <div class="section-body">

                    <?php if (!$status['test_passed'] && $questionCount > 0): ?>
                        <!-- Test o'tilmagan -->
                        <div class="alert alert-warning">
                            <i class="fas fa-lock"></i> Avval testni muvaffaqiyatli topshiring
                        </div>

                    <?php elseif (!$practicalFile): ?>
                        <!-- O'qituvchi hali fayl yuklamagan -->
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            O'qituvchi hali amaliy ish topshirig'ini yuklamagan. Keyinroq tekshiring.
                        </div>

                    <?php else: ?>
                        <!-- O'qituvchi topshirig'i fayli -->
                        <div class="practical-file-card">
                            <div class="practical-file-icon">
                                <i class="fas fa-<?= $practicalFile['file_type'] === 'pdf' ? 'file-pdf' : 'file-word' ?>"></i>
                            </div>
                            <div style="flex:1;">
                                <div style="font-weight: 600;"><?= e($practicalFile['file_name']) ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-tertiary); margin-top: 0.2rem;">
                                    O'qituvchi tomonidan berilgan topshiriq
                                </div>
                            </div>
                            <a href="<?= SITE_URL . '/' . e($practicalFile['file_path']) ?>" 
                               target="_blank" class="btn btn-primary btn-sm">
                                <i class="fas fa-download"></i> Yuklab olish
                            </a>
                        </div>

                        <!-- Talabaning javobi -->
                        <?php if ($practicalSubmission): ?>
                            <div class="ai-result-card">
                                <div class="ai-result-header">
                                    <div>
                                        <i class="fas fa-file-alt"></i>
                                        <strong>Yuborilgan ish:</strong>
                                        <a href="<?= e($practicalSubmission['file_url']) ?>" target="_blank" class="ms-2">
                                            Ko'rish <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    </div>
                                    <?php if ($practicalSubmission['status'] === 'graded'): ?>
                                        <span class="badge badge-<?= $practicalSubmission['grade'] >= 3 ? 'success' : 'danger' ?>">
                                            Baho: <?= $practicalSubmission['grade'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">
                                            <i class="fas fa-spinner fa-spin"></i> Tekshirilmoqda
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($practicalSubmission['status'] === 'graded' && $practicalSubmission['ai_feedback']): ?>
                                    <div class="ai-result-body">
                                        <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.5rem;">
                                            <i class="fas fa-robot" style="color: var(--primary);"></i> <strong>AI taqrizi:</strong>
                                        </div>
                                        <p style="margin: 0; line-height: 1.7;"><?= nl2br(e($practicalSubmission['ai_feedback'])) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($practicalSubmission['status'] === 'graded' && $practicalSubmission['grade'] < 3): ?>
                                <div class="alert alert-warning mt-3">
                                    <i class="fas fa-redo"></i> Baho 3 dan past. Qaytadan topshiring:
                                </div>
                                <?php $showPracticalUpload = true; ?>
                            <?php elseif (in_array($practicalSubmission['status'], ['pending_manual', 'pending'])): ?>
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-info-circle"></i> Ish qabul qilindi, tekshirilmoqda. Qaytadan yubormoqchi bo'lsangiz:
                                </div>
                                <?php $showPracticalUpload = true; ?>
                            <?php endif; ?>

                        <?php else: ?>
                            <?php $showPracticalUpload = true; ?>
                        <?php endif; ?>

                        <?php if (!empty($showPracticalUpload)): ?>
                            <div style="margin-top: 1rem;">
                                <p style="color: var(--text-secondary); font-size: 0.9rem;">
                                    <i class="fas fa-upload"></i>
                                    Topshiriqni bajarib, javobingizni (PDF yoki rasm) yuklang. AI tekshiradi va baho qo'yadi.
                                </p>
                                <div class="upload-zone" id="practicalUploadZone" onclick="document.getElementById('practical-work-upload').click()" style="cursor:pointer;">
                                    <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: var(--text-tertiary); margin-bottom: 0.75rem;"></i>
                                    <div>Fayl tanlash yoki bu yerga tashlang</div>
                                    <div style="font-size: 0.8rem; color: var(--text-tertiary); margin-top: 0.5rem;">PDF, JPG, PNG (max 20MB)</div>
                                    <input type="file"
                                           id="practical-work-upload"
                                           accept=".pdf,.jpg,.jpeg,.png"
                                           style="display:none;" />
                                </div>
                                <div id="practicalFileInfo" style="display:none; margin-top:0.5rem; padding:0.5rem 1rem; background:var(--bg-secondary); border-radius:8px; font-size:0.9rem;">
                                    <i class="fas fa-file"></i> <span id="practicalFileName"></span>
                                </div>
                                <div id="practicalUploadProgress" style="display:none; text-align:center; padding: 1rem;">
                                    <div class="spinner-border text-primary" role="status"></div>
                                    <p style="margin-top: 0.5rem;">AI tahlil qilmoqda, iltimos kuting...</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ─── 5-BOSQICH: KOD YOZISH (Compiler) ──────────── -->
            <?php if ($task): ?>
            <div class="topic-content-area" style="margin-bottom: 1.5rem;">
                <div class="section-header">
                    <h3><i class="fas fa-code"></i> 5-bosqich: Kod yozish
                        <small style="font-weight:400; color:var(--text-tertiary);">("3" baho yoki yuqori kerak)</small>
                    </h3>
                </div>
                <div class="section-body">
                    <h4 style="margin-top:0;"><?= e($task['title']) ?></h4>
                    <p style="color: var(--text-secondary);"><?= e(mb_substr($task['description'], 0, 220)) ?>...</p>

                    <?php if (!($practicalDone || !$practicalFile)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-lock"></i> Avval amaliy ishni yakunlang
                        </div>
                    <?php elseif ($status['task_completed']): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            Kod topshirildi. Olingan baho: <strong><?= $status['task_grade'] ?></strong>
                        </div>
                        <a href="<?= SITE_URL ?>/student/code_editor.php?task_id=<?= $task['id'] ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-redo"></i> Qaytadan urinish
                        </a>
                    <?php else: ?>
                        <a href="<?= SITE_URL ?>/student/code_editor.php?task_id=<?= $task['id'] ?>" class="btn btn-primary">
                            <i class="fas fa-code"></i> Kod editorni ochish
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ─── 6-BOSQICH: MUSTAQIL ISH ──────────────────── -->
            <div class="topic-content-area" style="margin-bottom: 1.5rem;">
                <div class="section-header">
                    <h3><i class="fas fa-user-graduate"></i> 6-bosqich: Mustaqil ish
                        <small style="font-weight:400; color:var(--text-tertiary);">(Mavzu davomida o'rganilgan bilimlar asosida, matn yoki taqdimot shaklida tayyorlang. Ishni AI tekshiradi va baholaydi.
                            <br>Talablar:
                            <br>1. O'z fikrlaringizni aniq va ravon ifodalang
                            <br>2. Mavzu bo'yicha chuqur tahlil
                            <br>3. O'ziga xos yondashuv va ijodkorlik
                            <br> UNUTMANG! ANTIPLAGIAT SISTEMASI MAVJUD!
                        )</small>
                    </h3>
                </div>
                <div class="section-body">

                    <?php if ($independentSubmission): ?>
                        <!-- Yuborilgan ish natijalari -->
                        <div class="ai-result-card">
                            <div class="ai-result-header">
                                <div>
                                    <i class="fas fa-file-alt"></i>
                                    <strong>Yuborilgan ish:</strong>
                                    <a href="<?= e($independentSubmission['file_url']) ?>" target="_blank" class="ms-2">
                                        Ko'rish <i class="fas fa-external-link-alt"></i>
                                    </a>
                                </div>
                                <?php if ($independentSubmission['status'] === 'graded'): ?>
                                    <span class="badge badge-<?= $independentSubmission['grade'] >= 3 ? 'success' : 'danger' ?>">
                                        Baho: <?= $independentSubmission['grade'] ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-warning">
                                        <i class="fas fa-spinner fa-spin"></i> AI tekshirmoqda...
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($independentSubmission['status'] === 'graded' && $independentSubmission['ai_feedback']): ?>
                                <div class="ai-result-body">
                                    <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.5rem;">
                                        <i class="fas fa-robot" style="color: var(--primary);"></i> <strong>AI taqrizi:</strong>
                                    </div>
                                    <p style="margin: 0; line-height: 1.7;"><?= nl2br(e($independentSubmission['ai_feedback'])) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($independentSubmission['status'] === 'graded' && $independentSubmission['grade'] < 3): ?>
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-triangle"></i>
                                Baho 3 dan past. Mustaqil ishni qaytadan tayyorlab yuboring:
                            </div>
                            <?php $showIndependentUpload = true; ?>
                        <?php elseif (in_array($independentSubmission['status'], ['pending_manual', 'pending'])): ?>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle"></i> Ish qabul qilindi, tekshirilmoqda. Qaytadan yubormoqchi bo'lsangiz:
                            </div>
                            <?php $showIndependentUpload = true; ?>
                        <?php endif; ?>

                    <?php else: ?>
                        <?php $showIndependentUpload = true; ?>
                    <?php endif; ?>

                    <?php if (!empty($showIndependentUpload)): ?>
                        <div style="<?= $independentSubmission ? 'margin-top:1rem;' : '' ?>">
                            <p style="color: var(--text-secondary); font-size: 0.9rem;">
                                Mavzu yuzasidan mustaqil tayyorlagan ishingizni (qo'lyozma, PDF, yoki rasm) yuklang. 
                                AI baholaydi va taqriz yozadi.
                            </p>
                            <div class="upload-zone" id="independentUploadZone" onclick="document.getElementById('independent-work-upload').click()" style="cursor:pointer;">
                                <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: var(--text-tertiary); margin-bottom: 0.75rem;"></i>
                                <div>Fayl tanlash yoki bu yerga tashlang</div>
                                <div style="font-size: 0.8rem; color: var(--text-tertiary); margin-top: 0.5rem;">PDF, JPG, PNG (max 20MB)</div>
                                <input type="file"
                                       id="independent-work-upload"
                                       accept=".pdf,.jpg,.jpeg,.png"
                                       style="display:none;" />
                            </div>
                            <div id="independentFileInfo" style="display:none; margin-top:0.5rem; padding:0.5rem 1rem; background:var(--bg-secondary); border-radius:8px; font-size:0.9rem;">
                                <i class="fas fa-file"></i> <span id="independentFileName"></span>
                            </div>
                            <div id="independentUploadProgress" style="display:none; text-align:center; padding: 1rem;">
                                <div class="spinner-border text-primary" role="status"></div>
                                <p style="margin-top: 0.5rem;">AI tahlil qilmoqda, iltimos kuting...</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php endif; // currentTopic ?>
    </div>
</div>

<?php if ($currentTopic): ?>
<script>
// ═══════════════════════════════════════════════════════════
// ASOSIY O'ZGARUVCHILAR
// ═══════════════════════════════════════════════════════════
const TOPIC_ID    = <?= (int)$currentTopic['id'] ?>;
const SUBJECT_ID  = <?= (int)$subjectId ?>;
const HAS_VIDEO   = <?= $videoId ? 'true' : 'false' ?>;
const PROGRESS_API = '<?= SITE_URL ?>/api/progress_tracker.php';
const SUBMIT_API   = '<?= SITE_URL ?>/api/submit_work.php';

let lastSavedScroll = 0;
let lastSavedVideo  = 0;

// ═══════════════════════════════════════════════════════════
// SCROLL KUZATISH
// ═══════════════════════════════════════════════════════════
const contentEl = document.getElementById('topicContent');
function trackScroll() {
    if (!contentEl) return;
    const rect        = contentEl.getBoundingClientRect();
    const totalHeight = contentEl.scrollHeight;
    const scrolledFromTop = -rect.top + window.innerHeight;
    let percent = Math.round((scrolledFromTop / totalHeight) * 100);
    percent = Math.max(0, Math.min(100, percent));
    if (Math.abs(percent - lastSavedScroll) >= 5 || percent >= 100) {
        lastSavedScroll = percent;
        saveProgress({ content_scroll_percent: percent });
    }
}
window.addEventListener('scroll', () => requestAnimationFrame(trackScroll));

// ═══════════════════════════════════════════════════════════
// VIDEO KUZATISH (YouTube IFrame API)
// ═══════════════════════════════════════════════════════════
let player, videoCheckInterval;

function onYouTubeIframeAPIReady() {
    if (!HAS_VIDEO) return;
    player = new YT.Player('videoPlayer', {
        events: { 'onReady': onPlayerReady }
    });
}
function onPlayerReady(e) {
    videoCheckInterval = setInterval(() => {
        if (!player || !player.getDuration) return;
        const duration = player.getDuration();
        const current  = player.getCurrentTime();
        if (duration > 0) {
            const percent = Math.round((current / duration) * 100);
            if (Math.abs(percent - lastSavedVideo) >= 5 || percent >= 90) {
                lastSavedVideo = percent;
                saveProgress({ video_watch_percent: percent });
            }
        }
    }, 3000);
}

// ═══════════════════════════════════════════════════════════
// PROGRESS SAQLASH
// ═══════════════════════════════════════════════════════════
async function saveProgress(data) {
    try {
        await fetch(PROGRESS_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ topic_id: TOPIC_ID, ...data })
        });
    } catch(e) { console.error('Progress save error:', e); }
}

// ═══════════════════════════════════════════════════════════
// ISH YUBORISH (Amaliy yoki Mustaqil)
// ═══════════════════════════════════════════════════════════
async function submitWork(fileUrl, fileName, type) {
    try {
        const res = await fetch(SUBMIT_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                topic_id: TOPIC_ID,
                file_url: fileUrl,
                file_name: fileName,
                type: type   // 'practical' yoki 'independent'
            })
        });
        const data = await res.json();
        if (data.success) {
            location.reload();
        } else {
            alert('Xatolik: ' + (data.message || 'Noma\'lum xato'));
            document.getElementById(type === 'practical' ? 'practicalUploadProgress' : 'independentUploadProgress').style.display = 'none';
        }
    } catch(e) {
        alert('Server bilan bog\'lanishda xatolik');
        console.error(e);
    }
}

// ═══════════════════════════════════════════════════════════
// FAYL TANLASH VA YUBORISH
// ═══════════════════════════════════════════════════════════
function setupFileUpload(inputId, zoneId, fileInfoId, fileNameId, progressId, type) {
    const input = document.getElementById(inputId);
    if (!input) return;

    input.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;

        // Fayl hajmini tekshirish (20MB)
        if (file.size > 20 * 1024 * 1024) {
            alert('Fayl hajmi 20MB dan oshmasligi kerak!');
            this.value = '';
            return;
        }

        // Fayl nomini ko'rsatish
        document.getElementById(fileNameId).textContent = file.name;
        document.getElementById(fileInfoId).style.display = 'block';

        // Drag-drop zona ko'rinishini o'zgartirish
        const zone = document.getElementById(zoneId);
        zone.style.opacity = '0.6';
        zone.style.borderColor = 'var(--primary)';

        // Avtomatik yuborish
        sendFile(file, progressId, zoneId, type);
    });

    // Drag & Drop
    const zone = document.getElementById(zoneId);
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.style.borderColor = 'var(--primary)'; });
    zone.addEventListener('dragleave', () => { zone.style.borderColor = ''; });
    zone.addEventListener('drop', e => {
        e.preventDefault();
        const file = e.dataTransfer.files[0];
        if (file) {
            input.files = e.dataTransfer.files;
            input.dispatchEvent(new Event('change'));
        }
    });
}

async function sendFile(file, progressId, zoneId, type) {
    // Progress ko'rsatish
    document.getElementById(progressId).style.display = 'block';
    document.getElementById(zoneId).style.opacity = '0.4';

    // Faylni base64 ga o'tkazish
    const reader = new FileReader();
    reader.readAsDataURL(file);
    reader.onload = async () => {
        const base64Data = reader.result.split(',')[1];
        const mimeType   = file.type || 'application/pdf';

        try {
            const res = await fetch(SUBMIT_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    topic_id:   TOPIC_ID,
                    file_name:  file.name,
                    file_data:  base64Data,
                    mime_type:  mimeType,
                    type:       type
                })
            });
            const data = await res.json();
            if (data.success) {
                location.reload();
            } else {
                alert('Xatolik: ' + (data.message || 'Noma\'lum xato'));
                document.getElementById(progressId).style.display = 'none';
                document.getElementById(zoneId).style.opacity = '1';
            }
        } catch(e) {
            alert('Server bilan bog\'lanishda xatolik');
            document.getElementById(progressId).style.display = 'none';
            document.getElementById(zoneId).style.opacity = '1';
        }
    };
}

document.addEventListener('DOMContentLoaded', function () {
    setupFileUpload('practical-work-upload',   'practicalUploadZone',   'practicalFileInfo',   'practicalFileName',   'practicalUploadProgress',   'practical');
    setupFileUpload('independent-work-upload', 'independentUploadZone', 'independentFileInfo', 'independentFileName', 'independentUploadProgress', 'independent');
});
</script>

<?php
    $extraJs = ['https://www.youtube.com/iframe_api'];
endif;
require_once __DIR__ . '/../includes/footer.php';
?>
