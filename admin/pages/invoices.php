<?php
$pageTitle = 'Factures';
$db = Database::getInstance();

$status = $_GET['status'] ?? 'all';
$where = '1';
$params = [];
if ($status !== 'all') { $where = 'i.status = ?'; $params = [$status]; }

$total = $db->fetchColumn("SELECT COUNT(*) FROM wp_invoices i WHERE $where", $params);
$pagination = wp_paginate($total);
$invoices = $db->fetchAll(
    "SELECT i.*, u.first_name, u.last_name, u.email
     FROM wp_invoices i JOIN wp_users u ON i.user_id = u.id
     WHERE $where ORDER BY i.created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}",
    $params
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $invoiceId = (int)($_POST['invoice_id'] ?? 0);
    if ($action === 'mark_paid' && $invoiceId) {
        InvoiceManager::markPaid($invoiceId, 'manual');
        wp_flash('success', 'Facture marquee comme payee.');
    } elseif ($action === 'cancel' && $invoiceId) {
        $db->update('wp_invoices', ['status' => 'cancelled'], 'id = ?', [$invoiceId]);
        wp_flash('success', 'Facture annulee.');
    } elseif ($action === 'send_reminder' && $invoiceId) {
        $inv = $db->fetchOne("SELECT i.*, u.email, u.first_name FROM wp_invoices i JOIN wp_users u ON i.user_id = u.id WHERE i.id = ?", [$invoiceId]);
        if ($inv) {
            $mailer = new Mailer();
            $mailer->sendTemplate($inv['email'], 'payment_reminder', [
                'first_name' => $inv['first_name'],
                'invoice_number' => $inv['invoice_number'],
                'total' => wp_format_price($inv['total']),
                'currency' => 'EUR',
                'due_date' => wp_format_date($inv['due_date']),
                'invoice_url' => wp_url("client/?page=invoice-pay&id={$inv['id']}")
            ]);
            $db->update('wp_invoices', ['reminder_sent' => $inv['reminder_sent'] + 1, 'last_reminder_at' => date('Y-m-d H:i:s')], 'id = ?', [$invoiceId]);
            wp_flash('success', 'Relance envoyee.');
        }
    }
    wp_redirect(wp_url("admin/?page=invoices&status=$status"));
}

$statusLabels = ['draft' => 'Brouillon', 'pending' => 'En attente', 'paid' => 'Payee', 'overdue' => 'En retard', 'cancelled' => 'Annulee', 'refunded' => 'Remboursee'];
$statusColors = ['draft' => 'secondary', 'pending' => 'warning', 'paid' => 'success', 'overdue' => 'danger', 'cancelled' => 'dark', 'refunded' => 'info'];
?>

<ul class="nav nav-pills mb-4">
    <?php foreach (['all' => 'Toutes', 'pending' => 'En attente', 'overdue' => 'En retard', 'paid' => 'Payees', 'cancelled' => 'Annulees'] as $k => $v): ?>
    <li class="nav-item"><a class="nav-link <?= $status === $k ? 'active' : '' ?>" href="<?= wp_url("admin/?page=invoices&status=$k") ?>"><?= $v ?></a></li>
    <?php endforeach; ?>
</ul>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Facture</th><th>Client</th><th>Montant</th><th>Statut</th><th>Echeance</th><th>Relances</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($invoices as $inv): ?>
            <tr>
                <td><a href="<?= wp_url("admin/?page=invoice-detail&id={$inv['id']}") ?>" class="fw-semibold"><?= wp_escape($inv['invoice_number']) ?></a></td>
                <td><a href="<?= wp_url("admin/?page=client-detail&id={$inv['user_id']}") ?>"><?= wp_escape($inv['first_name'] . ' ' . $inv['last_name']) ?></a></td>
                <td class="fw-bold"><?= wp_format_price($inv['total']) ?></td>
                <td><span class="badge bg-<?= $statusColors[$inv['status']] ?? 'secondary' ?>"><?= $statusLabels[$inv['status']] ?? $inv['status'] ?></span></td>
                <td class="<?= $inv['status'] === 'overdue' ? 'text-danger' : '' ?>"><?= wp_format_date($inv['due_date']) ?></td>
                <td><?= $inv['reminder_sent'] ?></td>
                <td>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
                        <ul class="dropdown-menu">
                            <li><form method="POST"><?= wp_csrf_field() ?><input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                                <?php if (in_array($inv['status'], ['pending', 'overdue'])): ?>
                                    <button name="action" value="mark_paid" class="dropdown-item text-success"><i class="bi bi-check me-2"></i>Marquer payee</button>
                                    <button name="action" value="send_reminder" class="dropdown-item"><i class="bi bi-envelope me-2"></i>Envoyer relance</button>
                                    <button name="action" value="cancel" class="dropdown-item text-danger"><i class="bi bi-x me-2"></i>Annuler</button>
                                <?php endif; ?>
                            </form></li>
                        </ul>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= wp_pagination_html($pagination, wp_url("admin/?page=invoices&status=$status")) ?>
