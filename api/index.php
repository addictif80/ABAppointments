<?php
/**
 * ABAppointments - API
 */
require_once __DIR__ . '/../core/App.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$route = $_GET['route'] ?? '';
$db = Database::getInstance();
$manager = new AppointmentManager();

try {
    switch ($route) {
        case 'available-days':
            $serviceId  = (int)($_GET['service_id'] ?? 0);
            $providerId = (int)($_GET['provider_id'] ?? 0);
            $year       = (int)($_GET['year'] ?? 0);
            $month      = (int)($_GET['month'] ?? 0);

            if (!$serviceId || !$providerId || $year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
                ab_json(['error' => 'Paramètres invalides'], 400);
            }

            $days = $manager->getAvailableDays($providerId, $serviceId, $year, $month);
            ab_json(['days' => $days]);
            break;

        case 'available-slots':
            $serviceId = (int)($_GET['service_id'] ?? 0);
            $providerId = (int)($_GET['provider_id'] ?? 0);
            $date = $_GET['date'] ?? '';

            if (!$serviceId || !$providerId || !$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                ab_json(['error' => 'Paramètres invalides'], 400);
            }

            $slots = $manager->getAvailableSlots($providerId, $serviceId, $date);
            $response = ['slots' => $slots];
            if (AB_DEBUG) {
                $response['debug'] = $manager->getDebugInfo();
            }
            ab_json($response);
            break;

        case 'book':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                ab_json(['error' => 'Méthode non autorisée'], 405);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                ab_json(['error' => 'Données invalides'], 400);
            }

            $required = ['service_id', 'provider_id', 'date', 'time', 'first_name', 'last_name', 'email'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    ab_json(['error' => "Le champ '$field' est requis"], 400);
                }
            }

            if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                ab_json(['error' => 'Email invalide'], 400);
            }

            if (ab_setting('require_phone', '1') === '1' && empty($input['phone'])) {
                ab_json(['error' => 'Le téléphone est requis'], 400);
            }

            // Validate and parse date_of_birth
            $dobRaw = trim($input['date_of_birth'] ?? '');
            if (empty($dobRaw)) {
                ab_json(['error' => 'La date de naissance est requise'], 400);
            }
            $dobDb = ab_parse_dob($dobRaw);
            if (!$dobDb) {
                ab_json(['error' => 'Date de naissance invalide (format JJ.MM.AAAA)'], 400);
            }

            // Parse selected option IDs
            $selectedOptionIds = array_values(array_filter(
                array_map('intval', $input['options'] ?? []),
                fn($id) => $id > 0
            ));

            // Server-side age check
            $needsGuardian = false;
            if (ab_setting('age_check_enabled', '0') === '1') {
                $ageMinBooking = (int) ab_setting('age_min_booking', '10');
                $ageMinSolo    = (int) ab_setting('age_min_solo', '18');
                $dobDate = new DateTime($dobDb);
                $today   = new DateTime('today');
                $age     = (int) $today->diff($dobDate)->y;

                if ($ageMinBooking > 0 && $age < $ageMinBooking) {
                    ab_json(['error' => "Vous devez avoir au moins {$ageMinBooking} ans pour réserver en ligne."], 400);
                }
                if ($ageMinSolo > 0 && $age < $ageMinSolo) {
                    // Guardian required
                    $guardian = $input['guardian'] ?? null;
                    if (empty($guardian) || empty($guardian['first_name']) || empty($guardian['last_name'])
                        || empty($guardian['phone']) || empty($guardian['email'])) {
                        ab_json(['error' => 'Toutes les informations de l\'accompagnateur sont requises.'], 400);
                    }
                    if (!filter_var($guardian['email'], FILTER_VALIDATE_EMAIL)) {
                        ab_json(['error' => 'L\'email de l\'accompagnateur est invalide.'], 400);
                    }
                    $needsGuardian = true;
                }
            }

            // Validate slot availability
            $slots = $manager->getAvailableSlots((int)$input['provider_id'], (int)$input['service_id'], $input['date']);
            if (!in_array($input['time'], $slots)) {
                ab_json(['error' => 'Ce créneau n\'est plus disponible'], 400);
            }

            $startDatetime = $input['date'] . ' ' . $input['time'] . ':00';

            $result = $manager->create([
                'service_id' => (int)$input['service_id'],
                'provider_id' => (int)$input['provider_id'],
                'start_datetime' => $startDatetime,
                'first_name' => htmlspecialchars(trim($input['first_name']), ENT_QUOTES, 'UTF-8'),
                'last_name' => htmlspecialchars(trim($input['last_name']), ENT_QUOTES, 'UTF-8'),
                'email' => trim($input['email']),
                'phone' => htmlspecialchars(trim($input['phone'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'notes' => htmlspecialchars(trim($input['notes'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'date_of_birth' => $dobDb,
                'needs_guardian'       => $needsGuardian,
                'guardian_first_name'  => $needsGuardian ? htmlspecialchars(trim($input['guardian']['first_name']), ENT_QUOTES, 'UTF-8') : null,
                'guardian_last_name'   => $needsGuardian ? htmlspecialchars(trim($input['guardian']['last_name']), ENT_QUOTES, 'UTF-8') : null,
                'guardian_phone'       => $needsGuardian ? htmlspecialchars(trim($input['guardian']['phone']), ENT_QUOTES, 'UTF-8') : null,
                'guardian_email'       => $needsGuardian ? trim($input['guardian']['email']) : null,
                'options'              => $selectedOptionIds,
            ]);

            if ($result) {
                $responseData = [
                    'success' => true,
                    'hash' => $result['hash'],
                    'status' => $result['status'],
                    'needs_guardian' => $result['needs_guardian'] ?? false,
                    'deposit' => $result['deposit'],
                    'manage_url' => ab_url('manage/' . $result['hash']),
                ];

                // Send response immediately, then do slow tasks (emails, calendar sync)
                http_response_code(200);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($responseData, JSON_UNESCAPED_UNICODE);

                // Flush response to client before sending emails
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                } else {
                    if (ob_get_level() > 0) ob_end_flush();
                    flush();
                }

                // Now send emails (client won't wait)
                try {
                    $manager->sendNotifications($result['id'], $result['status'], $result['deposit']);
                } catch (Exception $e) {
                    // Log email error silently
                    error_log('ABAppointments email error: ' . $e->getMessage());
                }

                // Sync to Google Calendar
                try {
                    if (ab_feature_enabled('google_calendar')) {
                        $gcal = new GoogleCalendar();
                        if ($gcal->isConfigured()) {
                            $gcal->syncAppointment($result['id']);
                        }
                    }
                } catch (Exception $e) {
                    error_log('ABAppointments gcal error: ' . $e->getMessage());
                }

                // Sync to CalDAV
                try {
                    $caldav = new CalDAV();
                    $caldav->syncAppointment($result['id']);
                } catch (Exception $e) {
                    error_log('ABAppointments caldav error: ' . $e->getMessage());
                }

                exit;
            } else {
                ab_json(['error' => 'Erreur lors de la création du rendez-vous'], 500);
            }
            break;

        case 'waitlist-join':
            if (!ab_feature_enabled('waitlist')) {
                ab_json(['error' => 'Fonctionnalité désactivée'], 403);
            }
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                ab_json(['error' => 'Méthode non autorisée'], 405);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                ab_json(['error' => 'Données invalides'], 400);
            }

            $required = ['service_id', 'first_name', 'last_name', 'email', 'desired_date_start', 'desired_date_end'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    ab_json(['error' => "Le champ '$field' est requis"], 400);
                }
            }

            if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                ab_json(['error' => 'Email invalide'], 400);
            }

            $service = $db->fetchOne("SELECT id FROM ab_services WHERE id = ? AND is_active = 1", [(int)$input['service_id']]);
            if (!$service) {
                ab_json(['error' => 'Prestation invalide'], 400);
            }

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['desired_date_start']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['desired_date_end'])) {
                ab_json(['error' => 'Dates invalides'], 400);
            }

            $waitlist = new Waitlist();
            $waitlist->join([
                'service_id' => (int)$input['service_id'],
                'provider_id' => (int)($input['provider_id'] ?? 0),
                'first_name' => htmlspecialchars(trim($input['first_name']), ENT_QUOTES, 'UTF-8'),
                'last_name' => htmlspecialchars(trim($input['last_name']), ENT_QUOTES, 'UTF-8'),
                'email' => trim($input['email']),
                'phone' => htmlspecialchars(trim($input['phone'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'desired_date_start' => $input['desired_date_start'],
                'desired_date_end' => $input['desired_date_end'],
            ]);

            ab_json(['success' => true]);
            break;

        case 'calendar-events':
            // For admin calendar (FullCalendar)
            if (!Auth::check()) {
                ab_json(['error' => 'Non autorisé'], 401);
            }

            $start = $_GET['start'] ?? date('Y-m-01');
            $end = $_GET['end'] ?? date('Y-m-t');
            $providerId = Auth::isAdmin() ? null : Auth::userId();

            $appointments = $manager->getForCalendar($providerId, $start, $end);

            $events = array_map(function($a) {
                return [
                    'id' => $a['id'],
                    'title' => $a['customer_first_name'] . ' ' . $a['customer_last_name'] . ' - ' . $a['service_name'],
                    'start' => $a['start_datetime'],
                    'end' => $a['end_datetime'],
                    'color' => $a['service_color'] ?? '#e91e63',
                    'extendedProps' => [
                        'status' => $a['status'],
                        'phone' => $a['customer_phone'],
                    ],
                ];
            }, $appointments);

            // Add holidays as all-day events
            $holidaySql = "SELECT h.id, h.title, h.date_start, h.date_end, h.provider_id,
                                  u.first_name as provider_first, u.last_name as provider_last
                           FROM ab_holidays h
                           LEFT JOIN ab_users u ON h.provider_id = u.id
                           WHERE h.date_end >= ? AND h.date_start <= ?";
            $holidayParams = [$start, $end];
            if ($providerId) {
                $holidaySql .= " AND (h.provider_id = ? OR h.provider_id IS NULL)";
                $holidayParams[] = $providerId;
            }
            $holidays = $db->fetchAll($holidaySql, $holidayParams);

            foreach ($holidays as $h) {
                $isGlobal = $h['provider_id'] === null;
                $providerLabel = $isGlobal ? '' : ($h['provider_first'] . ' ' . $h['provider_last'] . ' – ');
                // FullCalendar end dates are exclusive — add 1 day to the inclusive date_end
                $endExclusive = date('Y-m-d', strtotime($h['date_end'] . ' +1 day'));
                // Use background display so the holiday shows in week/day views even with allDaySlot: false
                $events[] = [
                    'id'      => 'holiday-' . $h['id'],
                    'title'   => '🔒 ' . $providerLabel . $h['title'],
                    'start'   => $h['date_start'],
                    'end'     => $endExclusive,
                    'display' => 'background',
                    'color'   => $isGlobal ? '#e57373' : '#ffb74d',
                    'extendedProps' => ['type' => 'holiday'],
                ];
                // Regular all-day event for month view (hidden in week/day because allDaySlot: false)
                $events[] = [
                    'id'        => 'holiday-label-' . $h['id'],
                    'title'     => '🔒 ' . $providerLabel . $h['title'],
                    'start'     => $h['date_start'],
                    'end'       => $endExclusive,
                    'allDay'    => true,
                    'color'     => $isGlobal ? '#e57373' : '#ffb74d',
                    'textColor' => '#fff',
                    'extendedProps' => ['type' => 'holiday'],
                ];
            }

            ab_json($events);
            break;

        case 'booking-options':
            $serviceId = (int)($_GET['service_id'] ?? 0);
            if (!$serviceId) ab_json(['error' => 'service_id requis'], 400);
            $options = $db->fetchAll(
                "SELECT o.id, o.name, o.description, o.price_type, o.price_value
                 FROM ab_booking_options o
                 WHERE o.is_active = 1
                   AND (
                     NOT EXISTS (SELECT 1 FROM ab_booking_option_services bos WHERE bos.option_id = o.id)
                     OR EXISTS  (SELECT 1 FROM ab_booking_option_services bos WHERE bos.option_id = o.id AND bos.service_id = ?)
                   )
                 ORDER BY o.sort_order, o.name",
                [$serviceId]
            );
            ab_json(['options' => $options]);
            break;

        case 'services':
            $services = $db->fetchAll("SELECT id, name, duration, price, color, category_id, deposit_enabled, deposit_type, deposit_amount FROM ab_services WHERE is_active = 1 ORDER BY sort_order, name");
            ab_json(['services' => $services]);
            break;

        case 'providers':
            $serviceId = (int)($_GET['service_id'] ?? 0);
            $sql = "SELECT u.id, u.first_name, u.last_name, u.welcome_message FROM ab_users u";
            $params = [];
            if ($serviceId) {
                $sql .= " JOIN ab_provider_services ps ON u.id = ps.provider_id WHERE ps.service_id = ? AND u.is_active = 1 AND u.is_visible_booking = 1";
                $params[] = $serviceId;
            } else {
                $sql .= " WHERE u.is_active = 1 AND u.is_visible_booking = 1";
            }
            $sql .= " ORDER BY u.first_name";
            $providers = $db->fetchAll($sql, $params);
            ab_json(['providers' => $providers]);
            break;

        case 'test-smtp':
            if (!Auth::check()) ab_json(['error' => 'Non autorisé'], 401);
            $mailer = new Mailer();
            $adminEmail = ab_setting('business_email');
            if ($mailer->send($adminEmail, 'Test SMTP - ABAppointments', '<h2>Test réussi !</h2><p>Votre configuration SMTP fonctionne correctement.</p>')) {
                ab_json(['success' => true]);
            } else {
                ab_json(['success' => false, 'error' => $mailer->getLastError()]);
            }
            break;

        default:
            ab_json(['error' => 'Route non trouvée'], 404);
    }
} catch (Exception $e) {
    $error = AB_DEBUG ? $e->getMessage() : 'Erreur interne';
    ab_json(['error' => $error], 500);
}
