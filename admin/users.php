<?php
$pageTitle = 'Foydalanuvchilar';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('admin');

// CRUD AMALLAR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'CSRF token xato');
        redirect($_SERVER['REQUEST_URI']);
    }
    
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create') {
            $existing = db()->fetchOne("SELECT id FROM users WHERE username = ? OR email = ?", 
                [$_POST['username'], $_POST['email']]);
            if ($existing) {
                throw new Exception('Bu login yoki email allaqachon mavjud');
            }
            
            db()->insert('users', [
                'full_name' => trim($_POST['full_name']),
                'username' => trim($_POST['username']),
                'email' => trim($_POST['email']),
                'password' => Auth::hashPassword($_POST['password']),
                'role' => $_POST['role'],
                'phone' => trim($_POST['phone'] ?? ''),
                'status' => 'active'
            ]);
            setFlash('success', 'Foydalanuvchi muvaffaqiyatli yaratildi');
            
        } elseif ($action === 'update') {
            $data = [
                'full_name' => trim($_POST['full_name']),
                'email' => trim($_POST['email']),
                'role' => $_POST['role'],
                'phone' => trim($_POST['phone'] ?? ''),
                'status' => $_POST['status']
            ];
            if (!empty($_POST['password'])) {
                $data['password'] = Auth::hashPassword($_POST['password']);
            }
            db()->update('users', $data, 'id = :id', ['id' => (int)$_POST['user_id']]);
            setFlash('success', 'Ma\'lumotlar yangilandi');
            
        } elseif ($action === 'delete') {
            if ((int)$_POST['user_id'] === (int)$_SESSION['user_id']) {
                throw new Exception('O\'zingizni o\'chira olmaysiz');
            }
            db()->delete('users', 'id = :id', ['id' => (int)$_POST['user_id']]);
            setFlash('success', 'Foydalanuvchi o\'chirildi');
            
        } elseif ($action === 'reset_password') {
            $newPassword = bin2hex(random_bytes(4));
            db()->update('users', ['password' => Auth::hashPassword($newPassword)], 
                'id = :id', ['id' => (int)$_POST['user_id']]);
            setFlash('success', "Yangi parol: <strong>$newPassword</strong> (saqlab qoling!)");
            
        } elseif ($action === 'toggle_status') {
            $user = db()->fetchOne("SELECT status FROM users WHERE id = ?", [(int)$_POST['user_id']]);
            $newStatus = $user['status'] === 'active' ? 'blocked' : 'active';
            db()->update('users', ['status' => $newStatus], 'id = :id', ['id' => (int)$_POST['user_id']]);
            setFlash('success', 'Status o\'zgartirildi');
        }
    } catch (Exception $e) {
        setFlash('danger', $e->getMessage());
    }
    
    redirect($_SERVER['REQUEST_URI']);
}

// FILTRLASH
$filter_role = $_GET['role'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$where = ['1=1'];
$params = [];
if ($filter_role) {
    $where[] = "role = ?";
    $params[] = $filter_role;
}
if ($filter_status) {
    $where[] = "status = ?";
    $params[] = $filter_status;
}
if ($search) {
    $where[] = "(full_name LIKE ? OR username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}

$users = db()->fetchAll("SELECT * FROM users WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC", $params);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Toolbar -->
<div class="toolbar">
    <form method="GET" style="display: flex; gap: 0.75rem; flex: 1; flex-wrap: wrap;">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" name="search" class="form-control" 
                   placeholder="Qidiruv (ism, login, email)..." 
                   value="<?= e($search) ?>">
        </div>
        <select name="role" class="form-select" style="max-width: 180px;" onchange="this.form.submit()">
            <option value="">Barcha rollar</option>
            <option value="admin" <?= $filter_role === 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="teacher" <?= $filter_role === 'teacher' ? 'selected' : '' ?>>O'qituvchi</option>
            <option value="student" <?= $filter_role === 'student' ? 'selected' : '' ?>>Talaba</option>
        </select>
        <select name="status" class="form-select" style="max-width: 150px;" onchange="this.form.submit()">
            <option value="">Holat</option>
            <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Faol</option>
            <option value="blocked" <?= $filter_status === 'blocked' ? 'selected' : '' ?>>Bloklangan</option>
        </select>
        <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i></button>
    </form>
    
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
        <i class="fas fa-plus"></i> Yangi foydalanuvchi
    </button>
</div>

<!-- Foydalanuvchilar jadvali -->
<div class="card">
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>F.I.Sh</th>
                    <th>Login / Email</th>
                    <th>Telefon</th>
                    <th>Roli</th>
                    <th>Status</th>
                    <th>Yaratilgan</th>
                    <th>Amallar</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="8" style="text-align: center; padding: 2rem; color: var(--text-tertiary);">
                        Foydalanuvchi topilmadi
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($users as $i => $u): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div class="user-avatar" style="width: 36px; height: 36px;">
                                        <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                    </div>
                                    <strong><?= e($u['full_name']) ?></strong>
                                </div>
                            </td>
                            <td>
                                <div><?= e($u['username']) ?></div>
                                <small style="color: var(--text-tertiary);"><?= e($u['email']) ?></small>
                            </td>
                            <td><?= e($u['phone'] ?: '-') ?></td>
                            <td>
                                <span class="badge badge-<?= $u['role'] === 'admin' ? 'danger' : ($u['role'] === 'teacher' ? 'success' : 'primary') ?>">
                                    <?= $u['role'] === 'admin' ? 'Admin' : ($u['role'] === 'teacher' ? 'O\'qituvchi' : 'Talaba') ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= $u['status'] === 'active' ? 'success' : 'danger' ?>">
                                    <?= $u['status'] === 'active' ? 'Faol' : 'Bloklangan' ?>
                                </span>
                            </td>
                            <td style="font-size: 0.85rem; color: var(--text-tertiary);">
                                <?= formatDate($u['created_at']) ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.25rem;">
                                    <button class="btn btn-sm btn-secondary edit-user-btn" 
                                            data-user='<?= json_encode($u) ?>' 
                                            title="Tahrirlash">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= e(Auth::getCsrfToken()) ?>">
                                        <input type="hidden" name="action" value="reset_password">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-warning" 
                                                data-confirm="Parolni tiklamoqchimisiz?" title="Parolni tiklash">
                                            <i class="fas fa-key"></i>
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= e(Auth::getCsrfToken()) ?>">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-secondary" 
                                                title="<?= $u['status'] === 'active' ? 'Bloklash' : 'Blokdan chiqarish' ?>">
                                            <i class="fas fa-<?= $u['status'] === 'active' ? 'lock' : 'unlock' ?>"></i>
                                        </button>
                                    </form>
                                    
                                    <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?= e(Auth::getCsrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" 
                                                    data-confirm="O'chirishga ishonchingiz komilmi?" title="O'chirish">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Yangi foydalanuvchi -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background: var(--bg-secondary); color: var(--text-primary);">
            <div class="modal-header" style="border-color: var(--border);">
                <h5 class="modal-title">Yangi foydalanuvchi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e(Auth::getCsrfToken()) ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">F.I.Sh <span class="required">*</span></label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Roli <span class="required">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="student">Talaba</option>
                                <option value="teacher">O'qituvchi</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Login <span class="required">*</span></label>
                            <input type="text" name="username" class="form-control" required minlength="3">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="required">*</span></label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Telefon</label>
                            <input type="tel" name="phone" class="form-control" placeholder="+998 90 123 45 67">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Parol <span class="required">*</span></label>
                            <input type="password" name="password" class="form-control" required minlength="6">
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-color: var(--border);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Bekor qilish</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Saqlash
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Tahrirlash -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background: var(--bg-secondary); color: var(--text-primary);">
            <div class="modal-header" style="border-color: var(--border);">
                <h5 class="modal-title">Foydalanuvchini tahrirlash</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editUserForm">
                <input type="hidden" name="csrf_token" value="<?= e(Auth::getCsrfToken()) ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">F.I.Sh</label>
                            <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Roli</label>
                            <select name="role" id="edit_role" class="form-select" required>
                                <option value="student">Talaba</option>
                                <option value="teacher">O'qituvchi</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Telefon</label>
                            <input type="tel" name="phone" id="edit_phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="active">Faol</option>
                                <option value="blocked">Bloklangan</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Yangi parol (ixtiyoriy)</label>
                            <input type="password" name="password" class="form-control" placeholder="Bo'sh qoldirsangiz o'zgarmaydi">
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-color: var(--border);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Bekor qilish</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Yangilash
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$inlineJs = <<<'JS'
document.querySelectorAll('.edit-user-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const u = JSON.parse(btn.dataset.user);
        document.getElementById('edit_user_id').value = u.id;
        document.getElementById('edit_full_name').value = u.full_name;
        document.getElementById('edit_email').value = u.email;
        document.getElementById('edit_phone').value = u.phone || '';
        document.getElementById('edit_role').value = u.role;
        document.getElementById('edit_status').value = u.status;
        new bootstrap.Modal(document.getElementById('editUserModal')).show();
    });
});
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
