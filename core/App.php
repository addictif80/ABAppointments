<?php
/**
 * ABAppointments - Application Bootstrap
 */

// Load configuration
require_once __DIR__ . '/../config/config.php';

// Error handling
if (AB_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Timezone
date_default_timezone_set(AB_TIMEZONE);

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_name(AB_SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => AB_SESSION_LIFETIME,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Core classes
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/AppointmentManager.php';
require_once __DIR__ . '/GoogleCalendar.php';
require_once __DIR__ . '/CalDAV.php';

/**
 * Helper functions
 */
function ab_url(string $path = ''): string {
    static $baseUrl = null;
    if ($baseUrl === null) {
        if (defined('AB_BASE_URL') && AB_BASE_URL !== '' && AB_BASE_URL !== 'http://localhost/ABAppointments') {
            $baseUrl = rtrim(AB_BASE_URL, '/');
        } elseif (isset($_SERVER['HTTP_HOST'])) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
            // Go up one level if we're inside a subdirectory (public/, admin/, api/)
            if (preg_match('#/(public|admin|api)$#', $scriptDir)) {
                $scriptDir = dirname($scriptDir);
            }
            $baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim($scriptDir, '/');
        } else {
            $baseUrl = rtrim(AB_BASE_URL, '/');
        }
    }
    return $baseUrl . '/' . ltrim($path, '/');
}

function ab_escape(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function ab_safe_html(?string $str): string {
    if ($str === null || $str === '') return '';
    $allowed = '<b><i><u><strong><em><a><br><ul><ol><li><p><span><h5><h6><small><hr>';
    $clean = strip_tags($str, $allowed);
    // Remove dangerous attributes (on*, style with expression/url, javascript: in href)
    $clean = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $clean);
    $clean = preg_replace('/\s+on\w+\s*=\s*\S+/i', '', $clean);
    $clean = preg_replace('/href\s*=\s*["\']?\s*javascript\s*:/i', 'href="', $clean);
    return $clean;
}

function ab_setting(string $key, string $default = ''): string {
    return Settings::get($key, $default);
}

function ab_format_date(string $datetime): string {
    $format = ab_setting('date_format', 'd/m/Y');
    return date($format, strtotime($datetime));
}

function ab_format_time(string $datetime): string {
    $format = ab_setting('time_format', 'H:i');
    return date($format, strtotime($datetime));
}

function ab_format_price(float $amount): string {
    $symbol = ab_setting('currency_symbol', '€');
    return number_format($amount, 2, ',', ' ') . ' ' . $symbol;
}

function ab_flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function ab_get_flash(): array {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function ab_redirect(string $url): void {
    // Discard any buffered output so the Location header can be sent
    // even if the layout already started rendering HTML.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . $url);
    exit;
}

function ab_json(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function ab_generate_hash(): string {
    return bin2hex(random_bytes(16));
}
