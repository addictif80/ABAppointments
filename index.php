<?php
/**
 * ABAppointments - Main Entry Point
 * Redirects to the public booking page
 */

// Check if installed
if (!file_exists(__DIR__ . '/config/config.php') || filesize(__DIR__ . '/config/config.php') < 50) {
    header('Location: install/index.php');
    exit;
}

header('Location: public/');
exit;
