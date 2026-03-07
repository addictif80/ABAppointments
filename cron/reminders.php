<?php
/**
 * ABAppointments - Cron: Send appointment reminders
 *
 * Schedule this cron to run once daily (e.g., at 8:00 AM):
 * 0 8 * * * php /path/to/ABAppointments/cron/reminders.php
 */

require_once __DIR__ . '/../core/App.php';

$db = Database::getInstance();
$mailer = new Mailer();

// Get tomorrow's appointments
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$appointments = $db->fetchAll(
    "SELECT a.*, c.first_name as cf, c.last_name as cl, c.email as ce,
            s.name as sn, s.duration as sd
     FROM ab_appointments a
     JOIN ab_customers c ON a.customer_id = c.id
     JOIN ab_services s ON a.service_id = s.id
     WHERE DATE(a.start_datetime) = ? AND a.status IN ('confirmed', 'pending')",
    [$tomorrow]
);

$sent = 0;
foreach ($appointments as $a) {
    $result = $mailer->sendTemplate('appointment_reminder', $a['ce'], [
        'customer_name' => $a['cf'] . ' ' . $a['cl'],
        'service_name' => $a['sn'],
        'appointment_date' => ab_format_date($a['start_datetime']),
        'appointment_time' => ab_format_time($a['start_datetime']),
        'manage_url' => ab_url('manage/' . $a['hash']),
        'business_name' => ab_setting('business_name'),
    ]);
    if ($result) $sent++;
}

echo date('Y-m-d H:i:s') . " - $sent rappel(s) envoyé(s) sur " . count($appointments) . " rendez-vous.\n";
