<?php
/**
 * WebPanel - Service Provisioning & Management
 */
class ServiceManager {

    public static function provisionService($subscriptionId) {
        $db = Database::getInstance();
        $sub = $db->fetchOne(
            "SELECT s.*, p.*, s.id as id, s.status as status, p.name as name FROM wp_subscriptions s JOIN wp_products p ON s.product_id = p.id WHERE s.id = ?",
            [$subscriptionId]
        );
        if (!$sub) throw new Exception("Subscription not found");

        $user = $db->fetchOne("SELECT * FROM wp_users WHERE id = ?", [$sub['user_id']]);

        switch ($sub['type']) {
            case 'vps':
                return self::provisionVPS($sub, $user);
            case 'hosting':
                return self::provisionHosting($sub, $user);
            case 'navidrome':
                return self::provisionNavidrome($sub, $user);
        }
    }

    private static function provisionVPS($sub, $user) {
        $db = Database::getInstance();
        $proxmox = new ProxmoxAPI();

        $hostname = 'vps-' . $sub['id'];
        $password = wp_generate_password(16);

        // Get OS template
        $osTemplate = $db->fetchOne("SELECT * FROM wp_os_templates WHERE id = ? AND is_active = 1",
            [$_POST['os_template_id'] ?? 1]);
        if (!$osTemplate) {
            $osTemplate = $db->fetchOne("SELECT * FROM wp_os_templates WHERE is_active = 1 ORDER BY sort_order LIMIT 1");
        }

        // Get available IP
        $ip = $db->fetchOne("SELECT * FROM wp_ip_pool WHERE is_assigned = 0 AND type = 'ipv4' LIMIT 1");
        $ipAddress = $ip ? $ip['ip_address'] : null;

        try {
            $result = $proxmox->provisionVPS($sub, $hostname, $osTemplate['proxmox_template'], $password, $ipAddress);

            $vpsId = $db->insert('wp_services_vps', [
                'subscription_id' => $sub['id'],
                'hostname' => $hostname,
                'proxmox_vmid' => $result['vmid'],
                'proxmox_node' => $result['node'],
                'ip_address' => $ipAddress,
                'os_template_id' => $osTemplate['id'],
                'root_password' => base64_encode(openssl_encrypt($password, 'AES-256-CBC', SECRET_KEY, 0, substr(md5(SECRET_KEY), 0, 16))),
                'status' => 'running',
                'cores' => $sub['proxmox_cores'] ?? 1,
                'ram_mb' => $sub['proxmox_ram_mb'] ?? 512,
                'disk_gb' => $sub['proxmox_disk_gb'] ?? 10,
                'bandwidth_gb' => $sub['proxmox_bandwidth_gb']
            ]);

            if ($ip) {
                $db->update('wp_ip_pool', ['is_assigned' => 1, 'assigned_to_vps_id' => $vpsId], 'id = ?', [$ip['id']]);
            }

            // Send service ready email
            $mailer = new Mailer();
            $mailer->sendTemplate($user['email'], 'service_created', [
                'first_name' => $user['first_name'],
                'service_name' => $sub['name'] . ' - ' . $hostname,
                'service_details' => "IP: $ipAddress | OS: {$osTemplate['name']} | CPU: {$sub['proxmox_cores']} vCPU | RAM: {$sub['proxmox_ram_mb']} MB | Disque: {$sub['proxmox_disk_gb']} GB",
                'dashboard_url' => wp_url('client/?page=vps-detail&id=' . $sub['id'])
            ]);

            wp_log_activity('vps_provisioned', 'vps', $vpsId, ['vmid' => $result['vmid'], 'hostname' => $hostname]);
            return $vpsId;
        } catch (Exception $e) {
            $db->insert('wp_services_vps', [
                'subscription_id' => $sub['id'],
                'hostname' => $hostname,
                'status' => 'error',
                'cores' => $sub['proxmox_cores'] ?? 1,
                'ram_mb' => $sub['proxmox_ram_mb'] ?? 512,
                'disk_gb' => $sub['proxmox_disk_gb'] ?? 10
            ]);
            wp_log_activity('vps_provision_failed', 'subscription', $sub['id'], ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private static function provisionHosting($sub, $user) {
        $db = Database::getInstance();
        $cyberpanel = new CyberPanelAPI();

        $domain = $_POST['domain'] ?? 'site-' . $sub['id'] . '.example.com';

        try {
            $result = $cyberpanel->provisionHosting(
                $domain, $user['email'],
                $sub['hosting_package'] ?? 'default',
                $sub['hosting_disk_mb'] ?? 1024,
                $sub['hosting_bandwidth_mb'] ?? 10240,
                $sub['hosting_email_accounts'] ?? 5,
                $sub['hosting_databases'] ?? 3
            );

            $hostingId = $db->insert('wp_services_hosting', [
                'subscription_id' => $sub['id'],
                'domain' => $domain,
                'cyberpanel_username' => $result['username'],
                'cyberpanel_password' => base64_encode(openssl_encrypt($result['password'], 'AES-256-CBC', SECRET_KEY, 0, substr(md5(SECRET_KEY), 0, 16))),
                'cyberpanel_package' => $result['package'],
                'disk_mb' => $sub['hosting_disk_mb'] ?? 1024,
                'bandwidth_mb' => $sub['hosting_bandwidth_mb'] ?? 10240,
                'email_accounts' => $sub['hosting_email_accounts'] ?? 5,
                'databases' => $sub['hosting_databases'] ?? 3,
                'status' => 'active'
            ]);

            $mailer = new Mailer();
            $mailer->sendTemplate($user['email'], 'service_created', [
                'first_name' => $user['first_name'],
                'service_name' => $sub['name'] . ' - ' . $domain,
                'service_details' => "Domaine: $domain | Utilisateur: {$result['username']}",
                'dashboard_url' => wp_url('client/?page=hosting-detail&id=' . $sub['id'])
            ]);

            wp_log_activity('hosting_provisioned', 'hosting', $hostingId, ['domain' => $domain]);
            return $hostingId;
        } catch (Exception $e) {
            $db->insert('wp_services_hosting', [
                'subscription_id' => $sub['id'],
                'domain' => $domain,
                'disk_mb' => $sub['hosting_disk_mb'] ?? 1024,
                'bandwidth_mb' => $sub['hosting_bandwidth_mb'] ?? 10240,
                'status' => 'error'
            ]);
            wp_log_activity('hosting_provision_failed', 'subscription', $sub['id'], ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private static function provisionNavidrome($sub, $user) {
        $db = Database::getInstance();
        $navidrome = new NavidromeAPI();

        $baseUsername = strtolower($user['first_name'] . '.' . $user['last_name']);

        try {
            $result = $navidrome->provisionUser($baseUsername);

            $ndId = $db->insert('wp_services_navidrome', [
                'subscription_id' => $sub['id'],
                'navidrome_username' => $result['username'],
                'navidrome_password' => base64_encode(openssl_encrypt($result['password'], 'AES-256-CBC', SECRET_KEY, 0, substr(md5(SECRET_KEY), 0, 16))),
                'navidrome_user_id' => $result['user_id'],
                'storage_mb' => $sub['navidrome_storage_mb'] ?? 5120,
                'max_playlists' => $sub['navidrome_max_playlists'],
                'status' => 'active'
            ]);

            $mailer = new Mailer();
            $mailer->sendTemplate($user['email'], 'service_created', [
                'first_name' => $user['first_name'],
                'service_name' => $sub['name'],
                'service_details' => "Utilisateur: {$result['username']} | URL: " . wp_setting('navidrome_url'),
                'dashboard_url' => wp_url('client/?page=navidrome-detail&id=' . $sub['id'])
            ]);

            wp_log_activity('navidrome_provisioned', 'navidrome', $ndId);
            return $ndId;
        } catch (Exception $e) {
            $db->insert('wp_services_navidrome', [
                'subscription_id' => $sub['id'],
                'storage_mb' => $sub['navidrome_storage_mb'] ?? 5120,
                'status' => 'error'
            ]);
            throw $e;
        }
    }

    public static function suspendService($subscriptionId, $reason = 'Impaye') {
        $db = Database::getInstance();
        $sub = $db->fetchOne(
            "SELECT s.*, p.type FROM wp_subscriptions s JOIN wp_products p ON s.product_id = p.id WHERE s.id = ?",
            [$subscriptionId]
        );
        if (!$sub) return false;

        switch ($sub['type']) {
            case 'vps':
                $vps = $db->fetchOne("SELECT * FROM wp_services_vps WHERE subscription_id = ?", [$subscriptionId]);
                if ($vps && $vps['proxmox_vmid']) {
                    try {
                        $proxmox = new ProxmoxAPI();
                        $proxmox->stopVM($vps['proxmox_node'], $vps['proxmox_vmid']);
                    } catch (Exception $e) {}
                    $db->update('wp_services_vps', ['status' => 'suspended'], 'id = ?', [$vps['id']]);
                }
                break;

            case 'hosting':
                $hosting = $db->fetchOne("SELECT * FROM wp_services_hosting WHERE subscription_id = ?", [$subscriptionId]);
                if ($hosting && $hosting['domain']) {
                    try {
                        $cyberpanel = new CyberPanelAPI();
                        $cyberpanel->suspendWebsite($hosting['domain']);
                    } catch (Exception $e) {}
                    $db->update('wp_services_hosting', ['status' => 'suspended'], 'id = ?', [$hosting['id']]);
                }
                break;

            case 'navidrome':
                $nd = $db->fetchOne("SELECT * FROM wp_services_navidrome WHERE subscription_id = ?", [$subscriptionId]);
                if ($nd && $nd['navidrome_user_id']) {
                    try {
                        $navidrome = new NavidromeAPI();
                        $navidrome->suspendUser($nd['navidrome_user_id']);
                    } catch (Exception $e) {}
                    $db->update('wp_services_navidrome', ['status' => 'suspended'], 'id = ?', [$nd['id']]);
                }
                break;
        }

        $db->update('wp_subscriptions', [
            'status' => 'suspended',
            'suspended_at' => date('Y-m-d H:i:s'),
            'suspension_reason' => $reason
        ], 'id = ?', [$subscriptionId]);

        // Email notification
        $user = $db->fetchOne("SELECT * FROM wp_users WHERE id = ?", [$sub['user_id']]);
        if ($user) {
            $mailer = new Mailer();
            $mailer->sendTemplate($user['email'], 'service_suspended', [
                'first_name' => $user['first_name'],
                'service_name' => $sub['name'] ?? 'Service #' . $subscriptionId,
                'invoice_url' => wp_url('client/?page=invoices')
            ]);
        }

        wp_log_activity('service_suspended', 'subscription', $subscriptionId, ['reason' => $reason]);
        return true;
    }

    public static function unsuspendService($subscriptionId) {
        $db = Database::getInstance();
        $sub = $db->fetchOne(
            "SELECT s.*, p.type FROM wp_subscriptions s JOIN wp_products p ON s.product_id = p.id WHERE s.id = ?",
            [$subscriptionId]
        );
        if (!$sub) return false;

        switch ($sub['type']) {
            case 'vps':
                $vps = $db->fetchOne("SELECT * FROM wp_services_vps WHERE subscription_id = ?", [$subscriptionId]);
                if ($vps && $vps['proxmox_vmid']) {
                    try {
                        $proxmox = new ProxmoxAPI();
                        $proxmox->startVM($vps['proxmox_node'], $vps['proxmox_vmid']);
                    } catch (Exception $e) {}
                    $db->update('wp_services_vps', ['status' => 'running'], 'id = ?', [$vps['id']]);
                }
                break;

            case 'hosting':
                $hosting = $db->fetchOne("SELECT * FROM wp_services_hosting WHERE subscription_id = ?", [$subscriptionId]);
                if ($hosting && $hosting['domain']) {
                    try {
                        $cyberpanel = new CyberPanelAPI();
                        $cyberpanel->unsuspendWebsite($hosting['domain']);
                    } catch (Exception $e) {}
                    $db->update('wp_services_hosting', ['status' => 'active'], 'id = ?', [$hosting['id']]);
                }
                break;

            case 'navidrome':
                $nd = $db->fetchOne("SELECT * FROM wp_services_navidrome WHERE subscription_id = ?", [$subscriptionId]);
                if ($nd) {
                    $navidrome = new NavidromeAPI();
                    $password = self::decryptPassword($nd['navidrome_password']);
                    try {
                        // Try to restore password on existing Navidrome user
                        $navidrome->changePassword($nd['navidrome_user_id'], $password);
                    } catch (Exception $e) {
                        // User may have been destroyed — recreate them
                        try {
                            $result = $navidrome->createUser($nd['navidrome_username'], $password, false);
                            $db->update('wp_services_navidrome', [
                                'navidrome_user_id' => $result['id']
                            ], 'id = ?', [$nd['id']]);
                        } catch (Exception $e2) {
                            error_log("Navidrome unsuspend failed for sub $subscriptionId: " . $e2->getMessage());
                        }
                    }
                    $db->update('wp_services_navidrome', ['status' => 'active'], 'id = ?', [$nd['id']]);
                }
                break;
        }

        wp_log_activity('service_unsuspended', 'subscription', $subscriptionId);
        return true;
    }

    public static function terminateService($subscriptionId) {
        $db = Database::getInstance();
        $sub = $db->fetchOne(
            "SELECT s.*, p.type, p.name as product_name FROM wp_subscriptions s JOIN wp_products p ON s.product_id = p.id WHERE s.id = ?",
            [$subscriptionId]
        );
        if (!$sub) return false;

        switch ($sub['type']) {
            case 'vps':
                $vps = $db->fetchOne("SELECT * FROM wp_services_vps WHERE subscription_id = ?", [$subscriptionId]);
                if ($vps && $vps['proxmox_vmid']) {
                    try {
                        $proxmox = new ProxmoxAPI();
                        $proxmox->deleteVM($vps['proxmox_node'], $vps['proxmox_vmid']);
                    } catch (Exception $e) {}
                    // Release IP
                    if ($vps['id']) {
                        $db->update('wp_ip_pool', ['is_assigned' => 0, 'assigned_to_vps_id' => null], 'assigned_to_vps_id = ?', [$vps['id']]);
                    }
                }
                break;

            case 'hosting':
                $hosting = $db->fetchOne("SELECT * FROM wp_services_hosting WHERE subscription_id = ?", [$subscriptionId]);
                if ($hosting && $hosting['domain']) {
                    try {
                        $cyberpanel = new CyberPanelAPI();
                        $cyberpanel->deleteWebsite($hosting['domain']);
                    } catch (Exception $e) {}
                }
                break;

            case 'navidrome':
                $nd = $db->fetchOne("SELECT * FROM wp_services_navidrome WHERE subscription_id = ?", [$subscriptionId]);
                if ($nd && $nd['navidrome_user_id']) {
                    try {
                        $navidrome = new NavidromeAPI();
                        $navidrome->deleteUser($nd['navidrome_user_id']);
                    } catch (Exception $e) {}
                }
                break;
        }

        $db->update('wp_subscriptions', [
            'status' => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$subscriptionId]);

        // Email notification
        $user = $db->fetchOne("SELECT * FROM wp_users WHERE id = ?", [$sub['user_id']]);
        if ($user) {
            $mailer = new Mailer();
            $mailer->sendTemplate($user['email'], 'service_terminated', [
                'first_name' => $user['first_name'],
                'service_name' => $sub['product_name']
            ]);
        }

        wp_log_activity('service_terminated', 'subscription', $subscriptionId);
        return true;
    }

    public static function decryptPassword($encrypted) {
        if (empty($encrypted)) return '';
        $decoded = base64_decode($encrypted);
        return openssl_decrypt($decoded, 'AES-256-CBC', SECRET_KEY, 0, substr(md5(SECRET_KEY), 0, 16));
    }
}
