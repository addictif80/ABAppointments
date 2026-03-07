<?php
/**
 * ABAppointments - Embeddable Widget (for WordPress/iframe)
 * Same as index.php but without header/footer for iframe embedding
 */
require_once __DIR__ . '/../core/App.php';

if (ab_setting('embed_enabled', '1') !== '1') {
    http_response_code(403);
    exit('Widget désactivé.');
}

// Set headers to allow embedding
header('X-Frame-Options: ALLOWALL');
header('Content-Security-Policy: frame-ancestors *');

require __DIR__ . '/index.php';
