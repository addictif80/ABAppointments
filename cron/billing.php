<?php
/**
 * WebPanel - Cron: Billing (generate renewal invoices)
 * Run daily: 0 2 * * * php /path/to/cron/billing.php
 */
require_once __DIR__ . '/../core/App.php';

$db = Database::getInstance();

echo "[" . date('Y-m-d H:i:s') . "] Billing cron started\n";

// Mark overdue invoices
InvoiceManager::checkOverdue();
echo "  Overdue invoices updated\n";

// Generate renewal invoices for subscriptions due soon
$reminderDays = (int)wp_setting('reminder_days_before', 3);
$subscriptions = $db->fetchAll(
    "SELECT s.* FROM wp_subscriptions s
     WHERE s.status = 'active'
     AND s.auto_renew = 1
     AND s.next_due_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
     AND NOT EXISTS (
         SELECT 1 FROM wp_invoices i
         WHERE i.subscription_id = s.id
         AND i.status IN ('pending','paid')
         AND i.due_date >= s.next_due_date
     )",
    [$reminderDays]
);

$count = 0;
foreach ($subscriptions as $sub) {
    try {
        InvoiceManager::generateRenewalInvoice($sub['id']);
        $count++;
    } catch (Exception $e) {
        echo "  ERROR for subscription #{$sub['id']}: {$e->getMessage()}\n";
    }
}

echo "  Renewal invoices generated: $count\n";
echo "[" . date('Y-m-d H:i:s') . "] Done\n";
