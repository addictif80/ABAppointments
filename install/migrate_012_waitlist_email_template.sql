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
