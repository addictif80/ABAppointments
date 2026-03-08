<?php
/**
 * ABAppointments - Main Entry Point
 * Redirects to the public booking page
 */

// Check if installed
if (!file_exists(__DIR__ . '/config/config.php') || filesize(__DIR__ . '/config/config.php') < 50) {
    header('Location: ' . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/install/index.php');
    exit;
}

require_once __DIR__ . '/core/App.php';

// Handle /manage/HASH route as fallback (if mod_rewrite fails)
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$path = substr($requestUri, strlen($basePath));
$path = trim($path, '/');

if (preg_match('#^manage/([a-zA-Z0-9]+)$#', $path, $matches)) {
    $_GET['hash'] = $matches[1];
    require __DIR__ . '/public/manage.php';
    exit;
}

// Default: redirect to booking page (absolute URL)
header('Location: ' . ab_url('public/'));
exit;
