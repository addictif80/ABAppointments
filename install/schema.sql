-- WebPanel - Client Subscription Panel
-- Database Schema

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- USERS & AUTHENTICATION
-- ============================================

CREATE TABLE IF NOT EXISTS `wp_users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `company` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(30) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `postal_code` VARCHAR(20) DEFAULT NULL,
    `country` VARCHAR(100) DEFAULT 'France',
    `role` ENUM('admin','client') NOT NULL DEFAULT 'client',
    `status` ENUM('active','suspended','banned') NOT NULL DEFAULT 'active',
    `email_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `email_verify_token` VARCHAR(64) DEFAULT NULL,
    `password_reset_token` VARCHAR(64) DEFAULT NULL,
    `password_reset_expires` DATETIME DEFAULT NULL,
    `stripe_customer_id` VARCHAR(255) DEFAULT NULL,
    `credit_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `last_login` DATETIME DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_role` (`role`),
    INDEX `idx_status` (`status`),
    INDEX `idx_stripe` (`stripe_customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PRODUCTS & PLANS
-- ============================================

CREATE TABLE IF NOT EXISTS `wp_products` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `type` ENUM('vps','hosting','navidrome') NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `features` JSON DEFAULT NULL,
    `price_monthly` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `price_yearly` DECIMAL(10,2) DEFAULT NULL,
    `setup_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `proxmox_cores` INT DEFAULT NULL,
    `proxmox_ram_mb` INT DEFAULT NULL,
    `proxmox_disk_gb` INT DEFAULT NULL,
    `proxmox_bandwidth_gb` INT DEFAULT NULL,
    `proxmox_template` VARCHAR(255) DEFAULT NULL,
    `proxmox_pool` VARCHAR(100) DEFAULT NULL,
    `proxmox_storage` VARCHAR(100) DEFAULT NULL,
    `proxmox_bridge` VARCHAR(50) DEFAULT NULL,
    `hosting_disk_mb` INT DEFAULT NULL,
    `hosting_bandwidth_mb` INT DEFAULT NULL,
    `hosting_email_accounts` INT DEFAULT NULL,
    `hosting_databases` INT DEFAULT NULL,
    `hosting_domains` INT DEFAULT NULL,
    `hosting_package` VARCHAR(255) DEFAULT NULL,
    `navidrome_storage_mb` INT DEFAULT NULL,
    `navidrome_max_playlists` INT DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `stock` INT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_type` (`type`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wp_os_templates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `proxmox_template` VARCHAR(255) NOT NULL,
    `icon` VARCHAR(100) DEFAULT NULL,
    `category` VARCHAR(50) DEFAULT 'linux',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SUBSCRIPTIONS & SERVICES
-- ============================================

CREATE TABLE IF NOT EXISTS `wp_subscriptions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `status` ENUM('pending','active','suspended','cancelled','expired') NOT NULL DEFAULT 'pending',
    `billing_cycle` ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
    `price` DECIMAL(10,2) NOT NULL,
    `next_due_date` DATE NOT NULL,
    `started_at` DATETIME DEFAULT NULL,
    `suspended_at` DATETIME DEFAULT NULL,
    `cancelled_at` DATETIME DEFAULT NULL,
    `expires_at` DATETIME DEFAULT NULL,
    `suspension_reason` VARCHAR(255) DEFAULT NULL,
    `auto_renew` TINYINT(1) NOT NULL DEFAULT 1,
    `stripe_subscription_id` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_product` (`product_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_due` (`next_due_date`),
    FOREIGN KEY (`user_id`) REFERENCES `wp_users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `wp_products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wp_services_vps` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subscription_id` INT UNSIGNED NOT NULL UNIQUE,
    `hostname` VARCHAR(255) NOT NULL,
    `proxmox_vmid` INT DEFAULT NULL,
    `proxmox_node` VARCHAR(100) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `ipv6_address` VARCHAR(45) DEFAULT NULL,
    `os_template_id` INT UNSIGNED DEFAULT NULL,
    `root_password` TEXT DEFAULT NULL,
    `ssh_keys` TEXT DEFAULT NULL,
    `status` ENUM('creating','running','stopped','suspended','error','reinstalling') NOT NULL DEFAULT 'creating',
    `cores` INT NOT NULL,
    `ram_mb` INT NOT NULL,
    `disk_gb` INT NOT NULL,
    `bandwidth_gb` INT DEFAULT NULL,
    `bandwidth_used_gb` DECIMAL(10,2) DEFAULT 0.00,
    `last_status_check` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_vmid` (`proxmox_vmid`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`subscription_id`) REFERENCES `wp_subscriptions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`os_template_id`) REFERENCES `wp_os_templates`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wp_services_hosting` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subscription_id` INT UNSIGNED NOT NULL UNIQUE,
    `domain` VARCHAR(255) NOT NULL,
    `cyberpanel_username` VARCHAR(100) DEFAULT NULL,
    `cyberpanel_password` TEXT DEFAULT NULL,
    `cyberpanel_package` VARCHAR(255) DEFAULT NULL,
    `nameserver1` VARCHAR(255) DEFAULT NULL,
    `nameserver2` VARCHAR(255) DEFAULT NULL,
    `disk_mb` INT NOT NULL,
    `disk_used_mb` INT DEFAULT 0,
    `bandwidth_mb` INT NOT NULL,
    `bandwidth_used_mb` INT DEFAULT 0,
    `email_accounts` INT NOT NULL DEFAULT 5,
    `databases` INT NOT NULL DEFAULT 3,
    `ssl_active` TINYINT(1) NOT NULL DEFAULT 0,
    `php_version` VARCHAR(10) DEFAULT '8.2',
    `status` ENUM('creating','active','suspended','error') NOT NULL DEFAULT 'creating',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`subscription_id`) REFERENCES `wp_subscriptions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wp_services_navidrome` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subscription_id` INT UNSIGNED NOT NULL UNIQUE,
    `navidrome_username` VARCHAR(100) DEFAULT NULL,
    `navidrome_password` TEXT DEFAULT NULL,
    `navidrome_user_id` VARCHAR(255) DEFAULT NULL,
    `storage_mb` INT NOT NULL,
    `storage_used_mb` INT DEFAULT 0,
    `max_playlists` INT DEFAULT NULL,
    `status` ENUM('creating','active','suspended','error') NOT NULL DEFAULT 'creating',
    `last_login` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`subscription_id`) REFERENCES `wp_subscriptions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INVOICES & PAYMENTS
-- ============================================

CREATE TABLE IF NOT EXISTS `wp_invoices` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
    `user_id` INT UNSIGNED NOT NULL,
    `subscription_id` INT UNSIGNED DEFAULT NULL,
    `status` ENUM('draft','pending','paid','overdue','cancelled','refunded') NOT NULL DEFAULT 'draft',
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 20.00,
    `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `credit_applied` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'EUR',
    `promo_code_id` INT UNSIGNED DEFAULT NULL,
    `due_date` DATE NOT NULL,
    `paid_at` DATETIME DEFAULT NULL,
    `payment_method` VARCHAR(50) DEFAULT NULL,
    `stripe_payment_intent_id` VARCHAR(255) DEFAULT NULL,
    `stripe_checkout_session_id` VARCHAR(255) DEFAULT NULL,
    `stripe_invoice_id` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `reminder_sent` INT NOT NULL DEFAULT 0,
    `last_reminder_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_subscription` (`subscription_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_due` (`due_date`),
    FOREIGN KEY (`user_id`) REFERENCES `wp_users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`subscription_id`) REFERENCES `wp_subscriptions`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wp_invoice_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT UNSIGNED NOT NULL,
    `description` VARCHAR(500) NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `total` DECIMAL(10,2) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`invoice_id`) REFERENCES `wp_invoices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wp_payments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT UNSIGNED DEFAULT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'EUR',
    `method` ENUM('stripe','bank_transfer','manual') NOT NULL DEFAULT 'stripe',
    `status` ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
    `stripe_payment_intent_id` VARCHAR(255) DEFAULT NULL,
    `stripe_charge_id` VARCHAR(255) DEFAULT NULL,
    `transaction_id` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_invoice` (`invoice_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`invoice_id`) REFERENCES `wp_invoices`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`user_id`) REFERENCES `wp_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SUPPORT TICKETS
-- ============================================

CREATE TABLE IF NOT EXISTS `wp_tickets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ticket_number` VARCHAR(20) NOT NULL UNIQUE,
    `user_id` INT UNSIGNED NOT NULL,
    `subscription_id` INT UNSIGNED DEFAULT NULL,
    `department` ENUM('technical','billing','sales','other') NOT NULL DEFAULT 'technical',
    `priority` ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    `subject` VARCHAR(255) NOT NULL,
    `status` ENUM('open','in_progress','waiting_client','waiting_admin','resolved','closed') NOT NULL DEFAULT 'open',
    `assigned_to` INT UNSIGNED DEFAULT NULL,
    `closed_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_priority` (`priority`),
    FOREIGN KEY (`user_id`) REFERENCES `wp_users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`subscription_id`) REFERENCES `wp_subscriptions`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`assigned_to`) REFERENCES `wp_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wp_ticket_messages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `message` TEXT NOT NULL,
    `is_admin_reply` TINYINT(1) NOT NULL DEFAULT 0,
    `attachments` JSON DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ticket` (`ticket_id`),
    FOREIGN KEY (`ticket_id`) REFERENCES `wp_tickets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `wp_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ACTIVITY LOG
-- ============================================

CREATE TABLE IF NOT EXISTS `wp_activity_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(50) DEFAULT NULL,
    `entity_id` INT UNSIGNED DEFAULT NULL,
    `details` JSON DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `wp_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- EMAIL TEMPLATES
-- ============================================

CREATE TABLE IF NOT EXISTS `wp_email_templates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `body_html` TEXT NOT NULL,
    `variables` JSON DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SETTINGS
-- ============================================

CREATE TABLE IF NOT EXISTS `wp_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- IP POOL
-- ============================================

CREATE TABLE IF NOT EXISTS `wp_ip_pool` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL UNIQUE,
    `type` ENUM('ipv4','ipv6') NOT NULL DEFAULT 'ipv4',
    `gateway` VARCHAR(45) DEFAULT NULL,
    `netmask` VARCHAR(45) DEFAULT NULL,
    `is_assigned` TINYINT(1) NOT NULL DEFAULT 0,
    `assigned_to_vps_id` INT UNSIGNED DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_available` (`is_assigned`, `type`),
    FOREIGN KEY (`assigned_to_vps_id`) REFERENCES `wp_services_vps`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- MONITORING
-- ============================================

CREATE TABLE IF NOT EXISTS `wp_monitors` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `kuma_monitor_id` INT DEFAULT NULL,
    `name` VARCHAR(255) NOT NULL,
    `type` VARCHAR(50) NOT NULL DEFAULT 'http',
    `url` VARCHAR(500) DEFAULT NULL,
    `related_product_type` ENUM('vps','hosting','navidrome','infrastructure') DEFAULT 'infrastructure',
    `status` ENUM('up','down','pending','maintenance') NOT NULL DEFAULT 'pending',
    `uptime_24h` DECIMAL(5,2) DEFAULT NULL,
    `uptime_30d` DECIMAL(5,2) DEFAULT NULL,
    `last_check` DATETIME DEFAULT NULL,
    `is_public` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wp_incidents` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `monitor_id` INT UNSIGNED DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `severity` ENUM('minor','major','critical') NOT NULL DEFAULT 'minor',
    `status` ENUM('investigating','identified','monitoring','resolved') NOT NULL DEFAULT 'investigating',
    `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resolved_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`monitor_id`) REFERENCES `wp_monitors`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PROMO CODES
-- ============================================

CREATE TABLE IF NOT EXISTS `wp_promo_codes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `description` VARCHAR(255) DEFAULT NULL,
    `type` ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
    `value` DECIMAL(10,2) NOT NULL,
    `min_order_amount` DECIMAL(10,2) DEFAULT NULL,
    `max_discount` DECIMAL(10,2) DEFAULT NULL,
    `usage_limit` INT DEFAULT NULL,
    `usage_limit_per_user` INT DEFAULT 1,
    `used_count` INT NOT NULL DEFAULT 0,
    `applicable_products` JSON DEFAULT NULL,
    `applicable_billing_cycles` JSON DEFAULT NULL,
    `valid_from` DATETIME DEFAULT NULL,
    `valid_to` DATETIME DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_code` (`code`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_valid` (`valid_from`, `valid_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wp_promo_code_usage` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `promo_code_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `invoice_id` INT UNSIGNED DEFAULT NULL,
    `discount_amount` DECIMAL(10,2) NOT NULL,
    `used_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_code` (`promo_code_id`),
    INDEX `idx_user` (`user_id`),
    FOREIGN KEY (`promo_code_id`) REFERENCES `wp_promo_codes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `wp_users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`invoice_id`) REFERENCES `wp_invoices`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- GIFT CARDS
-- ============================================

CREATE TABLE IF NOT EXISTS `wp_gift_cards` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `initial_amount` DECIMAL(10,2) NOT NULL,
    `balance` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'EUR',
    `purchaser_user_id` INT UNSIGNED DEFAULT NULL,
    `recipient_email` VARCHAR(255) DEFAULT NULL,
    `recipient_name` VARCHAR(255) DEFAULT NULL,
    `message` TEXT DEFAULT NULL,
    `redeemed_by_user_id` INT UNSIGNED DEFAULT NULL,
    `status` ENUM('active','used','expired','cancelled') NOT NULL DEFAULT 'active',
    `purchased_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `redeemed_at` DATETIME DEFAULT NULL,
    `expires_at` DATE DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_code` (`code`),
    INDEX `idx_status` (`status`),
    INDEX `idx_purchaser` (`purchaser_user_id`),
    INDEX `idx_redeemed` (`redeemed_by_user_id`),
    FOREIGN KEY (`purchaser_user_id`) REFERENCES `wp_users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`redeemed_by_user_id`) REFERENCES `wp_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wp_credit_transactions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `type` ENUM('credit','debit') NOT NULL,
    `source` ENUM('gift_card','manual','refund') NOT NULL,
    `reference_id` INT UNSIGNED DEFAULT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `wp_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DEFAULT DATA
-- ============================================

INSERT INTO `wp_settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'WebPanel'),
('site_url', 'http://localhost'),
('company_name', 'My Company'),
('company_email', 'contact@example.com'),
('company_phone', ''),
('company_address', ''),
('company_siret', ''),
('company_tva', ''),
('currency', 'EUR'),
('currency_symbol', '€'),
('tax_rate', '20'),
('invoice_prefix', 'INV-'),
('invoice_next_number', '1'),
('stripe_public_key', ''),
('stripe_secret_key', ''),
('stripe_webhook_secret', ''),
('proxmox_host', ''),
('proxmox_port', '8006'),
('proxmox_user', 'root@pam'),
('proxmox_token_id', ''),
('proxmox_token_secret', ''),
('proxmox_default_node', 'pve'),
('proxmox_default_storage', 'local-lvm'),
('proxmox_default_bridge', 'vmbr0'),
('proxmox_vmid_start', '200'),
('cyberpanel_url', ''),
('cyberpanel_admin_user', 'admin'),
('cyberpanel_admin_pass', ''),
('navidrome_url', ''),
('navidrome_admin_user', ''),
('navidrome_admin_pass', ''),
('uptime_kuma_url', ''),
('uptime_kuma_api_key', ''),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_user', ''),
('smtp_pass', ''),
('smtp_encryption', 'tls'),
('smtp_from_email', ''),
('smtp_from_name', 'WebPanel'),
('payment_grace_days', '7'),
('suspension_grace_days', '14'),
('deletion_grace_days', '30'),
('reminder_days_before', '3'),
('primary_color', '#4F46E5'),
('logo_url', ''),
('terms_url', ''),
('privacy_url', ''),
('maintenance_mode', '0');

INSERT INTO `wp_email_templates` (`slug`, `name`, `subject`, `body_html`, `variables`) VALUES
('welcome', 'Bienvenue', 'Bienvenue sur {{site_name}}', '<h2>Bienvenue {{first_name}} !</h2><p>Votre compte a bien ete cree sur {{site_name}}.</p><p><a href="{{login_url}}">Acceder a mon espace</a></p>', '["first_name","last_name","email","site_name","login_url"]'),
('email_verification', 'Verification email', 'Verifiez votre adresse email', '<h2>Bonjour {{first_name}},</h2><p>Veuillez verifier votre adresse email :</p><p><a href="{{verify_url}}">Verifier mon email</a></p>', '["first_name","verify_url","site_name"]'),
('password_reset', 'Reinitialisation mot de passe', 'Reinitialisation de votre mot de passe', '<h2>Bonjour {{first_name}},</h2><p>Cliquez sur le lien ci-dessous pour reinitialiser votre mot de passe :</p><p><a href="{{reset_url}}">Reinitialiser</a></p><p>Ce lien expire dans 1 heure.</p>', '["first_name","reset_url","site_name"]'),
('invoice_created', 'Nouvelle facture', 'Facture {{invoice_number}} - {{site_name}}', '<h2>Bonjour {{first_name}},</h2><p>Facture : {{invoice_number}}</p><p>Montant : {{total}} {{currency}}</p><p>Echeance : {{due_date}}</p><p><a href="{{invoice_url}}">Voir et payer</a></p>', '["first_name","invoice_number","total","currency","due_date","invoice_url","site_name"]'),
('invoice_paid', 'Paiement confirme', 'Paiement confirme - Facture {{invoice_number}}', '<h2>Bonjour {{first_name}},</h2><p>Paiement recu pour la facture {{invoice_number}}.</p><p>Montant : {{total}} {{currency}}</p>', '["first_name","invoice_number","total","currency","site_name"]'),
('payment_reminder', 'Rappel de paiement', 'Rappel : Facture {{invoice_number}} en attente', '<h2>Bonjour {{first_name}},</h2><p>La facture {{invoice_number}} de {{total}} {{currency}} est en attente.</p><p>Echeance : {{due_date}}</p><p><a href="{{invoice_url}}">Payer maintenant</a></p>', '["first_name","invoice_number","total","currency","due_date","invoice_url","site_name"]'),
('service_suspended', 'Service suspendu', 'Votre service a ete suspendu', '<h2>Bonjour {{first_name}},</h2><p>Votre service <strong>{{service_name}}</strong> a ete suspendu pour impaye.</p><p><a href="{{invoice_url}}">Regulariser</a></p>', '["first_name","service_name","invoice_url","site_name"]'),
('service_terminated', 'Service supprime', 'Votre service a ete supprime', '<h2>Bonjour {{first_name}},</h2><p>Votre service <strong>{{service_name}}</strong> a ete supprime suite au non-paiement.</p>', '["first_name","service_name","site_name"]'),
('service_created', 'Service active', 'Votre service {{service_name}} est pret !', '<h2>Bonjour {{first_name}},</h2><p>Votre service <strong>{{service_name}}</strong> est actif.</p><p>{{service_details}}</p><p><a href="{{dashboard_url}}">Mon espace client</a></p>', '["first_name","service_name","service_details","dashboard_url","site_name"]'),
('ticket_reply', 'Reponse ticket', 'Reponse a votre ticket #{{ticket_number}}', '<h2>Bonjour {{first_name}},</h2><p>Nouvelle reponse sur le ticket #{{ticket_number}} :</p><p>{{message}}</p><p><a href="{{ticket_url}}">Voir le ticket</a></p>', '["first_name","ticket_number","subject","message","ticket_url","site_name"]');

INSERT INTO `wp_os_templates` (`name`, `slug`, `proxmox_template`, `icon`, `category`, `sort_order`) VALUES
('Debian 12', 'debian-12', 'local:vztmpl/debian-12-standard_12.2-1_amd64.tar.zst', 'debian', 'linux', 1),
('Ubuntu 22.04', 'ubuntu-2204', 'local:vztmpl/ubuntu-22.04-standard_22.04-1_amd64.tar.zst', 'ubuntu', 'linux', 2),
('Ubuntu 24.04', 'ubuntu-2404', 'local:vztmpl/ubuntu-24.04-standard_24.04-1_amd64.tar.zst', 'ubuntu', 'linux', 3),
('AlmaLinux 9', 'alma-9', 'local:vztmpl/almalinux-9-default_20230607_amd64.tar.xz', 'almalinux', 'linux', 4),
('Rocky Linux 9', 'rocky-9', 'local:vztmpl/rockylinux-9-default_20230629_amd64.tar.xz', 'rocky', 'linux', 5),
('Alpine 3.19', 'alpine-319', 'local:vztmpl/alpine-3.19-default_20240207_amd64.tar.xz', 'alpine', 'linux', 6);

SET FOREIGN_KEY_CHECKS = 1;
