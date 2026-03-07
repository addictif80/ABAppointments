<?php
/**
 * ABAppointments - Google Calendar Sync
 */
class GoogleCalendar {
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct() {
        $this->clientId = Settings::get('google_client_id');
        $this->clientSecret = Settings::get('google_client_secret');
        $this->redirectUri = Settings::get('google_redirect_uri', ab_url('admin/index.php?page=google-callback'));
    }

    public function isConfigured(): bool {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    public function getAuthUrl(int $providerId): string {
        $params = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $providerId,
        ]);
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
    }

    public function handleCallback(string $code, int $providerId): bool {
        $tokenData = $this->requestToken([
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if (!$tokenData || !isset($tokenData['access_token'])) return false;

        $db = Database::getInstance();
        $existing = $db->fetchOne("SELECT id FROM ab_google_sync WHERE provider_id = ?", [$providerId]);

        $data = [
            'provider_id' => $providerId,
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? '',
            'token_expiry' => date('Y-m-d H:i:s', time() + ($tokenData['expires_in'] ?? 3600)),
            'sync_enabled' => 1,
        ];

        if ($existing) {
            $db->update('ab_google_sync', $data, 'provider_id = ?', [$providerId]);
        } else {
            $db->insert('ab_google_sync', $data);
        }

        return true;
    }

    public function syncAppointment(int $appointmentId): bool {
        $db = Database::getInstance();
        $manager = new AppointmentManager();
        $appointment = $manager->getAppointment($appointmentId);
        if (!$appointment) return false;

        $sync = $db->fetchOne(
            "SELECT * FROM ab_google_sync WHERE provider_id = ? AND sync_enabled = 1",
            [$appointment['provider_id']]
        );
        if (!$sync) return false;

        $accessToken = $this->getValidToken($sync);
        if (!$accessToken) return false;

        $calendarId = $sync['google_calendar_id'] ?: 'primary';

        $event = [
            'summary' => $appointment['service_name'] . ' - ' . $appointment['customer_first_name'] . ' ' . $appointment['customer_last_name'],
            'description' => 'Client: ' . $appointment['customer_first_name'] . ' ' . $appointment['customer_last_name']
                . "\nTél: " . $appointment['customer_phone']
                . "\nEmail: " . $appointment['customer_email']
                . ($appointment['notes'] ? "\nNotes: " . $appointment['notes'] : ''),
            'start' => [
                'dateTime' => date('c', strtotime($appointment['start_datetime'])),
                'timeZone' => Settings::get('timezone', 'Europe/Paris'),
            ],
            'end' => [
                'dateTime' => date('c', strtotime($appointment['end_datetime'])),
                'timeZone' => Settings::get('timezone', 'Europe/Paris'),
            ],
            'colorId' => '11',
        ];

        $url = "https://www.googleapis.com/calendar/v3/calendars/" . urlencode($calendarId) . "/events";
        $result = $this->apiRequest($url, $accessToken, 'POST', $event);

        if ($result) {
            $db->update('ab_google_sync', ['last_sync' => date('Y-m-d H:i:s')], 'provider_id = ?', [$appointment['provider_id']]);
            return true;
        }

        return false;
    }

    private function getValidToken(array $sync): ?string {
        if (strtotime($sync['token_expiry']) > time()) {
            return $sync['access_token'];
        }

        if (empty($sync['refresh_token'])) return null;

        $tokenData = $this->requestToken([
            'refresh_token' => $sync['refresh_token'],
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token',
        ]);

        if (!$tokenData || !isset($tokenData['access_token'])) return null;

        $db = Database::getInstance();
        $db->update('ab_google_sync', [
            'access_token' => $tokenData['access_token'],
            'token_expiry' => date('Y-m-d H:i:s', time() + ($tokenData['expires_in'] ?? 3600)),
        ], 'provider_id = ?', [$sync['provider_id']]);

        return $tokenData['access_token'];
    }

    private function requestToken(array $params): ?array {
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response ? json_decode($response, true) : null;
    }

    private function apiRequest(string $url, string $token, string $method = 'GET', ?array $body = null): ?array {
        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response ? json_decode($response, true) : null;
    }
}
