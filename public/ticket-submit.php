<?php
/**
 * ABAppointments - Ticket Creation / Reply Endpoint (multipart form-data)
 */
require_once __DIR__ . '/../core/App.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ab_json(['error' => 'Méthode non autorisée'], 405);
}

$db = Database::getInstance();
$tickets = new Tickets();
$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $subject = trim($_POST['subject'] ?? '');
    $category = $_POST['category'] ?? '';
    $body = trim($_POST['body'] ?? '');
    $providerId = (int) ($_POST['provider_id'] ?? 0);

    if ($subject === '' || $body === '') {
        ab_json(['error' => 'Merci de renseigner un sujet et un message.'], 400);
    }
    if (!in_array($category, ['commercial', 'technique'], true)) {
        ab_json(['error' => 'Catégorie invalide.'], 400);
    }
    if ($category === 'commercial') {
        $provider = $db->fetchOne("SELECT id FROM ab_users WHERE id = ? AND is_active = 1", [$providerId]);
        if (!$provider) {
            ab_json(['error' => 'Merci de choisir un prestataire.'], 400);
        }
    }

    if (!empty($_SESSION['customer_id'])) {
        $customerId = (int) $_SESSION['customer_id'];
    } else {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ab_json(['error' => 'Merci de renseigner votre nom, prénom et un email valide.'], 400);
        }
        $customerId = $tickets->resolveCustomer($firstName, $lastName, $email, trim($_POST['phone'] ?? ''));
    }

    $ticket = $tickets->create([
        'customer_id' => $customerId,
        'subject' => $subject,
        'category' => $category,
        'provider_id' => $providerId,
        'body' => $body,
        'created_by' => 'customer',
        'files' => $_FILES['attachments'] ?? [],
    ]);

    ab_json([
        'success' => true,
        'ticket_url' => ab_url('public/ticket.php?hash=' . $ticket['hash']),
    ]);
}

if ($action === 'reply') {
    $hash = trim($_POST['ticket_hash'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if (!$hash) {
        ab_json(['error' => 'Ticket introuvable.'], 404);
    }
    if ($body === '') {
        ab_json(['error' => 'Merci de saisir un message.'], 400);
    }

    $ticket = $tickets->getByHash($hash);
    if (!$ticket) {
        ab_json(['error' => 'Ticket introuvable.'], 404);
    }
    if ($ticket['status'] === 'closed') {
        ab_json(['error' => 'Ce ticket est clôturé et n\'accepte plus de réponses.'], 400);
    }

    $tickets->reply($ticket, 'customer', null, $body, $_FILES['attachments'] ?? []);

    ab_json(['success' => true]);
}

ab_json(['error' => 'Action invalide.'], 400);
