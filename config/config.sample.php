<?php
/**
 * ABAppointments - Configuration
 * Renommer ce fichier en config.php et modifier les valeurs
 */

// Mode debug (désactiver en production)
define('AB_DEBUG', false);

// URL de base de l'application (sans slash final)
define('AB_BASE_URL', 'http://localhost/ABAppointments');

// Base de données
define('AB_DB_HOST', 'localhost');
define('AB_DB_NAME', 'ab_appointments');
define('AB_DB_USER', 'root');
define('AB_DB_PASS', '');
define('AB_DB_PREFIX', 'ab_');
define('AB_DB_CHARSET', 'utf8mb4');

// Clé secrète pour les tokens (générer une clé aléatoire unique)
define('AB_SECRET_KEY', 'CHANGE_THIS_TO_A_RANDOM_STRING_OF_64_CHARACTERS');

// Fuseau horaire par défaut
define('AB_TIMEZONE', 'Europe/Paris');

// Session
define('AB_SESSION_NAME', 'ab_session');
define('AB_SESSION_LIFETIME', 7200); // 2 heures

// Upload
define('AB_UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('AB_MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 Mo
