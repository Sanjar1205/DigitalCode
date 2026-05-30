<?php
/**
 * Header (sidebar bilan)
 * Roli asosida menyu ko'rsatadi
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

Auth::requireLogin();

$user = Auth::user();
$role = $user['role'];
$currentPage = basename($_SERVER['PHP_SELF']);
$pageTitle = $pageTitle ?? 'Dashboard';

// Roli asosida menyu
$menus = [
    'admin' => [
        ['url' => 'dashboard.php', 'icon' => 'fa-tachometer-alt', 'title' => 'Dashboard'],
        ['url' => 'users.php', 'icon' => 'fa-users', 'title' => 'Foydalanuvchilar'],
        ['url' => 'subjects.php', 'icon' => 'fa-book', 'title' => 'Fanlar'],
        ['url' => 'assignments.php', 'icon' => 'fa-link', 'title' => 'Biriktirish'],
        ['url' => 'reports.php', 'icon' => 'fa-chart-bar', 'title' => 'Hisobotlar'],
        ['url' => 'settings.php', 'icon' => 'fa-cog', 'title' => 'Sozlamalar'],
        ['url' => 'logs.php', 'icon' => 'fa-history', 'title' => 'Loglar'],
    ],
    'teacher' => [
        ['url' => 'dashboard.php', 'icon' => 'fa-tachometer-alt', 'title' => 'Dashboard'],
        ['url' => 'my_subjects.php', 'icon' => 'fa-book', 'title' => 'Mening fanlarim'],
        ['url' => 'topics.php', 'icon' => 'fa-list-alt', 'title' => 'Mavzular'],
        ['url' => 'questions.php', 'icon' => 'fa-question-circle', 'title' => 'Test savollari'],
        ['url' => 'tasks.php', 'icon' => 'fa-code', 'title' => 'Amaliy masalalar'],
        ['url' => 'monitoring.php', 'icon' => 'fa-eye', 'title' => 'Monitoring'],
        ['url' => 'reports.php', 'icon' => 'fa-chart-bar', 'title' => 'Hisobotlar'],
    ],
    'student' => [
        ['url' => 'dashboard.php', 'icon' => 'fa-tachometer-alt', 'title' => 'Dashboard'],
        ['url' => 'my_subjects.php', 'icon' => 'fa-book', 'title' => 'Mening fanlarim'],
        ['url' => 'grades.php', 'icon' => 'fa-medal', 'title' => 'Mening baholarim'],
        ['url' => 'my_submissions.php', 'icon' => 'fa-code', 'title' => 'Mening yechimlarim'],
        ['url' => 'ai_assistant.php', 'icon' => 'fa-robot', 'title' => 'AI Yordamchi'],
        ['url' => 'profile.php', 'icon' => 'fa-user', 'title' => 'Profil'],
    ]
];

$menu = $menus[$role] ?? [];
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(Auth::getCsrfToken()) ?>">
    <title><?= pageTitle($pageTitle) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <?php if (!empty($extraCss)): foreach ($extraCss as $css): ?>
        <link rel="stylesheet" href="<?= e($css) ?>">
    <?php endforeach; endif; ?>
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo-icon">
                    <i class="fas fa-code"></i>
                </div>
                <div>
                    <h2>CodeAcademy</h2>
                    <span><?php
                        echo $role === 'admin' ? 'Admin Panel' 
                            : ($role === 'teacher' ? 'O\'qituvchi' : 'Talaba');
                    ?></span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="sidebar-section">Asosiy</div>
                <?php foreach ($menu as $item): ?>
                    <a href="<?= SITE_URL ?>/<?= $role ?>/<?= $item['url'] ?>" 
                       class="sidebar-link <?= $currentPage === $item['url'] ? 'active' : '' ?>">
                        <i class="fas <?= $item['icon'] ?>"></i>
                        <span><?= e($item['title']) ?></span>
                    </a>
                <?php endforeach; ?>
                
                <div class="sidebar-section">Akkaunt</div>
                <a href="<?= SITE_URL ?>/logout.php" class="sidebar-link" 
                   data-confirm="Tizimdan chiqmoqchimisiz?">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Chiqish</span>
                </a>
            </nav>
        </aside>
        
        <!-- Main content -->
        <div class="main-content">
            <!-- Top header -->
            <header class="top-header">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button class="menu-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title"><?= e($pageTitle) ?></h1>
                </div>
                
                <div class="header-actions">
                    <button class="theme-toggle" title="Mavzuni o'zgartirish">
                        <i class="fas fa-moon"></i>
                    </button>
                    
                    <div class="user-menu">
                        <div class="user-avatar">
                            <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="user-name"><?= e($user['full_name']) ?></div>
                            <div class="user-role">
                                <?= $role === 'admin' ? 'Administrator' 
                                    : ($role === 'teacher' ? 'O\'qituvchi' : 'Talaba') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Content -->
            <main class="page-content fade-in">
                <?php if ($flash): ?>
                    <div class="alert alert-<?= e($flash['type']) ?>">
                        <i class="fas fa-info-circle"></i>
                        <span><?= e($flash['message']) ?></span>
                    </div>
                <?php endif; ?>
