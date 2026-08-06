ALTER TABLE `ab_customers`
    ADD COLUMN `dob_token`         VARCHAR(64)  NULL AFTER `date_of_birth`,
    ADD COLUMN `dob_token_expires` DATETIME     NULL AFTER `dob_token`,
    ADD INDEX  `idx_dob_token`     (`dob_token`);
