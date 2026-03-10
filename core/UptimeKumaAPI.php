<?php
/**
 * WebPanel - Uptime Kuma API Client
 */
class UptimeKumaAPI {
    private $url;
    private $apiKey;

    public function __construct() {
        $this->url = rtrim(wp_setting('uptime_kuma_url'), '/');
        $this->apiKey = wp_setting('uptime_kuma_api_key');
    }

    private function request($endpoint) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "{$this->url}/api/$endpoint",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->apiKey}",
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) throw new Exception("Uptime Kuma API: $error");
        return json_decode($response, true);
    }

    public function getMonitors() {
        // Use the status page API or push API
        return $this->request('status-page/heartbeat');
    }

    public function getStatusPage($slug = 'default') {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "{$this->url}/api/status-page/$slug",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 10
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }

    public function getHeartbeats() {
        return $this->request('status-page/heartbeat');
    }

    /**
     * Sync monitors from Uptime Kuma to local database
     */
    public function syncMonitors() {
        $db = Database::getInstance();

        try {
            $data = $this->getHeartbeats();
            if (!$data || !isset($data['heartbeatList'])) return false;

            foreach ($data['heartbeatList'] as $monitorId => $heartbeats) {
                $lastBeat = end($heartbeats);
                $status = ($lastBeat['status'] ?? 0) === 1 ? 'up' : 'down';

                // Calculate uptime
                $total = count($heartbeats);
                $upCount = count(array_filter($heartbeats, fn($h) => ($h['status'] ?? 0) === 1));
                $uptime = $total > 0 ? round(($upCount / $total) * 100, 2) : 0;

                $existing = $db->fetchOne("SELECT id FROM wp_monitors WHERE kuma_monitor_id = ?", [$monitorId]);
                if ($existing) {
                    $db->update('wp_monitors', [
                        'status' => $status,
                        'uptime_24h' => $uptime,
                        'last_check' => date('Y-m-d H:i:s')
                    ], 'id = ?', [$existing['id']]);
                }
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function isConfigured() {
        return !empty($this->url);
    }
}
