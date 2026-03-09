-- Migration: Add CalDAV sync support per provider
-- Run this on existing databases to add CalDAV calendar synchronization

CREATE TABLE IF NOT EXISTS `ab_caldav_sync` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `provider_id` INT UNSIGNED NOT NULL,
  `caldav_url` VARCHAR(500) DEFAULT NULL COMMENT 'URL complète du calendrier CalDAV',
  `caldav_username` VARCHAR(255) DEFAULT NULL,
  `caldav_password` VARCHAR(500) DEFAULT NULL,
  `sync_enabled` TINYINT(1) DEFAULT 0,
  `last_sync` DATETIME DEFAULT NULL,
  FOREIGN KEY (`provider_id`) REFERENCES `ab_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
