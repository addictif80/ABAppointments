<?php
/**
 * WebPanel - Proxmox VE API Client (QEMU)
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

    // VM Operations (QEMU only)
    public function createVM($node, $params) {
        return $this->request('POST', "/nodes/$node/qemu", $params);
    }

    public function cloneVM($node, $templateVmid, $newVmid, $name, $storage = null) {
        $params = [
            'newid' => $newVmid,
            'name' => $name,
            'full' => 1
        ];
        if ($storage) {
            $params['storage'] = $storage;
        }
        return $this->request('POST', "/nodes/$node/qemu/$templateVmid/clone", $params);
    }

    public function resizeDisk($node, $vmid, $disk, $size) {
        return $this->request('PUT', "/nodes/$node/qemu/$vmid/resize", [
            'disk' => $disk,
            'size' => $size
        ]);
    }

    public function configureVM($node, $vmid, $params) {
        return $this->request('PUT', "/nodes/$node/qemu/$vmid/config", $params);
    }

    public function getVMStatus($node, $vmid) {
        return $this->request('GET', "/nodes/$node/qemu/$vmid/status/current");
    }

    public function getVMConfig($node, $vmid) {
        return $this->request('GET', "/nodes/$node/qemu/$vmid/config");
    }

    public function startVM($node, $vmid) {
        return $this->request('POST', "/nodes/$node/qemu/$vmid/status/start");
    }

    public function stopVM($node, $vmid) {
        return $this->request('POST', "/nodes/$node/qemu/$vmid/status/stop");
    }

    public function shutdownVM($node, $vmid) {
        return $this->request('POST', "/nodes/$node/qemu/$vmid/status/shutdown");
    }

    public function rebootVM($node, $vmid) {
        return $this->request('POST', "/nodes/$node/qemu/$vmid/status/reboot");
    }

    public function suspendVM($node, $vmid) {
        return $this->request('POST', "/nodes/$node/qemu/$vmid/status/suspend");
    }

    public function deleteVM($node, $vmid) {
        return $this->request('DELETE', "/nodes/$node/qemu/$vmid");
    }

    public function getVMRRDData($node, $vmid, $timeframe = 'hour') {
        return $this->request('GET', "/nodes/$node/qemu/$vmid/rrddata", ['timeframe' => $timeframe]);
    }

    // VNC console
    public function createVNCProxy($node, $vmid) {
        return $this->request('POST', "/nodes/$node/qemu/$vmid/vncproxy", ['websocket' => 1]);
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
        return $this->request('POST', "/nodes/$node/qemu/$vmid/termproxy");
    }

    // Wait for a task to complete
    public function waitForTask($node, $upid, $timeout = 120) {
        $start = time();
        while (time() - $start < $timeout) {
            $status = $this->request('GET', "/nodes/$node/tasks/$upid/status");
            if (($status['status'] ?? '') === 'stopped') {
                if (($status['exitstatus'] ?? '') === 'OK') {
                    return true;
                }
                throw new Exception("Proxmox task failed: " . ($status['exitstatus'] ?? 'unknown error'));
            }
            sleep(2);
        }
        throw new Exception("Proxmox task timeout after {$timeout}s");
    }

    // Reinstall VM (clone from new template)
    public function reinstallVM($node, $vmid, $templateVmid, $password) {
        // Get current config for resources
        $config = $this->getVMConfig($node, $vmid);

        // Stop and delete current VM
        try { $this->stopVM($node, $vmid); } catch (Exception $e) {}
        sleep(5);
        $this->deleteVM($node, $vmid);
        sleep(3);

        $storage = wp_setting('proxmox_default_storage', 'local-lvm');

        // Clone from template
        $upid = $this->cloneVM($node, $templateVmid, $vmid, $config['name'] ?? "vps-$vmid", $storage);
        $this->waitForTask($node, $upid, 180);

        // Reconfigure with original resources + new password
        $configParams = [
            'cores' => $config['cores'] ?? 1,
            'memory' => $config['memory'] ?? 512,
            'ciuser' => 'root',
            'cipassword' => $password
        ];

        // Preserve network config if present
        if (!empty($config['net0'])) {
            $configParams['net0'] = $config['net0'];
        }
        if (!empty($config['ipconfig0'])) {
            $configParams['ipconfig0'] = $config['ipconfig0'];
        }

        $this->configureVM($node, $vmid, $configParams);

        // Start the VM
        $this->startVM($node, $vmid);

        return $vmid;
    }

    // Provision a new VPS (QEMU with cloud-init)
    public function provisionVPS($product, $hostname, $templateVmid, $password, $ipAddress = null) {
        $node = wp_setting('proxmox_default_node', 'pve');
        $storage = $product['proxmox_storage'] ?: wp_setting('proxmox_default_storage', 'local-lvm');
        $bridge = $product['proxmox_bridge'] ?: wp_setting('proxmox_default_bridge', 'vmbr0');
        $vmid = $this->getNextVmid();

        // Clone from template VM
        $upid = $this->cloneVM($node, $templateVmid, $vmid, $hostname, $storage);
        $this->waitForTask($node, $upid, 180);

        // Resize disk to match product specs
        $diskSize = ($product['proxmox_disk_gb'] ?? 10) . 'G';
        $this->resizeDisk($node, $vmid, 'scsi0', $diskSize);

        // Build cloud-init network config
        $ipConfig = $ipAddress
            ? "ip=$ipAddress/24,gw=" . wp_setting('proxmox_default_gateway', '')
            : 'ip=dhcp';

        // Configure VM resources and cloud-init
        $configParams = [
            'cores' => $product['proxmox_cores'] ?? 1,
            'memory' => $product['proxmox_ram_mb'] ?? 512,
            'net0' => "virtio,bridge=$bridge",
            'ipconfig0' => $ipConfig,
            'ciuser' => 'root',
            'cipassword' => $password,
            'agent' => 1
        ];

        if (!empty($product['proxmox_pool'])) {
            $configParams['pool'] = $product['proxmox_pool'];
        }

        $this->configureVM($node, $vmid, $configParams);

        // Start the VM
        $this->startVM($node, $vmid);

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
