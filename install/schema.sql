-- ABAppointments Database Schema
-- Système de prise de rendez-vous pour prestations ongulaires

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Table: settings (paramètres généraux)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ab_settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: users (administrateurs / prestataires)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ab_users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `role` ENUM('admin', 'provider') NOT NULL DEFAULT 'provider',
  `avatar` VARCHAR(255) DEFAULT NULL,
  `timezone` VARCHAR(50) DEFAULT 'Europe/Paris',
  `notes` TEXT,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: service_categories
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ab_service_categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT,
  `sort_order` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: services (prestations)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ab_services` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `duration` INT NOT NULL COMMENT 'Durée en minutes',
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(3) DEFAULT 'EUR',
  `color` VARCHAR(7) DEFAULT '#e91e63',
  `deposit_enabled` TINYINT(1) DEFAULT 0 COMMENT 'Acompte requis',
  `deposit_type` ENUM('fixed', 'percentage') DEFAULT 'percentage',
  `deposit_amount` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Montant ou pourcentage acompte',
  `buffer_before` INT DEFAULT 0 COMMENT 'Temps tampon avant (minutes)',
  `buffer_after` INT DEFAULT 0 COMMENT 'Temps tampon après (minutes)',
  `max_attendees` INT DEFAULT 1,
  `sort_order` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `ab_service_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: provider_services (liaison prestataire-service)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ab_provider_services` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `provider_id` INT UNSIGNED NOT NULL,
  `service_id` INT UNSIGNED NOT NULL,
  UNIQUE KEY `provider_service` (`provider_id`, `service_id`),
  FOREIGN KEY (`provider_id`) REFERENCES `ab_users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `ab_services`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: working_hours (horaires de travail)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ab_working_hours` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `provider_id` INT UNSIGNED NOT NULL,
  `day_of_week` TINYINT NOT NULL COMMENT '0=Dimanche, 1=Lundi...6=Samedi',
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `break_start` TIME DEFAULT NULL,
  `break_end` TIME DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  FOREIGN KEY (`provider_id`) REFERENCES `ab_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: breaks (pauses additionnelles)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ab_breaks` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `provider_id` INT UNSIGNED NOT NULL,
  `day_of_week` TINYINT NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  FOREIGN KEY (`provider_id`) REFERENCES `ab_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: holidays (jours fériés / congés)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ab_holidays` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `provider_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL = tous les prestataires',
  `title` VARCHAR(200) NOT NULL,
  `date_start` DATE NOT NULL,
  `date_end` DATE NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`provider_id`) REFERENCES `ab_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: customers (clients)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ab_customers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `address` TEXT,
  `city` VARCHAR(100) DEFAULT NULL,
  `zip_code` VARCHAR(10) DEFAULT NULL,
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `customer_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: appointments (rendez-vous)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ab_appointments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `hash` VARCHAR(64) NOT NULL UNIQUE COMMENT 'Identifiant unique pour liens publics',
  `customer_id` INT UNSIGNED NOT NULL,
  `provider_id` INT UNSIGNED NOT NULL,
  `service_id` INT UNSIGNED NOT NULL,
  `start_datetime` DATETIME NOT NULL,
  `end_datetime` DATETIME NOT NULL,
  `status` ENUM('pending', 'confirmed', 'cancelled', 'completed', 'no_show') DEFAULT 'pending',
  `notes` TEXT,
  `admin_notes` TEXT,
  `color` VARCHAR(7) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `ab_customers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`provider_id`) REFERENCES `ab_users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `ab_services`(`id`) ON DELETE CASCADE,
  INDEX `idx_start` (`start_datetime`),
  INDEX `idx_provider_date` (`provider_id`, `start_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: deposits (acomptes)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ab_deposits` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `appointment_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(3) DEFAULT 'EUR',
  `payment_method` ENUM('bank_transfer', 'card', 'cash', 'paypal', 'stripe', 'other') DEFAULT 'bank_transfer',
  `payment_reference` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending', 'paid', 'refunded', 'cancelled') DEFAULT 'pending',
  `due_date` DATETIME DEFAULT NULL,
  `paid_at` DATETIME DEFAULT NULL,
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`appointment_id`) REFERENCES `ab_appointments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: email_templates
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ab_email_templates` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `name` VARCHAR(200) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body` TEXT NOT NULL,
  `variables` TEXT COMMENT 'Variables disponibles (JSON)',
  `is_active` TINYINT(1) DEFAULT 1,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: google_calendar_sync
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ab_google_sync` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `provider_id` INT UNSIGNED NOT NULL,
  `google_calendar_id` VARCHAR(255) DEFAULT NULL,
  `access_token` TEXT,
  `refresh_token` TEXT,
  `token_expiry` DATETIME DEFAULT NULL,
  `sync_enabled` TINYINT(1) DEFAULT 0,
  `last_sync` DATETIME DEFAULT NULL,
  FOREIGN KEY (`provider_id`) REFERENCES `ab_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Données par défaut
-- --------------------------------------------------------

-- Paramètres par défaut
INSERT INTO `ab_settings` (`setting_key`, `setting_value`) VALUES
('business_name', 'Mon Salon d\'Ongles'),
('business_email', 'contact@monsalon.fr'),
('business_phone', ''),
('business_address', ''),
('business_logo', ''),
('timezone', 'Europe/Paris'),
('date_format', 'd/m/Y'),
('time_format', 'H:i'),
('slot_interval', '15'),
('booking_advance_min', '60'),
('booking_advance_max', '43200'),
('cancellation_limit', '1440'),
('require_phone', '1'),
('allow_customer_cancel', '1'),
('auto_confirm', '0'),
('currency', 'EUR'),
('currency_symbol', '€'),
('deposit_instructions', 'Veuillez effectuer votre virement sur le compte suivant :\nIBAN: XXXX XXXX XXXX XXXX\nBIC: XXXXXXXX\nRéférence: Votre numéro de réservation'),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_encryption', 'tls'),
('smtp_username', ''),
('smtp_password', ''),
('smtp_from_email', ''),
('smtp_from_name', ''),
('google_client_id', ''),
('google_client_secret', ''),
('google_redirect_uri', ''),
('primary_color', '#e91e63'),
('secondary_color', '#9c27b0'),
('embed_enabled', '1');

-- Templates email par défaut
INSERT INTO `ab_email_templates` (`slug`, `name`, `subject`, `body`, `variables`) VALUES
('appointment_confirmed', 'Confirmation de rendez-vous', 'Confirmation de votre rendez-vous - {business_name}',
'<h2>Bonjour {customer_name},</h2>
<p>Votre rendez-vous a été confirmé !</p>
<table style="border-collapse:collapse;width:100%;max-width:500px;">
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Prestation</td><td style="padding:8px;border:1px solid #ddd;">{service_name}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Date</td><td style="padding:8px;border:1px solid #ddd;">{appointment_date}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Heure</td><td style="padding:8px;border:1px solid #ddd;">{appointment_time}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Durée</td><td style="padding:8px;border:1px solid #ddd;">{service_duration} min</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Prix</td><td style="padding:8px;border:1px solid #ddd;">{service_price} €</td></tr>
</table>
{deposit_section}
<p>Pour annuler ou modifier votre rendez-vous :<br><a href="{manage_url}">Gérer mon rendez-vous</a></p>
<p>Cordialement,<br>{business_name}</p>',
'["customer_name","service_name","appointment_date","appointment_time","service_duration","service_price","deposit_section","manage_url","business_name"]'),

('appointment_pending', 'Rendez-vous en attente', 'Votre demande de rendez-vous - {business_name}',
'<h2>Bonjour {customer_name},</h2>
<p>Votre demande de rendez-vous a bien été enregistrée et est en attente de confirmation.</p>
<table style="border-collapse:collapse;width:100%;max-width:500px;">
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Prestation</td><td style="padding:8px;border:1px solid #ddd;">{service_name}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Date</td><td style="padding:8px;border:1px solid #ddd;">{appointment_date}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Heure</td><td style="padding:8px;border:1px solid #ddd;">{appointment_time}</td></tr>
</table>
{deposit_section}
<p>Vous recevrez un email de confirmation prochainement.</p>
<p>Cordialement,<br>{business_name}</p>',
'["customer_name","service_name","appointment_date","appointment_time","deposit_section","business_name"]'),

('appointment_cancelled', 'Annulation de rendez-vous', 'Annulation de votre rendez-vous - {business_name}',
'<h2>Bonjour {customer_name},</h2>
<p>Votre rendez-vous du {appointment_date} à {appointment_time} pour la prestation "{service_name}" a été annulé.</p>
<p>Si vous souhaitez reprendre rendez-vous, n''hésitez pas à consulter notre agenda en ligne.</p>
<p>Cordialement,<br>{business_name}</p>',
'["customer_name","service_name","appointment_date","appointment_time","business_name"]'),

('deposit_required', 'Acompte requis', 'Acompte requis pour votre rendez-vous - {business_name}',
'<h2>Bonjour {customer_name},</h2>
<p>Un acompte de <strong>{deposit_amount} €</strong> est requis pour confirmer votre rendez-vous :</p>
<table style="border-collapse:collapse;width:100%;max-width:500px;">
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Prestation</td><td style="padding:8px;border:1px solid #ddd;">{service_name}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Date</td><td style="padding:8px;border:1px solid #ddd;">{appointment_date}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Heure</td><td style="padding:8px;border:1px solid #ddd;">{appointment_time}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Montant acompte</td><td style="padding:8px;border:1px solid #ddd;">{deposit_amount} €</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Date limite</td><td style="padding:8px;border:1px solid #ddd;">{deposit_due_date}</td></tr>
</table>
<h3>Instructions de paiement :</h3>
<p>{deposit_instructions}</p>
<p>Cordialement,<br>{business_name}</p>',
'["customer_name","service_name","appointment_date","appointment_time","deposit_amount","deposit_due_date","deposit_instructions","business_name"]'),

('deposit_confirmed', 'Acompte reçu', 'Acompte reçu - {business_name}',
'<h2>Bonjour {customer_name},</h2>
<p>Nous confirmons la réception de votre acompte de <strong>{deposit_amount} €</strong>.</p>
<p>Votre rendez-vous est maintenant confirmé :</p>
<table style="border-collapse:collapse;width:100%;max-width:500px;">
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Prestation</td><td style="padding:8px;border:1px solid #ddd;">{service_name}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Date</td><td style="padding:8px;border:1px solid #ddd;">{appointment_date}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Heure</td><td style="padding:8px;border:1px solid #ddd;">{appointment_time}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Reste à payer</td><td style="padding:8px;border:1px solid #ddd;">{remaining_amount} €</td></tr>
</table>
<p>Cordialement,<br>{business_name}</p>',
'["customer_name","service_name","appointment_date","appointment_time","deposit_amount","remaining_amount","business_name"]'),

('admin_new_appointment', 'Nouveau rendez-vous (admin)', 'Nouveau rendez-vous - {customer_name}',
'<h2>Nouveau rendez-vous</h2>
<p>Un nouveau rendez-vous a été pris :</p>
<table style="border-collapse:collapse;width:100%;max-width:500px;">
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Client</td><td style="padding:8px;border:1px solid #ddd;">{customer_name}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Email</td><td style="padding:8px;border:1px solid #ddd;">{customer_email}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Téléphone</td><td style="padding:8px;border:1px solid #ddd;">{customer_phone}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Prestation</td><td style="padding:8px;border:1px solid #ddd;">{service_name}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Date</td><td style="padding:8px;border:1px solid #ddd;">{appointment_date}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Heure</td><td style="padding:8px;border:1px solid #ddd;">{appointment_time}</td></tr>
</table>
<p><a href="{admin_url}">Voir dans l''administration</a></p>',
'["customer_name","customer_email","customer_phone","service_name","appointment_date","appointment_time","admin_url"]'),

('appointment_reminder', 'Rappel de rendez-vous', 'Rappel : votre rendez-vous demain - {business_name}',
'<h2>Bonjour {customer_name},</h2>
<p>Nous vous rappelons votre rendez-vous prévu demain :</p>
<table style="border-collapse:collapse;width:100%;max-width:500px;">
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Prestation</td><td style="padding:8px;border:1px solid #ddd;">{service_name}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Date</td><td style="padding:8px;border:1px solid #ddd;">{appointment_date}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Heure</td><td style="padding:8px;border:1px solid #ddd;">{appointment_time}</td></tr>
</table>
<p>Pour annuler ou modifier :<br><a href="{manage_url}">Gérer mon rendez-vous</a></p>
<p>A bientôt !<br>{business_name}</p>',
'["customer_name","service_name","appointment_date","appointment_time","manage_url","business_name"]');

-- Admin par défaut (mot de passe: admin123 - à changer !)
INSERT INTO `ab_users` (`first_name`, `last_name`, `email`, `password`, `role`) VALUES
('Admin', 'Salon', 'admin@monsalon.fr', '$2y$10$placeholder_will_be_set_during_install', 'admin');

SET FOREIGN_KEY_CHECKS = 1;
