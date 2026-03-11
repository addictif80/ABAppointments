<?php
/**
 * WebPanel - Admin Panel Router
 */
require_once __DIR__ . '/../core/App.php';

$page = $_GET['page'] ?? 'dashboard';

if ($page !== 'login') {
    Auth::requireAdmin();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page !== 'login') {
    if (!wp_verify_csrf()) {
        wp_flash('error', 'Token de securite invalide.');
        wp_redirect($_SERVER['REQUEST_URI']);
    }
}

$allowedPages = [
    'login', 'dashboard', 'clients', 'client-detail',
    'products', 'subscriptions', 'subscription-detail',
    'invoices', 'invoice-detail', 'payments',
    'promo-codes', 'gift-cards',
    'tickets', 'ticket-detail',
    'monitoring', 'incidents',
    'ip-pool', 'os-templates', 'email-templates',
    'settings', 'activity-log', 'logout'
];

if ($page === 'logout') {
    Auth::logout();
    wp_redirect(wp_url('admin/?page=login'));
}

if (!in_array($page, $allowedPages)) $page = 'dashboard';

$pageFile = __DIR__ . "/pages/$page.php";
if (!file_exists($pageFile)) { $page = 'dashboard'; $pageFile = __DIR__ . '/pages/dashboard.php'; }

if ($page === 'login') {
    require $pageFile;
} else {
    require __DIR__ . '/layout.php';
}
