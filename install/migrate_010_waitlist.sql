CREATE TABLE `ab_waitlist` (
    `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `service_id`          INT UNSIGNED  NOT NULL,
    `provider_id`         INT UNSIGNED  NULL,
    `first_name`          VARCHAR(100)  NOT NULL,
    `last_name`           VARCHAR(100)  NOT NULL,
    `email`               VARCHAR(255)  NOT NULL,
    `phone`               VARCHAR(30)   NULL,
    `desired_date_start`  DATE          NOT NULL,
    `desired_date_end`    DATE          NOT NULL,
    `status`              ENUM('waiting','notified','booked','cancelled') NOT NULL DEFAULT 'waiting',
    `notified_at`         DATETIME      NULL,
    `created_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`service_id`) REFERENCES `ab_services`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`provider_id`) REFERENCES `ab_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
