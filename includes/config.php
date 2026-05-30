<?php
/**
 * CodeAcademy - Konfiguratsiya fayli
 * 
 * @author CodeAcademy Team
 * @version 1.0.0
 */

// Xatoliklarni ko'rsatish (PRODUCTION da false qiling)
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ═══════════════════════════════════════════════════════════

// ═══════════════════════════════════════════════════════════
// DATABASE SOZLAMALARI
// ═══════════════════════════════════════════════════════════
define('DB_HOST', 'mysql.railway.internal');
define('DB_NAME', 'railway');
define('DB_USER', 'root');
define('DB_PASS', 'rDLfjxshgIdletSdHAGnZLYyHpenZRaO');
define('DB_PORT', 3306);
define('DB_CHARSET', 'utf8mb4');
// // DATABASE SOZLAMALARI
// // ═══════════════════════════════════════════════════════════
// define('DB_HOST', 'fdb1032.awardspace.net');
// define('DB_NAME', '4760988_codeacademy');
// define('DB_USER', '4760988_codeacademy');
// define('DB_PASS', '1234567Yy#'); // Baza ochganingizda o'zingiz yozgan parolni kiriting
// define('DB_CHARSET', 'utf8mb4');

// ═══════════════════════════════════════════════════════════
// SAYT SOZLAMALARI (Yangi server manzillari)
// ═══════════════════════════════════════════════════════════
define('SITE_NAME', 'CodeAcademy');
define('SITE_URL', 'http://codeacdemy.atwebpages.com'); // Yangi domeningiz
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL', SITE_URL . '/assets/uploads/');

// ═══════════════════════════════════════════════════════════
// SESSION SOZLAMALARI
// ═══════════════════════════════════════════════════════════
define('SESSION_NAME', 'CODEACADEMY_SESS');
define('SESSION_TIMEOUT', 3600); // 1 soat
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 daqiqa

// ═══════════════════════════════════════════════════════════
// API KALITLARI (kabinetdan ham o'zgartirish mumkin)
// ═══════════════════════════════════════════════════════════
define('JUDGE0_API_KEY', ''); // RapidAPI key
define('JUDGE0_API_URL', 'https://judge0-ce.p.rapidapi.com');

// AI API (OpenAI yoki Claude)
define('AI_PROVIDER', 'claude'); // 'claude' yoki 'openai'
define('CLAUDE_API_KEY', ''); // Anthropic API key
define('OPENAI_API_KEY', ''); // OpenAI API key
define('GEMINI_API_KEY', 'SIZNING_KALITINGIZ');

// ═══════════════════════════════════════════════════════════
// VAQT MINTAQASI
// ═══════════════════════════════════════════════════════════
date_default_timezone_set('Asia/Tashkent');

// ═══════════════════════════════════════════════════════════
// SESSION BOSHLASH
// ═══════════════════════════════════════════════════════════
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path' => '/',
        'domain' => '',
        'secure' => false, // HTTPS uchun true
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// CSRF Token generatsiya
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
