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
     * Get days in a month that have at least one available slot.
     * Uses a single DB round-trip per data type (not per day) for performance.
     */
    public function getAvailableDays(int $providerId, int $serviceId, int $year, int $month): array {
        $service = $this->db->fetchOne("SELECT * FROM ab_services WHERE id = ? AND is_active = 1", [$serviceId]);
        if (!$service) return [];

        // All working hours for this provider indexed by day_of_week
        $workingHoursAll = $this->db->fetchAll(
            "SELECT * FROM ab_working_hours WHERE provider_id = ? AND is_active = 1",
            [$providerId]
        );
        $workingHoursByDay = [];
        foreach ($workingHoursAll as $wh) {
            $workingHoursByDay[(int)$wh['day_of_week']] = $wh;
        }

        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $daysInMonth = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
        $monthEnd = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        // All holidays overlapping this month
        $holidays = $this->db->fetchAll(
            "SELECT date_start, date_end FROM ab_holidays
             WHERE (provider_id = ? OR provider_id IS NULL) AND date_end >= ? AND date_start <= ?",
            [$providerId, $monthStart, $monthEnd]
        );

        // All existing appointments this month, grouped by date
        $appointments = $this->db->fetchAll(
            "SELECT DATE(start_datetime) as appt_date, start_datetime, end_datetime
             FROM ab_appointments
             WHERE provider_id = ? AND DATE(start_datetime) BETWEEN ? AND ? AND status NOT IN ('cancelled')",
            [$providerId, $monthStart, $monthEnd]
        );
        $appointmentsByDate = [];
        foreach ($appointments as $appt) {
            $appointmentsByDate[$appt['appt_date']][] = $appt;
        }

        // All breaks for this provider, grouped by day_of_week
        $breaks = $this->db->fetchAll(
            "SELECT day_of_week, start_time, end_time FROM ab_breaks WHERE provider_id = ?",
            [$providerId]
        );
        $breaksByDay = [];
        foreach ($breaks as $brk) {
            $breaksByDay[(int)$brk['day_of_week']][] = $brk;
        }

        $interval = (int) Settings::get('slot_interval', '15');
        $now = time();
        $minAdvance = (int) Settings::get('booking_advance_min', '60') * 60;
        $maxAdvance = (int) Settings::get('booking_advance_max', '43200') * 60;

        $availableDays = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $dayOfWeek = (int) date('w', strtotime($date));

            if (!isset($workingHoursByDay[$dayOfWeek])) continue;
            $wh = $workingHoursByDay[$dayOfWeek];

            // Check holiday
            $isHoliday = false;
            foreach ($holidays as $h) {
                if ($date >= $h['date_start'] && $date <= $h['date_end']) {
                    $isHoliday = true;
                    break;
                }
            }
            if ($isHoliday) continue;

            $startTime = strtotime($date . ' ' . $wh['start_time']);
            $endTime   = strtotime($date . ' ' . $wh['end_time']);
            $dayAppointments = $appointmentsByDate[$date] ?? [];
            $dayBreaks = $breaksByDay[$dayOfWeek] ?? [];

            // Lunch break timestamps (computed once per day)
            $lunchStart = ($wh['break_start'] && $wh['break_end']) ? strtotime($date . ' ' . $wh['break_start']) : null;
            $lunchEnd   = $lunchStart ? strtotime($date . ' ' . $wh['break_end']) : null;

            $hasSlot = false;
            for ($time = $startTime; $time + ($service['duration'] * 60) <= $endTime; $time += $interval * 60) {
                if ($time < $now + $minAdvance || $time > $now + $maxAdvance) continue;

                $slotStart = $time + ($service['buffer_before'] * 60);
                $blockEnd  = $slotStart + ($service['duration'] * 60) + ($service['buffer_after'] * 60);

                if ($lunchStart !== null && $time < $lunchEnd && $blockEnd > $lunchStart) continue;

                $inBreak = false;
                foreach ($dayBreaks as $brk) {
                    $bStart = strtotime($date . ' ' . $brk['start_time']);
                    $bEnd   = strtotime($date . ' ' . $brk['end_time']);
                    if ($time < $bEnd && $blockEnd > $bStart) { $inBreak = true; break; }
                }
                if ($inBreak) continue;

                $conflict = false;
                foreach ($dayAppointments as $appt) {
                    $aStart = strtotime($appt['start_datetime']);
                    $aEnd   = strtotime($appt['end_datetime']);
                    if ($time < $aEnd && $blockEnd > $aStart) { $conflict = true; break; }
                }
                if ($conflict) continue;

                $hasSlot = true;
                break;
            }

            if ($hasSlot) $availableDays[] = $date;
        }

        return $availableDays;
    }

    /**
     * Create a new appointment
     */
    public function create(array $data): ?array {
        $service = $this->db->fetchOne("SELECT * FROM ab_services WHERE id = ? AND is_active = 1", [$data['service_id']]);
        if (!$service) return null;

        $endDatetime = date('Y-m-d H:i:s', strtotime($data['start_datetime']) + ($service['duration'] * 60));
        $hash = ab_generate_hash();

        $needsGuardian = !empty($data['needs_guardian']);
        $guardianToken = null;
        $guardianTokenExpires = null;
        if ($needsGuardian) {
            $guardianToken = bin2hex(random_bytes(24));
            $guardianTokenExpires = date('Y-m-d H:i:s', strtotime('+7 days'));
        }

        $this->db->beginTransaction();
        try {
            // Create or update customer
            $customer = $this->db->fetchOne("SELECT id FROM ab_customers WHERE email = ?", [$data['email']]);
            if ($customer) {
                $customerId = $customer['id'];
                $dobUpdate = [];
                if (!empty($data['date_of_birth'])) {
                    $dobUpdate['date_of_birth'] = $data['date_of_birth'];
                }
                $this->db->update('ab_customers', array_merge([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'phone' => $data['phone'] ?? '',
                ], $dobUpdate), 'id = ?', [$customerId]);
            } else {
                $customerId = $this->db->insert('ab_customers', [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? '',
                    'notes' => $data['customer_notes'] ?? '',
                    'date_of_birth' => $data['date_of_birth'] ?? null,
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
                'needs_guardian' => $needsGuardian ? 1 : 0,
                'guardian_first_name' => $data['guardian_first_name'] ?? null,
                'guardian_last_name' => $data['guardian_last_name'] ?? null,
                'guardian_phone' => $data['guardian_phone'] ?? null,
                'guardian_email' => $data['guardian_email'] ?? null,
                'guardian_token' => $guardianToken,
                'guardian_token_expires' => $guardianTokenExpires,
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

            // Save selected options inside transaction
            $savedOptions = [];
            if (!empty($data['options'])) {
                $placeholders = implode(',', array_fill(0, count($data['options']), '?'));
                $validOpts = $this->db->fetchAll(
                    "SELECT id, name, price_type, price_value FROM ab_booking_options WHERE id IN ($placeholders) AND is_active = 1",
                    $data['options']
                );
                foreach ($validOpts as $opt) {
                    $optPrice = $opt['price_type'] === 'percentage'
                        ? round($service['price'] * (float)$opt['price_value'] / 100, 2)
                        : (float)$opt['price_value'];
                    $this->db->insert('ab_appointment_options', [
                        'appointment_id' => $appointmentId,
                        'option_id'      => $opt['id'],
                        'option_name'    => $opt['name'],
                        'option_price'   => $optPrice,
                    ]);
                    $savedOptions[] = ['name' => $opt['name'], 'price' => $optPrice];
                }
            }

            $this->db->commit();

            return [
                'id' => $appointmentId,
                'hash' => $hash,
                'status' => $status,
                'deposit' => $depositInfo,
                'needs_guardian' => $needsGuardian,
                'options' => $savedOptions,
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
            // Sync to CalDAV
            try {
                $caldav = new CalDAV();
                if ($caldav->isEnabledFor($appointment['provider_id'])) {
                    $caldav->syncAppointment($appointmentId);
                }
            } catch (Exception $e) {
                error_log('ABAppointments CalDAV sync error: ' . $e->getMessage());
            }
        } elseif ($status === 'cancelled') {
            $this->sendStatusEmail($appointment, 'appointment_cancelled');
            // Remove from CalDAV
            try {
                $caldav = new CalDAV();
                if ($caldav->isEnabledFor($appointment['provider_id'])) {
                    $caldav->deleteEvent($appointmentId);
                }
            } catch (Exception $e) {
                error_log('ABAppointments CalDAV delete error: ' . $e->getMessage());
            }
            // Notify matching waitlist entries that a slot just freed up
            if (ab_feature_enabled('waitlist')) {
                try {
                    (new Waitlist())->notifyForFreedSlot(
                        $appointment['service_id'],
                        $appointment['provider_id'],
                        date('Y-m-d', strtotime($appointment['start_datetime']))
                    );
                } catch (Exception $e) {
                    error_log('ABAppointments waitlist notify error: ' . $e->getMessage());
                }
            }
        } elseif ($status === 'completed') {
            if (ab_feature_enabled('reviews')) {
                try {
                    (new Reviews())->requestForAppointment($appointment);
                } catch (Exception $e) {
                    error_log('ABAppointments review request error: ' . $e->getMessage());
                }
            }
        }

        return true;
    }

    /**
     * Reschedule an appointment to a new start date/time (provider/admin action)
     * Returns ['success' => bool, 'error' => string|null]
     */
    public function reschedule(int $appointmentId, string $newStartDatetime): array {
        $appointment = $this->getAppointment($appointmentId);
        if (!$appointment) return ['success' => false, 'error' => 'not_found'];
        if ($appointment['status'] === 'cancelled') return ['success' => false, 'error' => 'cancelled'];

        $service = $this->db->fetchOne("SELECT * FROM ab_services WHERE id = ?", [$appointment['service_id']]);
        if (!$service) return ['success' => false, 'error' => 'service_not_found'];

        $newEndDatetime = date('Y-m-d H:i:s', strtotime($newStartDatetime) + ($service['duration'] * 60));

        // Check for conflicts with the provider's other appointments
        $conflict = $this->db->fetchOne(
            "SELECT id FROM ab_appointments
             WHERE provider_id = ? AND id != ? AND status NOT IN ('cancelled')
               AND start_datetime < ? AND end_datetime > ?",
            [$appointment['provider_id'], $appointmentId, $newEndDatetime, $newStartDatetime]
        );
        if ($conflict) return ['success' => false, 'error' => 'conflict'];

        $oldDate = ab_format_date($appointment['start_datetime']);
        $oldTime = ab_format_time($appointment['start_datetime']);

        $this->db->update('ab_appointments', [
            'start_datetime' => $newStartDatetime,
            'end_datetime' => $newEndDatetime,
        ], 'id = ?', [$appointmentId]);

        $updated = $this->getAppointment($appointmentId);
        $this->sendRescheduleEmail($updated, $oldDate, $oldTime);

        try {
            $caldav = new CalDAV();
            if ($caldav->isEnabledFor($updated['provider_id'])) {
                $caldav->syncAppointment($appointmentId);
            }
        } catch (Exception $e) {
            error_log('ABAppointments CalDAV sync error: ' . $e->getMessage());
        }

        // The old slot just freed up - notify matching waitlist entries
        try {
            (new Waitlist())->notifyForFreedSlot(
                $appointment['service_id'],
                $appointment['provider_id'],
                date('Y-m-d', strtotime($appointment['start_datetime']))
            );
        } catch (Exception $e) {
            error_log('ABAppointments waitlist notify error: ' . $e->getMessage());
        }

        return ['success' => true];
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
            // Sync to CalDAV
            try {
                $caldav = new CalDAV();
                if ($caldav->isEnabledFor($appointment['provider_id'])) {
                    $caldav->syncAppointment($appointment['id']);
                }
            } catch (Exception $e) {
                error_log('ABAppointments CalDAV sync error: ' . $e->getMessage());
            }

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
                    u.first_name as provider_first_name, u.last_name as provider_last_name,
                    u.email as provider_email
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
                    u.first_name as provider_first_name, u.last_name as provider_last_name,
                    u.email as provider_email
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
        $businessName = ab_setting('business_name');

        // Build options summary HTML for emails
        $appointmentOptions = $this->db->fetchAll(
            "SELECT option_name, option_price FROM ab_appointment_options WHERE appointment_id = ? ORDER BY id",
            [$appointmentId]
        );
        $optionsHtml = '';
        $optionsTotal = 0.0;
        foreach ($appointmentOptions as $ao) {
            $optionsTotal += (float)$ao['option_price'];
            $optionsHtml .= '<tr><td style="padding:4px 12px;color:#666;">'
                . ab_escape($ao['option_name']) . '</td>'
                . '<td style="padding:4px 12px;text-align:right;">+'
                . number_format((float)$ao['option_price'], 2, ',', ' ') . ' €</td></tr>';
        }
        $totalPrice = (float)$appointment['service_price'] + $optionsTotal;
        $optionsSummaryHtml = '';
        if ($optionsTotal > 0) {
            $optionsSummaryHtml = '<div style="margin:15px 0;padding:12px 15px;background:#f8f9fa;border-radius:6px;border-left:3px solid #e91e63;">'
                . '<strong>Détail du tarif :</strong>'
                . '<table style="width:100%;margin-top:8px;">'
                . '<tr><td style="padding:4px 12px;color:#666;">Prestation</td>'
                . '<td style="padding:4px 12px;text-align:right;">' . number_format((float)$appointment['service_price'], 2, ',', ' ') . ' €</td></tr>'
                . $optionsHtml
                . '<tr style="border-top:1px solid #dee2e6;font-weight:bold;">'
                . '<td style="padding:8px 12px;">Total</td>'
                . '<td style="padding:8px 12px;text-align:right;">' . number_format($totalPrice, 2, ',', ' ') . ' €</td></tr>'
                . '</table></div>';
        }

        // If guardian required: send guardian-specific emails instead
        if (!empty($appointment['needs_guardian'])) {
            $this->sendGuardianNotifications($appointment, $mailer, $customerName, $businessName);
            return;
        }

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

        // Prepend options summary to deposit section
        $depositSection = $optionsSummaryHtml . $depositSection;

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
        $notifVars = [
            'customer_name' => $customerName,
            'customer_email' => $appointment['customer_email'],
            'customer_phone' => $appointment['customer_phone'],
            'service_name' => $appointment['service_name'],
            'appointment_date' => ab_format_date($appointment['start_datetime']),
            'appointment_time' => ab_format_time($appointment['start_datetime']),
            'admin_url' => ab_url('admin/index.php?page=appointments'),
            'options_summary' => $optionsSummaryHtml,
        ];

        if ($adminEmail) {
            $mailer->sendTemplate('admin_new_appointment', $adminEmail, $notifVars);
        }

        // Send provider notification (if different from admin email)
        $providerEmail = $appointment['provider_email'] ?? '';
        if ($providerEmail && $providerEmail !== $adminEmail) {
            $mailer->sendTemplate('admin_new_appointment', $providerEmail, $notifVars);
        }
    }

    /**
     * Send guardian-flow notifications (customer, guardian, admin)
     */
    private function sendGuardianNotifications(array $appointment, Mailer $mailer, string $customerName, string $businessName): void {
        $apptDate    = ab_format_date($appointment['start_datetime']);
        $apptTime    = ab_format_time($appointment['start_datetime']);
        $guardianName = $appointment['guardian_first_name'] . ' ' . $appointment['guardian_last_name'];
        $confirmUrl  = ab_url('public/guardian-confirm.php?token=' . $appointment['guardian_token']);
        $adminUrl    = ab_url('admin/index.php?page=appointments');

        // 1. Email to customer: awaiting guardian
        $customerHtml = '<h2 style="color:#333;">Votre rendez-vous est en attente de validation</h2>'
            . '<p>Bonjour <strong>' . ab_escape($customerName) . '</strong>,</p>'
            . '<p>Votre demande de rendez-vous a bien été enregistrée :</p>'
            . '<table style="width:100%;border-collapse:collapse;margin:15px 0;font-size:15px;">'
            . '<tr style="background:#f9f9f9;"><td style="padding:8px 12px;color:#666;width:40%;">Prestation</td><td style="padding:8px 12px;font-weight:bold;">' . ab_escape($appointment['service_name']) . '</td></tr>'
            . '<tr><td style="padding:8px 12px;color:#666;">Date</td><td style="padding:8px 12px;">' . $apptDate . '</td></tr>'
            . '<tr style="background:#f9f9f9;"><td style="padding:8px 12px;color:#666;">Heure</td><td style="padding:8px 12px;">' . $apptTime . '</td></tr>'
            . '</table>'
            . '<p>Un email de confirmation a été envoyé à votre accompagnateur <strong>' . ab_escape($guardianName) . '</strong> '
            . '(' . ab_escape($appointment['guardian_email']) . '). '
            . 'Votre rendez-vous sera confirmé dès que celui-ci aura validé sa présence.</p>'
            . '<p style="color:#888;font-size:0.9em;">Si vous n\'avez pas reçu de confirmation dans 48h, contactez-nous directement.</p>';

        $mailer->send($appointment['customer_email'], 'Rendez-vous en attente de validation – ' . $businessName, $customerHtml, $customerName);

        // 2. Email to guardian: please confirm
        $guardianHtml = '<h2 style="color:#333;">Confirmation requise : accompagnement de ' . ab_escape($appointment['customer_first_name']) . '</h2>'
            . '<p>Bonjour <strong>' . ab_escape($guardianName) . '</strong>,</p>'
            . '<p><strong>' . ab_escape($customerName) . '</strong> souhaite prendre rendez-vous chez <strong>' . ab_escape($businessName) . '</strong> et vous a désigné(e) comme accompagnateur(trice).</p>'
            . '<table style="width:100%;border-collapse:collapse;margin:15px 0;font-size:15px;">'
            . '<tr style="background:#f9f9f9;"><td style="padding:8px 12px;color:#666;width:40%;">Prestation</td><td style="padding:8px 12px;font-weight:bold;">' . ab_escape($appointment['service_name']) . '</td></tr>'
            . '<tr><td style="padding:8px 12px;color:#666;">Date</td><td style="padding:8px 12px;">' . $apptDate . '</td></tr>'
            . '<tr style="background:#f9f9f9;"><td style="padding:8px 12px;color:#666;">Heure</td><td style="padding:8px 12px;">' . $apptTime . '</td></tr>'
            . '<tr><td style="padding:8px 12px;color:#666;">Durée</td><td style="padding:8px 12px;">' . $appointment['service_duration'] . ' minutes</td></tr>'
            . '</table>'
            . '<p>Votre <strong>présence physique</strong> au rendez-vous est requise en tant qu\'accompagnateur(trice). '
            . 'En cliquant sur le bouton ci-dessous, vous confirmez que vous serez présent(e).</p>'
            . '<div style="text-align:center;margin:30px 0;">'
            . '<a href="' . $confirmUrl . '" style="background:#28a745;color:#ffffff;padding:15px 30px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:1.1em;display:inline-block;">✔&nbsp; Je confirme ma présence</a>'
            . '</div>'
            . '<p style="color:#888;font-size:0.85em;">Si vous n\'êtes pas concerné(e) par ce message, ignorez-le. Ce lien est valable 7 jours.</p>';

        $mailer->send($appointment['guardian_email'], 'Confirmation requise – accompagnement de ' . $appointment['customer_first_name'] . ' chez ' . $businessName, $guardianHtml, $guardianName);

        // 3. Email to admin: new booking pending guardian
        $adminEmail = ab_setting('business_email');
        if ($adminEmail) {
            $adminHtml = '<h2 style="color:#e65100;">⏳ Nouveau RDV – accord adulte requis</h2>'
                . '<p>Un nouveau rendez-vous a été pris et nécessite la validation d\'un accompagnateur adulte.</p>'
                . '<table style="width:100%;border-collapse:collapse;margin:15px 0;font-size:15px;">'
                . '<tr style="background:#f9f9f9;"><td style="padding:8px 12px;color:#666;width:40%;">Client</td><td style="padding:8px 12px;">' . ab_escape($customerName) . '</td></tr>'
                . '<tr><td style="padding:8px 12px;color:#666;">Email client</td><td style="padding:8px 12px;">' . ab_escape($appointment['customer_email']) . '</td></tr>'
                . '<tr style="background:#f9f9f9;"><td style="padding:8px 12px;color:#666;">Prestation</td><td style="padding:8px 12px;font-weight:bold;">' . ab_escape($appointment['service_name']) . '</td></tr>'
                . '<tr><td style="padding:8px 12px;color:#666;">Date / Heure</td><td style="padding:8px 12px;">' . $apptDate . ' à ' . $apptTime . '</td></tr>'
                . '<tr style="background:#f9f9f9;"><td style="padding:8px 12px;color:#666;">Accompagnateur</td><td style="padding:8px 12px;">' . ab_escape($guardianName) . '<br><small style="color:#888;">' . ab_escape($appointment['guardian_email']) . ' – ' . ab_escape($appointment['guardian_phone']) . '</small></td></tr>'
                . '<tr><td style="padding:8px 12px;color:#666;">Accord adulte</td><td style="padding:8px 12px;color:#e65100;font-weight:bold;">⏳ En attente</td></tr>'
                . '</table>'
                . '<p><a href="' . $adminUrl . '" style="background:#e91e63;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold;">Voir les rendez-vous</a></p>';

            $mailer->send($adminEmail, '⏳ Accord adulte requis – ' . $customerName . ' (' . $businessName . ')', $adminHtml);
        }
    }

    /**
     * Confirm a guardian token and notify admin
     * Returns the appointment data on success, null on failure
     */
    public function confirmByGuardianToken(string $token): ?array {
        $appointment = $this->db->fetchOne(
            "SELECT a.*,
                    c.first_name as customer_first_name, c.last_name as customer_last_name,
                    c.email as customer_email,
                    s.name as service_name, s.duration as service_duration,
                    u.first_name as provider_first_name, u.last_name as provider_last_name
             FROM ab_appointments a
             JOIN ab_customers c ON a.customer_id = c.id
             JOIN ab_services s ON a.service_id = s.id
             JOIN ab_users u ON a.provider_id = u.id
             WHERE a.guardian_token = ?
               AND a.guardian_token_expires > NOW()
               AND a.guardian_confirmed_at IS NULL
               AND a.status NOT IN ('cancelled')",
            [$token]
        );

        if (!$appointment) return null;

        // Mark guardian confirmed, clear token
        $this->db->update('ab_appointments',
            [
                'guardian_confirmed_at' => date('Y-m-d H:i:s'),
                'guardian_token' => null,
                'guardian_token_expires' => null,
            ],
            'id = ?',
            [$appointment['id']]
        );

        // Notify admin
        $this->sendGuardianConfirmedNotification($appointment);

        return $appointment;
    }

    /**
     * Send admin notification when guardian confirms
     */
    private function sendGuardianConfirmedNotification(array $appointment): void {
        $adminEmail = ab_setting('business_email');
        if (!$adminEmail) return;

        $mailer = new Mailer();
        $businessName = ab_setting('business_name');
        $customerName = $appointment['customer_first_name'] . ' ' . $appointment['customer_last_name'];
        $guardianName = $appointment['guardian_first_name'] . ' ' . $appointment['guardian_last_name'];
        $apptDate = ab_format_date($appointment['start_datetime']);
        $apptTime = ab_format_time($appointment['start_datetime']);
        $adminUrl = ab_url('admin/index.php?page=appointments');

        $html = '<h2 style="color:#28a745;">✅ Accord adulte confirmé</h2>'
            . '<p>L\'accompagnateur a confirmé sa présence pour le rendez-vous suivant :</p>'
            . '<table style="width:100%;border-collapse:collapse;margin:15px 0;font-size:15px;">'
            . '<tr style="background:#f9f9f9;"><td style="padding:8px 12px;color:#666;width:40%;">Client</td><td style="padding:8px 12px;">' . ab_escape($customerName) . '</td></tr>'
            . '<tr><td style="padding:8px 12px;color:#666;">Prestation</td><td style="padding:8px 12px;font-weight:bold;">' . ab_escape($appointment['service_name']) . '</td></tr>'
            . '<tr style="background:#f9f9f9;"><td style="padding:8px 12px;color:#666;">Date / Heure</td><td style="padding:8px 12px;">' . $apptDate . ' à ' . $apptTime . '</td></tr>'
            . '<tr><td style="padding:8px 12px;color:#666;">Accompagnateur</td><td style="padding:8px 12px;">' . ab_escape($guardianName) . '</td></tr>'
            . '<tr style="background:#f9f9f9;"><td style="padding:8px 12px;color:#666;">Accord adulte</td><td style="padding:8px 12px;color:#28a745;font-weight:bold;">✅ Confirmé</td></tr>'
            . '</table>'
            . '<p><a href="' . $adminUrl . '" style="background:#e91e63;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold;">Gérer le rendez-vous</a></p>';

        $mailer->send($adminEmail, '✅ Accord adulte confirmé – ' . $customerName . ' (' . $businessName . ')', $html);
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

    private function sendRescheduleEmail(array $appointment, string $oldDate, string $oldTime): void {
        $mailer = new Mailer();
        $customerName = $appointment['customer_first_name'] . ' ' . $appointment['customer_last_name'];

        $mailer->sendTemplate('appointment_modified', $appointment['customer_email'], [
            'customer_name' => $customerName,
            'service_name' => $appointment['service_name'],
            'old_date' => $oldDate,
            'old_time' => $oldTime,
            'appointment_date' => ab_format_date($appointment['start_datetime']),
            'appointment_time' => ab_format_time($appointment['start_datetime']),
            'business_name' => ab_setting('business_name'),
            'manage_url' => ab_url('manage/' . $appointment['hash']),
        ]);
    }
}
