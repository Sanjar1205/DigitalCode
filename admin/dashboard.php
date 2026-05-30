<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
Auth::requireRole('admin');

// Statistika
$stats = [
    'users' => db()->fetchOne("SELECT COUNT(*) as cnt FROM users")['cnt'],
    'admins' => db()->fetchOne("SELECT COUNT(*) as cnt FROM users WHERE role='admin'")['cnt'],
    'teachers' => db()->fetchOne("SELECT COUNT(*) as cnt FROM users WHERE role='teacher'")['cnt'],
    'students' => db()->fetchOne("SELECT COUNT(*) as cnt FROM users WHERE role='student'")['cnt'],
    'subjects' => db()->fetchOne("SELECT COUNT(*) as cnt FROM subjects")['cnt'],
    'topics' => db()->fetchOne("SELECT COUNT(*) as cnt FROM topics")['cnt'],
    'submissions' => db()->fetchOne("SELECT COUNT(*) as cnt FROM task_submissions")['cnt'],
    'active_today' => db()->fetchOne("SELECT COUNT(DISTINCT user_id) as cnt FROM activity_logs WHERE DATE(created_at) = CURDATE()")['cnt'],
];

// So'nggi loglar
$recentLogs = db()->fetchAll(
    "SELECT al.*, u.full_name FROM activity_logs al 
     LEFT JOIN users u ON al.user_id = u.id 
     ORDER BY al.created_at DESC LIMIT 10"
);

// So'nggi foydalanuvchilar
$recentUsers = db()->fetchAll(
    "SELECT id, full_name, username, email, role, created_at, status 
     FROM users ORDER BY created_at DESC LIMIT 5"
);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Statistika -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <h3><?= $stats['users'] ?></h3>
            <p>Jami foydalanuvchilar</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon success">
            <i class="fas fa-chalkboard-teacher"></i>
        </div>
        <div class="stat-content">
            <h3><?= $stats['teachers'] ?></h3>
            <p>O'qituvchilar</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon info">
            <i class="fas fa-user-graduate"></i>
        </div>
        <div class="stat-content">
            <h3><?= $stats['students'] ?></h3>
            <p>Talabalar</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="fas fa-book"></i>
        </div>
        <div class="stat-content">
            <h3><?= $stats['subjects'] ?></h3>
            <p>Fanlar</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="fas fa-list"></i>
        </div>
        <div class="stat-content">
            <h3><?= $stats['topics'] ?></h3>
            <p>Mavzular</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon success">
            <i class="fas fa-code"></i>
        </div>
        <div class="stat-content">
            <h3><?= $stats['submissions'] ?></h3>
            <p>Kod topshiriqlari</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon danger">
            <i class="fas fa-bolt"></i>
        </div>
        <div class="stat-content">
            <h3><?= $stats['active_today'] ?></h3>
            <p>Bugungi faollar</p>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- So'nggi foydalanuvchilar -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h2>So'nggi foydalanuvchilar</h2>
                <a href="<?= SITE_URL ?>/admin/users.php" class="btn btn-outline-primary btn-sm">
                    Hammasi <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>F.I.Sh</th>
                            <th>Login</th>
                            <th>Roli</th>
                            <th>Status</th>
                            <th>Sana</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $u): ?>
                            <tr>
                                <td>
                                    <strong><?= e($u['full_name']) ?></strong>
                                </td>
                                <td><?= e($u['username']) ?></td>
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
                                <td style="color: var(--text-tertiary); font-size: 0.85rem;">
                                    <?= formatDate($u['created_at']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- So'nggi faoliyat -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h2>So'nggi faoliyat</h2>
                <a href="<?= SITE_URL ?>/admin/logs.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-history"></i>
                </a>
            </div>
            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($recentLogs)): ?>
                    <p style="color: var(--text-tertiary); text-align: center;">Hech qanday faoliyat yo'q</p>
                <?php else: ?>
                    <?php foreach ($recentLogs as $log): ?>
                        <div style="padding: 0.75rem 0; border-bottom: 1px solid var(--border);">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <strong style="font-size: 0.9rem;"><?= e($log['full_name'] ?? 'Tizim') ?></strong>
                                    <div style="font-size: 0.85rem; color: var(--text-secondary);">
                                        <?= e($log['action']) ?>
                                    </div>
                                    <?php if ($log['details']): ?>
                                        <div style="font-size: 0.75rem; color: var(--text-tertiary);">
                                            <?= e($log['details']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <small style="color: var(--text-tertiary); font-size: 0.75rem;">
                                    <?= date('H:i', strtotime($log['created_at'])) ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
