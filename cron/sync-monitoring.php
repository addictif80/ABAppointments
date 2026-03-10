<?php
/**
 * WebPanel - Cron: Sync monitoring data from Uptime Kuma
 * Run every 5 min: */5 * * * * php /path/to/cron/sync-monitoring.php
 */
require_once __DIR__ . '/../core/App.php';

echo "[" . date('Y-m-d H:i:s') . "] Monitoring sync started\n";

$kuma = new UptimeKumaAPI();
if ($kuma->isConfigured()) {
    if ($kuma->syncMonitors()) {
        echo "  Monitors synced successfully\n";
    } else {
        echo "  Sync failed\n";
    }
} else {
    echo "  Uptime Kuma not configured, skipping\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Done\n";
