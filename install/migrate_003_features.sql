-- Migration: Add provider welcome message, visibility toggle, and announcement settings
-- Run this on existing databases to apply the new features

-- Add welcome_message and is_visible_booking columns to ab_users
ALTER TABLE `ab_users`
    ADD COLUMN `welcome_message` TEXT COMMENT 'Message affiché aux clients lors de la sélection' AFTER `timezone`,
    ADD COLUMN `is_visible_booking` TINYINT(1) DEFAULT 1 COMMENT 'Visible dans le processus de réservation' AFTER `welcome_message`;

-- Add announcement settings
INSERT IGNORE INTO `ab_settings` (`setting_key`, `setting_value`) VALUES
('booking_announcement', ''),
('admin_announcement', '');
