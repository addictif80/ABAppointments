CREATE TABLE `ab_booking_options` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(200)    NOT NULL,
    `description` TEXT            NULL,
    `price_type`  ENUM('fixed','percentage') NOT NULL DEFAULT 'fixed',
    `price_value` DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    `is_active`   TINYINT(1)     NOT NULL DEFAULT 1,
    `sort_order`  INT             NOT NULL DEFAULT 0,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ab_booking_option_services` (
    `option_id`  INT UNSIGNED NOT NULL,
    `service_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`option_id`, `service_id`),
    FOREIGN KEY (`option_id`)  REFERENCES `ab_booking_options`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`service_id`) REFERENCES `ab_services`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ab_appointment_options` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `appointment_id` INT UNSIGNED  NOT NULL,
    `option_id`      INT UNSIGNED  NULL,
    `option_name`    VARCHAR(200)  NOT NULL,
    `option_price`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`appointment_id`) REFERENCES `ab_appointments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
