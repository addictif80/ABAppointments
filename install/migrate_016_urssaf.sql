-- --------------------------------------------------------
-- Migration: déclaration mensuelle de chiffre d'affaires (URSSAF)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ab_urssaf_declarations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `period_year` SMALLINT UNSIGNED NOT NULL,
  `period_month` TINYINT UNSIGNED NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `appointment_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `recipient_email` VARCHAR(255) DEFAULT NULL,
  `pdf_filename` VARCHAR(255) DEFAULT NULL,
  `sent_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `period` (`period_year`, `period_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ab_email_templates` (`slug`, `name`, `subject`, `body`, `variables`) VALUES
('urssaf_declaration', 'Rappel déclaration URSSAF (chiffre d\'affaires mensuel)', 'Déclaration URSSAF à faire - {period_label}',
'<h2>Chiffre d\'affaires - {period_label}</h2>
<p>Bonjour,</p>
<p>Voici le récapitulatif du chiffre d\'affaires encaissé via {business_name} pour <strong>{period_label}</strong> :</p>
<table style="width:100%;border-collapse:collapse;margin:15px 0;">
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Nombre de rendez-vous</td><td style="padding:8px;border:1px solid #ddd;">{appointment_count}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Chiffre d\'affaires du mois</td><td style="padding:8px;border:1px solid #ddd;">{total_amount}</td></tr>
</table>
<p>Le détail des transactions est disponible dans le PDF joint à cet email.</p>
<p><strong>Pensez à effectuer votre déclaration de chiffre d\'affaires sur autoentrepreneur.urssaf.fr avant la date limite applicable à votre échéance.</strong></p>
<p>Cordialement,<br>{business_name}</p>',
'["business_name","period_label","appointment_count","total_amount"]')
ON DUPLICATE KEY UPDATE `slug` = `slug`;
