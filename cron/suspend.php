<?php
/**
 * WebPanel - Cron: Auto-suspend and terminate services for non-payment
 * Run daily: 0 6 * * * php /path/to/cron/suspend.php
 */
require_once __DIR__ . '/../core/App.php';

$db = Database::getInstance();

echo "[" . date('Y-m-d H:i:s') . "] Suspension cron started\n";

$suspensionDays = (int)wp_setting('suspension_grace_days', 14);
$deletionDays = (int)wp_setting('deletion_grace_days', 30);

// Auto-suspend: services with overdue invoices past grace period
$toSuspend = $db->fetchAll(
    "SELECT DISTINCT s.id, s.user_id
     FROM wp_subscriptions s
     JOIN wp_invoices i ON i.subscription_id = s.id
     WHERE s.status = 'active'
     AND i.status = 'overdue'
     AND i.due_date < DATE_SUB(CURDATE(), INTERVAL ? DAY)",
    [$suspensionDays]
);

$suspendCount = 0;
foreach ($toSuspend as $sub) {
    try {
        ServiceManager::suspendService($sub['id'], 'Impaye automatique');
        $suspendCount++;
        echo "  Suspended subscription #{$sub['id']}\n";
    } catch (Exception $e) {
        echo "  ERROR suspending #{$sub['id']}: {$e->getMessage()}\n";
    }
}
echo "  Services suspended: $suspendCount\n";

// Auto-terminate: suspended services past deletion grace period
$toTerminate = $db->fetchAll(
    "SELECT s.id FROM wp_subscriptions s
     WHERE s.status = 'suspended'
     AND s.suspended_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
    [$deletionDays - $suspensionDays]
);

$terminateCount = 0;
foreach ($toTerminate as $sub) {
    try {
        ServiceManager::terminateService($sub['id']);
        $terminateCount++;
        echo "  Terminated subscription #{$sub['id']}\n";
    } catch (Exception $e) {
        echo "  ERROR terminating #{$sub['id']}: {$e->getMessage()}\n";
    }
}
echo "  Services terminated: $terminateCount\n";

echo "[" . date('Y-m-d H:i:s') . "] Done\n";
