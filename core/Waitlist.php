<?php
/**
 * ABAppointments - Waitlist Manager
 */
class Waitlist {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function join(array $data): int {
        return $this->db->insert('ab_waitlist', [
            'service_id' => (int)$data['service_id'],
            'provider_id' => !empty($data['provider_id']) ? (int)$data['provider_id'] : null,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? '',
            'desired_date_start' => $data['desired_date_start'],
            'desired_date_end' => $data['desired_date_end'],
        ]);
    }

    /**
     * Called whenever a slot frees up (cancellation or reschedule) so that
     * matching waitlist entries can be notified by email.
     */
    public function notifyForFreedSlot(int $serviceId, int $providerId, string $date): void {
        $entries = $this->db->fetchAll(
            "SELECT * FROM ab_waitlist
             WHERE status = 'waiting' AND service_id = ?
               AND (provider_id IS NULL OR provider_id = ?)
               AND ? BETWEEN desired_date_start AND desired_date_end
             ORDER BY created_at ASC",
            [$serviceId, $providerId, $date]
        );
        if (!$entries) return;

        $service = $this->db->fetchOne("SELECT name FROM ab_services WHERE id = ?", [$serviceId]);
        $provider = $this->db->fetchOne("SELECT first_name, last_name FROM ab_users WHERE id = ?", [$providerId]);
        $mailer = new Mailer();

        foreach ($entries as $entry) {
            $mailer->sendTemplate('waitlist_slot_available', $entry['email'], [
                'customer_name' => $entry['first_name'] . ' ' . $entry['last_name'],
                'service_name' => $service['name'] ?? '',
                'appointment_date' => ab_format_date($date),
                'provider_name' => $provider ? ($provider['first_name'] . ' ' . $provider['last_name']) : '',
                'booking_url' => ab_url('public/index.php?service_id=' . $serviceId . ($providerId ? '&provider_id=' . $providerId : '')),
                'business_name' => ab_setting('business_name'),
            ]);
            $this->db->update('ab_waitlist', [
                'status' => 'notified',
                'notified_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$entry['id']]);
        }
    }

    public function getAll(string $status = ''): array {
        $sql = "SELECT w.*, s.name as service_name,
                       u.first_name as provider_first_name, u.last_name as provider_last_name
                FROM ab_waitlist w
                JOIN ab_services s ON w.service_id = s.id
                LEFT JOIN ab_users u ON w.provider_id = u.id";
        $params = [];
        if ($status) {
            $sql .= " WHERE w.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY w.created_at DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function delete(int $id): void {
        $this->db->delete('ab_waitlist', 'id = ?', [$id]);
    }
}
