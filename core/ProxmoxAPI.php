<?php
/**
 * WebPanel - Proxmox VE API Client
 */
class ProxmoxAPI {
    private $host;
    private $port;
    private $tokenId;
    private $tokenSecret;

    public function __construct() {
        $this->host = wp_setting('proxmox_host');
        $this->port = wp_setting('proxmox_port', '8006');
        $this->tokenId = wp_setting('proxmox_user') . '!' . wp_setting('proxmox_token_id');
        $this->tokenSecret = wp_setting('proxmox_token_secret');
    }

    private function request($method, $path, $data = []) {
        $url = "https://{$this->host}:{$this->port}/api2/json{$path}";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                "Authorization: PVEAPIToken={$this->tokenId}={$this->tokenSecret}",
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ]);

        switch (strtoupper($method)) {
            case 'GET':
                if ($data) $url .= '?' . http_build_query($data);
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) throw new Exception("Proxmox API error: $error");

        $decoded = json_decode($response, true);
        if ($httpCode >= 400) {
            $msg = $decoded['errors'] ?? $decoded['message'] ?? "HTTP $httpCode";
            throw new Exception("Proxmox API: $msg");
        }

        return $decoded['data'] ?? $decoded;
    }

    public function getNodes() {
        return $this->request('GET', '/nodes');
    }

    public function getNodeStatus($node) {
        return $this->request('GET', "/nodes/$node/status");
    }

    public function getNextVmid() {
        return $this->request('GET', '/cluster/nextid');
    }

    // VM Operations
    public function createVM($node, $params) {
        return $this->request('POST', "/nodes/$node/qemu", $params);
    }

    public function createLXC($node, $params) {
        return $this->request('POST', "/nodes/$node/lxc", $params);
    }

    public function getVMStatus($node, $vmid) {
        // Try QEMU first, then LXC
        try {
            return $this->request('GET', "/nodes/$node/qemu/$vmid/status/current");
        } catch (Exception $e) {
            return $this->request('GET', "/nodes/$node/lxc/$vmid/status/current");
        }
    }

    public function getVMConfig($node, $vmid) {
        try {
            return $this->request('GET', "/nodes/$node/qemu/$vmid/config");
        } catch (Exception $e) {
            return $this->request('GET', "/nodes/$node/lxc/$vmid/config");
        }
    }

    public function startVM($node, $vmid) {
        try {
            return $this->request('POST', "/nodes/$node/qemu/$vmid/status/start");
        } catch (Exception $e) {
            return $this->request('POST', "/nodes/$node/lxc/$vmid/status/start");
        }
    }

    public function stopVM($node, $vmid) {
        try {
            return $this->request('POST', "/nodes/$node/qemu/$vmid/status/stop");
        } catch (Exception $e) {
            return $this->request('POST', "/nodes/$node/lxc/$vmid/status/stop");
        }
    }

    public function shutdownVM($node, $vmid) {
        try {
            return $this->request('POST', "/nodes/$node/qemu/$vmid/status/shutdown");
        } catch (Exception $e) {
            return $this->request('POST', "/nodes/$node/lxc/$vmid/status/shutdown");
        }
    }

    public function rebootVM($node, $vmid) {
        try {
            return $this->request('POST', "/nodes/$node/qemu/$vmid/status/reboot");
        } catch (Exception $e) {
            return $this->request('POST', "/nodes/$node/lxc/$vmid/status/reboot");
        }
    }

    public function suspendVM($node, $vmid) {
        try {
            return $this->request('POST', "/nodes/$node/qemu/$vmid/status/suspend");
        } catch (Exception $e) {
            return $this->request('POST', "/nodes/$node/lxc/$vmid/status/suspend");
        }
    }

    public function deleteVM($node, $vmid) {
        try {
            return $this->request('DELETE', "/nodes/$node/qemu/$vmid");
        } catch (Exception $e) {
            return $this->request('DELETE', "/nodes/$node/lxc/$vmid");
        }
    }

    public function getVMRRDData($node, $vmid, $timeframe = 'hour') {
        try {
            return $this->request('GET', "/nodes/$node/qemu/$vmid/rrddata", ['timeframe' => $timeframe]);
        } catch (Exception $e) {
            return $this->request('GET', "/nodes/$node/lxc/$vmid/rrddata", ['timeframe' => $timeframe]);
        }
    }

    // VNC console for web terminal
    public function createVNCProxy($node, $vmid) {
        try {
            return $this->request('POST', "/nodes/$node/qemu/$vmid/vncproxy", ['websocket' => 1]);
        } catch (Exception $e) {
            return $this->request('POST', "/nodes/$node/lxc/$vmid/vncproxy", ['websocket' => 1]);
        }
    }

    public function getVNCWebSocket($node, $vmid) {
        $proxy = $this->createVNCProxy($node, $vmid);
        return [
            'url' => "wss://{$this->host}:{$this->port}/api2/json/nodes/$node/qemu/$vmid/vncwebsocket?port={$proxy['port']}&vncticket=" . urlencode($proxy['ticket']),
            'ticket' => $proxy['ticket'],
            'port' => $proxy['port']
        ];
    }

    // Terminal proxy (xterm.js)
    public function createTermProxy($node, $vmid) {
        try {
            return $this->request('POST', "/nodes/$node/lxc/$vmid/termproxy");
        } catch (Exception $e) {
            return $this->request('POST', "/nodes/$node/qemu/$vmid/termproxy");
        }
    }

    // Reinstall VM
    public function reinstallVM($node, $vmid, $osTemplate, $password) {
        // Stop VM first
        try { $this->stopVM($node, $vmid); } catch (Exception $e) {}
        sleep(3);

        // Get current config for resources
        $config = $this->getVMConfig($node, $vmid);

        // Delete and recreate
        $this->deleteVM($node, $vmid);
        sleep(2);

        return $this->createLXC($node, [
            'vmid' => $vmid,
            'ostemplate' => $osTemplate,
            'hostname' => $config['hostname'] ?? "vps-$vmid",
            'password' => $password,
            'cores' => $config['cores'] ?? 1,
            'memory' => $config['memory'] ?? 512,
            'rootfs' => ($config['rootfs'] ?? 'local-lvm:8'),
            'net0' => $config['net0'] ?? 'name=eth0,bridge=vmbr0,ip=dhcp',
            'start' => 1,
            'unprivileged' => 1
        ]);
    }

    // Provision a new VPS
    public function provisionVPS($product, $hostname, $osTemplate, $password, $ipAddress = null) {
        $node = wp_setting('proxmox_default_node', 'pve');
        $storage = $product['proxmox_storage'] ?: wp_setting('proxmox_default_storage', 'local-lvm');
        $bridge = $product['proxmox_bridge'] ?: wp_setting('proxmox_default_bridge', 'vmbr0');
        $vmid = $this->getNextVmid();

        $netConfig = "name=eth0,bridge=$bridge";
        if ($ipAddress) {
            $netConfig .= ",ip=$ipAddress/24,gw=" . wp_setting('proxmox_default_gateway', '');
        } else {
            $netConfig .= ",ip=dhcp";
        }

        $params = [
            'vmid' => $vmid,
            'ostemplate' => $osTemplate,
            'hostname' => $hostname,
            'password' => $password,
            'cores' => $product['proxmox_cores'] ?? 1,
            'memory' => $product['proxmox_ram_mb'] ?? 512,
            'rootfs' => "$storage:" . ($product['proxmox_disk_gb'] ?? 10),
            'net0' => $netConfig,
            'start' => 1,
            'unprivileged' => 1
        ];

        if (!empty($product['proxmox_pool'])) {
            $params['pool'] = $product['proxmox_pool'];
        }

        $this->createLXC($node, $params);

        return [
            'vmid' => $vmid,
            'node' => $node,
            'ip_address' => $ipAddress
        ];
    }

    public function isConfigured() {
        return !empty($this->host) && !empty(wp_setting('proxmox_token_id'));
    }
}
