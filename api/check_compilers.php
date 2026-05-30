<?php
/**
 * Kompilyatorlarni tekshirish va sozlash sahifasi
 * Foydalanish: http://localhost/codeacademy/api/check_compilers.php
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('admin');

$action = $_POST['action'] ?? '';
$message = '';

if ($action === 'save' && Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    setSetting('compiler_cpp', trim($_POST['compiler_cpp'] ?? 'g++'));
    setSetting('compiler_python', trim($_POST['compiler_python'] ?? 'python'));
    setSetting('compiler_javac', trim($_POST['compiler_javac'] ?? 'javac'));
    setSetting('compiler_java', trim($_POST['compiler_java'] ?? 'java'));
    $message = '✓ Yo\'llar saqlandi';
}

$cppPath = getSetting('compiler_cpp', 'g++');
$pythonPath = getSetting('compiler_python', 'python');
$javacPath = getSetting('compiler_javac', 'javac');
$javaPath = getSetting('compiler_java', 'java');

function checkCompiler(string $cmd, string $versionFlag = '--version'): array {
    $fullCmd = escapeshellcmd($cmd) . ' ' . $versionFlag . ' 2>&1';
    exec($fullCmd, $output, $returnCode);
    return [
        'works' => $returnCode === 0,
        'output' => implode("\n", $output)
    ];
}

function checkExecuteCode(string $language): array {
    $tempDir = __DIR__ . '/../temp';
    if (!is_dir($tempDir)) @mkdir($tempDir, 0777, true);
    
    $sessionId = 'test_' . bin2hex(random_bytes(4));
    $workDir = $tempDir . DIRECTORY_SEPARATOR . $sessionId;
    @mkdir($workDir, 0777, true);
    
    $testCode = [
        'cpp' => "#include <iostream>\nusing namespace std;\nint main(){int a,b;cin>>a>>b;cout<<a+b;return 0;}",
        'python' => "a, b = map(int, input().split())\nprint(a + b)",
        'java' => "import java.util.Scanner;\npublic class Main{public static void main(String[]a){Scanner s=new Scanner(System.in);System.out.print(s.nextInt()+s.nextInt());}}"
    ];
    
    $stdin = "5 3\n";
    $expected = "8";
    
    if ($language === 'cpp') {
        $src = $workDir . '/code.cpp';
        $exe = $workDir . '/code' . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
        file_put_contents($src, $testCode['cpp']);
        exec(escapeshellcmd(getSetting('compiler_cpp', 'g++')) . ' ' . escapeshellarg($src) . ' -o ' . escapeshellarg($exe) . ' 2>&1', $compileOut, $compileRet);
        if ($compileRet !== 0) {
            cleanupTest($workDir);
            return ['works' => false, 'error' => 'Kompilyatsiya xatosi: ' . implode("\n", $compileOut)];
        }
        $cmd = escapeshellarg($exe);
    } elseif ($language === 'python') {
        $src = $workDir . '/code.py';
        file_put_contents($src, $testCode['python']);
        $cmd = escapeshellcmd(getSetting('compiler_python', 'python')) . ' ' . escapeshellarg($src);
    } elseif ($language === 'java') {
        $src = $workDir . '/Main.java';
        file_put_contents($src, $testCode['java']);
        chdir($workDir);
        exec(escapeshellcmd(getSetting('compiler_javac', 'javac')) . ' ' . escapeshellarg($src) . ' 2>&1', $compileOut, $compileRet);
        if ($compileRet !== 0) {
            cleanupTest($workDir);
            return ['works' => false, 'error' => 'Java kompilyatsiya xatosi: ' . implode("\n", $compileOut)];
        }
        $cmd = escapeshellcmd(getSetting('compiler_java', 'java')) . ' -cp ' . escapeshellarg($workDir) . ' Main';
    } else {
        return ['works' => false, 'error' => 'Noma\'lum til'];
    }
    
    $inputFile = $workDir . '/input.txt';
    $outputFile = $workDir . '/output.txt';
    file_put_contents($inputFile, $stdin);
    
    exec($cmd . ' < ' . escapeshellarg($inputFile) . ' > ' . escapeshellarg($outputFile) . ' 2>&1', $output, $returnCode);
    
    $actual = file_exists($outputFile) ? trim(file_get_contents($outputFile)) : '';
    cleanupTest($workDir);
    
    return [
        'works' => ($actual === $expected),
        'expected' => $expected,
        'actual' => $actual,
        'return_code' => $returnCode
    ];
}

function cleanupTest(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (glob($dir . '/*') as $f) @unlink($f);
    @rmdir($dir);
}

$pageTitle = 'Kompilyatorlarni tekshirish';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 1rem;">
    <a href="<?= SITE_URL ?>/admin/settings.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Settings'ga qaytish
    </a>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?= e($message) ?></div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <h2><i class="fas fa-cogs"></i> Lokal Kompilyatorlar Sozlash</h2>
        <p style="color: var(--text-secondary);">
            Mahalliy kompilyatorlardan foydalanish uchun yo'llarni sozlang. 
            Agar kompilyatorlar PATH'ga qo'shilgan bo'lsa — faqat nomini yozish kifoya (masalan: <code>g++</code>).
            Aks holda to'liq yo'lni kiriting (masalan: <code>C:\MinGW\bin\g++.exe</code>).
        </p>
    </div>
</div>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= e(Auth::getCsrfToken()) ?>">
    <input type="hidden" name="action" value="save">
    
    <div class="card mb-3">
        <div class="card-body">
            <h3>Kompilyator yo'llari</h3>
            
            <div class="form-group">
                <label>C++ kompilyator (<code>g++</code>):</label>
                <input type="text" name="compiler_cpp" value="<?= e($cppPath) ?>" 
                       class="form-control" placeholder="g++ yoki C:\MinGW\bin\g++.exe">
            </div>
            
            <div class="form-group">
                <label>Python interpretator:</label>
                <input type="text" name="compiler_python" value="<?= e($pythonPath) ?>" 
                       class="form-control" placeholder="python yoki C:\Python311\python.exe">
            </div>
            
            <div class="form-group">
                <label>Java kompilyator (<code>javac</code>):</label>
                <input type="text" name="compiler_javac" value="<?= e($javacPath) ?>" 
                       class="form-control" placeholder="javac">
            </div>
            
            <div class="form-group">
                <label>Java interpretator:</label>
                <input type="text" name="compiler_java" value="<?= e($javaPath) ?>" 
                       class="form-control" placeholder="java">
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Saqlash
            </button>
        </div>
    </div>
</form>

<div class="card mb-3">
    <div class="card-body">
        <h3><i class="fas fa-vial"></i> Kompilyatorlarni tekshirish</h3>
        
        <?php
        $tests = [
            'cpp' => ['name' => 'C++ (g++)', 'cmd' => $cppPath, 'flag' => '--version'],
            'python' => ['name' => 'Python', 'cmd' => $pythonPath, 'flag' => '--version'],
            'javac' => ['name' => 'Java Compiler (javac)', 'cmd' => $javacPath, 'flag' => '-version'],
            'java' => ['name' => 'Java Runtime (java)', 'cmd' => $javaPath, 'flag' => '-version'],
        ];
        ?>
        
        <table class="table">
            <thead>
                <tr>
                    <th>Kompilyator</th>
                    <th>Komanda</th>
                    <th>Holat</th>
                    <th>Versiya</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tests as $key => $t): 
                    $result = checkCompiler($t['cmd'], $t['flag']);
                ?>
                <tr>
                    <td><strong><?= e($t['name']) ?></strong></td>
                    <td><code><?= e($t['cmd']) ?></code></td>
                    <td>
                        <?php if ($result['works']): ?>
                            <span class="badge" style="background: var(--success); color: white;">✓ Ishlayapti</span>
                        <?php else: ?>
                            <span class="badge" style="background: var(--danger); color: white;">✗ Topilmadi</span>
                        <?php endif; ?>
                    </td>
                    <td><small><code><?= e(substr($result['output'], 0, 100)) ?></code></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <h3><i class="fas fa-play-circle"></i> Real test (5 + 3 = 8)</h3>
        <p style="color: var(--text-secondary);">Har til uchun oddiy "ikki sonni qo'shish" testi:</p>
        
        <?php
        $langTests = [
            'cpp' => 'C++',
            'python' => 'Python',
            'java' => 'Java'
        ];
        ?>
        
        <table class="table">
            <thead>
                <tr>
                    <th>Til</th>
                    <th>Holat</th>
                    <th>Kutilgan</th>
                    <th>Olingan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($langTests as $lang => $langName): 
                    $result = checkExecuteCode($lang);
                ?>
                <tr>
                    <td><strong><?= e($langName) ?></strong></td>
                    <td>
                        <?php if ($result['works']): ?>
                            <span class="badge" style="background: var(--success); color: white;">✓ Ishlayapti</span>
                        <?php else: ?>
                            <span class="badge" style="background: var(--danger); color: white;">✗ Xato</span>
                        <?php endif; ?>
                    </td>
                    <td><code><?= e($result['expected'] ?? '?') ?></code></td>
                    <td>
                        <?php if (isset($result['error'])): ?>
                            <small style="color: var(--danger);"><?= e($result['error']) ?></small>
                        <?php else: ?>
                            <code><?= e($result['actual'] ?? '(bo\'sh)') ?></code>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h3><i class="fas fa-info-circle"></i> O'rnatish qo'llanmasi</h3>
        
        <h4>Windows uchun</h4>
        <ul>
            <li><strong>C++ (g++):</strong> 
                <a href="https://winlibs.com/" target="_blank">winlibs.com</a> dan MinGW-w64 yuklab oling, 
                C:\MinGW\ ga arxivdan chiqaring va PATH ga <code>C:\MinGW\bin</code> qo'shing
            </li>
            <li><strong>Python:</strong> 
                <a href="https://www.python.org/downloads/" target="_blank">python.org</a> dan yuklab olishda 
                "Add to PATH" tasdiqlang
            </li>
            <li><strong>Java:</strong> 
                <a href="https://adoptium.net/" target="_blank">adoptium.net</a> dan JDK 17+ ni o'rnating, 
                JAVA_HOME va PATH'ni sozlang
            </li>
        </ul>
        
        <h4>Tekshirish (CMD'da)</h4>
        <pre style="background: #1a1a1a; color: #0f0; padding: 1rem; border-radius: 5px;">
g++ --version
python --version
javac -version
java -version
        </pre>
        
        <p>Agar har biri versiyani ko'rsatsa — tayyor!</p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
