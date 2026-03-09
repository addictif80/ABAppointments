<?php
/**
 * ABAppointments - CalDAV Calendar Sync
 * Synchronise les rendez-vous avec un serveur CalDAV (Nextcloud, Radicale, iCloud, etc.)
 */
class CalDAV {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get CalDAV config for a provider
     */
    public function getConfig(int $providerId): ?array {
        return $this->db->fetchOne(
            "SELECT * FROM ab_caldav_sync WHERE provider_id = ?",
            [$providerId]
        );
    }

    /**
     * Check if a provider has CalDAV sync enabled
     */
    public function isEnabledFor(int $providerId): bool {
        $config = $this->getConfig($providerId);
        return $config && $config['sync_enabled'] && !empty($config['caldav_url']);
    }

    /**
     * Save CalDAV configuration for a provider
     */
    public function saveConfig(int $providerId, array $data): void {
        $existing = $this->getConfig($providerId);

        $row = [
            'provider_id' => $providerId,
            'caldav_url' => rtrim(trim($data['caldav_url'] ?? ''), '/'),
            'caldav_username' => trim($data['caldav_username'] ?? ''),
            'caldav_password' => trim($data['caldav_password'] ?? ''),
            'sync_enabled' => isset($data['caldav_enabled']) ? 1 : 0,
        ];

        // Don't overwrite password if left blank on edit
        if ($existing && $row['caldav_password'] === '') {
            unset($row['caldav_password']);
        }

        if ($existing) {
            $this->db->update('ab_caldav_sync', $row, 'provider_id = ?', [$providerId]);
        } else {
            $this->db->insert('ab_caldav_sync', $row);
        }
    }

    /**
     * Test CalDAV connection
     */
    public function testConnection(int $providerId): array {
        $config = $this->getConfig($providerId);
        if (!$config || empty($config['caldav_url'])) {
            return ['success' => false, 'error' => 'Configuration CalDAV manquante'];
        }

        $ch = curl_init($config['caldav_url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PROPFIND',
            CURLOPT_HTTPHEADER => [
                'Depth: 0',
                'Content-Type: application/xml; charset=utf-8',
            ],
            CURLOPT_POSTFIELDS => '<?xml version="1.0" encoding="UTF-8"?>'
                . '<d:propfind xmlns:d="DAV:"><d:prop><d:displayname/></d:prop></d:propfind>',
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if (!empty($config['caldav_username'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $config['caldav_username'] . ':' . $config['caldav_password']);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => 'Connexion impossible : ' . $error];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            $this->db->update('ab_caldav_sync', ['last_sync' => date('Y-m-d H:i:s')], 'provider_id = ?', [$providerId]);
            return ['success' => true];
        }

        if ($httpCode === 401) {
            return ['success' => false, 'error' => 'Authentification échouée (401)'];
        }

        return ['success' => false, 'error' => 'Erreur HTTP ' . $httpCode];
    }

    /**
     * Sync an appointment to CalDAV
     */
    public function syncAppointment(int $appointmentId): bool {
        $manager = new AppointmentManager();
        $appointment = $manager->getAppointment($appointmentId);
        if (!$appointment) return false;

        $config = $this->getConfig($appointment['provider_id']);
        if (!$config || !$config['sync_enabled'] || empty($config['caldav_url'])) {
            return false;
        }

        $uid = 'abappt-' . $appointment['id'] . '-' . $appointment['hash'] . '@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $ical = $this->buildICalEvent($appointment, $uid);

        $eventUrl = $config['caldav_url'] . '/' . $uid . '.ics';

        $ch = curl_init($eventUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/calendar; charset=utf-8',
                'If-None-Match: *',
            ],
            CURLOPT_POSTFIELDS => $ical,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if (!empty($config['caldav_username'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $config['caldav_username'] . ':' . $config['caldav_password']);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log('ABAppointments CalDAV error: ' . $error);
            return false;
        }

        // 201 Created or 204 No Content = success, also accept 200
        if ($httpCode >= 200 && $httpCode < 300) {
            $this->db->update('ab_caldav_sync', ['last_sync' => date('Y-m-d H:i:s')], 'provider_id = ?', [$appointment['provider_id']]);
            return true;
        }

        // If If-None-Match failed (412), try without it (update existing)
        if ($httpCode === 412) {
            return $this->updateEvent($eventUrl, $ical, $config);
        }

        error_log('ABAppointments CalDAV sync failed: HTTP ' . $httpCode . ' - ' . $response);
        return false;
    }

    /**
     * Delete a CalDAV event when appointment is cancelled
     */
    public function deleteEvent(int $appointmentId): bool {
        $manager = new AppointmentManager();
        $appointment = $manager->getAppointment($appointmentId);
        if (!$appointment) return false;

        $config = $this->getConfig($appointment['provider_id']);
        if (!$config || !$config['sync_enabled'] || empty($config['caldav_url'])) {
            return false;
        }

        $uid = 'abappt-' . $appointment['id'] . '-' . $appointment['hash'] . '@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $eventUrl = $config['caldav_url'] . '/' . $uid . '.ics';

        $ch = curl_init($eventUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if (!empty($config['caldav_username'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $config['caldav_username'] . ':' . $config['caldav_password']);
        }

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode >= 200 && $httpCode < 300) || $httpCode === 404;
    }

    /**
     * Update existing event (without If-None-Match)
     */
    private function updateEvent(string $eventUrl, string $ical, array $config): bool {
        $ch = curl_init($eventUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/calendar; charset=utf-8',
            ],
            CURLOPT_POSTFIELDS => $ical,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if (!empty($config['caldav_username'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $config['caldav_username'] . ':' . $config['caldav_password']);
        }

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }

    /**
     * Build iCalendar VEVENT string
     */
    private function buildICalEvent(array $appointment, string $uid): string {
        $tz = Settings::get('timezone', 'Europe/Paris');
        $now = gmdate('Ymd\THis\Z');

        $dtStart = $this->toICalDate($appointment['start_datetime'], $tz);
        $dtEnd = $this->toICalDate($appointment['end_datetime'], $tz);

        $summary = $this->escapeIcal($appointment['service_name'] . ' - '
            . $appointment['customer_first_name'] . ' ' . $appointment['customer_last_name']);

        $description = $this->escapeIcal(
            'Client: ' . $appointment['customer_first_name'] . ' ' . $appointment['customer_last_name']
            . '\nTél: ' . ($appointment['customer_phone'] ?? '')
            . '\nEmail: ' . ($appointment['customer_email'] ?? '')
            . ($appointment['notes'] ? '\nNotes: ' . $appointment['notes'] : '')
        );

        $status = match ($appointment['status']) {
            'confirmed' => 'CONFIRMED',
            'cancelled' => 'CANCELLED',
            'pending' => 'TENTATIVE',
            default => 'CONFIRMED',
        };

        return "BEGIN:VCALENDAR\r\n"
            . "VERSION:2.0\r\n"
            . "PRODID:-//ABAppointments//CalDAV Sync//FR\r\n"
            . "CALSCALE:GREGORIAN\r\n"
            . "BEGIN:VEVENT\r\n"
            . "UID:" . $uid . "\r\n"
            . "DTSTAMP:" . $now . "\r\n"
            . "DTSTART;TZID=" . $tz . ":" . $dtStart . "\r\n"
            . "DTEND;TZID=" . $tz . ":" . $dtEnd . "\r\n"
            . "SUMMARY:" . $summary . "\r\n"
            . "DESCRIPTION:" . $description . "\r\n"
            . "STATUS:" . $status . "\r\n"
            . "END:VEVENT\r\n"
            . "END:VCALENDAR\r\n";
    }

    /**
     * Convert datetime to iCal local format
     */
    private function toICalDate(string $datetime, string $tz): string {
        $dt = new DateTime($datetime, new DateTimeZone($tz));
        return $dt->format('Ymd\THis');
    }

    /**
     * Escape text for iCalendar
     */
    private function escapeIcal(string $text): string {
        $text = str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $text);
        return $text;
    }
}
