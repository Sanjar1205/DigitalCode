<?php
/**
 * Login sahifasi
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Allaqachon kirgan bo'lsa, kabinetga yo'naltirish
if (Auth::isLoggedIn()) {
    $role = $_SESSION['user_role'];
    redirect(SITE_URL . "/$role/dashboard.php");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!Auth::verifyCsrfToken($csrfToken)) {
        $error = 'CSRF token xato. Sahifani qaytadan yuklang.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Iltimos, hamma maydonlarni to\'ldiring';
    } else {
        // Brute-force himoyasi
        $attempts = $_SESSION['login_attempts'] ?? 0;
        $lockedUntil = $_SESSION['locked_until'] ?? 0;
        
        if ($attempts >= MAX_LOGIN_ATTEMPTS && time() < $lockedUntil) {
            $minutes = ceil(($lockedUntil - time()) / 60);
            $error = "Juda ko'p urinish. $minutes daqiqadan keyin urinib ko'ring.";
        } else {
            $result = Auth::login($username, $password);
            
            if ($result['success']) {
                unset($_SESSION['login_attempts'], $_SESSION['locked_until']);
                redirect(SITE_URL . "/{$result['role']}/dashboard.php");
            } else {
                $_SESSION['login_attempts'] = $attempts + 1;
                if ($_SESSION['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
                    $_SESSION['locked_until'] = time() + LOGIN_LOCKOUT_TIME;
                }
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= pageTitle('Tizimga kirish') ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-logo">
                <div class="logo-icon">
                    <i class="fas fa-code"></i>
                </div>
                <h1>CodeAcademy</h1>
                <p>Dasturlash o'qitish platformasi</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= e(Auth::getCsrfToken()) ?>">
                
                <div class="form-group">
                    <label class="form-label">Login yoki Email <span class="required">*</span></label>
                    <input type="text" name="username" class="form-control" 
                           placeholder="username yoki email@misol.uz" 
                           value="<?= e($_POST['username'] ?? '') ?>" required autofocus>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Parol <span class="required">*</span></label>
                    <div style="position: relative;">
                        <input type="password" name="password" id="password" 
                               class="form-control" placeholder="••••••••" required
                               style="padding-right: 2.5rem;">
                        <button type="button" onclick="togglePassword()" 
                                style="position: absolute; right: 0.85rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-tertiary); cursor: pointer;">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    <i class="fas fa-sign-in-alt"></i>
                    Kirish
                </button>
            </form>
            
            <div style="margin-top: 1.5rem; text-align: center; color: var(--text-tertiary); font-size: 0.85rem;">
                Login bilan bog'liq muammoda admin bilan bog'laning
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 1rem; color: rgba(255,255,255,0.8); font-size: 0.85rem;">
            © <?= date('Y') ?> CodeAcademy. Barcha huquqlar himoyalangan.
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
    </script>
</body>
</html>
