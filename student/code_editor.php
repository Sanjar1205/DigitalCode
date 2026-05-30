<?php
$pageTitle = 'Kod editor';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('student');

$studentId = $_SESSION['user_id'];
$taskId = (int)($_GET['task_id'] ?? 0);

// Masala va ruxsat tekshirish
$task = db()->fetchOne(
    "SELECT t.*, tp.title as topic_title, tp.subject_id, s.programming_language 
     FROM tasks t 
     JOIN topics tp ON t.topic_id = tp.id 
     JOIN subjects s ON tp.subject_id = s.id 
     JOIN subject_students ss ON s.id = ss.subject_id 
     WHERE t.id = ? AND ss.student_id = ?", [$taskId, $studentId]
);

if (!$task) {
    setFlash('danger', 'Bu masalaga ruxsatingiz yo\'q');
    redirect(SITE_URL . '/student/my_subjects.php');
}

// Oxirgi yuborilgan kod
$lastSubmission = db()->fetchOne(
    "SELECT * FROM task_submissions WHERE student_id = ? AND task_id = ? 
     ORDER BY submitted_at DESC LIMIT 1", [$studentId, $taskId]
);

// Default kod templatlari
$templates = [
    'cpp' => "#include <iostream>\nusing namespace std;\n\nint main() {\n    // Kod yozing\n    \n    return 0;\n}",
    'java' => "import java.util.Scanner;\n\npublic class Main {\n    public static void main(String[] args) {\n        Scanner sc = new Scanner(System.in);\n        // Kod yozing\n        \n    }\n}",
    'python' => "# Kod yozing\n",
    'javascript' => "// Kod yozing\nconst readline = require('readline');\nconst rl = readline.createInterface({ input: process.stdin });\n\nrl.on('line', (line) => {\n    // Har bir liniya uchun\n});",
    'php' => "<?php\n// Kod yozing\n",
    'csharp' => "using System;\n\nclass Program {\n    static void Main() {\n        // Kod yozing\n        \n    }\n}"
];

$language = $task['programming_language'];
$initialCode = $lastSubmission ? $lastSubmission['code'] : ($templates[$language] ?? '// Kod yozing');

// Visible test caselar
$visibleTests = db()->fetchAll(
    "SELECT * FROM test_cases WHERE task_id = ? AND is_hidden = 0 ORDER BY order_number", [$taskId]
);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.editor-layout {
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 1rem;
    height: calc(100vh - var(--header-height) - 4rem);
}
@media (max-width: 992px) {
    .editor-layout { grid-template-columns: 1fr; height: auto; }
}
.task-panel {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    overflow-y: auto;
}
.editor-panel {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.editor-container {
    flex: 1;
    background: #1e1e1e;
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    overflow: hidden;
    min-height: 400px;
}
.results-panel {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: 1rem;
    max-height: 300px;
    overflow-y: auto;
}
.test-result {
    padding: 0.75rem;
    border-radius: var(--radius);
    margin-bottom: 0.5rem;
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.85rem;
}
.test-result.passed {
    background: rgba(16, 185, 129, 0.1);
    border-left: 3px solid var(--success);
}
.test-result.failed {
    background: rgba(239, 68, 68, 0.1);
    border-left: 3px solid var(--danger);
}
.editor-toolbar {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: 0.75rem 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}
.task-section {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border);
}
.task-section:last-child { border-bottom: none; }
.io-block {
    background: var(--bg-tertiary);
    padding: 0.75rem;
    border-radius: var(--radius);
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.85rem;
    margin: 0.5rem 0;
    white-space: pre-wrap;
}
</style>

<div style="margin-bottom: 1rem; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
    <a href="<?= SITE_URL ?>/student/learn.php?subject_id=<?= $task['subject_id'] ?>&topic_id=<?= $task['topic_id'] ?>" 
       class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Mavzuga qaytish
    </a>
    <h2 style="margin: 0; flex: 1;"><?= e($task['title']) ?></h2>
    <span class="badge badge-<?= ['easy'=>'success','medium'=>'warning','hard'=>'danger'][$task['difficulty']] ?>">
        <?= ['easy'=>'Oson','medium'=>'O\'rta','hard'=>'Qiyin'][$task['difficulty']] ?>
    </span>
    <?php if ($lastSubmission && $lastSubmission['grade'] > 0): ?>
        <span class="badge badge-<?= gradeColor($lastSubmission['grade']) ?>" style="font-size: 1rem;">
            Oxirgi baho: <?= $lastSubmission['grade'] ?>
        </span>
    <?php endif; ?>
</div>

<div class="editor-layout">
    <!-- LEFT: Masala matni -->
    <div class="task-panel">
        <div class="task-section">
            <h4 style="margin: 0 0 0.5rem;">Masala</h4>
            <div style="font-size: 0.95rem; line-height: 1.6;">
                <?= nl2br(e($task['description'])) ?>
            </div>
        </div>
        
        <?php if ($task['input_example']): ?>
            <div class="task-section">
                <h4 style="margin: 0 0 0.5rem;">Kirish misoli</h4>
                <div class="io-block"><?= e($task['input_example']) ?></div>
                <h4 style="margin: 1rem 0 0.5rem;">Chiqish misoli</h4>
                <div class="io-block"><?= e($task['output_example']) ?></div>
            </div>
        <?php endif; ?>
        
        <div class="task-section">
            <h4 style="margin: 0 0 0.5rem;">Cheklovlar</h4>
            <div style="font-size: 0.85rem; color: var(--text-secondary);">
                <div><i class="fas fa-clock"></i> Vaqt: <?= $task['time_limit'] ?> ms</div>
                <div><i class="fas fa-memory"></i> Xotira: <?= $task['memory_limit'] ?> MB</div>
                <div><i class="fas fa-language"></i> Til: <strong><?= strtoupper($language) ?></strong></div>
            </div>
        </div>
        
        <?php if (!empty($visibleTests)): ?>
            <div class="task-section">
                <h4 style="margin: 0 0 0.75rem;">Test misollari</h4>
                <?php foreach ($visibleTests as $i => $t): ?>
                    <div style="margin-bottom: 1rem;">
                        <strong style="font-size: 0.85rem;">Test #<?= $i + 1 ?></strong>
                        <div style="font-size: 0.8rem; color: var(--text-tertiary); margin: 0.25rem 0;">Input:</div>
                        <div class="io-block"><?= e($t['input_data']) ?></div>
                        <div style="font-size: 0.8rem; color: var(--text-tertiary); margin: 0.25rem 0;">Output:</div>
                        <div class="io-block"><?= e($t['expected_output']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- RIGHT: Editor + Natijalar -->
    <div class="editor-panel">
        <div class="editor-toolbar">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <i class="fas fa-code" style="color: var(--primary);"></i>
                <span style="font-weight: 500;"><?= strtoupper($language) ?> editor</span>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <button class="btn btn-sm btn-secondary" onclick="resetCode()">
                    <i class="fas fa-undo"></i> Tiklash
                </button>
                <button class="btn btn-sm btn-warning" onclick="runCode()" id="runBtn">
                    <i class="fas fa-play"></i> Ishga tushirish
                </button>
                <button class="btn btn-sm btn-success" onclick="submitCode()" id="submitBtn">
                    <i class="fas fa-paper-plane"></i> Topshirish
                </button>
            </div>
        </div>
        
        <div class="editor-container" id="editor"></div>
        
        <div class="results-panel" id="resultsPanel" style="display: none;">
            <h5 style="margin: 0 0 0.75rem;">Natijalar</h5>
            <div id="resultsContent"></div>
        </div>
    </div>
</div>

<?php
$initialCodeJs = json_encode($initialCode);
$languageJs = json_encode($language);
$taskIdJs = $taskId;
$topicIdJs = $task['topic_id'];
$subjectIdJs = $task['subject_id'];
$apiRunUrl = SITE_URL . '/api/code_executor.php';

$extraJs = ['https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs/loader.min.js'];
$inlineJs = <<<JS
const TASK_ID = $taskIdJs;
const TOPIC_ID = $topicIdJs;
const SUBJECT_ID = $subjectIdJs;
const LANGUAGE = $languageJs;
const INITIAL_CODE = $initialCodeJs;
const API_URL = '$apiRunUrl';

let editor;

// Monaco Editor sozlash
require.config({ paths: { vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs' }});
require(['vs/editor/editor.main'], function () {
    const monacoLang = {
        'cpp': 'cpp', 'java': 'java', 'python': 'python',
        'javascript': 'javascript', 'php': 'php', 'csharp': 'csharp'
    }[LANGUAGE] || 'plaintext';
    
    editor = monaco.editor.create(document.getElementById('editor'), {
        value: INITIAL_CODE,
        language: monacoLang,
        theme: 'vs-dark',
        fontSize: 14,
        fontFamily: 'JetBrains Mono, monospace',
        minimap: { enabled: false },
        automaticLayout: true,
        scrollBeyondLastLine: false,
        wordWrap: 'on',
        tabSize: 4
    });
    
    // Avtomatik saqlash (har 30 sekund)
    setInterval(() => {
        localStorage.setItem('code_' + TASK_ID, editor.getValue());
    }, 30000);
    
    // Saqlangan kodi bor bo'lsa qayta yuklash
    const saved = localStorage.getItem('code_' + TASK_ID);
    if (saved && saved !== INITIAL_CODE && confirm('Saqlangan ish bor. Yuklaymizmi?')) {
        editor.setValue(saved);
    }
});

function resetCode() {
    if (confirm('Boshlang\\'ich kodga qaytmoqchimisiz?')) {
        editor.setValue(INITIAL_CODE);
    }
}

async function runCode() {
    await executeCode(false);
}

async function submitCode() {
    if (!confirm('Yechimni yakuniy topshirmoqchimisiz?')) return;
    await executeCode(true);
}

async function executeCode(isSubmit) {
    const btn = isSubmit ? document.getElementById('submitBtn') : document.getElementById('runBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ishlatilmoqda...';
    
    const resultsPanel = document.getElementById('resultsPanel');
    const resultsContent = document.getElementById('resultsContent');
    resultsPanel.style.display = 'block';
    resultsContent.innerHTML = '<div style="text-align: center; padding: 1rem;"><div class="spinner" style="margin: 0 auto;"></div><p>Kompilyatsiya qilinmoqda...</p></div>';
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                task_id: TASK_ID,
                code: editor.getValue(),
                language: LANGUAGE,
                submit: isSubmit
            })
        });
        
        // Avval text sifatida o'qib, keyin JSON parse qilamiz - shunda xato chiqsa ko'rsata olamiz
        const responseText = await response.text();
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseErr) {
            resultsContent.innerHTML = '<div class="alert alert-danger">' +
                '<strong>Server javobini o\'qib bo\'lmadi (JSON parse xato).</strong><br>' +
                'HTTP status: ' + response.status + '<br>' +
                '<small>Server javobi (birinchi 1000 belgi):</small>' +
                '<pre style="max-height:300px;overflow:auto;background:#1a1a1a;color:#0f0;padding:10px;font-size:11px;margin-top:8px;">' + 
                (responseText.substring(0, 1000) || '(bo\'sh javob)').replace(/</g,'&lt;') + 
                '</pre></div>';
            return;
        }
        
        displayResults(result, isSubmit);
        
        // Submit bo'lsa va passed bo'lsa - qayta yo'naltiramiz
        if (isSubmit && result.success && result.grade >= 3) {
            setTimeout(() => {
                location.href = '/codeacademy/student/learn.php?subject_id=' + SUBJECT_ID + '&topic_id=' + TOPIC_ID;
            }, 3000);
        }
    } catch (e) {
        resultsContent.innerHTML = '<div class="alert alert-danger">Tarmoq xatosi: ' + e.message + '</div>';
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

function displayResults(result, isSubmit) {
    const panel = document.getElementById('resultsContent');
    
    if (!result.success) {
        panel.innerHTML = '<div class="alert alert-danger"><strong>Xatolik:</strong> ' + (result.message || 'Noma\\'lum xatolik') + '</div>';
        return;
    }
    
    let html = '';
    
    if (result.compile_error) {
        html += '<div class="alert alert-danger"><strong>Kompilyatsiya xatosi:</strong><pre style="margin-top:0.5rem;">' + escapeHtml(result.compile_error) + '</pre></div>';
        panel.innerHTML = html;
        return;
    }
    
    // Statistika
    if (isSubmit) {
        const gradeColors = { 5: 'success', 4: 'primary', 3: 'warning', 2: 'danger' };
        const gradeText = { 5: "A'lo (5)", 4: 'Yaxshi (4)', 3: "Qoniqarli (3)", 2: "Qoniqarsiz (2)" };
        html += '<div class="alert alert-' + gradeColors[result.grade] + '">';
        html += '<strong>Topshiriq baholandi!</strong><br>';
        html += 'Test natijasi: ' + result.passed + '/' + result.total + ' (' + result.score_percent + '%)<br>';
        html += '<strong>Baho: ' + gradeText[result.grade] + '</strong>';
        html += '</div>';
    } else {
        html += '<p><strong>Test natijalari:</strong> ' + result.passed + '/' + result.total + ' o\\'tildi (' + result.score_percent + '%)</p>';
    }
    
    // Har bir test natijasi
    (result.tests || []).forEach((t, i) => {
        const cls = t.passed ? 'passed' : 'failed';
        html += '<div class="test-result ' + cls + '">';
        html += '<strong>Test #' + (i + 1) + '</strong> ';
        if (t.passed) {
            html += '<span style="color: var(--success);"><i class="fas fa-check"></i> O\\'tdi</span>';
        } else {
            html += '<span style="color: var(--danger);"><i class="fas fa-times"></i> O\\'tmadi</span>';
            if (!t.hidden && t.input) {
                html += '<div style="margin-top:0.5rem; font-size: 0.8rem;"><strong>Input:</strong> ' + escapeHtml(t.input) + '</div>';
                html += '<div style="font-size: 0.8rem;"><strong>Kutilgan:</strong> ' + escapeHtml(t.expected) + '</div>';
                html += '<div style="font-size: 0.8rem;"><strong>Sizning natijangiz:</strong> ' + escapeHtml(t.actual || '(bo\\'sh)') + '</div>';
            }
        }
        html += '</div>';
    });
    
    panel.innerHTML = html;
}

function escapeHtml(s) {
    if (!s) return '';
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
