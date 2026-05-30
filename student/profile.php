<?php
$pageTitle = 'Mening profilim';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('student');

$studentId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'CSRF token xato'); redirect($_SERVER['REQUEST_URI']);
    }
    
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'update_profile') {
            db()->update('users', [
                'full_name' => trim($_POST['full_name']),
                'email' => trim($_POST['email']),
                'phone' => trim($_POST['phone'] ?? '')
            ], 'id = :id', ['id' => $studentId]);
            $_SESSION['user_name'] = trim($_POST['full_name']);
            setFlash('success', 'Profil yangilandi');
            
        } elseif ($action === 'change_password') {
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            
            $user = db()->fetchOne("SELECT password FROM users WHERE id = ?", [$studentId]);
            
            if (!password_verify($current, $user['password'])) {
                throw new Exception('Joriy parol noto\'g\'ri');
            }
            if (strlen($new) < 6) {
                throw new Exception('Yangi parol kamida 6 ta belgi bo\'lishi kerak');
            }
            if ($new !== $confirm) {
                throw new Exception('Parollar mos kelmadi');
            }
            
            db()->update('users', [
                'password' => Auth::hashPassword($new)
            ], 'id = :id', ['id' => $studentId]);
            
            setFlash('success', 'Parol o\'zgartirildi');
        }
    } catch (Exception $e) {
        setFlash('danger', $e->getMessage());
    }
    redirect($_SERVER['REQUEST_URI']);
}

$user = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$studentId]);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-3">
    <!-- Profil ma'lumotlari -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body" style="text-align: center;">
                <div style="width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem; font-weight: 700; margin: 0 auto 1rem;">
                    <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                </div>
                <h3 style="margin-bottom: 0.25rem;"><?= e($user['full_name']) ?></h3>
                <p style="color: var(--text-tertiary); margin-bottom: 1.5rem;">
                    @<?= e($user['username']) ?>
                </p>
                
                <div style="text-align: left; padding-top: 1rem; border-top: 1px solid var(--border);">
                    <div style="margin-bottom: 0.75rem;">
                        <small style="color: var(--text-tertiary);">Roli</small>
                        <div><span class="badge badge-info">Talaba</span></div>
                    </div>
                    <div style="margin-bottom: 0.75rem;">
                        <small style="color: var(--text-tertiary);">Ro'yxatdan o'tgan</small>
                        <div><?= formatDate($user['created_at']) ?></div>
                    </div>
                    <div style="margin-bottom: 0.75rem;">
                        <small style="color: var(--text-tertiary);">Oxirgi kirish</small>
                        <div><?= $user['last_login'] ? formatDate($user['last_login'], true) : '—' ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tahrirlash formalari -->
    <div class="col-lg-8">
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#info">
                    <i class="fas fa-user"></i> Shaxsiy ma'lumotlar
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#password">
                    <i class="fas fa-key"></i> Parolni o'zgartirish
                </button>
            </li>
        </ul>
        
        <div class="tab-content">
            <!-- Shaxsiy ma'lumotlar -->
            <div class="tab-pane fade show active" id="info">
                <div class="card">
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= e(Auth::getCsrfToken()) ?>">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Login</label>
                                    <input type="text" class="form-control" value="<?= e($user['username']) ?>" disabled>
                                    <small class="text-muted">Loginni o'zgartirib bo'lmaydi</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">To'liq ism <span class="required">*</span></label>
                                    <input type="text" name="full_name" class="form-control" value="<?= e($user['full_name']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Telefon</label>
                                    <input type="tel" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>" placeholder="+998 90 123 45 67">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary mt-3">
                                <i class="fas fa-save"></i> Saqlash
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Parol -->
            <div class="tab-pane fade" id="password">
                <div class="card">
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= e(Auth::getCsrfToken()) ?>">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="form-group">
                                <label class="form-label">Joriy parol <span class="required">*</span></label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Yangi parol <span class="required">*</span></label>
                                <input type="password" name="new_password" class="form-control" required minlength="6">
                                <small class="text-muted">Kamida 6 ta belgi</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Yangi parolni tasdiqlang <span class="required">*</span></label>
                                <input type="password" name="confirm_password" class="form-control" required minlength="6">
                            </div>
                            
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key"></i> Parolni o'zgartirish
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
