ALTER TABLE `ab_working_hours`
    ADD COLUMN `single_appointment_per_day` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`;

CREATE TABLE IF NOT EXISTS `ab_day_exceptions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `provider_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL = tous les prestataires',
  `exception_date` DATE NOT NULL,
  `mode` ENUM('normal', 'single_appointment', 'closed') NOT NULL DEFAULT 'normal',
  `note` VARCHAR(200) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `provider_date` (`provider_id`, `exception_date`),
  FOREIGN KEY (`provider_id`) REFERENCES `ab_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
