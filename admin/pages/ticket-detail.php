<?php
$pageTitle = 'Detail Ticket';
$db = Database::getInstance();
$ticketId = (int)($_GET['id'] ?? 0);

$ticket = $db->fetchOne(
    "SELECT t.*, u.first_name, u.last_name, u.email FROM wp_tickets t JOIN wp_users u ON t.user_id = u.id WHERE t.id = ?",
    [$ticketId]
);
if (!$ticket) { wp_flash('error', 'Ticket introuvable.'); wp_redirect(wp_url('admin/?page=tickets')); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'reply';
    if ($action === 'reply') {
        $message = trim($_POST['message'] ?? '');
        $newStatus = $_POST['new_status'] ?? $ticket['status'];
        if (!empty($message)) {
            $db->insert('wp_ticket_messages', [
                'ticket_id' => $ticketId,
                'user_id' => $_SESSION['user_id'],
                'message' => $message,
                'is_admin_reply' => 1
            ]);
            $db->update('wp_tickets', ['status' => $newStatus], 'id = ?', [$ticketId]);

            // Notify client
            $mailer = new Mailer();
            $mailer->sendTemplate($ticket['email'], 'ticket_reply', [
                'first_name' => $ticket['first_name'],
                'ticket_number' => $ticket['ticket_number'],
                'subject' => $ticket['subject'],
                'message' => mb_substr($message, 0, 200),
                'ticket_url' => wp_url("client/?page=ticket-detail&id=$ticketId")
            ]);
            wp_flash('success', 'Reponse envoyee.');
        }
    } elseif ($action === 'update_status') {
        $db->update('wp_tickets', ['status' => $_POST['status'] ?? 'open'], 'id = ?', [$ticketId]);
        if ($_POST['status'] === 'closed') $db->update('wp_tickets', ['closed_at' => date('Y-m-d H:i:s')], 'id = ?', [$ticketId]);
        wp_flash('success', 'Statut mis a jour.');
    } elseif ($action === 'assign') {
        $db->update('wp_tickets', ['assigned_to' => $_SESSION['user_id'], 'status' => 'in_progress'], 'id = ?', [$ticketId]);
        wp_flash('success', 'Ticket assigne.');
    }
    wp_redirect(wp_url("admin/?page=ticket-detail&id=$ticketId"));
}

$messages = $db->fetchAll(
    "SELECT m.*, u.first_name, u.last_name, u.role FROM wp_ticket_messages m JOIN wp_users u ON m.user_id = u.id WHERE m.ticket_id = ? ORDER BY m.created_at ASC",
    [$ticketId]
);
$pageTitle = 'Ticket #' . $ticket['ticket_number'];
$statusLabels = ['open' => 'Ouvert', 'in_progress' => 'En cours', 'waiting_client' => 'Attente client', 'waiting_admin' => 'Attente support', 'resolved' => 'Resolu', 'closed' => 'Ferme'];
$statusColors = ['open' => 'primary', 'in_progress' => 'info', 'waiting_client' => 'warning', 'waiting_admin' => 'secondary', 'resolved' => 'success', 'closed' => 'dark'];
?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-1"><?= wp_escape($ticket['subject']) ?></h5>
                <small class="text-muted">#<?= wp_escape($ticket['ticket_number']) ?> | <?= wp_escape($ticket['first_name'] . ' ' . $ticket['last_name']) ?> (<?= wp_escape($ticket['email']) ?>) | <?= ucfirst($ticket['department']) ?></small>
            </div>
        </div>

        <?php foreach ($messages as $msg): ?>
        <div class="card mb-2 <?= $msg['is_admin_reply'] ? 'border-start border-3 border-primary' : '' ?>">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between">
                    <strong><?= wp_escape($msg['first_name'] . ' ' . $msg['last_name']) ?> <?= $msg['role'] === 'admin' ? '<span class="badge bg-primary">Admin</span>' : '' ?></strong>
                    <small class="text-muted"><?= wp_format_datetime($msg['created_at']) ?></small>
                </div>
                <div class="mt-1"><?= nl2br(wp_escape($msg['message'])) ?></div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if ($ticket['status'] !== 'closed'): ?>
        <div class="card mt-3">
            <div class="card-body">
                <form method="POST">
                    <?= wp_csrf_field() ?><input type="hidden" name="action" value="reply">
                    <textarea name="message" class="form-control mb-2" rows="4" required placeholder="Votre reponse..."></textarea>
                    <div class="d-flex justify-content-between">
                        <select name="new_status" class="form-select" style="width:auto">
                            <?php foreach ($statusLabels as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $k === 'waiting_client' ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i> Repondre</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0">Informations</h6></div>
            <div class="card-body">
                <div class="mb-2"><span class="text-muted">Statut:</span> <span class="badge bg-<?= $statusColors[$ticket['status']] ?? 'secondary' ?>"><?= $statusLabels[$ticket['status']] ?? $ticket['status'] ?></span></div>
                <div class="mb-2"><span class="text-muted">Priorite:</span> <span class="badge bg-<?= ['low'=>'secondary','medium'=>'primary','high'=>'warning','critical'=>'danger'][$ticket['priority']] ?? 'secondary' ?>"><?= ucfirst($ticket['priority']) ?></span></div>
                <div class="mb-2"><span class="text-muted">Cree:</span> <?= wp_format_datetime($ticket['created_at']) ?></div>
                <div class="mb-2"><span class="text-muted">MAJ:</span> <?= wp_format_datetime($ticket['updated_at']) ?></div>

                <hr>
                <form method="POST" class="mb-2"><?= wp_csrf_field() ?><input type="hidden" name="action" value="assign">
                    <button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-person-plus me-1"></i> M'assigner</button>
                </form>
                <form method="POST"><?= wp_csrf_field() ?><input type="hidden" name="action" value="update_status">
                    <div class="d-flex gap-1">
                        <button name="status" value="resolved" class="btn btn-sm btn-success flex-fill">Resoudre</button>
                        <button name="status" value="closed" class="btn btn-sm btn-dark flex-fill">Fermer</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0">Client</h6></div>
            <div class="card-body">
                <a href="<?= wp_url("admin/?page=client-detail&id={$ticket['user_id']}") ?>" class="fw-semibold"><?= wp_escape($ticket['first_name'] . ' ' . $ticket['last_name']) ?></a>
                <div class="text-muted small"><?= wp_escape($ticket['email']) ?></div>
            </div>
        </div>
    </div>
</div>
