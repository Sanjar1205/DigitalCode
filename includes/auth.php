<?php
/**
 * Autentifikatsiya va Avtorizatsiya
 */

require_once __DIR__ . '/db.php';

class Auth {
    
    /**
     * Tizimga kirish
     */
    public static function login($username, $password) {
        $user = db()->fetchOne(
            "SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active' LIMIT 1",
            [$username, $username]
        );
        
        if (!$user) {
            return ['success' => false, 'message' => 'Foydalanuvchi topilmadi'];
        }
        
        if (!password_verify($password, $user['password'])) {
            self::logActivity($user['id'], 'login_failed', 'Noto\'g\'ri parol');
            return ['success' => false, 'message' => 'Noto\'g\'ri parol'];
        }
        
        // Session yaratish
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_avatar'] = $user['avatar'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['login_time'] = time();
        
        // last_login yangilash
        db()->update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $user['id']]);
        
        self::logActivity($user['id'], 'login_success', 'Tizimga kirdi');
        
        return ['success' => true, 'role' => $user['role'], 'user' => $user];
    }
    
    /**
     * Tizimdan chiqish
     */
    public static function logout() {
        if (isset($_SESSION['user_id'])) {
            self::logActivity($_SESSION['user_id'], 'logout', 'Tizimdan chiqdi');
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
    
    /**
     * Foydalanuvchi tizimga kirgan-kirmaganligini tekshirish
     */
    public static function isLoggedIn() {
        if (!isset($_SESSION['user_id'])) return false;
        
        // Session timeout tekshirish
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_TIMEOUT) {
            self::logout();
            return false;
        }
        
        // Session vaqtini yangilash
        $_SESSION['login_time'] = time();
        return true;
    }
    
    /**
     * Joriy foydalanuvchini olish
     */
    public static function user() {
        if (!self::isLoggedIn()) return null;
        return db()->fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    }
    
    /**
     * Foydalanuvchi roli tekshirish
     */
    public static function hasRole($role) {
        if (!self::isLoggedIn()) return false;
        if (is_array($role)) {
            return in_array($_SESSION['user_role'], $role);
        }
        return $_SESSION['user_role'] === $role;
    }
    
    /**
     * Faqat ma'lum rollar uchun cheklash
     */
    public static function requireRole($role) {
        if (!self::isLoggedIn()) {
            header('Location: ' . SITE_URL . '/index.php');
            exit;
        }
        if (!self::hasRole($role)) {
            http_response_code(403);
            die('Sizda bu sahifaga kirish huquqi yo\'q');
        }
    }
    
    /**
     * Login talab qilinadi
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: ' . SITE_URL . '/index.php');
            exit;
        }
    }
    
    /**
     * Parolni hash qilish
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * CSRF token tekshirish
     */
    public static function verifyCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * CSRF token olish
     */
    public static function getCsrfToken() {
        return $_SESSION['csrf_token'] ?? '';
    }
    
    /**
     * Faoliyatni log qilish
     */
    public static function logActivity($userId, $action, $details = null) {
        try {
            db()->insert('activity_logs', [
                'user_id' => $userId,
                'action' => $action,
                'details' => $details,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
            ]);
        } catch (Exception $e) {
            // Log xatolarini sukutda kechiramiz
        }
    }
}
