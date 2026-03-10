<?php
/**
 * WebPanel - Cron: Send payment reminders
 * Run daily: 0 9 * * * php /path/to/cron/reminders.php
 */
require_once __DIR__ . '/../core/App.php';

$db = Database::getInstance();
$mailer = new Mailer();

echo "[" . date('Y-m-d H:i:s') . "] Payment reminder cron started\n";

$reminderDays = (int)wp_setting('reminder_days_before', 3);

// Remind about upcoming due dates
$upcomingInvoices = $db->fetchAll(
    "SELECT i.*, u.email, u.first_name
     FROM wp_invoices i JOIN wp_users u ON i.user_id = u.id
     WHERE i.status = 'pending'
     AND i.due_date = DATE_ADD(CURDATE(), INTERVAL ? DAY)
     AND i.reminder_sent = 0",
    [$reminderDays]
);

$count = 0;
foreach ($upcomingInvoices as $inv) {
    $sent = $mailer->sendTemplate($inv['email'], 'payment_reminder', [
        'first_name' => $inv['first_name'],
        'invoice_number' => $inv['invoice_number'],
        'total' => wp_format_price($inv['total']),
        'currency' => wp_setting('currency', 'EUR'),
        'due_date' => wp_format_date($inv['due_date']),
        'invoice_url' => wp_url("client/?page=invoice-pay&id={$inv['id']}")
    ]);

    if ($sent) {
        $db->update('wp_invoices', [
            'reminder_sent' => $inv['reminder_sent'] + 1,
            'last_reminder_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$inv['id']]);
        $count++;
    }
}

echo "  Upcoming reminders sent: $count\n";

// Remind about overdue invoices (every 3 days)
$overdueInvoices = $db->fetchAll(
    "SELECT i.*, u.email, u.first_name
     FROM wp_invoices i JOIN wp_users u ON i.user_id = u.id
     WHERE i.status = 'overdue'
     AND (i.last_reminder_at IS NULL OR i.last_reminder_at < DATE_SUB(NOW(), INTERVAL 3 DAY))
     AND i.reminder_sent < 5"
);

$overdueCount = 0;
foreach ($overdueInvoices as $inv) {
    $sent = $mailer->sendTemplate($inv['email'], 'payment_reminder', [
        'first_name' => $inv['first_name'],
        'invoice_number' => $inv['invoice_number'],
        'total' => wp_format_price($inv['total']),
        'currency' => wp_setting('currency', 'EUR'),
        'due_date' => wp_format_date($inv['due_date']),
        'invoice_url' => wp_url("client/?page=invoice-pay&id={$inv['id']}")
    ]);

    if ($sent) {
        $db->update('wp_invoices', [
            'reminder_sent' => $inv['reminder_sent'] + 1,
            'last_reminder_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$inv['id']]);
        $overdueCount++;
    }
}

echo "  Overdue reminders sent: $overdueCount\n";
echo "[" . date('Y-m-d H:i:s') . "] Done\n";
