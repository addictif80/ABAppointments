ALTER TABLE `ab_appointments`
    ADD COLUMN `needs_guardian`         TINYINT(1)   NOT NULL DEFAULT 0 AFTER `notes`,
    ADD COLUMN `guardian_first_name`    VARCHAR(100) NULL AFTER `needs_guardian`,
    ADD COLUMN `guardian_last_name`     VARCHAR(100) NULL AFTER `guardian_first_name`,
    ADD COLUMN `guardian_phone`         VARCHAR(30)  NULL AFTER `guardian_last_name`,
    ADD COLUMN `guardian_email`         VARCHAR(255) NULL AFTER `guardian_phone`,
    ADD COLUMN `guardian_token`         VARCHAR(64)  NULL AFTER `guardian_email`,
    ADD COLUMN `guardian_token_expires` DATETIME     NULL AFTER `guardian_token`,
    ADD COLUMN `guardian_confirmed_at`  DATETIME     NULL AFTER `guardian_token_expires`,
    ADD INDEX  `idx_guardian_token`     (`guardian_token`);
