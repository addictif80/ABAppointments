<?php
/**
 * ABAppointments - Cron: Monthly URSSAF revenue declaration
 *
 * Generates a PDF with the previous month's transactions and total revenue
 * (chiffre d'affaires), then emails it to the configured recipient as a
 * reminder to declare income on autoentrepreneur.urssaf.fr.
 *
 * Schedule this cron to run once daily (the script checks the configured
 * day of month itself, e.g. 5th of each month):
 * 0 8 * * * php /path/to/ABAppointments/cron/urssaf-declaration.php
 */

require_once __DIR__ . '/../core/App.php';

$db = Database::getInstance();

if (ab_setting('urssaf_enabled', '0') !== '1') {
    echo date('Y-m-d H:i:s') . " - Déclaration URSSAF désactivée dans les paramètres, rien à faire.\n";
    exit;
}

$declarationDay = (int) ab_setting('urssaf_declaration_day', '5');
$today = (int) date('j');
if ($declarationDay < 1) $declarationDay = 1;
if ($declarationDay > 28) $declarationDay = 28;

if ($today < $declarationDay) {
    echo date('Y-m-d H:i:s') . " - Jour configuré ($declarationDay) pas encore atteint, rien à faire.\n";
    exit;
}

// Declares the previous calendar month's revenue.
$periodTimestamp = strtotime('first day of last month');
$year = (int) date('Y', $periodTimestamp);
$month = (int) date('n', $periodTimestamp);

$existing = $db->fetchOne(
    "SELECT id, sent_at FROM ab_urssaf_declarations WHERE period_year = ? AND period_month = ?",
    [$year, $month]
);
if ($existing && $existing['sent_at']) {
    echo date('Y-m-d H:i:s') . " - Déclaration déjà envoyée pour $month/$year.\n";
    exit;
}

$recipient = ab_setting('urssaf_recipient_email') ?: ab_setting('business_email');
if (empty($recipient)) {
    echo date('Y-m-d H:i:s') . " - Aucun destinataire configuré pour la déclaration URSSAF.\n";
    exit;
}

$report = new UrssafReport();
$data = $report->getData($year, $month);
$pdfPath = $report->generateAndStore($year, $month);

$mailer = new Mailer();
$sent = $mailer->sendTemplate('urssaf_declaration', $recipient, [
    'business_name' => ab_setting('business_name'),
    'period_label' => $data['label'],
    'appointment_count' => (string) $data['count'],
    'total_amount' => ab_format_price($data['total']),
], '', [[
    'filename' => basename($pdfPath),
    'content' => file_get_contents($pdfPath),
    'mime' => 'application/pdf',
]]);

$recordData = [
    'total_amount' => $data['total'],
    'appointment_count' => $data['count'],
    'recipient_email' => $recipient,
    'pdf_filename' => basename($pdfPath),
];

if ($existing) {
    $recordData['sent_at'] = $sent ? date('Y-m-d H:i:s') : null;
    $db->update('ab_urssaf_declarations', $recordData, 'id = ?', [$existing['id']]);
} else {
    $recordData['period_year'] = $year;
    $recordData['period_month'] = $month;
    $recordData['sent_at'] = $sent ? date('Y-m-d H:i:s') : null;
    $db->insert('ab_urssaf_declarations', $recordData);
}

if ($sent) {
    echo date('Y-m-d H:i:s') . " - Déclaration URSSAF pour $month/$year envoyée à $recipient.\n";
} else {
    echo date('Y-m-d H:i:s') . " - Échec d'envoi de la déclaration URSSAF pour $month/$year : " . $mailer->getLastError() . "\n";
}
