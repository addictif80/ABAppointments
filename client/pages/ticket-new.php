<?php
$pageTitle = 'Nouveau Ticket';
$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// Get user subscriptions for linking
$subscriptions = $db->fetchAll(
    "SELECT s.id, p.name, p.type FROM wp_subscriptions s JOIN wp_products p ON s.product_id = p.id WHERE s.user_id = ? AND s.status = 'active'",
    [$userId]
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $department = in_array($_POST['department'] ?? '', ['technical', 'billing', 'sales', 'other']) ? $_POST['department'] : 'technical';
    $priority = in_array($_POST['priority'] ?? '', ['low', 'medium', 'high', 'critical']) ? $_POST['priority'] : 'medium';
    $message = trim($_POST['message'] ?? '');
    $subscriptionId = (int)($_POST['subscription_id'] ?? 0) ?: null;

    if (empty($subject) || empty($message)) {
        wp_flash('error', 'Veuillez remplir le sujet et le message.');
    } else {
        $ticketNumber = 'TK-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);

        $db->beginTransaction();
        try {
            $ticketId = $db->insert('wp_tickets', [
                'ticket_number' => $ticketNumber,
                'user_id' => $userId,
                'subscription_id' => $subscriptionId,
                'department' => $department,
                'priority' => $priority,
                'subject' => $subject,
                'status' => 'open'
            ]);

            $db->insert('wp_ticket_messages', [
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'message' => $message,
                'is_admin_reply' => 0
            ]);

            $db->commit();
            wp_log_activity('ticket_created', 'ticket', $ticketId);
            wp_flash('success', "Ticket $ticketNumber cree avec succes.");
            wp_redirect(wp_url("client/?page=ticket-detail&id=$ticketId"));
        } catch (Exception $e) {
            $db->rollback();
            wp_flash('error', 'Erreur lors de la creation du ticket.');
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Ouvrir un ticket de support</h5></div>
            <div class="card-body">
                <form method="POST">
                    <?= wp_csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Departement *</label>
                            <select name="department" class="form-select">
                                <option value="technical">Technique</option>
                                <option value="billing">Facturation</option>
                                <option value="sales">Commercial</option>
                                <option value="other">Autre</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Priorite</label>
                            <select name="priority" class="form-select">
                                <option value="low">Basse</option>
                                <option value="medium" selected>Moyenne</option>
                                <option value="high">Haute</option>
                                <option value="critical">Critique</option>
                            </select>
                        </div>
                        <?php if (!empty($subscriptions)): ?>
                        <div class="col-12">
                            <label class="form-label">Service concerne</label>
                            <select name="subscription_id" class="form-select">
                                <option value="">-- Aucun --</option>
                                <?php foreach ($subscriptions as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= wp_escape($s['name']) ?> (<?= strtoupper($s['type']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-12">
                            <label class="form-label">Sujet *</label>
                            <input type="text" name="subject" class="form-control" required value="<?= wp_escape($_POST['subject'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Message *</label>
                            <textarea name="message" class="form-control" rows="6" required><?= wp_escape($_POST['message'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i> Envoyer</button>
                        <a href="<?= wp_url('client/?page=tickets') ?>" class="btn btn-outline-secondary ms-2">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
