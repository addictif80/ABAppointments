<?php
/**
 * ABAppointments - Authentication
 */
class Auth {
    public static function login(string $email, string $password): ?array {
        $db = Database::getInstance();
        $user = $db->fetchOne(
            "SELECT * FROM ab_users WHERE email = ? AND is_active = 1",
            [$email]
        );

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            return $user;
        }
        return null;
    }

    public static function logout(): void {
        session_destroy();
        $_SESSION = [];
    }

    public static function check(): bool {
        return isset($_SESSION['user_id']);
    }

    public static function requireAuth(): void {
        if (!self::check()) {
            header('Location: ' . AB_BASE_URL . '/admin/index.php?page=login');
            exit;
        }
    }

    public static function requireAdmin(): void {
        self::requireAuth();
        if ($_SESSION['user_role'] !== 'admin') {
            http_response_code(403);
            exit('Accès refusé');
        }
    }

    public static function isAdmin(): bool {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }

    public static function userId(): ?int {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function verifyCsrf(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function csrfToken(): string {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function csrfField(): string {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::csrfToken()) . '">';
    }

    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}
