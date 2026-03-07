<?php
$db = Database::getInstance();
$manager = new AppointmentManager();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'confirm' && isset($_POST['deposit_id'])) {
        $manager->confirmDeposit(
            (int)$_POST['deposit_id'],
            $_POST['payment_method'] ?? 'bank_transfer',
            $_POST['reference'] ?? ''
        );
        ab_flash('success', 'Acompte confirmé et rendez-vous validé.');
        ab_redirect(ab_url('admin/index.php?page=deposits'));
    }

    if (($_POST['action'] ?? '') === 'refund' && isset($_POST['deposit_id'])) {
        $db->update('ab_deposits', ['status' => 'refunded'], 'id = ?', [(int)$_POST['deposit_id']]);
        ab_flash('success', 'Acompte marqué comme remboursé.');
        ab_redirect(ab_url('admin/index.php?page=deposits'));
    }
}

$statusFilter = $_GET['status'] ?? 'pending';
$sql = "SELECT d.*, a.start_datetime, a.status as appt_status, a.hash,
               c.first_name as cf, c.last_name as cl, c.email as ce, c.phone as cp,
               s.name as sn, s.price as sp
        FROM ab_deposits d
        JOIN ab_appointments a ON d.appointment_id = a.id
        JOIN ab_customers c ON a.customer_id = c.id
        JOIN ab_services s ON a.service_id = s.id";
$params = [];
if ($statusFilter) {
    $sql .= " WHERE d.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY d.created_at DESC";
$deposits = $db->fetchAll($sql, $params);

// Stats
$totalPending = $db->fetchOne("SELECT COUNT(*) as cnt, COALESCE(SUM(amount),0) as total FROM ab_deposits WHERE status = 'pending'");
$totalPaid = $db->fetchOne("SELECT COUNT(*) as cnt, COALESCE(SUM(amount),0) as total FROM ab_deposits WHERE status = 'paid'");
?>

<h4 class="mb-3"><i class="bi bi-cash-coin"></i> Gestion des acomptes</h4>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card p-3" style="border-left-color: #ffc107;">
            <div class="text-muted small">En attente</div>
            <div class="h4 mb-0"><?= $totalPending['cnt'] ?></div>
            <div class="text-muted"><?= ab_format_price($totalPending['total']) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card p-3" style="border-left-color: #28a745;">
            <div class="text-muted small">Reçus</div>
            <div class="h4 mb-0"><?= $totalPaid['cnt'] ?></div>
            <div class="text-muted"><?= ab_format_price($totalPaid['total']) ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white">
        <div class="d-flex gap-2">
            <a href="<?= ab_url('admin/index.php?page=deposits&status=pending') ?>" class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-warning' : 'btn-outline-warning' ?>">En attente</a>
            <a href="<?= ab_url('admin/index.php?page=deposits&status=paid') ?>" class="btn btn-sm <?= $statusFilter === 'paid' ? 'btn-success' : 'btn-outline-success' ?>">Payés</a>
            <a href="<?= ab_url('admin/index.php?page=deposits&status=refunded') ?>" class="btn btn-sm <?= $statusFilter === 'refunded' ? 'btn-info' : 'btn-outline-info' ?>">Remboursés</a>
            <a href="<?= ab_url('admin/index.php?page=deposits&status=') ?>" class="btn btn-sm <?= $statusFilter === '' ? 'btn-primary' : 'btn-outline-primary' ?>">Tous</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Client</th><th>Prestation</th><th>RDV</th><th>Montant</th><th>Prix total</th><th>Statut</th><th>Date limite</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($deposits as $d): ?>
            <tr>
                <td>
                    <strong><?= ab_escape($d['cf'] . ' ' . $d['cl']) ?></strong>
                    <br><small class="text-muted"><?= ab_escape($d['ce']) ?></small>
                </td>
                <td><?= ab_escape($d['sn']) ?></td>
                <td><?= ab_format_date($d['start_datetime']) ?> <?= ab_format_time($d['start_datetime']) ?></td>
                <td><strong><?= ab_format_price($d['amount']) ?></strong></td>
                <td><?= ab_format_price($d['sp']) ?></td>
                <td><span class="badge badge-<?= $d['status'] ?>"><?= $d['status'] ?></span></td>
                <td><?= $d['due_date'] ? ab_format_date($d['due_date']) : '-' ?></td>
                <td>
                    <?php if ($d['status'] === 'pending'): ?>
                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#confirmModal<?= $d['id'] ?>">
                        <i class="bi bi-check-circle"></i> Confirmer
                    </button>
                    <!-- Modal -->
                    <div class="modal fade" id="confirmModal<?= $d['id'] ?>">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST">
                                    <?= Auth::csrfField() ?>
                                    <input type="hidden" name="action" value="confirm">
                                    <input type="hidden" name="deposit_id" value="<?= $d['id'] ?>">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Confirmer l'acompte</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Confirmer la réception de <strong><?= ab_format_price($d['amount']) ?></strong> de <?= ab_escape($d['cf'] . ' ' . $d['cl']) ?> ?</p>
                                        <div class="mb-3">
                                            <label class="form-label">Mode de paiement</label>
                                            <select name="payment_method" class="form-select">
                                                <option value="bank_transfer">Virement bancaire</option>
                                                <option value="card">Carte bancaire</option>
                                                <option value="cash">Espèces</option>
                                                <option value="paypal">PayPal</option>
                                                <option value="other">Autre</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Référence de paiement</label>
                                            <input type="text" name="reference" class="form-control" placeholder="Numéro de transaction...">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                        <button type="submit" class="btn btn-success"><i class="bi bi-check"></i> Confirmer le paiement</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php elseif ($d['status'] === 'paid'): ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Marquer comme remboursé ?')">
                        <?= Auth::csrfField() ?>
                        <input type="hidden" name="action" value="refund">
                        <input type="hidden" name="deposit_id" value="<?= $d['id'] ?>">
                        <button class="btn btn-sm btn-outline-info"><i class="bi bi-arrow-counterclockwise"></i></button>
                    </form>
                    <?php endif; ?>
                    <a href="<?= ab_url('admin/index.php?page=appointments&action=view&id=' . $d['appointment_id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($deposits)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">Aucun acompte</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
