<?php
/**
 * AI Chat API
 * Claude yoki OpenAI bilan suhbatlashish (faqat dasturlash mavzularida)
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

$aiEnabled = getSetting('enable_ai_assistant', '1') === '1';
if (!$aiEnabled) {
    echo json_encode(['success' => false, 'message' => 'AI yordamchi o\'chirilgan']);
    exit;
}

$studentId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Xabar bo\'sh bo\'lishi mumkin emas']);
    exit;
}

if (mb_strlen($message) > 2000) {
    echo json_encode(['success' => false, 'message' => 'Xabar 2000 ta belgidan oshmasligi kerak']);
    exit;
}

// AI model va barcha kalitlarni bazadan olish
$aiModel = getSetting('ai_model', 'gemini-flash-latest');
$claudeKey = getSetting('claude_api_key', '');
$openaiKey = getSetting('openai_api_key', '');
$geminiKey = getSetting('gemini_api_key', ''); // Gemini kalitini ham olamiz

// Qaysi provayder tanlanganini aniqlash va mos kalitni yuklash
if (strpos($aiModel, 'gemini') === 0) {
    $apiKey = $geminiKey;
} elseif (strpos($aiModel, 'claude') === 0) {
    $apiKey = $claudeKey;
} else {
    $apiKey = $openaiKey;
}

// Kalit borligini tekshirish (Endi Gemini uchun ham ishlaydi)
if (empty($apiKey)) {
    echo json_encode([
        'success' => false, 
        'message' => 'AI API kalit sozlanmagan. Administratorga murojaat qiling.'
    ]);
    exit;
}

// SYSTEM PROMPT — faqat dasturlash mavzularida javob ber
$systemPrompt = "Sen \"CodeAcademy\" o'quv platformasining AI yordamchisisan. Sening vazifang — talabalarga DASTURLASH, ALGORITMLAR, MA'LUMOTLAR TUZILMASI, KOMPYUTER FANLARI, va TEXNOLOGIYA mavzularida yordam berish. ".
"\n\nQAT'IY QOIDALAR:" .
"\n1. Faqat dasturlash, kompyuter fanlari va texnologiya bilan bog'liq savollarga javob ber." .
"\n2. Boshqa mavzulardagi savollarga (siyosat, din, dars to'g'risi bo'lmagan masalalar, shaxsiy hayot va h.k.) JAVOB BERMA. Buning o'rniga muloyimlik bilan: 'Kechirasiz, men faqat dasturlash mavzularida yordam bera olaman' deb javob ber." .
"\n3. Javoblaringni o'zbek tilida ber (agar boshqa tilda so'ralmasa)." .
"\n4. Kod misollarini markdown ` ``` ` ichida ber." .
"\n5. Talabaga to'g'ridan-to'g'ri javob (vazifaning yechimi) bermaslik o'rniga, mavzuni tushuntirib, ko'rsatmalar berish va ularning o'zlari yechishlariga yordam ber." .
"\n6. Qisqa va aniq javob ber. Keraksiz so'zlardan saqlan." .
"\n7. Agar savol noaniq bo'lsa, qayta savol berib aniqlashtir.";

// Claude API chaqiruvi
function callClaude(string $apiKey, string $model, string $systemPrompt, string $userMessage): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.anthropic.com/v1/messages',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'model' => $model,
            'max_tokens' => 1500,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userMessage]
            ]
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) return ['error' => 'cURL: ' . $err];
    
    $data = json_decode($response, true);
    if ($httpCode !== 200) {
        $msg = $data['error']['message'] ?? 'HTTP ' . $httpCode;
        return ['error' => $msg];
    }
    
    $text = $data['content'][0]['text'] ?? '';
    return ['text' => $text];
}

// OpenAI API chaqiruvi
function callOpenAI(string $apiKey, string $model, string $systemPrompt, string $userMessage): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'model' => $model,
            'max_tokens' => 1500,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage]
            ]
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) return ['error' => 'cURL: ' . $err];
    
    $data = json_decode($response, true);
    if ($httpCode !== 200) {
        $msg = $data['error']['message'] ?? 'HTTP ' . $httpCode;
        return ['error' => $msg];
    }
    
    $text = $data['choices'][0]['message']['content'] ?? '';
    return ['text' => $text];
}

function callGemini(string $apiKey, string $model, string $systemPrompt, string $userMessage): array {
    // Model nomi dinamik ravishda "gemini-flash-latest" kabi boradi
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent";

    $ch = curl_init($url);
    
    // cURL parametrlarini aynan siz yuborgan namunaga moslaymiz
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-goog-api-key: ' . $apiKey // Header orqali kalit yuborish
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'contents' => [
                [
                    'parts' => [
                        // System prompt va foydalanuvchi xabari birlashtiriladi
                        ['text' => $systemPrompt . "\n\nUser: " . $userMessage]
                    ]
                ]
            ]
        ])
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) return ['error' => 'cURL Error: ' . $err];
    
    $data = json_decode($response, true);
    
    // Agar Google xatolik qaytarsa
    if (isset($data['error'])) {
        return ['error' => $data['error']['message'] ?? 'Noma\'lum xatolik'];
    }

    // Javob matnini olish
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    return ['text' => $text];
}

// AI model va barcha kalitlarni olish
$aiModel = getSetting('ai_model', 'claude-3-5-sonnet-20241022');
$claudeKey = getSetting('claude_api_key', '');
$openaiKey = getSetting('openai_api_key', '');
$geminiKey = getSetting('gemini_api_key', ''); // Yangi kalit

// API chaqirish mantig'ini kengaytiramiz
if (strpos($aiModel, 'gemini') === 0) {
    // Agar model nomi 'gemini' bilan boshlansa, yangi funksiyani chaqiramiz
    $result = callGemini($geminiKey, $aiModel, $systemPrompt, $message);
} else {
    // ESKI KODINGIZ: Agar Gemini bo'lmasa, eski mantiq o'zgarishsiz ishlaydi
    $useClaude = strpos($aiModel, 'claude') === 0;
    $apiKey = $useClaude ? $claudeKey : $openaiKey;

    $result = $useClaude
        ? callClaude($apiKey, $aiModel, $systemPrompt, $message)
        : callOpenAI($apiKey, $aiModel, $systemPrompt, $message);
}

// Xatolikni tekshirish (o'zgarishsiz qoldi)
if (isset($result['error'])) {
    echo json_encode(['success' => false, 'message' => 'AI xatolik: ' . $result['error']]);
    exit;
}
$aiResponse = $result['text'] ?: 'Kechirasiz, javob ololmadim';

// Ma'lumotlar bazasiga saqlash
try {
    db()->insert('ai_chat_history', [
        'student_id' => $studentId,
        'user_message' => $message,
        'ai_response' => $aiResponse,
        'model' => $aiModel
    ]);
} catch (Exception $e) {
    // Saqlashda xato bo'lsa ham javob qaytaramiz
}

echo json_encode([
    'success' => true,
    'response' => $aiResponse
]);
