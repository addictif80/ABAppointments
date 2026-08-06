<?php
/**
 * ABAppointments - Support Ticket Manager
 */
class Tickets {
    private Database $db;

    private const ALLOWED_EXTENSIONS = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'webp' => 'image/webp',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'csv' => 'text/csv',
    ];

    private const MAX_FILES_PER_MESSAGE = 5;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    private function storageDir(): string {
        return dirname(__DIR__) . '/storage/ticket_attachments/';
    }

    private function maxUploadSize(): int {
        return defined('AB_MAX_UPLOAD_SIZE') ? AB_MAX_UPLOAD_SIZE : 5 * 1024 * 1024;
    }

    public function categoryLabel(string $category): string {
        return $category === 'commercial' ? 'Commercial' : 'Technique';
    }

    /**
     * Find an existing customer by email, or create a minimal record.
     * An optional date of birth is stored so the customer can later log
     * into their personal account (public/account.php requires it), and
     * is backfilled onto an existing customer record that doesn't have one.
     */
    public function resolveCustomer(string $firstName, string $lastName, string $email, string $phone = '', ?string $dob = null): int {
        $customer = $this->db->fetchOne("SELECT id, date_of_birth FROM ab_customers WHERE email = ?", [$email]);
        if ($customer) {
            if ($dob && empty($customer['date_of_birth'])) {
                $this->db->update('ab_customers', ['date_of_birth' => $dob], 'id = ?', [$customer['id']]);
            }
            return (int) $customer['id'];
        }
        return $this->db->insert('ab_customers', [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'date_of_birth' => $dob,
        ]);
    }

    /**
     * Email every ticket link for a given customer email, for the "lost my
     * link" recovery flow. Silently does nothing if the email is unknown or
     * has no tickets, so the caller can always show a generic success message
     * (avoids leaking which emails have an account).
     */
    public function sendRecoveryLinks(string $email): void {
        $customer = $this->db->fetchOne("SELECT * FROM ab_customers WHERE email = ?", [$email]);
        if (!$customer) return;

        $ticketsList = $this->listForCustomer((int) $customer['id']);
        if (!$ticketsList) return;

        $statusLabels = ['open' => 'Ouvert', 'resolved' => 'Résolu', 'closed' => 'Clôturé'];
        $rows = '';
        foreach ($ticketsList as $t) {
            $rows .= '<tr>'
                . '<td style="padding:6px 10px;border:1px solid #ddd;">' . htmlspecialchars($t['subject'], ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="padding:6px 10px;border:1px solid #ddd;">' . ($statusLabels[$t['status']] ?? $t['status']) . '</td>'
                . '<td style="padding:6px 10px;border:1px solid #ddd;"><a href="' . ab_url('public/ticket.php?hash=' . $t['hash']) . '">Voir</a></td>'
                . '</tr>';
        }

        $mailer = new Mailer();
        $mailer->sendTemplate('ticket_recovery', $email, [
            'customer_name' => $customer['first_name'] . ' ' . $customer['last_name'],
            'tickets_list' => '<table style="border-collapse:collapse;width:100%;max-width:500px;">' . $rows . '</table>',
            'business_name' => ab_setting('business_name'),
        ]);
    }

    /**
     * Providers the customer has already had an appointment with — used to
     * pre-select the "commercial" recipient when they open a ticket.
     */
    public function getFamiliarProviders(int $customerId): array {
        return $this->db->fetchAll(
            "SELECT DISTINCT u.id, u.first_name, u.last_name
             FROM ab_appointments a
             JOIN ab_users u ON a.provider_id = u.id
             WHERE a.customer_id = ? AND u.is_active = 1
             ORDER BY u.first_name",
            [$customerId]
        );
    }

    /**
     * Create a new ticket with its first message (and optional attachments).
     * $data: customer_id, subject, category, provider_id (commercial only),
     *        body, created_by ('customer'|'staff'), sender_user_id (staff only), files
     */
    public function create(array $data): array {
        $hash = ab_generate_hash();

        $ticketId = $this->db->insert('ab_tickets', [
            'hash' => $hash,
            'customer_id' => $data['customer_id'],
            'subject' => $data['subject'],
            'category' => $data['category'],
            'assigned_provider_id' => $data['category'] === 'commercial' ? (!empty($data['provider_id']) ? (int)$data['provider_id'] : null) : null,
            'created_by' => $data['created_by'],
        ]);

        $messageId = $this->db->insert('ab_ticket_messages', [
            'ticket_id' => $ticketId,
            'sender_type' => $data['created_by'],
            'sender_user_id' => $data['created_by'] === 'staff' ? ($data['sender_user_id'] ?? null) : null,
            'body' => $data['body'],
        ]);

        $this->storeAttachments($messageId, $data['files'] ?? []);

        $ticket = $this->getById($ticketId);
        $this->notifyCreated($ticket, $data['body']);

        return $ticket;
    }

    /**
     * Post a reply on an existing ticket (customer or staff side).
     */
    public function reply(array $ticket, string $senderType, ?int $senderUserId, string $body, array $files = []): void {
        $messageId = $this->db->insert('ab_ticket_messages', [
            'ticket_id' => $ticket['id'],
            'sender_type' => $senderType,
            'sender_user_id' => $senderType === 'staff' ? $senderUserId : null,
            'body' => $body,
        ]);

        $this->storeAttachments($messageId, $files);

        // A customer reply always reopens a resolved/closed ticket.
        if ($senderType === 'customer' && $ticket['status'] !== 'open') {
            $this->db->update('ab_tickets', ['status' => 'open'], 'id = ?', [$ticket['id']]);
        } else {
            $this->db->update('ab_tickets', ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$ticket['id']]);
        }

        $ticket = $this->getById($ticket['id']);
        $this->notifyReply($ticket, $senderType, $body);
    }

    public function updateStatus(int $ticketId, string $status): void {
        $this->db->update('ab_tickets', ['status' => $status], 'id = ?', [$ticketId]);
    }

    public function reassign(int $ticketId, ?int $providerId): void {
        // Keep category and assignment in sync: a ticket assigned to a
        // provider must be "commercial" for that provider's access checks
        // (and staff notifications) to work; unassigning falls back to
        // "technique" so it stays visible to admins.
        $this->db->update('ab_tickets', [
            'assigned_provider_id' => $providerId ?: null,
            'category' => $providerId ? 'commercial' : 'technique',
        ], 'id = ?', [$ticketId]);
    }

    public function getById(int $id): ?array {
        return $this->db->fetchOne(
            "SELECT t.*, c.first_name as customer_first_name, c.last_name as customer_last_name, c.email as customer_email,
                    u.first_name as provider_first_name, u.last_name as provider_last_name, u.email as provider_email
             FROM ab_tickets t
             JOIN ab_customers c ON t.customer_id = c.id
             LEFT JOIN ab_users u ON t.assigned_provider_id = u.id
             WHERE t.id = ?",
            [$id]
        );
    }

    public function getByHash(string $hash): ?array {
        return $this->db->fetchOne(
            "SELECT t.*, c.first_name as customer_first_name, c.last_name as customer_last_name, c.email as customer_email,
                    u.first_name as provider_first_name, u.last_name as provider_last_name, u.email as provider_email
             FROM ab_tickets t
             JOIN ab_customers c ON t.customer_id = c.id
             LEFT JOIN ab_users u ON t.assigned_provider_id = u.id
             WHERE t.hash = ?",
            [$hash]
        );
    }

    public function getMessages(int $ticketId): array {
        $messages = $this->db->fetchAll(
            "SELECT m.*, u.first_name as staff_first_name, u.last_name as staff_last_name
             FROM ab_ticket_messages m
             LEFT JOIN ab_users u ON m.sender_user_id = u.id
             WHERE m.ticket_id = ?
             ORDER BY m.created_at ASC, m.id ASC",
            [$ticketId]
        );
        if (!$messages) return [];

        $ids = array_column($messages, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $attachments = $this->db->fetchAll(
            "SELECT * FROM ab_ticket_attachments WHERE message_id IN ($placeholders) ORDER BY id",
            $ids
        );
        $byMessage = [];
        foreach ($attachments as $a) {
            $byMessage[$a['message_id']][] = $a;
        }
        foreach ($messages as &$m) {
            $m['attachments'] = $byMessage[$m['id']] ?? [];
        }
        return $messages;
    }

    public function listForCustomer(int $customerId): array {
        return $this->db->fetchAll(
            "SELECT t.*, u.first_name as provider_first_name, u.last_name as provider_last_name
             FROM ab_tickets t
             LEFT JOIN ab_users u ON t.assigned_provider_id = u.id
             WHERE t.customer_id = ?
             ORDER BY t.updated_at DESC",
            [$customerId]
        );
    }

    public function listForAdmin(string $statusFilter = ''): array {
        $sql = "SELECT t.*, c.first_name as customer_first_name, c.last_name as customer_last_name,
                       u.first_name as provider_first_name, u.last_name as provider_last_name
                FROM ab_tickets t
                JOIN ab_customers c ON t.customer_id = c.id
                LEFT JOIN ab_users u ON t.assigned_provider_id = u.id";
        $params = [];
        if ($statusFilter) {
            $sql .= " WHERE t.status = ?";
            $params[] = $statusFilter;
        }
        $sql .= " ORDER BY t.updated_at DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function listForProvider(int $providerId, string $statusFilter = ''): array {
        $sql = "SELECT t.*, c.first_name as customer_first_name, c.last_name as customer_last_name
                FROM ab_tickets t
                JOIN ab_customers c ON t.customer_id = c.id
                WHERE t.assigned_provider_id = ?";
        $params = [$providerId];
        if ($statusFilter) {
            $sql .= " AND t.status = ?";
            $params[] = $statusFilter;
        }
        $sql .= " ORDER BY t.updated_at DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function countOpen(bool $isAdmin, ?int $providerId): int {
        if ($isAdmin) {
            return (int) ($this->db->fetchOne("SELECT COUNT(*) as c FROM ab_tickets WHERE status = 'open'")['c'] ?? 0);
        }
        return (int) ($this->db->fetchOne(
            "SELECT COUNT(*) as c FROM ab_tickets WHERE status = 'open' AND assigned_provider_id = ?",
            [$providerId]
        )['c'] ?? 0);
    }

    public function canStaffAccess(array $ticket, bool $isAdmin, int $userId): bool {
        if ($isAdmin) return true;
        return $ticket['category'] === 'commercial' && (int) $ticket['assigned_provider_id'] === $userId;
    }

    public function getAttachment(int $id): ?array {
        return $this->db->fetchOne(
            "SELECT a.*, m.ticket_id
             FROM ab_ticket_attachments a
             JOIN ab_ticket_messages m ON a.message_id = m.id
             WHERE a.id = ?",
            [$id]
        );
    }

    public function attachmentPath(array $attachment): string {
        return $this->storageDir() . $attachment['stored_filename'];
    }

    private function storeAttachments(int $messageId, array $files): void {
        if (empty($files)) return;

        $normalized = $this->normalizeFilesArray($files);
        $count = 0;

        foreach ($normalized as $file) {
            if ($count >= self::MAX_FILES_PER_MESSAGE) break;
            if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] <= 0) continue;
            if ($file['size'] > $this->maxUploadSize()) continue;
            if (!is_uploaded_file($file['tmp_name'])) continue;

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!isset(self::ALLOWED_EXTENSIONS[$ext])) continue;

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $actualMime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = array_unique(array_values(self::ALLOWED_EXTENSIONS));
            $mimeOk = in_array($actualMime, $allowedMimes, true)
                || ($ext === 'csv' && $actualMime === 'text/plain'); // libmagic often misreports plain CSV
            if (!$mimeOk) continue;

            $destDir = $this->storageDir();
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            $storedName = bin2hex(random_bytes(16)) . '.' . $ext;

            if (!move_uploaded_file($file['tmp_name'], $destDir . $storedName)) {
                continue;
            }

            $this->db->insert('ab_ticket_attachments', [
                'message_id' => $messageId,
                'original_filename' => mb_substr($file['name'], 0, 255),
                'stored_filename' => $storedName,
                'mime_type' => self::ALLOWED_EXTENSIONS[$ext],
                'size_bytes' => $file['size'],
            ]);
            $count++;
        }
    }

    private function normalizeFilesArray(array $files): array {
        if (!isset($files['name'])) return [];

        if (is_array($files['name'])) {
            $out = [];
            foreach ($files['name'] as $i => $name) {
                if ($name === '') continue;
                $out[] = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i],
                ];
            }
            return $out;
        }

        if ($files['name'] === '') return [];
        return [$files];
    }

    private function notifyCreated(array $ticket, string $firstMessageBody): void {
        $mailer = new Mailer();
        $customerName = $ticket['customer_first_name'] . ' ' . $ticket['customer_last_name'];
        $categoryLabel = $this->categoryLabel($ticket['category']);
        $ticketUrl = ab_url('public/ticket.php?hash=' . $ticket['hash']);
        $adminUrl = ab_url('admin/index.php?page=tickets&action=view&id=' . $ticket['id']);

        $mailer->sendTemplate('ticket_created_customer', $ticket['customer_email'], [
            'customer_name' => $customerName,
            'subject' => $ticket['subject'],
            'category_label' => $categoryLabel,
            'ticket_url' => $ticketUrl,
            'business_name' => ab_setting('business_name'),
        ]);

        // Don't notify staff of a ticket they just opened themselves.
        if ($ticket['created_by'] !== 'staff') {
            $this->notifyStaff($ticket, 'ticket_created_staff', [
                'customer_name' => $customerName,
                'subject' => $ticket['subject'],
                'category_label' => $categoryLabel,
                'message_excerpt' => $this->excerpt($firstMessageBody),
                'admin_url' => $adminUrl,
            ]);
        }
    }

    private function notifyReply(array $ticket, string $senderType, string $body): void {
        $mailer = new Mailer();
        $customerName = $ticket['customer_first_name'] . ' ' . $ticket['customer_last_name'];
        $excerpt = $this->excerpt($body);

        if ($senderType === 'customer') {
            $this->notifyStaff($ticket, 'ticket_reply_staff', [
                'customer_name' => $customerName,
                'subject' => $ticket['subject'],
                'message_excerpt' => $excerpt,
                'admin_url' => ab_url('admin/index.php?page=tickets&action=view&id=' . $ticket['id']),
            ]);
        } else {
            $mailer->sendTemplate('ticket_reply_customer', $ticket['customer_email'], [
                'customer_name' => $customerName,
                'subject' => $ticket['subject'],
                'message_excerpt' => $excerpt,
                'ticket_url' => ab_url('public/ticket.php?hash=' . $ticket['hash']),
                'business_name' => ab_setting('business_name'),
            ]);
        }
    }

    private function notifyStaff(array $ticket, string $template, array $vars): void {
        $mailer = new Mailer();
        $recipients = [];

        if ($ticket['category'] === 'commercial' && !empty($ticket['provider_email'])) {
            $recipients[] = $ticket['provider_email'];
        } else {
            $adminEmail = ab_setting('business_email');
            if ($adminEmail) $recipients[] = $adminEmail;
            $admins = $this->db->fetchAll("SELECT email FROM ab_users WHERE role = 'admin' AND is_active = 1");
            foreach ($admins as $a) {
                $recipients[] = $a['email'];
            }
        }

        foreach (array_unique(array_filter($recipients)) as $email) {
            $mailer->sendTemplate($template, $email, $vars);
        }
    }

    private function excerpt(string $body): string {
        $plain = trim(strip_tags($body));
        $short = mb_strlen($plain) > 200 ? mb_substr($plain, 0, 200) . '…' : $plain;
        return nl2br(htmlspecialchars($short, ENT_QUOTES, 'UTF-8'));
    }
}
