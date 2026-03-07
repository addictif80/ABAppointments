<?php
/**
 * ABAppointments - Admin Router
 */
require_once __DIR__ . '/../core/App.php';

$page = $_GET['page'] ?? 'dashboard';

// Public pages (no auth needed)
$publicPages = ['login', 'google-callback'];

if (!in_array($page, $publicPages)) {
    Auth::requireAuth();
}

// Route to page
$allowedPages = [
    'login', 'dashboard', 'appointments', 'services', 'categories',
    'providers', 'customers', 'settings', 'working-hours', 'holidays',
    'deposits', 'email-templates', 'google-callback', 'profile'
];

if (!in_array($page, $allowedPages)) {
    $page = 'dashboard';
}

$pageFile = __DIR__ . '/pages/' . $page . '.php';
if (file_exists($pageFile)) {
    // Handle POST actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !Auth::verifyCsrf($_POST['csrf_token'])) {
            ab_flash('error', 'Token de sécurité invalide. Veuillez réessayer.');
            ab_redirect(ab_url('admin/index.php?page=' . $page));
        }
    }

    if ($page === 'login') {
        require $pageFile;
    } else {
        // Wrap in admin layout
        $pageContent = $page;
        require __DIR__ . '/layout.php';
    }
} else {
    http_response_code(404);
    echo 'Page non trouvée';
}
