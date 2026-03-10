<?php
/**
 * WebPanel - API Endpoints
 */
require_once __DIR__ . '/../core/App.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = Database::getInstance();

try {
    switch ($action) {
        case 'products':
            $type = $_GET['type'] ?? null;
            $where = 'is_active = 1';
            $params = [];
            if ($type) { $where .= ' AND type = ?'; $params[] = $type; }
            $products = $db->fetchAll("SELECT id, type, name, slug, description, features, price_monthly, price_yearly, setup_fee, proxmox_cores, proxmox_ram_mb, proxmox_disk_gb, proxmox_bandwidth_gb, hosting_disk_mb, hosting_bandwidth_mb, hosting_email_accounts, hosting_databases, hosting_domains, navidrome_storage_mb, navidrome_max_playlists FROM wp_products WHERE $where ORDER BY type, sort_order", $params);
            foreach ($products as &$p) { $p['features'] = json_decode($p['features'] ?? '[]', true); }
            wp_json(['success' => true, 'data' => $products]);
            break;

        case 'status':
            $monitors = $db->fetchAll("SELECT name, status, uptime_24h, uptime_30d, last_check FROM wp_monitors WHERE is_public = 1 ORDER BY name");
            $incidents = $db->fetchAll("SELECT title, description, severity, status, started_at, resolved_at FROM wp_incidents WHERE status != 'resolved' OR resolved_at > DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY created_at DESC LIMIT 10");
            wp_json(['success' => true, 'monitors' => $monitors, 'incidents' => $incidents]);
            break;

        case 'vnc':
            Auth::requireClient();
            $vpsId = (int)($_GET['vps_id'] ?? 0);
            $vps = $db->fetchOne("SELECT v.*, s.user_id FROM wp_services_vps v JOIN wp_subscriptions s ON v.subscription_id = s.id WHERE v.id = ? AND s.user_id = ?", [$vpsId, $_SESSION['user_id']]);
            if (!$vps) wp_json(['error' => 'VPS introuvable'], 404);
            $proxmox = new ProxmoxAPI();
            $vnc = $proxmox->getVNCWebSocket($vps['proxmox_node'], $vps['proxmox_vmid']);
            wp_json(['success' => true, 'data' => $vnc]);
            break;

        case 'vps-status':
            Auth::requireClient();
            $subId = (int)($_GET['subscription_id'] ?? 0);
            $vps = $db->fetchOne("SELECT v.* FROM wp_services_vps v JOIN wp_subscriptions s ON v.subscription_id = s.id WHERE v.subscription_id = ? AND s.user_id = ?", [$subId, $_SESSION['user_id']]);
            if (!$vps || !$vps['proxmox_vmid']) wp_json(['error' => 'VPS introuvable'], 404);
            try {
                $proxmox = new ProxmoxAPI();
                $status = $proxmox->getVMStatus($vps['proxmox_node'], $vps['proxmox_vmid']);
                wp_json(['success' => true, 'status' => $status['status'] ?? 'unknown', 'data' => $status]);
            } catch (Exception $e) {
                wp_json(['success' => true, 'status' => $vps['status']]);
            }
            break;

        default:
            wp_json(['error' => 'Action invalide'], 400);
    }
} catch (Exception $e) {
    wp_json(['error' => $e->getMessage()], 500);
}
