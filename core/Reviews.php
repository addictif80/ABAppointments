<?php
/**
 * ABAppointments - Customer Reviews Manager
 */
class Reviews {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Send a review request email for a just-completed appointment
     * (skipped silently if one was already sent/submitted).
     */
    public function requestForAppointment(array $appointment): void {
        $existing = $this->db->fetchOne("SELECT id FROM ab_reviews WHERE appointment_id = ?", [$appointment['id']]);
        if ($existing) return;

        $mailer = new Mailer();
        $mailer->sendTemplate('review_request', $appointment['customer_email'], [
            'customer_name' => $appointment['customer_first_name'] . ' ' . $appointment['customer_last_name'],
            'service_name' => $appointment['service_name'],
            'appointment_date' => ab_format_date($appointment['start_datetime']),
            'review_url' => ab_url('public/review.php?hash=' . $appointment['hash']),
            'business_name' => ab_setting('business_name'),
        ]);
    }

    /**
     * Look up an appointment (plus any already-submitted review) by its public hash,
     * for the review submission page.
     */
    public function getByAppointmentHash(string $hash): ?array {
        return $this->db->fetchOne(
            "SELECT a.id, a.hash, a.status, a.start_datetime,
                    c.first_name as customer_first_name, c.last_name as customer_last_name,
                    s.name as service_name,
                    r.id as review_id
             FROM ab_appointments a
             JOIN ab_customers c ON a.customer_id = c.id
             JOIN ab_services s ON a.service_id = s.id
             LEFT JOIN ab_reviews r ON r.appointment_id = a.id
             WHERE a.hash = ?",
            [$hash]
        );
    }

    public function submit(int $appointmentId, int $rating, string $comment): bool {
        $existing = $this->db->fetchOne("SELECT id FROM ab_reviews WHERE appointment_id = ?", [$appointmentId]);
        if ($existing) return false;

        $rating = max(1, min(5, $rating));
        $this->db->insert('ab_reviews', [
            'appointment_id' => $appointmentId,
            'rating' => $rating,
            'comment' => $comment,
            'status' => 'pending',
            'submitted_at' => date('Y-m-d H:i:s'),
        ]);
        return true;
    }

    public function getAll(string $status = ''): array {
        $sql = "SELECT r.*, a.start_datetime,
                       c.first_name as customer_first_name, c.last_name as customer_last_name,
                       s.name as service_name
                FROM ab_reviews r
                JOIN ab_appointments a ON r.appointment_id = a.id
                JOIN ab_customers c ON a.customer_id = c.id
                JOIN ab_services s ON a.service_id = s.id";
        $params = [];
        if ($status) {
            $sql .= " WHERE r.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY r.created_at DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function updateStatus(int $id, string $status): void {
        $this->db->update('ab_reviews', ['status' => $status], 'id = ?', [$id]);
    }

    public function delete(int $id): void {
        $this->db->delete('ab_reviews', 'id = ?', [$id]);
    }

    public function getApproved(int $limit = 10): array {
        $limit = max(1, min(50, $limit));
        return $this->db->fetchAll(
            "SELECT r.rating, r.comment, r.created_at, c.first_name as customer_first_name
             FROM ab_reviews r
             JOIN ab_appointments a ON r.appointment_id = a.id
             JOIN ab_customers c ON a.customer_id = c.id
             WHERE r.status = 'approved'
             ORDER BY r.created_at DESC LIMIT {$limit}"
        );
    }

    public function getStats(): array {
        return $this->db->fetchOne(
            "SELECT ROUND(AVG(rating), 1) as avg_rating, COUNT(*) as total
             FROM ab_reviews WHERE status = 'approved'"
        ) ?? ['avg_rating' => null, 'total' => 0];
    }
}
