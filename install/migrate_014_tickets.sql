CREATE TABLE `ab_tickets` (
    `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `hash`                  VARCHAR(64)  NOT NULL,
    `customer_id`           INT UNSIGNED NOT NULL,
    `subject`               VARCHAR(255) NOT NULL,
    `category`              ENUM('commercial','technique') NOT NULL,
    `assigned_provider_id`  INT UNSIGNED NULL,
    `status`                ENUM('open','resolved','closed') NOT NULL DEFAULT 'open',
    `created_by`            ENUM('customer','staff') NOT NULL DEFAULT 'customer',
    `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `hash` (`hash`),
    FOREIGN KEY (`customer_id`) REFERENCES `ab_customers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_provider_id`) REFERENCES `ab_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ab_ticket_messages` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ticket_id`       INT UNSIGNED NOT NULL,
    `sender_type`     ENUM('customer','staff') NOT NULL,
    `sender_user_id`  INT UNSIGNED NULL,
    `body`            TEXT NOT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`ticket_id`) REFERENCES `ab_tickets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`sender_user_id`) REFERENCES `ab_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ab_ticket_attachments` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `message_id`          INT UNSIGNED NOT NULL,
    `original_filename`   VARCHAR(255) NOT NULL,
    `stored_filename`     VARCHAR(255) NOT NULL,
    `mime_type`           VARCHAR(150) NOT NULL,
    `size_bytes`          INT UNSIGNED NOT NULL,
    `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`message_id`) REFERENCES `ab_ticket_messages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `ab_email_templates` (`slug`, `name`, `subject`, `body`, `variables`) VALUES
('ticket_created_customer', 'Ticket ouvert (confirmation client)', 'Votre demande de support a été enregistrée - {business_name}',
'<h2>Bonjour {customer_name},</h2>
<p>Votre demande de support a bien été enregistrée :</p>
<table style="border-collapse:collapse;width:100%;max-width:500px;">
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Sujet</td><td style="padding:8px;border:1px solid #ddd;">{subject}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Catégorie</td><td style="padding:8px;border:1px solid #ddd;">{category_label}</td></tr>
</table>
<p>Vous pouvez suivre et répondre à ce ticket à tout moment :<br><a href="{ticket_url}">Voir mon ticket</a></p>
<p>Nous reviendrons vers vous dans les meilleurs délais.</p>
<p>Cordialement,<br>{business_name}</p>',
'["customer_name","subject","category_label","ticket_url","business_name"]'),

('ticket_created_staff', 'Nouveau ticket (notification prestataire/admin)', 'Nouveau ticket de support - {subject}',
'<h2>Nouveau ticket de support</h2>
<table style="border-collapse:collapse;width:100%;max-width:500px;">
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Client</td><td style="padding:8px;border:1px solid #ddd;">{customer_name}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Sujet</td><td style="padding:8px;border:1px solid #ddd;">{subject}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Catégorie</td><td style="padding:8px;border:1px solid #ddd;">{category_label}</td></tr>
</table>
<p>{message_excerpt}</p>
<p><a href="{admin_url}" style="background:#e91e63;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold;">Voir le ticket</a></p>',
'["customer_name","subject","category_label","message_excerpt","admin_url"]'),

('ticket_reply_customer', 'Nouvelle réponse (notification client)', 'Nouvelle réponse à votre ticket - {subject}',
'<h2>Bonjour {customer_name},</h2>
<p>Vous avez reçu une nouvelle réponse concernant votre ticket "{subject}" :</p>
<blockquote style="margin:12px 0;padding:10px 15px;background:#f8f9fa;border-left:3px solid #e91e63;color:#555;">{message_excerpt}</blockquote>
<p><a href="{ticket_url}">Voir la conversation et répondre</a></p>
<p>Cordialement,<br>{business_name}</p>',
'["customer_name","subject","message_excerpt","ticket_url","business_name"]'),

('ticket_reply_staff', 'Nouvelle réponse client (notification prestataire/admin)', 'Le client a répondu - {subject}',
'<h2>Nouvelle réponse du client</h2>
<p><strong>{customer_name}</strong> a répondu au ticket "{subject}" :</p>
<blockquote style="margin:12px 0;padding:10px 15px;background:#f8f9fa;border-left:3px solid #e91e63;color:#555;">{message_excerpt}</blockquote>
<p><a href="{admin_url}" style="background:#e91e63;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold;">Voir et répondre</a></p>',
'["customer_name","subject","message_excerpt","admin_url"]')
ON DUPLICATE KEY UPDATE `slug` = `slug`;
