<?php
/**
 * ABAppointments - Read-only ICS calendar feed
 *
 * Lets any iCal/CalDAV client (DAVdroid, FamilyBoard, Google Calendar, Apple Calendar...)
 * subscribe directly to the appointments calendar via a simple GET, without requiring an
 * external CalDAV server as a relay. URL: /calendar.ics?token=XXXX[&provider_id=N]
 */
require_once __DIR__ . '/../core/App.php';

$token = $_GET['token'] ?? '';
$expectedToken = ab_setting('ics_feed_token');

if ($expectedToken === '' || !hash_equals($expectedToken, $token)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Accès refusé.');
}

$manager = new AppointmentManager();
$caldav = new CalDAV();

$providerId = isset($_GET['provider_id']) && $_GET['provider_id'] !== '' ? (int)$_GET['provider_id'] : null;
$startDate = date('Y-m-d', strtotime('-60 days'));
$endDate = date('Y-m-d', strtotime('+365 days'));

$appointments = $manager->getForCalendar($providerId, $startDate, $endDate);
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$calName = str_replace(["\\", ";", ",", "\n"], ["\\\\", "\\;", "\\,", "\\n"], ab_setting('business_name', 'ABAppointments'));

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="abappointments.ics"');

echo "BEGIN:VCALENDAR\r\n"
    . "VERSION:2.0\r\n"
    . "PRODID:-//ABAppointments//Calendar Feed//FR\r\n"
    . "CALSCALE:GREGORIAN\r\n"
    . "METHOD:PUBLISH\r\n"
    . "X-WR-CALNAME:" . $calName . "\r\n";

foreach ($appointments as $appointment) {
    $uid = $caldav->eventUid($appointment, $host);
    echo $caldav->buildVEvent($appointment, $uid);
}

echo "END:VCALENDAR\r\n";
