<?php
$pageTitle = 'Faoliyat loglari';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_old') {
    if (Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $deleted = db()->delete('activity_logs', 'created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
        setFlash('success', "$deleted ta eski log o'chirildi (30 kundan oldingi)");
    }
    redirect($_SERVER['REQUEST_URI']);
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;
$action_filter = $_GET['action'] ?? '';

$where = ['1=1'];
$params = [];
if ($action_filter) {
    $where[] = 'al.action = ?';
    $params[] = $action_filter;
}

$total = db()->fetchOne(
    "SELECT COUNT(*) as cnt FROM activity_logs al WHERE " . implode(' AND ', $where), $params
)['cnt'];
$totalPages = ceil($total / $perPage);

$logs = db()->fetchAll(
    "SELECT al.*, u.full_name, u.role 
     FROM activity_logs al 
     LEFT JOIN users u ON al.user_id = u.id 
     WHERE " . implode(' AND ', $where) . "
     ORDER BY al.created_at DESC 
     LIMIT $perPage OFFSET $offset",
    $params
);

$actions = db()->fetchAll("SELECT DISTINCT action FROM activity_logs ORDER BY action");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="toolbar">
    <form method="GET" style="display: flex; gap: 0.75rem; flex: 1;">
        <select name="action" class="form-select" style="max-width: 250px;" onchange="this.form.submit()">
            <option value="">Barcha amallar</option>
            <?php foreach ($actions as $a): ?>
                <option value="<?= e($a['action']) ?>" <?= $action_filter === $a['action'] ? 'selected' : '' ?>>
                    <?= e($a['action']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
    
    <form method="POST" style="display: inline;">
        <input type="hidden" name="csrf_token" value="<?= e(Auth::getCsrfToken()) ?>">
        <input type="hidden" name="action" value="clear_old">
        <button type="submit" class="btn btn-warning" data-confirm="30 kundan oldingi loglarni o'chirmoqchimisiz?">
            <i class="fas fa-broom"></i> Eski loglarni tozalash
        </button>
    </form>
</div>

<div class="card">
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Vaqt</th>
                    <th>Foydalanuvchi</th>
                    <th>Amal</th>
                    <th>Tafsilotlar</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td style="white-space: nowrap; font-size: 0.85rem;">
                            <?= formatDate($log['created_at'], true) ?>
                        </td>
                        <td>
                            <?php if ($log['user_id']): ?>
                                <strong><?= e($log['full_name']) ?></strong>
                                <br><small style="color: var(--text-tertiary);"><?= e($log['role']) ?></small>
                            <?php else: ?>
                                <em style="color: var(--text-tertiary);">Tizim</em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= str_contains($log['action'], 'fail') ? 'danger' : (str_contains($log['action'], 'success') || str_contains($log['action'], 'login') ? 'success' : 'info') ?>">
                                <?= e($log['action']) ?>
                            </span>
                        </td>
                        <td style="font-size: 0.85rem;"><?= e($log['details'] ?: '-') ?></td>
                        <td style="font-size: 0.85rem; color: var(--text-tertiary);">
                            <?= e($log['ip_address'] ?: '-') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($totalPages > 1): ?>
        <div style="padding: 1rem; display: flex; justify-content: center; gap: 0.5rem;">
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?page=<?= $i ?><?= $action_filter ? '&action=' . urlencode($action_filter) : '' ?>" 
                   class="btn btn-sm btn-<?= $i === $page ? 'primary' : 'secondary' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<p style="text-align: center; margin-top: 1rem; color: var(--text-tertiary);">
    Jami: <?= $total ?> ta yozuv
</p>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
