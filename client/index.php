<?php
/**
 * WebPanel - Client Panel Router
 */
require_once __DIR__ . '/../core/App.php';

$page = $_GET['page'] ?? 'dashboard';
$publicPages = ['login', 'register', 'forgot-password', 'reset-password'];

if (!in_array($page, $publicPages)) {
    Auth::requireClient();
}

// CSRF check on POST (except login/register)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($page, ['login', 'register'])) {
    if (!wp_verify_csrf()) {
        wp_flash('error', 'Token de securite invalide.');
        wp_redirect($_SERVER['REQUEST_URI']);
    }
}

$allowedPages = array_merge($publicPages, [
    'dashboard', 'services', 'order', 'order-confirm',
    'subscriptions', 'vps-detail', 'hosting-detail', 'navidrome-detail',
    'invoices', 'invoice-detail', 'invoice-pay',
    'gift-cards',
    'tickets', 'ticket-detail', 'ticket-new',
    'profile', 'logout'
]);

if ($page === 'logout') {
    Auth::logout();
    wp_redirect(wp_url('client/?page=login'));
}

if (!in_array($page, $allowedPages)) {
    $page = 'dashboard';
}

$pageFile = __DIR__ . "/pages/$page.php";
if (!file_exists($pageFile)) {
    $page = 'dashboard';
    $pageFile = __DIR__ . "/pages/dashboard.php";
}

if (in_array($page, $publicPages)) {
    require $pageFile;
} else {
    require __DIR__ . '/layout.php';
}
