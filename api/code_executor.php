<?php
/**
 * Code Executor - JDoodle API
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Avtorizatsiya talab qilinadi']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Faqat POST']);
    exit;
}

$studentId = (int)$_SESSION['user_id'];
$input     = json_decode(file_get_contents('php://input'), true);
$taskId    = (int)($input['task_id']  ?? 0);
$code      = $input['code']           ?? '';
$lang      = strtolower(trim($input['language'] ?? ''));
$isSubmit  = (bool)($input['submit'] ?? false);

if (!$taskId || empty($code) || !$lang) {
    echo json_encode(['success' => false, 'message' => 'Majburiy maydonlar: task_id, code, language']);
    exit;
}

$task = db()->fetchOne("SELECT * FROM tasks WHERE id = ?", [$taskId]);
if (!$task) {
    echo json_encode(['success' => false, 'message' => 'Masala topilmadi']);
    exit;
}

$testCases = $isSubmit
    ? db()->fetchAll("SELECT * FROM test_cases WHERE task_id = ? ORDER BY id", [$taskId])
    : db()->fetchAll("SELECT * FROM test_cases WHERE task_id = ? AND is_hidden = 0 ORDER BY id", [$taskId]);

if (empty($testCases)) {
    echo json_encode(['success' => false, 'message' => 'Test caselar topilmadi']);
    exit;
}

// JDoodle til nomlari va versiyalari
$langMap = [
    'cpp'        => ['language' => 'cpp17',      'versionIndex' => '0'],
    'c'          => ['language' => 'c',           'versionIndex' => '5'],
    'java'       => ['language' => 'java',        'versionIndex' => '4'],
    'python'     => ['language' => 'python3',     'versionIndex' => '4'],
    'javascript' => ['language' => 'nodejs',      'versionIndex' => '4'],
    'php'        => ['language' => 'php',         'versionIndex' => '4'],
    'csharp'     => ['language' => 'csharp',      'versionIndex' => '4'],
];

if (!isset($langMap[$lang])) {
    echo json_encode(['success' => false, 'message' => "Til qo'llab-quvvatlanmaydi: $lang"]);
    exit;
}

$clientId     = getSetting('jdoodle_client_id', '');
$clientSecret = getSetting('jdoodle_client_secret', '');

if (empty($clientId) || empty($clientSecret)) {
    echo json_encode(['success' => false, 'message' => 'JDoodle API kalitlari sozlanmagan. Admin paneliga kiring.']);
    exit;
}

function runJDoodle(string $clientId, string $clientSecret, string $lang, string $versionIndex, string $code, string $stdin): array {
    $payload = [
        'clientId'     => $clientId,
        'clientSecret' => $clientSecret,
        'script'       => $code,
        'stdin'        => $stdin,
        'language'     => $lang,
        'versionIndex' => $versionIndex,
    ];

    $ch = curl_init('https://api.jdoodle.com/v1/execute');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($payload),
    ]);

    $res  = curl_exec($ch);
    $code_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) return ['error' => 'cURL: ' . $err];
    if ($code_http !== 200) return ['error' => 'JDoodle HTTP ' . $code_http . ': ' . substr($res, 0, 300)];

    $data = json_decode($res, true);
    if (!$data) return ['error' => 'JDoodle javob parse xatosi'];
    if (isset($data['error'])) return ['error' => 'JDoodle: ' . $data['error']];

    return [
        'output' => trim($data['output'] ?? ''),
        'memory' => $data['memory'] ?? '',
        'cpuTime'=> $data['cpuTime'] ?? '',
        'statusCode' => $data['statusCode'] ?? 200,
    ];
}

$jLang    = $langMap[$lang]['language'];
$jVersion = $langMap[$lang]['versionIndex'];

$testResults = [];
$passedCount = 0;

foreach ($testCases as $tc) {
    $result = runJDoodle($clientId, $clientSecret, $jLang, $jVersion, $code, $tc['input_data']);

    if (isset($result['error'])) {
        $testResults[] = [
            'passed'   => false,
            'hidden'   => (bool)$tc['is_hidden'],
            'input'    => $tc['is_hidden'] ? null : $tc['input_data'],
            'expected' => $tc['is_hidden'] ? null : trim($tc['expected_output']),
            'actual'   => null,
            'status'   => 'API Error: ' . $result['error'],
        ];
        continue;
    }

    $actual   = $result['output'];
    $expected = trim($tc['expected_output']);

    // Normalize: \r\n -> \n, trailing spaces olib tashlash
    $actualNorm   = trim(str_replace(["\r\n", "\r"], "\n", $actual));
    $expectedNorm = trim(str_replace(["\r\n", "\r"], "\n", $expected));

    // Runtime xatolik tekshirish
    $isError  = ($result['statusCode'] != 200) || 
                (stripos($actual, 'error') !== false && empty($actual));

    $passed = !$isError && ($actualNorm === $expectedNorm);
    if ($passed) $passedCount++;

    $testResults[] = [
        'passed'   => $passed,
        'hidden'   => (bool)$tc['is_hidden'],
        'input'    => $tc['is_hidden'] ? null : $tc['input_data'],
        'expected' => $tc['is_hidden'] ? null : $expected,
        'actual'   => $tc['is_hidden'] ? null : $actual,
        'status'   => $passed ? 'Accepted' : ($isError ? 'Runtime Error' : 'Wrong Answer'),
    ];
}

$total   = count($testCases);
$score   = $total > 0 ? round(($passedCount / $total) * 100) : 0;
$grade   = $score >= 90 ? 5 : ($score >= 70 ? 4 : ($score >= 50 ? 3 : 2));

if ($isSubmit) {
    db()->insert('task_submissions', [
        'student_id'    => $studentId,
        'task_id'       => $taskId,
        'code'          => $code,
        'language'      => $lang,
        'passed_tests'  => $passedCount,
        'total_tests'   => $total,
        'score_percent' => $score,
        'grade'         => $grade,
        'submitted_at'  => date('Y-m-d H:i:s'),
    ]);

    // Progress yangilash
    if ($grade >= 3) {
        $exists = db()->fetchOne(
            "SELECT id FROM student_progress WHERE student_id = ? AND topic_id = ?",
            [$studentId, $task['topic_id']]
        );
        if ($exists) {
            db()->update('student_progress',
                ['task_completed' => 1, 'task_grade' => $grade],
                'student_id = :sid AND topic_id = :tid',
                ['sid' => $studentId, 'tid' => $task['topic_id']]
            );
        }
    }

    // Grades jadvaliga
    $subject = db()->fetchOne("SELECT subject_id FROM topics WHERE id = ?", [$task['topic_id']]);
    if ($subject) {
        db()->query("DELETE FROM grades WHERE student_id = ? AND topic_id = ? AND type = 'task'",
            [$studentId, $task['topic_id']]);
        db()->insert('grades', [
            'student_id' => $studentId,
            'subject_id' => $subject['subject_id'],
            'topic_id'   => $task['topic_id'],
            'grade'      => $grade,
            'type'       => 'task',
            'comment'    => "Kod: {$passedCount}/{$total} test ({$score}%)",
            'given_by'   => $studentId,
        ]);
    }
}

echo json_encode([
    'success'       => true,
    'passed'        => $passedCount,
    'total'         => $total,
    'score_percent' => $score,
    'grade'         => $grade,
    'tests'         => $testResults,
]);


// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++bu eski kod va unda local complier ishlatilgan. o'rniga men, jdoodle apidan foydlaandim.
// /**
//  * LOKAL Code Executor API
//  * Mahalliy kompilyatorlar (g++, python, javac+java) orqali kodni ishga tushiradi
//  * Judge0 API kerak emas!
//  * 
//  * Talab qilinadigan kompilyatorlar:
//  * - C++: g++ (MinGW yoki MSYS2)
//  * - Python: python (3.x)
//  * - Java: javac va java (JDK 11+)
//  */

// // ============================================================
// // GLOBAL ERROR HANDLER - har qanday xato JSON sifatida qaytadi
// // ============================================================
// ini_set('display_errors', '0');
// error_reporting(E_ALL);

// set_error_handler(function($errno, $errstr, $errfile, $errline) {
//     if (!(error_reporting() & $errno)) return false;
//     while (ob_get_level()) ob_end_clean();
//     header('Content-Type: application/json; charset=utf-8');
//     http_response_code(500);
//     echo json_encode([
//         'success' => false,
//         'message' => "PHP xato: $errstr",
//         'file' => basename($errfile) . ':' . $errline,
//         'errno' => $errno
//     ]);
//     exit;
// });

// set_exception_handler(function($e) {
//     while (ob_get_level()) ob_end_clean();
//     header('Content-Type: application/json; charset=utf-8');
//     http_response_code(500);
//     echo json_encode([
//         'success' => false,
//         'message' => 'Exception: ' . $e->getMessage(),
//         'file' => basename($e->getFile()) . ':' . $e->getLine()
//     ]);
//     exit;
// });

// register_shutdown_function(function() {
//     $error = error_get_last();
//     if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
//         while (ob_get_level()) ob_end_clean();
//         header('Content-Type: application/json; charset=utf-8');
//         http_response_code(500);
//         echo json_encode([
//             'success' => false,
//             'message' => 'Fatal: ' . $error['message'],
//             'file' => basename($error['file']) . ':' . $error['line']
//         ]);
//     }
// });

// ob_start();
// header('Content-Type: application/json; charset=utf-8');
// require_once __DIR__ . '/../includes/auth.php';
// require_once __DIR__ . '/../includes/functions.php';

// // ============================================================
// // AUTH & VALIDATION
// // ============================================================
// if (!Auth::isLoggedIn() || !Auth::hasRole('student')) {
//     http_response_code(401);
//     echo json_encode(['success' => false, 'message' => 'Avtorizatsiya talab qilinadi']);
//     exit;
// }

// if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
//     http_response_code(405);
//     echo json_encode(['success' => false, 'message' => 'Faqat POST']);
//     exit;
// }

// $studentId = (int)$_SESSION['user_id'];
// $input = json_decode(file_get_contents('php://input'), true);

// $taskId = (int)($input['task_id'] ?? 0);
// $code = $input['code'] ?? '';
// $language = strtolower(trim($input['language'] ?? ''));
// $isSubmit = (bool)($input['submit'] ?? false);

// if (!$taskId || !$code || !$language) {
//     echo json_encode(['success' => false, 'message' => 'task_id, code va language talab qilinadi']);
//     exit;
// }

// // Kod uzunligi limiti (50KB)
// if (strlen($code) > 50000) {
//     echo json_encode(['success' => false, 'message' => 'Kod juda uzun (maksimum 50KB)']);
//     exit;
// }

// // Xavfsiz emas funksiyalarni bloklash
// $blacklist = [
//     'cpp' => ['system(', 'exec(', 'popen(', 'fork(', '#include <windows.h>', 'WinExec', 'ShellExecute'],
//     'python' => ['os.system', 'os.popen', 'subprocess', 'eval(', 'exec(', '__import__', 'compile('],
//     'java' => ['Runtime.getRuntime', 'ProcessBuilder', 'System.exit']
// ];

// if (isset($blacklist[$language])) {
//     foreach ($blacklist[$language] as $banned) {
//         if (stripos($code, $banned) !== false) {
//             echo json_encode([
//                 'success' => false, 
//                 'message' => "Xavfsiz emas kod: '{$banned}' ishlatish ta'qiqlangan"
//             ]);
//             exit;
//         }
//     }
// }

// // ============================================================
// // MASALA VA TEST CASE'LARNI OLISH
// // ============================================================
// $task = db()->fetchOne(
//     "SELECT t.* FROM tasks t 
//      JOIN topics tp ON t.topic_id = tp.id 
//      JOIN subject_students ss ON tp.subject_id = ss.subject_id 
//      WHERE t.id = ? AND ss.student_id = ?", [$taskId, $studentId]
// );

// if (!$task) {
//     http_response_code(403);
//     echo json_encode(['success' => false, 'message' => 'Bu masalaga ruxsat yo\'q']);
//     exit;
// }

// // Run rejimida faqat visible test, Submit rejimida hammasi
// $testCases = $isSubmit
//     ? db()->fetchAll("SELECT * FROM test_cases WHERE task_id = ? ORDER BY order_number, id", [$taskId])
//     : db()->fetchAll("SELECT * FROM test_cases WHERE task_id = ? AND is_hidden = 0 ORDER BY order_number, id", [$taskId]);

// if (empty($testCases)) {
//     echo json_encode(['success' => false, 'message' => 'Test case\'lar topilmadi']);
//     exit;
// }

// // ============================================================
// // COMPILER PATHS - Settings'dan olish, default qiymatlar bilan
// // ============================================================
// $compilers = [
//     'cpp' => [
//         'compile_cmd' => getSetting('compiler_cpp', 'g++'),
//         'ext' => 'cpp',
//         'compile' => true
//     ],
//     'python' => [
//         'run_cmd' => getSetting('compiler_python', 'python'),
//         'ext' => 'py',
//         'compile' => false
//     ],
//     'java' => [
//         'compile_cmd' => getSetting('compiler_javac', 'javac'),
//         'run_cmd' => getSetting('compiler_java', 'java'),
//         'ext' => 'java',
//         'compile' => true
//     ],
// ];

// if (!isset($compilers[$language])) {
//     echo json_encode(['success' => false, 'message' => "Til qo'llab-quvvatlanmaydi: $language"]);
//     exit;
// }

// $config = $compilers[$language];

// // ============================================================
// // VAQTINCHA PAPKA TAYYORLASH
// // ============================================================
// $tempDir = realpath(__DIR__ . '/../temp');
// if (!$tempDir || !is_dir($tempDir)) {
//     @mkdir(__DIR__ . '/../temp', 0777, true);
//     $tempDir = realpath(__DIR__ . '/../temp');
// }

// if (!is_writable($tempDir)) {
//     echo json_encode(['success' => false, 'message' => 'temp/ papkasi yozish uchun ochiq emas']);
//     exit;
// }

// // Unique session ID
// $sessionId = 's_' . $studentId . '_' . time() . '_' . bin2hex(random_bytes(4));
// $workDir = $tempDir . DIRECTORY_SEPARATOR . $sessionId;
// mkdir($workDir, 0777, true);

// // Java uchun class nomi "Main" bo'lishi shart
// if ($language === 'java') {
//     // Code'da public class nomi "Main" bo'lishini ta'minlash
//     if (!preg_match('/public\s+class\s+Main/', $code)) {
//         $code = preg_replace('/public\s+class\s+\w+/', 'public class Main', $code, 1);
//     }
//     $sourceFile = $workDir . DIRECTORY_SEPARATOR . 'Main.java';
// } else {
//     $sourceFile = $workDir . DIRECTORY_SEPARATOR . 'code.' . $config['ext'];
// }

// file_put_contents($sourceFile, $code);

// // ============================================================
// // KOMPILYATSIYA (agar kerak bo'lsa)
// // ============================================================
// $timeLimit = max(1, (int)$task['time_limit'] / 1000); // ms -> s
// $memoryLimit = (int)$task['memory_limit']; // MB
// $compileError = null;
// $execCommand = '';
// $isWindows = (PHP_OS_FAMILY === 'Windows');

// if ($language === 'cpp') {
//     $exeFile = $workDir . DIRECTORY_SEPARATOR . ($isWindows ? 'code.exe' : 'code');
//     $compileCmd = sprintf('%s -O2 -std=c++17 %s -o %s 2>&1', 
//         escapeshellcmd($config['compile_cmd']),
//         escapeshellarg($sourceFile),
//         escapeshellarg($exeFile)
//     );
    
//     exec($compileCmd, $output, $returnCode);
    
//     if ($returnCode !== 0) {
//         $compileError = implode("\n", $output);
//     } else {
//         $execCommand = escapeshellarg($exeFile);
//     }
    
// } elseif ($language === 'java') {
//     $compileCmd = sprintf('%s %s 2>&1', 
//         escapeshellcmd($config['compile_cmd']),
//         escapeshellarg($sourceFile)
//     );
    
//     chdir($workDir);
//     exec($compileCmd, $output, $returnCode);
    
//     if ($returnCode !== 0) {
//         $compileError = implode("\n", $output);
//     } else {
//         $execCommand = sprintf('%s -cp %s Main', 
//             escapeshellcmd($config['run_cmd']),
//             escapeshellarg($workDir)
//         );
//     }
    
// } elseif ($language === 'python') {
//     $execCommand = sprintf('%s %s', 
//         escapeshellcmd($config['run_cmd']),
//         escapeshellarg($sourceFile)
//     );
// }

// // Kompilyatsiya xatosi bo'lsa
// if ($compileError) {
//     cleanup($workDir);
//     echo json_encode([
//         'success' => true,
//         'compile_error' => $compileError,
//         'passed' => 0,
//         'total' => count($testCases),
//         'tests' => []
//     ]);
//     exit;
// }

// // ============================================================
// // HAR TEST CASE UCHUN ISHGA TUSHIRISH
// // ============================================================
// $testResults = [];
// $passedCount = 0;

// foreach ($testCases as $i => $tc) {
//     $inputFile = $workDir . DIRECTORY_SEPARATOR . "input_{$i}.txt";
//     $outputFile = $workDir . DIRECTORY_SEPARATOR . "output_{$i}.txt";
//     file_put_contents($inputFile, $tc['input_data']);
    
//     // Timeout va execution
//     $startTime = microtime(true);
    
//     if ($isWindows) {
//         // Windows: timeout buyrug'i yo'q, PHP'ning o'zida nazorat qilamiz
//         $cmd = $execCommand . ' < ' . escapeshellarg($inputFile) . ' > ' . escapeshellarg($outputFile) . ' 2>&1';
//         $result = executeWithTimeout($cmd, $timeLimit + 1, $workDir);
//     } else {
//         // Linux/Mac: timeout buyrug'i bilan
//         $cmd = sprintf('timeout %d %s < %s > %s 2>&1',
//             $timeLimit + 1,
//             $execCommand,
//             escapeshellarg($inputFile),
//             escapeshellarg($outputFile)
//         );
//         exec($cmd, $output, $returnCode);
//         $result = ['return_code' => $returnCode, 'timed_out' => ($returnCode === 124)];
//     }
    
//     $elapsed = round((microtime(true) - $startTime) * 1000); // ms
    
//     // Vaqt tugagan
//     if ($result['timed_out'] || $elapsed > ($timeLimit * 1000 + 500)) {
//         $testResults[] = [
//             'passed' => false,
//             'hidden' => (bool)$tc['is_hidden'],
//             'input' => $tc['is_hidden'] ? null : $tc['input_data'],
//             'expected' => $tc['is_hidden'] ? null : trim($tc['expected_output']),
//             'actual' => null,
//             'stderr' => null,
//             'status' => 'Time Limit Exceeded',
//             'time_ms' => $elapsed
//         ];
//         continue;
//     }
    
//     // Output o'qish
//     $actual = file_exists($outputFile) ? file_get_contents($outputFile) : '';
//     $expected = trim($tc['expected_output']);
//     $actualTrimmed = trim($actual);
    
//     // Runtime xatosi tekshirish
//     $hasRuntimeError = ($result['return_code'] ?? 0) !== 0 && $actualTrimmed === '';
    
//     $passed = !$hasRuntimeError && (normalizeOutput($actualTrimmed) === normalizeOutput($expected));
//     if ($passed) $passedCount++;
    
//     $testResults[] = [
//         'passed' => $passed,
//         'hidden' => (bool)$tc['is_hidden'],
//         'input' => $tc['is_hidden'] ? null : $tc['input_data'],
//         'expected' => $tc['is_hidden'] ? null : $expected,
//         'actual' => $tc['is_hidden'] ? null : $actualTrimmed,
//         'stderr' => $hasRuntimeError ? substr($actualTrimmed, 0, 500) : null,
//         'status' => $hasRuntimeError ? 'Runtime Error' : ($passed ? 'Accepted' : 'Wrong Answer'),
//         'time_ms' => $elapsed
//     ];
// }

// // ============================================================
// // CLEANUP
// // ============================================================
// cleanup($workDir);

// // ============================================================
// // NATIJA HISOBLASH
// // ============================================================
// $totalCount = count($testCases);
// $scorePercent = $totalCount > 0 ? round(($passedCount / $totalCount) * 100, 2) : 0;
// $grade = calculateGrade($scorePercent);

// $response = [
//     'success' => true,
//     'passed' => $passedCount,
//     'total' => $totalCount,
//     'score_percent' => $scorePercent,
//     'grade' => $grade,
//     'tests' => $testResults
// ];

// // ============================================================
// // SUBMIT BO'LSA - DB GA SAQLASH
// // ============================================================
// if ($isSubmit) {
//     try {
//         $status = $passedCount === $totalCount ? 'accepted' : 'wrong_answer';
        
//         // Vaqt limitidan oshgan testlar bormi?
//         $hasTimeLimit = false;
//         foreach ($testResults as $tr) {
//             if ($tr['status'] === 'Time Limit Exceeded') {
//                 $hasTimeLimit = true;
//                 break;
//             }
//         }
//         if ($hasTimeLimit && $passedCount < $totalCount) {
//             $status = 'time_limit';
//         }
        
//         db()->insert('task_submissions', [
//             'student_id' => $studentId,
//             'task_id' => $taskId,
//             'code' => $code,
//             'language' => $language,
//             'status' => $status,
//             'passed_tests' => $passedCount,
//             'total_tests' => $totalCount,
//             'score_percent' => $scorePercent,
//             'grade' => $grade
//         ]);
        
//         // Progress yangilash
//         $existing = db()->fetchOne(
//             "SELECT * FROM student_progress WHERE student_id = ? AND topic_id = ?",
//             [$studentId, $task['topic_id']]
//         );
        
//         $progressUpdate = [
//             'task_grade' => $grade
//         ];
        
//         if ($grade >= 3) {
//             $progressUpdate['task_completed'] = 1;
//         }
        
//         if ($existing) {
//             db()->update('student_progress', $progressUpdate, 
//                 'student_id = :sid AND topic_id = :tid',
//                 ['sid' => $studentId, 'tid' => $task['topic_id']]);
//         } else {
//             db()->insert('student_progress', array_merge($progressUpdate, [
//                 'student_id' => $studentId,
//                 'topic_id' => $task['topic_id'],
//                 'unlocked' => 1
//             ]));
//         }
        
//         // Bahoni grades jadvaliga
//         $subject = db()->fetchOne("SELECT subject_id FROM topics WHERE id = ?", [$task['topic_id']]);
//         if ($subject) {
//             // Eski 'task' bahoni o'chirib, yangisini qo'yish (shu mavzu uchun)
//             db()->query("DELETE FROM grades WHERE student_id = ? AND topic_id = ? AND type = 'task'", 
//                 [$studentId, $task['topic_id']]);
            
//             db()->insert('grades', [
//                 'student_id' => $studentId,
//                 'subject_id' => $subject['subject_id'],
//                 'topic_id' => $task['topic_id'],
//                 'grade' => $grade,
//                 'type' => 'task',
//                 'comment' => "Amaliy: {$passedCount}/{$totalCount} test ({$scorePercent}%)",
//                 'given_by' => $studentId
//             ]);
//         }
        
//         Auth::logActivity('task_submitted', "Task #{$taskId}, lang: {$language}, grade: {$grade}");
//         $response['saved'] = true;
        
//     } catch (Exception $e) {
//         $response['saved'] = false;
//         $response['save_error'] = $e->getMessage();
//     }
// }

// echo json_encode($response);
// exit;

// // ============================================================
// // HELPER FUNCTIONS
// // ============================================================

// /**
//  * Output'ni normalize qilish (whitespace farqlarni e'tiborga olmaslik)
//  */
// function normalizeOutput(string $str): string {
//     // Trailing whitespace har qatorda olib tashlansin
//     $lines = explode("\n", str_replace("\r", '', $str));
//     $lines = array_map('rtrim', $lines);
//     // Oxirdagi bo'sh qatorlarni olib tashlash
//     while (end($lines) === '') array_pop($lines);
//     return implode("\n", $lines);
// }

// /**
//  * Vaqtinchalik papkani o'chirish
//  */
// function cleanup(string $dir): void {
//     if (!is_dir($dir)) return;
//     $files = glob($dir . DIRECTORY_SEPARATOR . '*');
//     foreach ($files as $f) {
//         if (is_file($f)) @unlink($f);
//     }
//     @rmdir($dir);
// }

// /**
//  * Windows uchun timeout bilan exec (proc_open orqali)
//  */
// function executeWithTimeout(string $cmd, int $timeoutSec, string $cwd): array {
//     $descriptorspec = [
//         0 => ['pipe', 'r'],
//         1 => ['pipe', 'w'],
//         2 => ['pipe', 'w']
//     ];
    
//     $process = proc_open($cmd, $descriptorspec, $pipes, $cwd);
//     if (!is_resource($process)) {
//         return ['return_code' => -1, 'timed_out' => false];
//     }
    
//     fclose($pipes[0]);
//     stream_set_blocking($pipes[1], false);
//     stream_set_blocking($pipes[2], false);
    
//     $startTime = time();
//     $timedOut = false;
    
//     while (true) {
//         $status = proc_get_status($process);
//         if (!$status['running']) break;
        
//         if ((time() - $startTime) >= $timeoutSec) {
//             $timedOut = true;
//             // Process'ni majburiy o'chirish
//             if (PHP_OS_FAMILY === 'Windows') {
//                 exec("taskkill /F /T /PID " . $status['pid'] . " 2>NUL");
//             } else {
//                 proc_terminate($process, 9);
//             }
//             break;
//         }
        
//         usleep(50000); // 50ms kutish
//     }
    
//     fclose($pipes[1]);
//     fclose($pipes[2]);
//     $returnCode = proc_close($process);
    
//     return [
//         'return_code' => $returnCode,
//         'timed_out' => $timedOut
//     ];
// }
