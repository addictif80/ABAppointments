INSERT INTO `ab_email_templates` (`slug`, `name`, `subject`, `body`, `variables`) VALUES
('review_request', 'Demande d''avis', 'Votre avis nous intéresse - {business_name}',
'<h2>Bonjour {customer_name},</h2>
<p>Nous espérons que votre rendez-vous du {appointment_date} pour "{service_name}" s''est bien passé !</p>
<p>Votre avis compte beaucoup pour nous, auriez-vous une minute pour le partager ?</p>
<p><a href="{review_url}" style="background:#e91e63;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold;">Laisser un avis</a></p>
<p>Cordialement,<br>{business_name}</p>',
'["customer_name","service_name","appointment_date","review_url","business_name"]')
ON DUPLICATE KEY UPDATE `slug` = `slug`;
