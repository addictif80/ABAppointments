<?php
/**
 * ABAppointments - Ticket Attachment Gate
 * Streams a ticket attachment only to the ticket's own customer (via hash)
 * or to staff with rights over the ticket (admin, or the assigned provider).
 */
require_once __DIR__ . '/../core/App.php';

$attachmentId = (int) ($_GET['id'] ?? 0);
if (!$attachmentId) {
    http_response_code(404);
    exit('Fichier non trouvé.');
}

$tickets = new Tickets();
$attachment = $tickets->getAttachment($attachmentId);
if (!$attachment) {
    http_response_code(404);
    exit('Fichier non trouvé.');
}

$ticket = $tickets->getById((int) $attachment['ticket_id']);
if (!$ticket) {
    http_response_code(404);
    exit('Fichier non trouvé.');
}

$authorized = false;
if (Auth::check()) {
    $authorized = $tickets->canStaffAccess($ticket, Auth::isAdmin(), (int) Auth::userId());
} elseif (isset($_GET['hash']) && hash_equals($ticket['hash'], (string) $_GET['hash'])) {
    $authorized = true;
}

if (!$authorized) {
    http_response_code(403);
    exit('Accès refusé.');
}

$path = $tickets->attachmentPath($attachment);
if (!is_file($path)) {
    http_response_code(404);
    exit('Fichier non trouvé.');
}

$downloadName = preg_replace('/[^\w.\-]+/u', '_', $attachment['original_filename']);

header('Content-Type: ' . $attachment['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $downloadName . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
readfile($path);
