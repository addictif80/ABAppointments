<?php
/**
 * ABAppointments - Appointment Manager
 */
class AppointmentManager {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get available time slots for a provider on a given date
     */
    private array $debugInfo = [];

    public function getDebugInfo(): array {
        return $this->debugInfo;
    }

    public function getAvailableSlots(int $providerId, int $serviceId, string $date): array {
        $this->debugInfo = [];

        $service = $this->db->fetchOne("SELECT * FROM ab_services WHERE id = ? AND is_active = 1", [$serviceId]);
        if (!$service) {
            $this->debugInfo['reason'] = 'Service introuvable ou inactif (id=' . $serviceId . ')';
            return [];
        }
        $this->debugInfo['service'] = $service['name'] . ' (' . $service['duration'] . 'min)';

        $dayOfWeek = (int) date('w', strtotime($date));
        $this->debugInfo['day_of_week'] = $dayOfWeek;
        $workingHours = $this->db->fetchOne(
            "SELECT * FROM ab_working_hours WHERE provider_id = ? AND day_of_week = ? AND is_active = 1",
            [$providerId, $dayOfWeek]
        );
        if (!$workingHours) {
            $allHours = $this->db->fetchAll("SELECT day_of_week, start_time, end_time, is_active FROM ab_working_hours WHERE provider_id = ?", [$providerId]);
            $this->debugInfo['reason'] = 'Pas d\'horaires pour ce prestataire (id=' . $providerId . ') le jour ' . $dayOfWeek;
            $this->debugInfo['all_hours'] = $allHours;
            return [];
        }
        $this->debugInfo['working_hours'] = $workingHours['start_time'] . ' - ' . $workingHours['end_time'];

        // Check if holiday
        $holiday = $this->db->fetchOne(
            "SELECT id FROM ab_holidays WHERE (provider_id = ? OR provider_id IS NULL) AND ? BETWEEN date_start AND date_end",
            [$providerId, $date]
        );
        if ($holiday) {
            $this->debugInfo['reason'] = 'Jour férié/congé';
            return [];
        }

        // Get existing appointments
        $appointments = $this->db->fetchAll(
            "SELECT start_datetime, end_datetime FROM ab_appointments
             WHERE provider_id = ? AND DATE(start_datetime) = ? AND status NOT IN ('cancelled')",
            [$providerId, $date]
        );

        // Get breaks
        $breaks = $this->db->fetchAll(
            "SELECT start_time, end_time FROM ab_breaks WHERE provider_id = ? AND day_of_week = ?",
            [$providerId, $dayOfWeek]
        );

        $interval = (int) Settings::get('slot_interval', '15');
        $duration = $service['duration'] + $service['buffer_before'] + $service['buffer_after'];
        $slots = [];

        $startTime = strtotime($date . ' ' . $workingHours['start_time']);
        $endTime = strtotime($date . ' ' . $workingHours['end_time']);

        // Booking advance limits
        $now = time();
        $minAdvance = (int) Settings::get('booking_advance_min', '60') * 60;
        $maxAdvance = (int) Settings::get('booking_advance_max', '43200') * 60;

        $this->debugInfo['now'] = date('Y-m-d H:i:s', $now);
        $this->debugInfo['min_advance_hours'] = round($minAdvance / 3600, 1);
        $this->debugInfo['max_advance_days'] = round($maxAdvance / 86400, 1);
        $this->debugInfo['slot_range'] = date('H:i', $startTime) . ' - ' . date('H:i', $endTime);
        $this->debugInfo['earliest_bookable'] = date('Y-m-d H:i', $now + $minAdvance);
        $this->debugInfo['latest_bookable'] = date('Y-m-d H:i', $now + $maxAdvance);

        $skippedAdvance = 0;
        $skippedBreak = 0;
        $skippedConflict = 0;
        $totalChecked = 0;

        for ($time = $startTime; $time + ($service['duration'] * 60) <= $endTime; $time += $interval * 60) {
            $totalChecked++;
            // Check advance limits
            if ($time < $now + $minAdvance || $time > $now + $maxAdvance) {
                $skippedAdvance++;
                continue;
            }

            $slotStart = $time + ($service['buffer_before'] * 60);
            $slotEnd = $slotStart + ($service['duration'] * 60);
            $blockEnd = $slotEnd + ($service['buffer_after'] * 60);

            // Check working hours break
            if ($workingHours['break_start'] && $workingHours['break_end']) {
                $breakStart = strtotime($date . ' ' . $workingHours['break_start']);
                $breakEnd = strtotime($date . ' ' . $workingHours['break_end']);
                if ($time < $breakEnd && $blockEnd > $breakStart) {
                    $skippedBreak++;
                    continue;
                }
            }

            // Check additional breaks
            $inBreak = false;
            foreach ($breaks as $brk) {
                $bStart = strtotime($date . ' ' . $brk['start_time']);
                $bEnd = strtotime($date . ' ' . $brk['end_time']);
                if ($time < $bEnd && $blockEnd > $bStart) {
                    $inBreak = true;
                    break;
                }
            }
            if ($inBreak) {
                $skippedBreak++;
                continue;
            }

            // Check existing appointments
            $conflict = false;
            foreach ($appointments as $appt) {
                $aStart = strtotime($appt['start_datetime']);
                $aEnd = strtotime($appt['end_datetime']);
                if ($time < $aEnd && $blockEnd > $aStart) {
                    $conflict = true;
                    break;
                }
            }
            if ($conflict) {
                $skippedConflict++;
                continue;
            }

            $slots[] = date('H:i', $time);
        }

        $this->debugInfo['total_checked'] = $totalChecked;
        $this->debugInfo['skipped_advance'] = $skippedAdvance;
        $this->debugInfo['skipped_break'] = $skippedBreak;
        $this->debugInfo['skipped_conflict'] = $skippedConflict;
        $this->debugInfo['slots_found'] = count($slots);

        return $slots;
    }

    /**
     * Create a new appointment
     */
    public function create(array $data): ?array {
        $service = $this->db->fetchOne("SELECT * FROM ab_services WHERE id = ? AND is_active = 1", [$data['service_id']]);
        if (!$service) return null;

        $endDatetime = date('Y-m-d H:i:s', strtotime($data['start_datetime']) + ($service['duration'] * 60));
        $hash = ab_generate_hash();

        $this->db->beginTransaction();
        try {
            // Create or update customer
            $customer = $this->db->fetchOne("SELECT id FROM ab_customers WHERE email = ?", [$data['email']]);
            if ($customer) {
                $customerId = $customer['id'];
                $this->db->update('ab_customers', [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'phone' => $data['phone'] ?? '',
                ], 'id = ?', [$customerId]);
            } else {
                $customerId = $this->db->insert('ab_customers', [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? '',
                    'notes' => $data['customer_notes'] ?? '',
                ]);
            }

            $autoConfirm = Settings::get('auto_confirm', '0');
            $status = ($autoConfirm === '1' && !$service['deposit_enabled']) ? 'confirmed' : 'pending';

            $appointmentId = $this->db->insert('ab_appointments', [
                'hash' => $hash,
                'customer_id' => $customerId,
                'provider_id' => $data['provider_id'],
                'service_id' => $data['service_id'],
                'start_datetime' => $data['start_datetime'],
                'end_datetime' => $endDatetime,
                'status' => $status,
                'notes' => $data['notes'] ?? '',
            ]);

            // Handle deposit if required
            $depositInfo = null;
            if ($service['deposit_enabled']) {
                $depositAmount = $service['deposit_type'] === 'percentage'
                    ? round($service['price'] * $service['deposit_amount'] / 100, 2)
                    : $service['deposit_amount'];

                $dueDate = date('Y-m-d H:i:s', strtotime($data['start_datetime']) - 86400);

                $depositId = $this->db->insert('ab_deposits', [
                    'appointment_id' => $appointmentId,
                    'amount' => $depositAmount,
                    'currency' => $service['currency'],
                    'status' => 'pending',
                    'due_date' => $dueDate,
                ]);

                $depositInfo = [
                    'id' => $depositId,
                    'amount' => $depositAmount,
                    'due_date' => $dueDate,
                ];
            }

            $this->db->commit();

            return [
                'id' => $appointmentId,
                'hash' => $hash,
                'status' => $status,
                'deposit' => $depositInfo,
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Update appointment status
     */
    public function updateStatus(int $appointmentId, string $status): bool {
        $appointment = $this->getAppointment($appointmentId);
        if (!$appointment) return false;

        $this->db->update('ab_appointments', ['status' => $status], 'id = ?', [$appointmentId]);

        if ($status === 'confirmed') {
            $this->sendStatusEmail($appointment, 'appointment_confirmed');
        } elseif ($status === 'cancelled') {
            $this->sendStatusEmail($appointment, 'appointment_cancelled');
        }

        return true;
    }

    /**
     * Confirm deposit payment
     */
    public function confirmDeposit(int $depositId, string $paymentMethod = 'bank_transfer', string $reference = ''): bool {
        $deposit = $this->db->fetchOne("SELECT * FROM ab_deposits WHERE id = ?", [$depositId]);
        if (!$deposit) return false;

        $this->db->update('ab_deposits', [
            'status' => 'paid',
            'payment_method' => $paymentMethod,
            'payment_reference' => $reference,
            'paid_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$depositId]);

        // Auto-confirm appointment
        $this->db->update('ab_appointments', ['status' => 'confirmed'], 'id = ?', [$deposit['appointment_id']]);

        // Send confirmation email
        $appointment = $this->getAppointment($deposit['appointment_id']);
        if ($appointment) {
            $service = $this->db->fetchOne("SELECT * FROM ab_services WHERE id = ?", [$appointment['service_id']]);
            $remaining = $service ? $service['price'] - $deposit['amount'] : 0;

            $mailer = new Mailer();
            $mailer->sendTemplate('deposit_confirmed', $appointment['customer_email'], [
                'customer_name' => $appointment['customer_first_name'] . ' ' . $appointment['customer_last_name'],
                'service_name' => $appointment['service_name'],
                'appointment_date' => ab_format_date($appointment['start_datetime']),
                'appointment_time' => ab_format_time($appointment['start_datetime']),
                'deposit_amount' => number_format($deposit['amount'], 2, ',', ' '),
                'remaining_amount' => number_format($remaining, 2, ',', ' '),
                'business_name' => ab_setting('business_name'),
            ]);
        }

        return true;
    }

    /**
     * Cancel appointment by hash (customer action)
     */
    public function cancelByHash(string $hash): bool {
        $appointment = $this->db->fetchOne(
            "SELECT a.*, s.name as service_name FROM ab_appointments a
             JOIN ab_services s ON a.service_id = s.id WHERE a.hash = ?",
            [$hash]
        );
        if (!$appointment || $appointment['status'] === 'cancelled') return false;

        $cancellationLimit = (int) Settings::get('cancellation_limit', '1440');
        $appointmentTime = strtotime($appointment['start_datetime']);
        if ($appointmentTime - time() < $cancellationLimit * 60) {
            return false;
        }

        return $this->updateStatus($appointment['id'], 'cancelled');
    }

    /**
     * Get full appointment details
     */
    public function getAppointment(int $id): ?array {
        return $this->db->fetchOne(
            "SELECT a.*,
                    c.first_name as customer_first_name, c.last_name as customer_last_name,
                    c.email as customer_email, c.phone as customer_phone,
                    s.name as service_name, s.duration as service_duration, s.price as service_price,
                    s.color as service_color,
                    u.first_name as provider_first_name, u.last_name as provider_last_name
             FROM ab_appointments a
             JOIN ab_customers c ON a.customer_id = c.id
             JOIN ab_services s ON a.service_id = s.id
             JOIN ab_users u ON a.provider_id = u.id
             WHERE a.id = ?",
            [$id]
        );
    }

    /**
     * Get appointment by hash
     */
    public function getByHash(string $hash): ?array {
        $appointment = $this->db->fetchOne(
            "SELECT a.*,
                    c.first_name as customer_first_name, c.last_name as customer_last_name,
                    c.email as customer_email, c.phone as customer_phone,
                    s.name as service_name, s.duration as service_duration, s.price as service_price,
                    u.first_name as provider_first_name, u.last_name as provider_last_name
             FROM ab_appointments a
             JOIN ab_customers c ON a.customer_id = c.id
             JOIN ab_services s ON a.service_id = s.id
             JOIN ab_users u ON a.provider_id = u.id
             WHERE a.hash = ?",
            [$hash]
        );

        if ($appointment) {
            $appointment['deposit'] = $this->db->fetchOne(
                "SELECT * FROM ab_deposits WHERE appointment_id = ?",
                [$appointment['id']]
            );
        }

        return $appointment;
    }

    /**
     * Get appointments for calendar view
     */
    public function getForCalendar(?int $providerId, string $startDate, string $endDate): array {
        $sql = "SELECT a.*,
                       c.first_name as customer_first_name, c.last_name as customer_last_name,
                       c.phone as customer_phone,
                       s.name as service_name, s.color as service_color
                FROM ab_appointments a
                JOIN ab_customers c ON a.customer_id = c.id
                JOIN ab_services s ON a.service_id = s.id
                WHERE a.start_datetime BETWEEN ? AND ? AND a.status != 'cancelled'";
        $params = [$startDate, $endDate];

        if ($providerId) {
            $sql .= " AND a.provider_id = ?";
            $params[] = $providerId;
        }

        $sql .= " ORDER BY a.start_datetime";
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Send notifications for new appointment
     */
    public function sendNotifications(int $appointmentId, string $status, ?array $depositInfo): void {
        $appointment = $this->getAppointment($appointmentId);
        if (!$appointment) return;

        $mailer = new Mailer();
        $customerName = $appointment['customer_first_name'] . ' ' . $appointment['customer_last_name'];

        $depositSection = '';
        if ($depositInfo) {
            $depositSection = '<div style="background:#fff3cd;border:1px solid #ffc107;padding:15px;border-radius:5px;margin:15px 0;">'
                . '<strong>Acompte requis : ' . number_format($depositInfo['amount'], 2, ',', ' ') . ' €</strong><br>'
                . 'Date limite : ' . ab_format_date($depositInfo['due_date']) . '<br><br>'
                . nl2br(ab_escape(ab_setting('deposit_instructions')))
                . '</div>';

            // Send dedicated deposit email
            $mailer->sendTemplate('deposit_required', $appointment['customer_email'], [
                'customer_name' => $customerName,
                'service_name' => $appointment['service_name'],
                'appointment_date' => ab_format_date($appointment['start_datetime']),
                'appointment_time' => ab_format_time($appointment['start_datetime']),
                'deposit_amount' => number_format($depositInfo['amount'], 2, ',', ' '),
                'deposit_due_date' => ab_format_date($depositInfo['due_date']),
                'deposit_instructions' => ab_setting('deposit_instructions'),
                'business_name' => ab_setting('business_name'),
            ]);
        }

        // Send customer email
        $templateSlug = ($status === 'confirmed') ? 'appointment_confirmed' : 'appointment_pending';
        $mailer->sendTemplate($templateSlug, $appointment['customer_email'], [
            'customer_name' => $customerName,
            'service_name' => $appointment['service_name'],
            'appointment_date' => ab_format_date($appointment['start_datetime']),
            'appointment_time' => ab_format_time($appointment['start_datetime']),
            'service_duration' => $appointment['service_duration'],
            'service_price' => number_format($appointment['service_price'], 2, ',', ' '),
            'deposit_section' => $depositSection,
            'manage_url' => ab_url('manage/' . $appointment['hash']),
            'business_name' => ab_setting('business_name'),
        ]);

        // Send admin notification
        $adminEmail = ab_setting('business_email');
        if ($adminEmail) {
            $mailer->sendTemplate('admin_new_appointment', $adminEmail, [
                'customer_name' => $customerName,
                'customer_email' => $appointment['customer_email'],
                'customer_phone' => $appointment['customer_phone'],
                'service_name' => $appointment['service_name'],
                'appointment_date' => ab_format_date($appointment['start_datetime']),
                'appointment_time' => ab_format_time($appointment['start_datetime']),
                'admin_url' => ab_url('admin/index.php?page=appointments'),
            ]);
        }
    }

    private function sendStatusEmail(array $appointment, string $template): void {
        $mailer = new Mailer();
        $customerName = $appointment['customer_first_name'] . ' ' . $appointment['customer_last_name'];

        $vars = [
            'customer_name' => $customerName,
            'service_name' => $appointment['service_name'],
            'appointment_date' => ab_format_date($appointment['start_datetime']),
            'appointment_time' => ab_format_time($appointment['start_datetime']),
            'business_name' => ab_setting('business_name'),
        ];

        if ($template === 'appointment_confirmed') {
            $vars['service_duration'] = $appointment['service_duration'];
            $vars['service_price'] = number_format($appointment['service_price'], 2, ',', ' ');
            $vars['deposit_section'] = '';
            $vars['manage_url'] = ab_url('manage/' . $appointment['hash']);
        }

        $mailer->sendTemplate($template, $appointment['customer_email'], $vars);
    }
}
