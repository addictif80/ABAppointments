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

INSERT INTO `ab_email_templates` (`slug`, `name`, `subject`, `body`, `variables`) VALUES
('waitlist_slot_available', 'Créneau disponible (liste d''attente)', 'Un créneau s''est libéré - {business_name}',
'<h2>Bonjour {customer_name},</h2>
<p>Bonne nouvelle ! Un créneau s''est libéré pour la prestation que vous attendiez :</p>
<table style="border-collapse:collapse;width:100%;max-width:500px;">
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Prestation</td><td style="padding:8px;border:1px solid #ddd;">{service_name}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Date</td><td style="padding:8px;border:1px solid #ddd;">{appointment_date}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Prestataire</td><td style="padding:8px;border:1px solid #ddd;">{provider_name}</td></tr>
</table>
<p>Réservez vite, ce créneau peut être repris à tout moment :<br><a href="{booking_url}">Réserver ce créneau</a></p>
<p>Cordialement,<br>{business_name}</p>',
'["customer_name","service_name","appointment_date","provider_name","booking_url","business_name"]')
ON DUPLICATE KEY UPDATE `slug` = `slug`;
