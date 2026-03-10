<?php
/**
 * WebPanel - Application Bootstrap
 */

// Load config
$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    header('Location: ' . dirname($_SERVER['SCRIPT_NAME']) . '/install/');
    exit;
}
require_once $configFile;

// Error handling
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Europe/Paris');

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_name(defined('SESSION_NAME') ? SESSION_NAME : 'webpanel_session');
    session_set_cookie_params([
        'lifetime' => defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 86400,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Autoload core classes
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/ProxmoxAPI.php';
require_once __DIR__ . '/CyberPanelAPI.php';
require_once __DIR__ . '/NavidromeAPI.php';
require_once __DIR__ . '/StripeManager.php';
require_once __DIR__ . '/UptimeKumaAPI.php';
require_once __DIR__ . '/InvoiceManager.php';
require_once __DIR__ . '/ServiceManager.php';

// Helper functions
function wp_url($path = '') {
    $base = rtrim(defined('BASE_URL') ? BASE_URL : '', '/');
    return $base . '/' . ltrim($path, '/');
}

function wp_escape($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function wp_setting($key, $default = '') {
    static $settings = null;
    if ($settings === null) {
        $settings = new Settings();
    }
    return $settings->get($key, $default);
}

function wp_flash($type, $message = null) {
    if ($message === null) {
        $messages = $_SESSION['flash'][$type] ?? [];
        unset($_SESSION['flash'][$type]);
        return $messages;
    }
    $_SESSION['flash'][$type][] = $message;
}

function wp_redirect($url) {
    header('Location: ' . $url);
    exit;
}

function wp_json($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function wp_format_price($amount, $currency = null) {
    if ($currency === null) $currency = wp_setting('currency_symbol', '€');
    return number_format((float)$amount, 2, ',', ' ') . ' ' . $currency;
}

function wp_format_date($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    return date($format, is_numeric($date) ? $date : strtotime($date));
}

function wp_format_datetime($date) {
    return wp_format_date($date, 'd/m/Y H:i');
}

function wp_generate_token($length = 32) {
    return bin2hex(random_bytes($length));
}

function wp_generate_password($length = 16) {
    $chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

function wp_log_activity($action, $entityType = null, $entityId = null, $details = null) {
    try {
        $db = Database::getInstance();
        $db->insert('wp_activity_log', [
            'user_id' => $_SESSION['user_id'] ?? null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details ? json_encode($details) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        // Silent fail for logging
    }
}

function wp_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = wp_generate_token();
    }
    return $_SESSION['csrf_token'];
}

function wp_csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . wp_csrf_token() . '">';
}

function wp_verify_csrf() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function wp_paginate($total, $perPage = 20, $currentPage = null) {
    if ($currentPage === null) $currentPage = max(1, (int)($_GET['page'] ?? 1));
    $totalPages = max(1, ceil($total / $perPage));
    $currentPage = min($currentPage, $totalPages);
    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => ($currentPage - 1) * $perPage
    ];
}

function wp_pagination_html($pagination, $baseUrl = '?') {
    if ($pagination['total_pages'] <= 1) return '';
    $html = '<nav><ul class="pagination justify-content-center">';
    $sep = strpos($baseUrl, '?') !== false ? '&' : '?';

    if ($pagination['current_page'] > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $sep . 'page=' . ($pagination['current_page'] - 1) . '">&laquo;</a></li>';
    }

    $start = max(1, $pagination['current_page'] - 2);
    $end = min($pagination['total_pages'], $pagination['current_page'] + 2);

    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $pagination['current_page'] ? ' active' : '';
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . $baseUrl . $sep . 'page=' . $i . '">' . $i . '</a></li>';
    }

    if ($pagination['current_page'] < $pagination['total_pages']) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $sep . 'page=' . ($pagination['current_page'] + 1) . '">&raquo;</a></li>';
    }

    $html .= '</ul></nav>';
    return $html;
}
