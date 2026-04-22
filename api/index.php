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
            ]);

            if ($result) {
                $responseData = [
                    'success' => true,
                    'hash' => $result['hash'],
                    'status' => $result['status'],
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
                    $gcal = new GoogleCalendar();
                    if ($gcal->isConfigured()) {
                        $gcal->syncAppointment($result['id']);
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

            ab_json($events);
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
