<?php
$pageTitle = 'Detail Ticket';
$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$ticketId = (int)($_GET['id'] ?? 0);

$ticket = $db->fetchOne("SELECT * FROM wp_tickets WHERE id = ? AND user_id = ?", [$ticketId, $userId]);
if (!$ticket) { wp_flash('error', 'Ticket introuvable.'); wp_redirect(wp_url('client/?page=tickets')); }

// Post reply
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');
    if (!empty($message) && !in_array($ticket['status'], ['closed'])) {
        $db->insert('wp_ticket_messages', [
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'message' => $message,
            'is_admin_reply' => 0
        ]);
        $db->update('wp_tickets', ['status' => 'waiting_admin'], 'id = ?', [$ticketId]);
        wp_flash('success', 'Reponse envoyee.');
        wp_redirect(wp_url("client/?page=ticket-detail&id=$ticketId"));
    }
}

$messages = $db->fetchAll(
    "SELECT m.*, u.first_name, u.last_name, u.role FROM wp_ticket_messages m JOIN wp_users u ON m.user_id = u.id WHERE m.ticket_id = ? ORDER BY m.created_at ASC",
    [$ticketId]
);

$statusLabels = ['open' => 'Ouvert', 'in_progress' => 'En cours', 'waiting_client' => 'Reponse attendue', 'waiting_admin' => 'En attente support', 'resolved' => 'Resolu', 'closed' => 'Ferme'];
$statusColors = ['open' => 'primary', 'in_progress' => 'info', 'waiting_client' => 'warning', 'waiting_admin' => 'secondary', 'resolved' => 'success', 'closed' => 'dark'];
$pageTitle = 'Ticket #' . $ticket['ticket_number'];
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1"><?= wp_escape($ticket['subject']) ?></h5>
                    <small class="text-muted">Ticket #<?= wp_escape($ticket['ticket_number']) ?> | <?= ucfirst($ticket['department']) ?> | <?= wp_format_datetime($ticket['created_at']) ?></small>
                </div>
                <span class="badge bg-<?= $statusColors[$ticket['status']] ?? 'secondary' ?> fs-6"><?= $statusLabels[$ticket['status']] ?? $ticket['status'] ?></span>
            </div>
        </div>

        <!-- Messages -->
        <?php foreach ($messages as $msg): ?>
        <div class="card mb-3 <?= $msg['is_admin_reply'] ? 'border-start border-4 border-primary' : '' ?>">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <strong>
                        <?= wp_escape($msg['first_name'] . ' ' . $msg['last_name']) ?>
                        <?php if ($msg['role'] === 'admin'): ?><span class="badge bg-primary ms-1">Support</span><?php endif; ?>
                    </strong>
                    <small class="text-muted"><?= wp_format_datetime($msg['created_at']) ?></small>
                </div>
                <div class="message-content"><?= nl2br(wp_escape($msg['message'])) ?></div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Reply form -->
        <?php if (!in_array($ticket['status'], ['closed'])): ?>
        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <?= wp_csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Votre reponse</label>
                        <textarea name="message" class="form-control" rows="4" required placeholder="Ecrivez votre reponse..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i> Repondre</button>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-secondary text-center">Ce ticket est ferme. <a href="<?= wp_url('client/?page=ticket-new') ?>">Ouvrir un nouveau ticket</a></div>
        <?php endif; ?>
    </div>
</div>
