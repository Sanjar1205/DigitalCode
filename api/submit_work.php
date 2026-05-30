<?php
/**
 * SUBMIT WORK API
 * - Student base64 fayl yuboradi
 * - Gemini PDF ni o'qib baholaydi
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!Auth::isLoggedIn() || !Auth::hasRole('student')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Avtorizatsiya talab qilinadi']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Faqat POST']);
    exit;
}

$studentId = (int)$_SESSION['user_id'];
$input     = json_decode(file_get_contents('php://input'), true);
$topicId   = (int)($input['topic_id']  ?? 0);
$fileName  = trim($input['file_name']  ?? '');
$fileData  = trim($input['file_data']  ?? ''); // base64
$mimeType  = trim($input['mime_type']  ?? 'application/pdf');
$type      = trim($input['type']       ?? '');

if (!$topicId || empty($fileName) || empty($fileData) || !in_array($type, ['practical', 'independent'])) {
    echo json_encode(['success' => false, 'message' => 'Noto\'g\'ri so\'rov ma\'lumotlari']);
    exit;
}

// Mavzu va fan
$topic = db()->fetchOne(
    "SELECT t.*, s.id as subject_id, s.name as subject_name
     FROM topics t
     JOIN subjects s ON t.subject_id = s.id
     JOIN subject_students ss ON s.id = ss.subject_id
     WHERE t.id = ? AND ss.student_id = ?",
    [$topicId, $studentId]
);
if (!$topic) {
    echo json_encode(['success' => false, 'message' => 'Bu mavzuga ruxsatingiz yo\'q']);
    exit;
}

// Submissions ga yozish (file_url ga fayl nomi saqlanadi)
try {
    $submissionId = db()->insert('submissions', [
        'student_id' => $studentId,
        'topic_id'   => $topicId,
        'type'       => $type,
        'file_url'   => $fileName,
        'file_name'  => $fileName,
        'status'     => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Bazaga yozishda xatolik: ' . $e->getMessage()]);
    exit;
}

// AI sozlamalari
$geminiKey = getSetting('gemini_api_key', '');
$claudeKey = getSetting('claude_api_key', '');
$aiModel   = getSetting('ai_model', 'gemini-1.5-pro-latest');

if (!empty($geminiKey) && strpos(strtolower($aiModel), 'gemini') !== false) {
    $useGemini = true;  $apiKey = $geminiKey;
} elseif (!empty($claudeKey)) {
    $useGemini = false; $apiKey = $claudeKey;
} elseif (!empty($geminiKey)) {
    $useGemini = true;  $apiKey = $geminiKey;
} else {
    $apiKey = '';
}

if (empty($apiKey)) {
    db()->update('submissions', ['status' => 'pending_manual'], 'id = :id', ['id' => $submissionId]);
    echo json_encode(['success' => true, 'message' => 'Ish qabul qilindi. O\'qituvchi tekshiradi.', 'ai_used' => false]);
    exit;
}

// Prompt tayyorlash
$typeLabel   = $type === 'practical' ? 'Amaliy ish' : 'Mustaqil ish';
$topicTitle  = $topic['title'];
$subjectName = $topic['subject_name'];

$teacherDescription = '';
$teacherCriteria    = '';
$practicalFileName  = '';

if ($type === 'practical') {
    $practicalFile = db()->fetchOne(
        "SELECT file_name, description, grading_criteria
         FROM topic_files
         WHERE topic_id = ? AND file_type = 'practical'
         ORDER BY id DESC LIMIT 1",
        [$topicId]
    );
    if ($practicalFile) {
        $practicalFileName  = $practicalFile['file_name']       ?? '';
        $teacherDescription = $practicalFile['description']     ?? '';
        $teacherCriteria    = $practicalFile['grading_criteria'] ?? '';
    }
}

if ($type === 'independent' && empty($teacherCriteria)) {
    $teacherCriteria = "1. Mavzu bo'yicha chuqur tahlil va o'z fikr-mulohazalari bo'lsin\n2. Amaliy misollar keltirilsin\n3. Mustaqil yondashuv va ijodkorlik ko'rinsin\n4. Matn aniq va ravon yozilgan bo'lsin";
}

$criteriaBlock = !empty($teacherCriteria) ? "\nO'QITUVCHI BAHOLASH TALABLARI:\n{$teacherCriteria}" : '';
$descBlock     = !empty($teacherDescription) ? "\nTopshiriq tavsifi: {$teacherDescription}" : '';
$fileBlock     = !empty($practicalFileName) ? "\nO'qituvchi topshirig'i fayli: \"{$practicalFileName}\"" : '';

$prompt = <<<PROMPT
Sen CodeAcademy dasturlash ta'lim platformasining qat'iy va adolatli AI baholovchisisan.
Talabaning "{$typeLabel}" ishini ikki bosqichda tekshir.

Yuqorida berilgan PDF — talabaning yuborgan ishi. Uni diqqat bilan o'qi.

━━━ KONTEKST ━━━
Fan: {$subjectName}
Mavzu: {$topicTitle}{$fileBlock}{$descBlock}{$criteriaBlock}
Fayl nomi: {$fileName}

━━━ BOSQICH 1: MAVZUGA MOSLIK ━━━
PDF ichidagi kontentga qarab, bu ish "{$topicTitle}" mavzusiga tegishlimi?
- UMUMAN bog'liq bo'lmasa (bo'sh fayl, boshqa fan, soxta kontent) → "rejected"
- Mos yoki qisman mos → baholashga o't

━━━ BOSQICH 2: BAHOLASH ━━━
{$criteriaBlock}

STANDART MEZONLAR (talablar yo'q bo'lsa):
| Mezon | Og'irlik |
|-------|----------|
| Mavzu mohiyatini to'g'ri tushunish | 35% |
| Bilimlarni amalda qo'llash | 25% |
| Izoh va tahlil chuqurligi | 25% |
| Tartib, aniqlik, mustaqillik | 15% |

BAHOLAR:
• 5 = 90-100 ball
• 4 = 70-89 ball
• 3 = 50-69 ball
• 2 = 0-49 ball

Faqat JSON formatda javob ber, boshqa hech narsa yozma:
{"status":"graded","grade":4,"feedback":"..."}
yoki
{"status":"rejected","grade":0,"feedback":"..."}
PROMPT;

// ─── AI funksiyalari ─────────────────────────────────────────
function callGemini(string $key, string $model, string $prompt, string $fileData, string $mimeType): array {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent';
    $body = [
        'contents' => [[
            'parts' => [
                ['inline_data' => ['mime_type' => $mimeType, 'data' => $fileData]],
                ['text' => $prompt]
            ]
        ]],
        'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 1500]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'X-goog-api-key: ' . $key],
        CURLOPT_POSTFIELDS     => json_encode($body)
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err)          return ['error' => 'cURL: ' . $err];
    if ($code !== 200) return ['error' => 'Gemini HTTP ' . $code . ': ' . substr($res, 0, 400)];
    $data = json_decode($res, true);
    if (isset($data['error'])) return ['error' => 'Gemini: ' . $data['error']['message']];
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (empty($text)) return ['error' => 'Gemini bo\'sh javob qaytardi'];
    return ['text' => trim($text)];
}

function callClaude(string $key, string $model, string $prompt, string $fileData, string $mimeType): array {
    // Claude uchun rasm yoki PDF
    $mediaTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (in_array($mimeType, $mediaTypes)) {
        $content = [
            ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mimeType, 'data' => $fileData]],
            ['type' => 'text', 'text' => $prompt]
        ];
    } else {
        // PDF — Claude document type
        $content = [
            ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $fileData]],
            ['type' => 'text', 'text' => $prompt]
        ];
    }

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . $key,
            'anthropic-version: 2023-06-01',
            'anthropic-beta: pdfs-2024-09-25'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model'      => $model,
            'max_tokens' => 1500,
            'messages'   => [['role' => 'user', 'content' => $content]]
        ])
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err)          return ['error' => 'cURL: ' . $err];
    if ($code !== 200) return ['error' => 'Claude HTTP ' . $code . ': ' . substr($res, 0, 400)];
    $data = json_decode($res, true);
    if (isset($data['error'])) return ['error' => 'Claude: ' . $data['error']['message']];
    $text = $data['content'][0]['text'] ?? '';
    if (empty($text)) return ['error' => 'Claude bo\'sh javob qaytardi'];
    return ['text' => trim($text)];
}

// AI chaqirish — fayl base64 bilan
$aiResult = $useGemini
    ? callGemini($apiKey, $aiModel, $prompt, $fileData, $mimeType)
    : callClaude($apiKey, $aiModel, $prompt, $fileData, $mimeType);

if (isset($aiResult['error'])) {
    db()->update('submissions', ['status' => 'pending_manual'], 'id = :id', ['id' => $submissionId]);
    echo json_encode([
        'success'  => true,
        'message'  => 'Ish qabul qilindi (AI xatoligi, o\'qituvchi tekshiradi)',
        'ai_error' => $aiResult['error']
    ]);
    exit;
}

// JSON parse - AI javobidan JSON ni topib olish
$raw = trim($aiResult['text']);
// ```json ... ``` ni olib tashlash
$raw = preg_replace('/^```json\s*/i', '', $raw);
$raw = preg_replace('/\s*```$/', '', $raw);
$raw = trim($raw);

// Agar hali ham JSON topilmasa, { ... } ni qidirish
$parsed = json_decode($raw, true);
if (!$parsed) {
    preg_match('/\{[^{}]*"status"[^{}]*\}/s', $raw, $matches);
    if (!empty($matches[0])) {
        $parsed = json_decode($matches[0], true);
    }
}
if (!$parsed) {
    preg_match('/\{.*\}/s', $raw, $matches);
    if (!empty($matches[0])) {
        $parsed = json_decode($matches[0], true);
    }
}

if (!$parsed || !isset($parsed['status'])) {
    $parsed = ['status' => 'graded', 'grade' => 3, 'feedback' => 'AI tahlil qildi lekin format noto\'g\'ri.'];
}

$aiStatus = $parsed['status'];
$grade    = (int)($parsed['grade']   ?? 3);
$feedback = trim($parsed['feedback'] ?? '');

if ($aiStatus === 'rejected') {
    db()->update('submissions', [
        'status'      => 'rejected',
        'grade'       => 0,
        'ai_feedback' => $feedback,
        'graded_at'   => date('Y-m-d H:i:s')
    ], 'id = :id', ['id' => $submissionId]);
    echo json_encode(['success' => true, 'rejected' => true, 'message' => 'Ish mavzuga mos kelmadi.', 'feedback' => $feedback]);
    exit;
}

$grade = max(2, min(5, $grade));

db()->update('submissions', [
    'status'      => 'graded',
    'grade'       => $grade,
    'ai_feedback' => $feedback,
    'graded_at'   => date('Y-m-d H:i:s')
], 'id = :id', ['id' => $submissionId]);

db()->query("DELETE FROM grades WHERE student_id = ? AND topic_id = ? AND type = ?", [$studentId, $topicId, $type]);
$adminId = db()->fetchOne("SELECT id FROM users WHERE role = 'admin' LIMIT 1")['id'] ?? 1;
db()->insert('grades', [
    'student_id' => $studentId,
    'subject_id' => $topic['subject_id'],
    'topic_id'   => $topicId,
    'grade'      => $grade,
    'type'       => $type,
    'comment'    => mb_substr($feedback, 0, 500),
    'given_by'   => $adminId
]);

if ($grade >= 3) {
    $fields = $type === 'practical'
        ? ['practical_completed' => 1, 'practical_grade' => $grade]
        : ['independent_completed' => 1, 'independent_grade' => $grade];

    $exists = db()->fetchOne(
        "SELECT id FROM student_progress WHERE student_id = ? AND topic_id = ?",
        [$studentId, $topicId]
    );
    if ($exists) {
        db()->update('student_progress', $fields,
            'student_id = :sid AND topic_id = :tid',
            ['sid' => $studentId, 'tid' => $topicId]
        );
    }
}

echo json_encode([
    'success'  => true,
    'grade'    => $grade,
    'feedback' => $feedback,
    'message'  => "{$typeLabel} baholandi! Baho: {$grade}"
]);
