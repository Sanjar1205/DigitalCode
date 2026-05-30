<?php
$pageTitle = 'Mavzular';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('teacher');

$teacherId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'CSRF token xato'); redirect($_SERVER['REQUEST_URI']);
    }

    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            $subjectId = (int)$_POST['subject_id'];
            $check = db()->fetchOne(
                "SELECT 1 FROM subject_teachers WHERE subject_id = ? AND teacher_id = ?",
                [$subjectId, $teacherId]
            );
            if (!$check) throw new Exception('Bu fan sizga biriktirilmagan');

            $maxOrder = db()->fetchOne(
                "SELECT MAX(order_number) as m FROM topics WHERE subject_id = ?", [$subjectId]
            )['m'];

            db()->insert('topics', [
                'subject_id'    => $subjectId,
                'title'         => trim($_POST['title']),
                'content'       => $_POST['content'] ?? '',
                'video_url'     => trim($_POST['video_url'] ?? ''),
                'order_number'  => ($maxOrder ?? 0) + 1,
                'passing_score' => (int)($_POST['passing_score'] ?? 60),
                'status'        => $_POST['status'] ?? 'draft',
                'created_by'    => $teacherId
            ]);
            setFlash('success', 'Yangi mavzu qo\'shildi');

        } elseif ($action === 'update') {
            db()->update('topics', [
                'title'         => trim($_POST['title']),
                'content'       => $_POST['content'] ?? '',
                'video_url'     => trim($_POST['video_url'] ?? ''),
                'passing_score' => (int)($_POST['passing_score'] ?? 60),
                'status'        => $_POST['status']
            ], 'id = :id', ['id' => (int)$_POST['topic_id']]);
            setFlash('success', 'Mavzu yangilandi');

        } elseif ($action === 'delete') {
            db()->delete('topics', 'id = :id', ['id' => (int)$_POST['topic_id']]);
            setFlash('success', 'Mavzu o\'chirildi');

        } elseif ($action === 'reorder') {
            $orders = json_decode($_POST['orders'], true) ?? [];
            foreach ($orders as $idx => $tid) {
                db()->update('topics', ['order_number' => $idx + 1], 'id = :id', ['id' => (int)$tid]);
            }
            setFlash('success', 'Tartib o\'zgartirildi');

        } elseif ($action === 'upload_practical') {
            $topicId         = (int)$_POST['topic_id'];
            $fileUrl         = trim($_POST['file_url']         ?? '');
            $fileName        = trim($_POST['file_name']        ?? 'amaliy_ish');
            $description     = trim($_POST['description']      ?? '');
            $gradingCriteria = trim($_POST['grading_criteria'] ?? '');

            if (!$topicId || empty($fileUrl)) {
                throw new Exception('Fayl ma\'lumotlari to\'liq emas');
            }

            // Eski faylni o'chirish
            db()->query(
                "DELETE FROM topic_files WHERE topic_id = ? AND file_type = 'practical'",
                [$topicId]
            );

            db()->insert('topic_files', [
                'topic_id'        => $topicId,
                'file_name'       => $fileName,
                'file_path'       => $fileUrl,
                'file_type'       => 'practical',
                'file_size'       => 0,
                'description'     => $description,
                'grading_criteria'=> $gradingCriteria
            ]);
            setFlash('success', 'Amaliy ish topshirig\'i saqlandi');

        } elseif ($action === 'update_practical_text') {
            // Fayl yuklamasdan faqat matn (tavsif va talablar) ni yangilash
            $topicId         = (int)$_POST['topic_id'];
            $description     = trim($_POST['description']      ?? '');
            $gradingCriteria = trim($_POST['grading_criteria'] ?? '');

            $existing = db()->fetchOne(
                "SELECT id FROM topic_files WHERE topic_id = ? AND file_type = 'practical'",
                [$topicId]
            );
            if ($existing) {
                db()->update('topic_files', [
                    'description'      => $description,
                    'grading_criteria' => $gradingCriteria
                ], 'id = :id', ['id' => $existing['id']]);
                setFlash('success', 'Baholash talablari yangilandi');
            } else {
                throw new Exception('Avval fayl yuklang');
            }

        } elseif ($action === 'delete_practical') {
            $topicId = (int)$_POST['topic_id'];
            db()->query(
                "DELETE FROM topic_files WHERE topic_id = ? AND file_type = 'practical'",
                [$topicId]
            );
            setFlash('success', 'Amaliy ish o\'chirildi');
        }

    } catch (Exception $e) {
        setFlash('danger', $e->getMessage());
    }
    redirect($_SERVER['REQUEST_URI']);
}

$subjectId = (int)($_GET['subject_id'] ?? 0);

$mySubjects = db()->fetchAll(
    "SELECT s.* FROM subjects s
     JOIN subject_teachers st ON s.id = st.subject_id
     WHERE st.teacher_id = ? ORDER BY s.name",
    [$teacherId]
);

$selectedSubject = null;
$topics = [];
if ($subjectId) {
    $selectedSubject = db()->fetchOne(
        "SELECT s.* FROM subjects s
         JOIN subject_teachers st ON s.id = st.subject_id
         WHERE s.id = ? AND st.teacher_id = ?",
        [$subjectId, $teacherId]
    );

    if ($selectedSubject) {
        $topics = db()->fetchAll(
            "SELECT t.*,
                (SELECT COUNT(*) FROM questions  WHERE topic_id = t.id) as question_count,
                (SELECT COUNT(*) FROM tasks       WHERE topic_id = t.id) as task_count,
                (SELECT id               FROM topic_files WHERE topic_id = t.id AND file_type = 'practical' ORDER BY id DESC LIMIT 1) as pf_id,
                (SELECT file_name        FROM topic_files WHERE topic_id = t.id AND file_type = 'practical' ORDER BY id DESC LIMIT 1) as pf_name,
                (SELECT file_path        FROM topic_files WHERE topic_id = t.id AND file_type = 'practical' ORDER BY id DESC LIMIT 1) as pf_url,
                (SELECT description      FROM topic_files WHERE topic_id = t.id AND file_type = 'practical' ORDER BY id DESC LIMIT 1) as pf_description,
                (SELECT grading_criteria FROM topic_files WHERE topic_id = t.id AND file_type = 'practical' ORDER BY id DESC LIMIT 1) as pf_criteria
             FROM topics t WHERE t.subject_id = ? ORDER BY t.order_number",
            [$subjectId]
        );
    }
}

$uploadcareKey = getSetting('uploadcare_public_key', '188f233d485fff1f871a');
require_once __DIR__ . '/../includes/header.php';
?>

<script src="https://ucarecdn.com/libs/widget/3.x/uploadcare.full.min.js"></script>

<style>
.practical-upload-zone {
    border: 2px dashed var(--border);
    border-radius: var(--radius);
    padding: 1.25rem;
    text-align: center;
    background: var(--bg-tertiary);
    transition: var(--transition);
    cursor: pointer;
}
.practical-upload-zone:hover { border-color: var(--primary); background: var(--primary-light); }
.practical-file-badge {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.4rem 0.75rem;
    background: rgba(16,185,129,0.12);
    border: 1px solid var(--success);
    border-radius: var(--radius);
    color: var(--success);
    font-size: 0.85rem;
}
.topic-row { border-bottom: 1px solid var(--border); }
.topic-row:last-child { border-bottom: none; }
.topic-main { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.25rem; }
.topic-expand-body {
    display: none;
    padding: 0 1.25rem 1.25rem 3.5rem;
    border-top: 1px solid var(--border);
    background: var(--bg-tertiary);
}
.topic-row.open .topic-expand-body { display: block; }
.topic-row.open .expand-icon { transform: rotate(90deg); }
.expand-icon { transition: transform 0.2s; cursor: pointer; color: var(--text-tertiary); }
.criteria-hint {
    font-size: 0.8rem; color: var(--text-tertiary);
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 0.6rem 0.9rem;
    margin-bottom: 0.5rem;
    line-height: 1.6;
}
</style>

<!-- Fan tanlash -->
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
    <div class="toolbar">
        <h2 style="margin: 0; flex: 1;"><?= e($selectedSubject['name']) ?> — Mavzular</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTopicModal">
            <i class="fas fa-plus"></i> Yangi mavzu
        </button>
    </div>

    <?php if (empty($topics)): ?>
        <div class="card">
            <div class="card-body" style="text-align: center; padding: 3rem;">
                <i class="fas fa-list" style="font-size: 3rem; color: var(--text-tertiary);"></i>
                <h3 style="color: var(--text-tertiary); margin-top: 1rem;">Hech qanday mavzu yo'q</h3>
            </div>
        </div>
    <?php else: ?>
        <div class="card" style="padding: 0;">
            <?php foreach ($topics as $t): ?>
                <div class="topic-row" id="topic-row-<?= $t['id'] ?>">

                    <div class="topic-main">
                        <i class="fas fa-chevron-right expand-icon" onclick="toggleTopic(<?= $t['id'] ?>)"></i>
                        <div class="topic-number"><?= $t['order_number'] ?></div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-weight: 600;"><?= e($t['title']) ?></div>
                            <div class="topic-meta" style="margin-top: 0.25rem;">
                                <span><i class="fas fa-question-circle"></i> <?= $t['question_count'] ?> savol</span>
                                <span><i class="fas fa-code"></i> <?= $t['task_count'] ?> masala</span>
                                <?php if ($t['video_url']): ?>
                                    <span><i class="fas fa-video"></i> Video bor</span>
                                <?php endif; ?>
                                <?php if ($t['pf_id']): ?>
                                    <span style="color: var(--success);"><i class="fas fa-tasks"></i> Amaliy ish bor</span>
                                    <?php if ($t['pf_criteria']): ?>
                                        <span style="color: var(--primary);"><i class="fas fa-list-check"></i> Talablar yozilgan</span>
                                    <?php else: ?>
                                        <span style="color: var(--warning);"><i class="fas fa-exclamation-triangle"></i> Talablar yo'q</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <span class="badge badge-<?= $t['status'] === 'published' ? 'success' : 'secondary' ?>">
                                    <?= $t['status'] === 'published' ? 'Faol' : 'Qoralama' ?>
                                </span>
                            </div>
                        </div>
                        <div style="display: flex; gap: 0.5rem; flex-shrink: 0;">
                            <a href="<?= SITE_URL ?>/teacher/questions.php?topic_id=<?= $t['id'] ?>"
                               class="btn btn-sm btn-secondary" title="Test savollari">
                                <i class="fas fa-question-circle"></i>
                            </a>
                            <a href="<?= SITE_URL ?>/teacher/tasks.php?topic_id=<?= $t['id'] ?>"
                               class="btn btn-sm btn-secondary" title="Kod masalasi">
                                <i class="fas fa-code"></i>
                            </a>
                            <button class="btn btn-sm btn-primary edit-topic-btn"
                                    data-id="<?= $t['id'] ?>"
                                    data-title="<?= e($t['title']) ?>"
                                    data-video="<?= e($t['video_url'] ?? '') ?>"
                                    data-score="<?= (int)$t['passing_score'] ?>"
                                    data-status="<?= e($t['status']) ?>"
                                    data-content-id="content-<?= $t['id'] ?>"
                                    title="Tahrirlash">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= e(Auth::getCsrfToken()) ?>">
                                <input type="hidden" name="action"   value="delete">
                                <input type="hidden" name="topic_id" value="<?= $t['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger"
                                        data-confirm="Mavzuni o'chirmoqchimisiz? Barcha ma'lumotlar yo'qoladi.">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- ─── Kengaytirilgan: Amaliy ish boshqaruvi ─── -->
                    <div class="topic-expand-body">
                        <div style="padding-top: 1rem;">
                            <div style="font-weight: 600; color: var(--text-secondary); margin-bottom: 1rem;">
                                <i class="fas fa-tasks"></i> 4-bosqich: Amaliy ish topshirig'i
                            </div>

                            <?php if ($t['pf_id']): ?>
                                <!-- ── Fayl bor ── -->
                                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.25rem; flex-wrap: wrap;">
                                    <div class="practical-file-badge">
                                        <i class="fas fa-file-alt"></i> <?= e($t['pf_name']) ?>
                                    </div>
                                    <a href="<?= e($t['pf_url']) ?>" target="_blank" class="btn btn-sm btn-secondary">
                                        <i class="fas fa-eye"></i> Ko'rish
                                    </a>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= e(Auth::getCsrfToken()) ?>">
                                        <input type="hidden" name="action"   value="delete_practical">
                                        <input type="hidden" name="topic_id" value="<?= $t['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"
                                                data-confirm="Amaliy ish faylini o'chirmoqchimisiz?">
                                            <i class="fas fa-trash"></i> Faylni o'chirish
                                        </button>
                                    </form>
                                </div>

                                <!-- Tavsif va baholash talablari (tahrirlash) -->
                                <form method="POST">
                                    <input type="hidden" name="csrf_token"  value="<?= e(Auth::getCsrfToken()) ?>">
                                    <input type="hidden" name="action"      value="update_practical_text">
                                    <input type="hidden" name="topic_id"    value="<?= $t['id'] ?>">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">
                                                <i class="fas fa-align-left"></i> Topshiriq tavsifi
                                                <small style="color:var(--text-tertiary);">(talabalar ko'radi)</small>
                                            </label>
                                            <textarea name="description" class="form-control" rows="3"
                                                      placeholder="Masalan: Berilgan mavzu bo'yicha C++ dastur yozing va natijasini PDF ko'rinishida yuboring."><?= e($t['pf_description'] ?? '') ?></textarea>
                                        </div>
                                        <div class="col-12">
                                            <div class="criteria-hint">
                                                <i class="fas fa-robot" style="color: var(--primary);"></i>
                                                <strong>AI baholash talablari</strong> — quyidagi matn AI ga prompt sifatida beriladi.
                                                Aniq yozsangiz, AI aniqroq baholaydi.<br>
                                                <em>Misol: "1. O'zgaruvchilar to'g'ri e'lon qilingan bo'lsin. 2. Funksiyadan foydalanilsin. 3. Natija ekranga chiqarilsin."</em>
                                            </div>
                                            <label class="form-label">
                                                <i class="fas fa-list-check"></i> AI baholash talablari
                                                <span class="required">*</span>
                                            </label>
                                            <textarea name="grading_criteria" class="form-control" rows="5"
                                                      placeholder="Baholash mezonlarini yozing. Har bir talab yangi qatordan boshlang:&#10;1. ...&#10;2. ...&#10;3. ..."><?= e($t['pf_criteria'] ?? '') ?></textarea>
                                            <small style="color: var(--text-tertiary);">
                                                Bu talablar asosida AI talabaning ishini baholaydi va nega bu bahoni qo'yganini tushuntiradi.
                                            </small>
                                        </div>
                                    </div>
                                    <div style="margin-top: 1rem;">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fas fa-save"></i> Talablarni saqlash
                                        </button>
                                    </div>
                                </form>

                            <?php else: ?>
                                <!-- ── Fayl yo'q — yangi yuklash ── -->
                                <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 1rem;">
                                    Talabalar bajara oladigan topshiriq faylini (PDF yoki Word) yuklang va baholash talablarini yozing.
                                </p>

                                <form method="POST" id="practical-form-<?= $t['id'] ?>">
                                    <input type="hidden" name="csrf_token"        value="<?= e(Auth::getCsrfToken()) ?>">
                                    <input type="hidden" name="action"            value="upload_practical">
                                    <input type="hidden" name="topic_id"          value="<?= $t['id'] ?>">
                                    <input type="hidden" name="file_url"          id="file-url-<?= $t['id'] ?>">
                                    <input type="hidden" name="file_name"         id="file-name-<?= $t['id'] ?>">

                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">Topshiriq fayli <span class="required">*</span></label>
                                            <div class="practical-upload-zone" id="zone-<?= $t['id'] ?>">
                                                <i class="fas fa-cloud-upload-alt" style="font-size: 1.5rem; color: var(--text-tertiary);"></i>
                                                <div style="margin-top: 0.5rem;">Faylni tanlash</div>
                                                <div style="font-size: 0.78rem; color: var(--text-tertiary);">PDF, DOCX (max 20MB)</div>
                                                <input type="hidden"
                                                       role="uploadcare-uploader"
                                                       id="practical-upload-<?= $t['id'] ?>"
                                                       data-topic-id="<?= $t['id'] ?>"
                                                       data-public-key="<?= e($uploadcareKey) ?>"
                                                       data-tabs="file"
                                                       data-file-types="pdf doc docx jpg jpeg png" />
                                            </div>
                                            <div id="upload-loading-<?= $t['id'] ?>" style="display:none; margin-top:0.5rem; text-align:center;">
                                                <div class="spinner-border spinner-border-sm text-primary"></div> Saqlanmoqda...
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">
                                                <i class="fas fa-align-left"></i> Topshiriq tavsifi
                                                <small style="color:var(--text-tertiary);">(ixtiyoriy)</small>
                                            </label>
                                            <textarea name="description" class="form-control" rows="2"
                                                      placeholder="Qisqacha topshiriq tavsifi..."></textarea>
                                        </div>
                                        <div class="col-12">
                                            <div class="criteria-hint">
                                                <i class="fas fa-robot" style="color: var(--primary);"></i>
                                                <strong>AI baholash talablari</strong> — bu matn AI ga prompt sifatida beriladi. Aniq yozsangiz, AI aniqroq baholaydi.<br>
                                                <em>Misol: "1. O'zgaruvchilar to'g'ri e'lon qilingan bo'lsin. 2. Funksiyadan foydalanilsin."</em>
                                            </div>
                                            <label class="form-label">
                                                <i class="fas fa-list-check"></i> AI baholash talablari
                                                <span class="required">*</span>
                                            </label>
                                            <textarea name="grading_criteria" class="form-control" rows="5"
                                                      placeholder="Baholash mezonlarini yozing:&#10;1. ...&#10;2. ...&#10;3. ..."></textarea>
                                            <small style="color: var(--text-tertiary);">
                                                Talablar qanchalik aniq bo'lsa, AI shunchalik to'g'ri baholaydi.
                                            </small>
                                        </div>
                                    </div>
                                    <div style="margin-top: 1rem;" id="save-btn-<?= $t['id'] ?>" style="display:none;">
                                        <button type="submit" class="btn btn-primary btn-sm" id="submit-practical-<?= $t['id'] ?>" disabled>
                                            <i class="fas fa-save"></i> Saqlash
                                        </button>
                                        <small style="color: var(--text-tertiary); margin-left: 0.5rem;">Avval fayl yuklang</small>
                                    </div>
                                    <div style="margin-top: 1rem;">
                                        <button type="submit" class="btn btn-primary btn-sm" id="submit-practical-<?= $t['id'] ?>" disabled>
                                            <i class="fas fa-save"></i> Saqlash
                                        </button>
                                        <small style="color: var(--text-tertiary); margin-left: 0.5rem;" id="save-hint-<?= $t['id'] ?>">Avval fayl yuklang</small>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <textarea id="content-<?= $t['id'] ?>" style="display:none;"><?= htmlspecialchars($t['content'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 3rem;">
            <i class="fas fa-arrow-up" style="font-size: 2rem; color: var(--text-tertiary);"></i>
            <h3 style="color: var(--text-tertiary); margin-top: 1rem;">Fanni tanlang</h3>
        </div>
    </div>
<?php endif; ?>

<!-- Modal: Yangi mavzu -->
<div class="modal fade" id="createTopicModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content" style="background: var(--bg-secondary); color: var(--text-primary);">
            <div class="modal-header" style="border-color: var(--border);">
                <h5 class="modal-title">Yangi mavzu qo'shish</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token"  value="<?= e(Auth::getCsrfToken()) ?>">
                <input type="hidden" name="action"      value="create">
                <input type="hidden" name="subject_id"  value="<?= $subjectId ?>">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Mavzu nomi <span class="required">*</span></label>
                            <input type="text" name="title" class="form-control" required
                                   placeholder="Masalan: 1-Mavzu: O'zgaruvchilar">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Video URL (YouTube)</label>
                            <input type="url" name="video_url" class="form-control"
                                   placeholder="https://www.youtube.com/embed/VIDEO_ID">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Test o'tish bali (%)</label>
                            <input type="number" name="passing_score" class="form-control" value="60" min="0" max="100">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="draft">Qoralama</option>
                                <option value="published">Faol</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Mavzu matni (HTML)</label>
                            <textarea name="content" class="form-control" rows="10"
                                      placeholder="<h2>Sarlavha</h2><p>Matn...</p>"></textarea>
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

<!-- Modal: Tahrirlash -->
<div class="modal fade" id="editTopicModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content" style="background: var(--bg-secondary); color: var(--text-primary);">
            <div class="modal-header" style="border-color: var(--border);">
                <h5 class="modal-title">Mavzuni tahrirlash</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token"  value="<?= e(Auth::getCsrfToken()) ?>">
                <input type="hidden" name="action"      value="update">
                <input type="hidden" name="topic_id"    id="edit_topic_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Mavzu nomi</label>
                            <input type="text" name="title" id="edit_title" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Video URL</label>
                            <input type="url" name="video_url" id="edit_video_url" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">O'tish bali (%)</label>
                            <input type="number" name="passing_score" id="edit_passing_score" class="form-control" min="0" max="100">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="draft">Qoralama</option>
                                <option value="published">Faol</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Mavzu matni</label>
                            <textarea name="content" id="edit_content" class="form-control" rows="12"></textarea>
                        </div>
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
$inlineJs = <<<JS
// Mavzuni kengaytirish
function toggleTopic(id) {
    document.getElementById('topic-row-' + id).classList.toggle('open');
}

// Edit modal
document.querySelectorAll('.edit-topic-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const contentEl = document.getElementById(btn.dataset.contentId);
        document.getElementById('edit_topic_id').value      = btn.dataset.id;
        document.getElementById('edit_title').value         = btn.dataset.title;
        document.getElementById('edit_video_url').value     = btn.dataset.video;
        document.getElementById('edit_passing_score').value = btn.dataset.score;
        document.getElementById('edit_status').value        = btn.dataset.status;
        document.getElementById('edit_content').value       = contentEl ? contentEl.value : '';
        new bootstrap.Modal(document.getElementById('editTopicModal')).show();
    });
});

// Uploadcare: amaliy ish fayli
document.querySelectorAll('[id^="practical-upload-"]').forEach(input => {
    const topicId = input.dataset.topicId;
    const widget  = uploadcare.Widget(input);

    widget.onUploadComplete(function(info) {
        // Fayl URL va nomini yashirin inputlarga yozish
        document.getElementById('file-url-'  + topicId).value = info.cdnUrl;
        document.getElementById('file-name-' + topicId).value = info.name;

        // Saqlash tugmasini faollashtirish
        const btn  = document.getElementById('submit-practical-' + topicId);
        const hint = document.getElementById('save-hint-' + topicId);
        if (btn)  { btn.disabled = false; }
        if (hint) { hint.textContent = 'Endi "Saqlash" ni bosing'; hint.style.color = 'var(--success)'; }

        // Zona vizual
        const zone = document.getElementById('zone-' + topicId);
        if (zone) zone.style.borderColor = 'var(--success)';
    });
});
JS;

require_once __DIR__ . '/../includes/footer.php';
?>
