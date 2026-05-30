<?php
$pageTitle = 'AI Yordamchi';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('student');

$studentId = $_SESSION['user_id'];

// AI yoqilganmi
$aiEnabled = getSetting('enable_ai_assistant', '1') === '1';

// Chat tarixi
$history = db()->fetchAll(
    "SELECT * FROM ai_chat_history WHERE student_id = ? ORDER BY created_at DESC LIMIT 50",
    [$studentId]
);
$history = array_reverse($history);

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.chat-layout {
    display: grid;
    grid-template-rows: 1fr auto;
    height: calc(100vh - var(--header-height) - 4rem);
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    overflow: hidden;
}
.chat-messages {
    overflow-y: auto;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.chat-input-area {
    border-top: 1px solid var(--border);
    padding: 1rem;
    background: var(--bg-tertiary);
}
.message {
    display: flex;
    gap: 0.75rem;
    align-items: start;
    max-width: 80%;
}
.message.user {
    align-self: flex-end;
    flex-direction: row-reverse;
}
.message-bubble {
    padding: 0.75rem 1rem;
    border-radius: var(--radius-md);
    line-height: 1.5;
    word-wrap: break-word;
}
.message.user .message-bubble {
    background: var(--primary);
    color: white;
    border-bottom-right-radius: 0.25rem;
}
.message.ai .message-bubble {
    background: var(--bg-tertiary);
    border: 1px solid var(--border);
    border-bottom-left-radius: 0.25rem;
}
.message-bubble pre {
    background: #1e293b;
    color: #e2e8f0;
    padding: 0.75rem;
    border-radius: var(--radius);
    overflow-x: auto;
    font-size: 0.85rem;
    margin: 0.5rem 0;
}
.message-bubble code {
    background: rgba(0,0,0,0.1);
    padding: 0.1rem 0.3rem;
    border-radius: 4px;
    font-size: 0.9em;
}
.message.user .message-bubble code {
    background: rgba(255,255,255,0.2);
}
.avatar-mini {
    width: 36px; height: 36px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    color: white; font-weight: 600;
}
.avatar-mini.user { background: var(--primary); }
.avatar-mini.ai { background: var(--secondary); }
.typing-indicator {
    display: flex; gap: 0.25rem; align-items: center;
}
.typing-indicator span {
    width: 8px; height: 8px; border-radius: 50%;
    background: var(--text-tertiary);
    animation: typing 1.4s ease-in-out infinite;
}
.typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
.typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
@keyframes typing {
    0%, 60%, 100% { opacity: 0.3; }
    30% { opacity: 1; }
}
</style>

<?php if (!$aiEnabled): ?>
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 3rem;">
            <i class="fas fa-robot" style="font-size: 3rem; color: var(--text-tertiary);"></i>
            <h3 style="color: var(--text-tertiary); margin-top: 1rem;">AI yordamchi o'chirilgan</h3>
            <p style="color: var(--text-tertiary);">Administrator bilan bog'laning</p>
        </div>
    </div>
<?php else: ?>
    <div class="chat-layout">
        <div class="chat-messages" id="chatMessages">
            <?php if (empty($history)): ?>
                <div class="message ai">
                    <div class="avatar-mini ai">AI</div>
                    <div class="message-bubble">
                        <strong>Assalomu alaykum! 👋</strong><br>
                        Men dasturlash bo'yicha AI yordamchimanman. Faqat dasturlash, algoritmlar va texnologiya mavzularida yordam bera olaman.
                        <br><br>
                        Misollar:
                        <ul style="margin: 0.5rem 0; padding-left: 1.5rem;">
                            <li>Bu kodda nima xatolik bor?</li>
                            <li>Rekursiya qanday ishlaydi?</li>
                            <li>Pythonda lambda funksiyalari haqida tushuntir</li>
                        </ul>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($history as $msg): ?>
                    <div class="message user">
                        <div class="avatar-mini user"><?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?></div>
                        <div class="message-bubble"><?= nl2br(e($msg['user_message'])) ?></div>
                    </div>
                    <div class="message ai">
                        <div class="avatar-mini ai">AI</div>
                        <div class="message-bubble"><?= formatAiResponse($msg['ai_response']) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="chat-input-area">
            <form id="chatForm" style="display: flex; gap: 0.5rem;">
                <textarea id="messageInput" 
                          class="form-control" 
                          placeholder="Savolingizni yozing... (Shift+Enter yangi qator)" 
                          rows="2" 
                          style="resize: none;"
                          required></textarea>
                <button type="submit" class="btn btn-primary" id="sendBtn">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php
function formatAiResponse(string $text): string {
    $text = e($text);
    // Code bloklarini formatlash
    $text = preg_replace('/```(\w+)?\n?(.*?)```/s', '<pre><code>$2</code></pre>', $text);
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
    $text = nl2br($text);
    return $text;
}

$apiUrl = SITE_URL . '/api/ai_chat.php';
$userInitial = strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1));
$inlineJs = <<<JS
const chatMessages = document.getElementById('chatMessages');
const chatForm = document.getElementById('chatForm');
const messageInput = document.getElementById('messageInput');
const sendBtn = document.getElementById('sendBtn');

// Avtomatik pastga scroll
chatMessages.scrollTop = chatMessages.scrollHeight;

// Shift+Enter — yangi qator, Enter — yuborish
messageInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        chatForm.requestSubmit();
    }
});

chatForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const message = messageInput.value.trim();
    if (!message) return;
    
    const userInitial = '$userInitial';
    
    addMessage('user', escapeHtml(message), userInitial);
    messageInput.value = '';
    sendBtn.disabled = true;
    
    // Typing indicator
    const typingDiv = document.createElement('div');
    typingDiv.className = 'message ai';
    typingDiv.id = 'typingIndicator';
    typingDiv.innerHTML = '<div class="avatar-mini ai">AI</div><div class="message-bubble"><div class="typing-indicator"><span></span><span></span><span></span></div></div>';
    chatMessages.appendChild(typingDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
    
    try {
        const response = await fetch('$apiUrl', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message })
        });
        const data = await response.json();
        
        document.getElementById('typingIndicator')?.remove();
        
        if (data.success) {
            addMessage('ai', formatResponse(data.response), 'AI');
        } else {
            addMessage('ai', '<em style="color: var(--danger);">Xatolik: ' + escapeHtml(data.message || 'Nomalum xatolik') + '</em>', 'AI');
        }
    } catch (err) {
        document.getElementById('typingIndicator')?.remove();
        addMessage('ai', '<em style="color: var(--danger);">Tarmoq xatosi: ' + err.message + '</em>', 'AI');
    } finally {
        sendBtn.disabled = false;
        messageInput.focus();
    }
});

function addMessage(type, content, avatar) {
    const div = document.createElement('div');
    div.className = 'message ' + type;
    div.innerHTML = '<div class="avatar-mini ' + type + '">' + avatar + '</div><div class="message-bubble">' + content + '</div>';
    chatMessages.appendChild(div);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function escapeHtml(s) {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\\n/g, '<br>');
}

function formatResponse(text) {
    text = escapeHtml(text);
    // Code bloklarni format qilish
    text = text.replace(/\`\`\`(\\w+)?[\\r\\n]?([\\s\\S]*?)\`\`\`/g, '<pre><code>$2</code></pre>');
    text = text.replace(/\`([^\`]+)\`/g, '<code>$1</code>');
    return text;
}
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
