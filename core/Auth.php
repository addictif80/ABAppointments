<?php
/**
 * WebPanel - Authentication
 */
class Auth {
    public static function login($email, $password) {
        $db = Database::getInstance();
        $user = $db->fetchOne("SELECT * FROM wp_users WHERE email = ? AND status != 'banned'", [$email]);
        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_email'] = $user['email'];
        $db->update('wp_users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
        wp_log_activity('login', 'user', $user['id']);
        return $user;
    }

    public static function logout() {
        wp_log_activity('logout', 'user', $_SESSION['user_id'] ?? null);
        session_destroy();
    }

    public static function check() {
        return isset($_SESSION['user_id']);
    }

    public static function user() {
        if (!self::check()) return null;
        $db = Database::getInstance();
        return $db->fetchOne("SELECT * FROM wp_users WHERE id = ?", [$_SESSION['user_id']]);
    }

    public static function isAdmin() {
        return ($_SESSION['user_role'] ?? '') === 'admin';
    }

    public static function requireAuth($redirect = null) {
        if (!self::check()) {
            wp_redirect($redirect ?: wp_url('client/?page=login'));
        }
    }

    public static function requireAdmin() {
        if (!self::check() || !self::isAdmin()) {
            wp_redirect(wp_url('admin/?page=login'));
        }
    }

    public static function requireClient() {
        if (!self::check()) {
            wp_redirect(wp_url('client/?page=login'));
        }
        if (self::isAdmin()) {
            // Admin can also access client panel
        }
    }

    public static function register($data) {
        $db = Database::getInstance();
        $existing = $db->fetchOne("SELECT id FROM wp_users WHERE email = ?", [$data['email']]);
        if ($existing) return ['error' => 'Cet email est deja utilise.'];

        $verifyToken = wp_generate_token();
        $userId = $db->insert('wp_users', [
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'company' => $data['company'] ?? null,
            'phone' => $data['phone'] ?? null,
            'role' => 'client',
            'email_verify_token' => $verifyToken
        ]);

        wp_log_activity('register', 'user', $userId);
        return ['success' => true, 'user_id' => $userId, 'verify_token' => $verifyToken];
    }

    public static function requestPasswordReset($email) {
        $db = Database::getInstance();
        $user = $db->fetchOne("SELECT id, first_name FROM wp_users WHERE email = ?", [$email]);
        if (!$user) return true; // Don't reveal if email exists

        $token = wp_generate_token();
        $db->update('wp_users', [
            'password_reset_token' => $token,
            'password_reset_expires' => date('Y-m-d H:i:s', strtotime('+1 hour'))
        ], 'id = ?', [$user['id']]);

        return ['token' => $token, 'user' => $user];
    }

    public static function resetPassword($token, $newPassword) {
        $db = Database::getInstance();
        $user = $db->fetchOne(
            "SELECT id FROM wp_users WHERE password_reset_token = ? AND password_reset_expires > NOW()",
            [$token]
        );
        if (!$user) return false;

        $db->update('wp_users', [
            'password' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]),
            'password_reset_token' => null,
            'password_reset_expires' => null
        ], 'id = ?', [$user['id']]);

        return true;
    }
}
