<?php
/**
 * WebPanel - Configuration
 * Renommer ce fichier en config.php et modifier les valeurs
 */

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'webpanel');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PREFIX', 'wp_');

// Security
define('SECRET_KEY', '<?php echo bin2hex(random_bytes(32)); ?>');
define('SESSION_NAME', 'webpanel_session');
define('SESSION_LIFETIME', 86400);

// Paths
define('BASE_PATH', __DIR__ . '/..');
define('BASE_URL', 'http://localhost');

// Debug
define('DEBUG_MODE', false);

// Upload
define('UPLOAD_DIR', BASE_PATH . '/assets/uploads');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);

// Timezone
define('APP_TIMEZONE', 'Europe/Paris');
define('APP_LOCALE', 'fr_FR');
