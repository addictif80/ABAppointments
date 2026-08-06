INSERT INTO `ab_email_templates` (`slug`, `name`, `subject`, `body`, `variables`) VALUES
('ticket_recovery', 'Récupération des liens de tickets', 'Vos tickets de support - {business_name}',
'<h2>Bonjour {customer_name},</h2>
<p>Voici vos tickets de support en cours :</p>
{tickets_list}
<p>Cordialement,<br>{business_name}</p>',
'["customer_name","tickets_list","business_name"]')
ON DUPLICATE KEY UPDATE `slug` = `slug`;
