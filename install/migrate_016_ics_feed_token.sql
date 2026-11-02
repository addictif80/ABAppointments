INSERT INTO `ab_settings` (`setting_key`, `setting_value`) VALUES
('ics_feed_token', '')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;
