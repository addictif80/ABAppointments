CREATE TABLE `ab_reviews` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `appointment_id` INT UNSIGNED  NOT NULL,
    `rating`         TINYINT UNSIGNED NOT NULL,
    `comment`        TEXT          NULL,
    `status`         ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `submitted_at`   DATETIME      NULL,
    `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `appointment_id` (`appointment_id`),
    FOREIGN KEY (`appointment_id`) REFERENCES `ab_appointments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
