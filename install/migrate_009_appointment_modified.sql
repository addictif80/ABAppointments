INSERT INTO `ab_email_templates` (`slug`, `name`, `subject`, `body`, `variables`) VALUES
('appointment_modified', 'Modification de rendez-vous', 'Modification de votre rendez-vous - {business_name}',
'<h2>Bonjour {customer_name},</h2>
<p>Votre rendez-vous pour la prestation "{service_name}" a été modifié par {business_name}.</p>
<table style="border-collapse:collapse;width:100%;max-width:500px;">
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Ancienne date</td><td style="padding:8px;border:1px solid #ddd;">{old_date} à {old_time}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Nouvelle date</td><td style="padding:8px;border:1px solid #ddd;">{appointment_date}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Nouvelle heure</td><td style="padding:8px;border:1px solid #ddd;">{appointment_time}</td></tr>
</table>
<p>Pour consulter ou annuler votre rendez-vous :<br><a href="{manage_url}">Gérer mon rendez-vous</a></p>
<p>Cordialement,<br>{business_name}</p>',
'["customer_name","service_name","old_date","old_time","appointment_date","appointment_time","manage_url","business_name"]')
ON DUPLICATE KEY UPDATE `slug` = `slug`;
